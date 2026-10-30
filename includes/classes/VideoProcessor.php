<?php
require_once __DIR__ . '/MediaCommand.php';
require_once __DIR__ . '/../media.php';
final class VideoProcessor {
    private PDO $conn;
    private const SIZE_LIMIT = 50000000;
    public function __construct(PDO $conn) { $this->conn = $conn; }
    public function upload($data, bool $queued=false): bool {
        if ($queued && (PHP_SAPI!=='cli' || !$data->jobId)) throw new RuntimeException('Invalid worker invocation.');
        $files = [];
        try {
            $upload = $data->videoDataArray;
            if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
                || !is_string($upload['tmp_name'] ?? null) || !($queued ? is_file($upload['tmp_name']) : is_uploaded_file($upload['tmp_name']))) {
                throw new InvalidArgumentException('Invalid upload.');
            }
            $size = filesize($upload['tmp_name']);
            if ($size < 1 || $size > self::SIZE_LIMIT) throw new InvalidArgumentException('Upload exceeds 50 MB.');
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
            if (!is_string($mime) || (!str_starts_with($mime, 'video/')
                && !in_array($mime, ['application/ogg', 'application/x-matroska'], true))) {
                throw new InvalidArgumentException('Unsupported media.');
            }
            if (!is_string($data->title) || !is_string($data->description) || trim($data->title) === ''
                || strlen($data->title) > 70 || strlen($data->description) > 1000
                || !in_array((string)$data->privacy, ['0','1'], true)) throw new InvalidArgumentException('Invalid video details.');
            $query = $this->conn->prepare('SELECT id FROM categories WHERE id=?');
            $query->execute([$data->categories]);
            if (!$query->fetchColumn()) throw new InvalidArgumentException('Invalid topic.');
            $root = dirname(__DIR__, 2);
            $relative = 'uploads/videos/' . ($queued ? 'job-'.$data->jobId : bin2hex(random_bytes(16)));
            if (!is_dir($root . '/uploads/videos/thumbnails')
                && !mkdir($root . '/uploads/videos/thumbnails', 0750, true)
                && !is_dir($root . '/uploads/videos/thumbnails')) throw new RuntimeException('Cannot create upload directory.');
            $source = $root . '/' . $relative . '.source'; $files[] = $source;
            if (!($queued ? copy($upload['tmp_name'], $source) : move_uploaded_file($upload['tmp_name'], $source))) throw new RuntimeException('Cannot store upload.');
            $ffprobe = mediaBinary('ffprobe');
            $ffmpeg = mediaBinary('ffmpeg');
            // Restrict demuxer protocols so malicious playlists cannot fetch network resources.
            $probe = MediaCommand::run([$ffprobe, '-v', 'error', '-protocol_whitelist', 'file,pipe',
                '-select_streams', 'v:0', '-show_entries', 'stream=codec_type:format=duration', '-of', 'json', $source], 20);
            $metadata = json_decode($probe, true, 512, JSON_THROW_ON_ERROR);
            $duration = (float)($metadata['format']['duration'] ?? 0);
            if (($metadata['streams'][0]['codec_type'] ?? '') !== 'video' || !is_finite($duration)
                || $duration <= 0 || $duration > 14400) throw new InvalidArgumentException('Invalid video duration.');
            $output = $root . '/' . $relative . '.mp4'; $files[] = $output;
            MediaCommand::run([$ffmpeg, '-nostdin', '-v', 'error', '-protocol_whitelist', 'file,pipe', '-i', $source,
                '-map', '0:v:0', '-map', '0:a:0?', '-c:v', 'libx264', '-preset', 'veryfast', '-threads', '2',
                '-vf', 'scale=w=1280:h=720:force_original_aspect_ratio=decrease:force_divisible_by=2', '-fs', '100000000', '-c:a', 'aac', '-movflags', '+faststart', '-y', $output]);
            $converted = json_decode(MediaCommand::run([$ffprobe, '-v', 'error', '-show_entries', 'format=duration', '-of', 'json', $output], 20), true, 512, JSON_THROW_ON_ERROR);
            if (abs((float)($converted['format']['duration'] ?? 0) - $duration) > 2 || filesize($output) >= 100000000) throw new RuntimeException('Converted video exceeded output limits.');
            $thumbRelative = 'uploads/videos/thumbnails/' . ($queued ? 'job-'.$data->jobId : bin2hex(random_bytes(16))) . '.jpg';
            $thumb = $root . '/' . $thumbRelative; $files[] = $thumb;
            MediaCommand::run([$ffmpeg, '-nostdin', '-v', 'error', '-ss', (string)($duration / 2), '-i', $output,
                '-frames:v', '1', '-vf', 'scale=320:-2', '-threads', '1', '-y', $thumb], 20);
            if (!is_file($output) || !filesize($output) || !is_file($thumb) || !filesize($thumb)) throw new RuntimeException('Incomplete media.');
            $seconds = (int)$duration;
            $formatted = $seconds >= 3600 ? sprintf('%d:%02d:%02d', intdiv($seconds,3600), intdiv($seconds%3600,60), $seconds%60)
                : sprintf('%02d:%02d', intdiv($seconds,60), $seconds%60);
            $this->conn->beginTransaction();
            $active=$this->conn->prepare('SELECT id FROM users WHERE username=? AND suspended=0 AND emailVerifiedAt IS NOT NULL FOR UPDATE');$active->execute([$data->uploadedBy]);
            if (!$active->fetchColumn()) throw new RuntimeException('Account is no longer allowed to publish.');
            $query = $this->conn->prepare('INSERT INTO videos (title,uploadedBy,description,category,privacy,filePath,views,duration,uploadJobId) VALUES (?,?,?,?,?,?,0,?,?)');
            $query->execute([$data->title, $data->uploadedBy, $data->description, $data->categories, $data->privacy, $relative . '.mp4', $formatted, $data->jobId]);
            $id = $this->conn->lastInsertId();
            $query = $this->conn->prepare('INSERT INTO thumbnails(videoId,filePath,selected) VALUES (?,?,1)');
            $query->execute([$id, $thumbRelative]);
            $this->conn->commit();
            unlink($source);
            return true;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            foreach ($files as $path) if (is_file($path)) unlink($path);
            error_log('Video upload failed: ' . $e->getMessage());
            return false;
        }
    }
}
