<?php

require_once __DIR__ . '/../media.php';

/** Validates, transcodes and publishes one queued upload. Runs in the CLI worker only. */
final class VideoProcessor
{
    public const SIZE_LIMIT = 50000000;
    public const OUTPUT_LIMIT = 100000000;
    public const MAX_DURATION = 14400;
    private const GENERIC_FAILURE = 'Processing failed. Check the file or contact the administrator.';

    /** Reason for the last failure, safe to show to the uploader. */
    public string $failure = '';

    public function __construct(private PDO $conn)
    {
    }

    /**
     * Seconds allowed for a transcode: three times the video's length plus two minutes, never
     * more than VIDEOTUBE_MAX_TRANSCODE_SECONDS (default one hour).
     */
    public static function transcodeTimeout(float $duration): int
    {
        $ceiling = (int)getenv('VIDEOTUBE_MAX_TRANSCODE_SECONDS');
        if ($ceiling < 1) {
            $ceiling = 3600;
        }
        return max(30, min($ceiling, (int)ceil($duration * 3) + 120));
    }

    public function process(VideoUploadData $data): bool
    {
        if (PHP_SAPI !== 'cli' || !$data->jobId) {
            throw new RuntimeException('Invalid worker invocation.');
        }
        $this->failure = '';
        $files = [];
        try {
            $upload = $data->videoDataArray;
            if (!is_array($upload) || !is_string($upload['tmp_name'] ?? null) || !is_file($upload['tmp_name'])) {
                throw new InvalidArgumentException('The uploaded file is missing.');
            }
            $size = filesize($upload['tmp_name']);
            if ($size < 1 || $size > self::SIZE_LIMIT) {
                throw new InvalidArgumentException('The file is larger than 50 MB.');
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
            if (!is_string($mime) || (!str_starts_with($mime, 'video/') && !in_array($mime, ['application/ogg', 'application/x-matroska'], true))) {
                throw new InvalidArgumentException('The file is not a supported video format.');
            }
            if (
                !is_string($data->title) || !is_string($data->description) || trim($data->title) === ''
                || strlen($data->title) > 70 || strlen($data->description) > 1000
                || !in_array((string)$data->privacy, ['0', '1'], true)
            ) {
                throw new InvalidArgumentException('The video details are invalid.');
            }
            $query = $this->conn->prepare('SELECT id FROM categories WHERE id=?');
            $query->execute([$data->categories]);
            if (!$query->fetchColumn()) {
                throw new InvalidArgumentException('The chosen category no longer exists.');
            }

            // Stored paths keep the historical "uploads/" prefix; storedFile() maps it onto the storage root.
            // The name is unguessable, and stable per job so a resumed job overwrites its own partial files.
            $name = is_string($data->mediaName) && preg_match('/^[a-f0-9]{32,64}$/D', $data->mediaName)
                ? $data->mediaName
                : bin2hex(random_bytes(24));
            $relative = 'uploads/videos/' . $name;
            storageDirectory('videos/thumbnails');
            $toPath = static fn(string $stored): string => storageRoot() . '/' . substr($stored, strlen('uploads/'));

            $source = $toPath($relative . '.source');
            $files[] = $source;
            if (!copy($upload['tmp_name'], $source)) {
                throw new RuntimeException('Cannot store upload.');
            }
            $ffprobe = mediaBinary('ffprobe');
            $ffmpeg = mediaBinary('ffmpeg');

            // Restrict demuxer protocols so malicious playlists cannot fetch network resources.
            $probe = MediaCommand::run([$ffprobe, '-v', 'error', '-protocol_whitelist', 'file,pipe',
                '-select_streams', 'v:0', '-show_entries', 'stream=codec_type:format=duration', '-of', 'json', $source], 20);
            $metadata = json_decode($probe, true, 512, JSON_THROW_ON_ERROR);
            $duration = (float)($metadata['format']['duration'] ?? 0);
            if (($metadata['streams'][0]['codec_type'] ?? '') !== 'video' || !is_finite($duration) || $duration <= 0) {
                throw new InvalidArgumentException('The file contains no playable video.');
            }
            if ($duration > self::MAX_DURATION) {
                throw new InvalidArgumentException('The video is longer than four hours.');
            }

            $output = $toPath($relative . '.mp4');
            $files[] = $output;
            MediaCommand::run([$ffmpeg, '-nostdin', '-v', 'error', '-protocol_whitelist', 'file,pipe', '-i', $source,
                '-map', '0:v:0', '-map', '0:a:0?', '-c:v', 'libx264', '-preset', 'veryfast', '-threads', '2',
                '-vf', 'scale=w=1280:h=720:force_original_aspect_ratio=decrease:force_divisible_by=2',
                '-fs', (string)self::OUTPUT_LIMIT, '-c:a', 'aac', '-movflags', '+faststart', '-y', $output], self::transcodeTimeout($duration));
            $converted = json_decode(MediaCommand::run([$ffprobe, '-v', 'error', '-show_entries', 'format=duration', '-of', 'json', $output], 20), true, 512, JSON_THROW_ON_ERROR);
            if (abs((float)($converted['format']['duration'] ?? 0) - $duration) > 2 || filesize($output) >= self::OUTPUT_LIMIT) {
                throw new InvalidArgumentException('The converted video is larger than this server stores (100 MB). Try a shorter or lower-quality file.');
            }

            // Three candidate thumbnails; the uploader can pick another on the edit page.
            $thumbnails = [];
            foreach ([0.5, 0.25, 0.75] as $index => $position) {
                $thumbRelative = 'uploads/videos/thumbnails/' . $name . '-' . ($index + 1) . '.jpg';
                $thumb = $toPath($thumbRelative);
                $files[] = $thumb;
                MediaCommand::run([$ffmpeg, '-nostdin', '-v', 'error', '-ss', (string)($duration * $position), '-i', $output,
                    '-frames:v', '1', '-vf', 'scale=320:-2', '-threads', '1', '-y', $thumb], 20);
                if (is_file($thumb) && filesize($thumb)) {
                    $thumbnails[] = $thumbRelative;
                }
            }
            if (!is_file($output) || !filesize($output) || !$thumbnails) {
                throw new RuntimeException('Incomplete media.');
            }

            $seconds = (int)$duration;
            $formatted = $seconds >= 3600
                ? sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60)
                : sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);

            $this->conn->beginTransaction();
            $active = $this->conn->prepare('SELECT id FROM users WHERE username=? AND suspended=0 AND emailVerifiedAt IS NOT NULL FOR UPDATE');
            $active->execute([$data->uploadedBy]);
            if (!$active->fetchColumn()) {
                throw new InvalidArgumentException('The account is no longer allowed to publish.');
            }
            $query = $this->conn->prepare('INSERT INTO videos (title,uploadedBy,description,category,privacy,filePath,views,duration,uploadJobId) VALUES (?,?,?,?,?,?,0,?,?)');
            $query->execute([$data->title, $data->uploadedBy, $data->description, $data->categories, $data->privacy, $relative . '.mp4', $formatted, $data->jobId]);
            $id = $this->conn->lastInsertId();
            $query = $this->conn->prepare('INSERT INTO thumbnails(videoId,filePath,selected) VALUES (?,?,?)');
            foreach ($thumbnails as $index => $thumbRelative) {
                $query->execute([$id, $thumbRelative, $index === 0 ? 1 : 0]);
            }
            $this->conn->commit();
            unlink($source);
            return true;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            foreach ($files as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            // Validation messages are written for the uploader; anything else stays in the log.
            $this->failure = match (true) {
                $e instanceof InvalidArgumentException => $e->getMessage(),
                $e instanceof MediaTimeoutException => 'Conversion took longer than this server allows. Try a shorter or smaller video.',
                default => self::GENERIC_FAILURE,
            };
            error_log('Video upload failed: ' . $e->getMessage());
            return false;
        }
    }
}
