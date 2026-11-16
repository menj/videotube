<?php

/** Stores profile pictures: every upload is re-encoded to a 256×256 JPEG, never served as sent. */
final class AvatarStore
{
    public const SIZE_LIMIT = 2097152;
    public const DEFAULT_PICTURE = 'assets/images/profilePictures/default.png';

    /** Input demuxer per detected type, so FFmpeg never guesses the format of an upload. */
    private const DEMUXERS = ['image/jpeg' => 'jpeg_pipe', 'image/png' => 'png_pipe', 'image/webp' => 'webp_pipe'];

    /**
     * @param array $upload one entry of $_FILES
     * @return string value for users.profilePic
     */
    public static function store(array $upload): string
    {
        if (($upload['error'] ?? -1) !== UPLOAD_ERR_OK || !is_string($upload['tmp_name'] ?? null) || !is_uploaded_file($upload['tmp_name'])) {
            throw new InvalidArgumentException('Choose a picture to upload.');
        }
        $size = filesize($upload['tmp_name']);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        if ($size < 1 || $size > self::SIZE_LIMIT || !isset(self::DEMUXERS[$mime])) {
            throw new InvalidArgumentException('Use a JPEG, PNG or WebP picture of up to 2 MB.');
        }
        $name = bin2hex(random_bytes(16));
        $target = storageDirectory('avatars') . '/' . $name . '.jpg';
        try {
            MediaCommand::run([mediaBinary('ffmpeg'), '-nostdin', '-v', 'error', '-protocol_whitelist', 'file',
                '-f', self::DEMUXERS[$mime], '-i', $upload['tmp_name'], '-frames:v', '1', '-map_metadata', '-1',
                '-vf', 'scale=256:256:force_original_aspect_ratio=increase,crop=256:256', '-pix_fmt', 'yuvj420p',
                '-q:v', '3', '-f', 'mjpeg', '-y', $target], 20);
        } catch (RuntimeException $e) {
            if (is_file($target)) {
                unlink($target);
            }
            throw new InvalidArgumentException('That picture could not be read. Try another file.');
        }
        if (!is_file($target) || !filesize($target)) {
            throw new InvalidArgumentException('That picture could not be read. Try another file.');
        }
        return 'avatar.php?f=' . $name;
    }
}
