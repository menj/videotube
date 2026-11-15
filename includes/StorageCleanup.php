<?php
/**
 * Finds media files that no database record refers to: leftovers of removed videos, deleted
 * accounts, replaced profile pictures and interrupted jobs.
 */
final class StorageCleanup
{
    private const AREAS = ['videos', 'videos/thumbnails', 'queue', 'avatars'];

    public function __construct(private PDO $db)
    {
    }

    /**
     * @param int $minimumAge only report files untouched for this many seconds, so work in
     *                        progress and recent removals (still wanted for recovery) are left alone
     * @return string[] absolute paths
     */
    public function orphans(int $minimumAge): array
    {
        $referenced = $this->referenced();
        $cutoff = time() - $minimumAge;
        $orphans = [];
        foreach (self::AREAS as $area) {
            $directory = storageRoot() . '/' . $area;
            foreach (is_dir($directory) ? scandir($directory) : [] as $entry) {
                $path = $directory . '/' . $entry;
                if ($entry[0] === '.' || !is_file($path)) {
                    continue;
                }
                if (!isset($referenced[$area . '/' . $entry]) && filemtime($path) <= $cutoff) {
                    $orphans[] = $path;
                }
            }
        }
        sort($orphans);
        return $orphans;
    }

    /** @return array<string,true> storage-relative paths that are still in use */
    private function referenced(): array
    {
        $used = [];
        $strip = static fn(string $stored): string => preg_replace('~^uploads/~', '', $stored);
        foreach ($this->db->query('SELECT filePath FROM videos')->fetchAll(PDO::FETCH_COLUMN) as $path) {
            $used[$strip($path)] = true;
        }
        foreach ($this->db->query('SELECT filePath FROM thumbnails')->fetchAll(PDO::FETCH_COLUMN) as $path) {
            $used[$strip($path)] = true;
        }
        // Waiting jobs keep their source, and a running job its half-written output of the same name.
        $jobs = $this->db->query("SELECT sourcePath FROM upload_jobs WHERE status IN ('pending','processing')")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($jobs as $path) {
            $name = pathinfo($path, PATHINFO_FILENAME);
            $used['queue/' . basename($path)] = true;
            $used["videos/$name.source"] = true;
            $used["videos/$name.mp4"] = true;
            foreach ([1, 2, 3] as $index) {
                $used["videos/thumbnails/$name-$index.jpg"] = true;
            }
        }
        foreach ($this->db->query("SELECT profilePic FROM users WHERE profilePic LIKE 'avatar.php?f=%'")->fetchAll(PDO::FETCH_COLUMN) as $picture) {
            $used['avatars/' . substr($picture, strlen('avatar.php?f=')) . '.jpg'] = true;
        }
        return $used;
    }
}
