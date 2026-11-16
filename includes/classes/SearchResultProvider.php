<?php

class SearchResultProvider
{
    private $conn;
    private $userLoggedInObj;

    public function __construct($conn, $userLoggedInObj)
    {
        $this->conn = $conn;
        $this->userLoggedInObj = $userLoggedInObj;
    }

    /** The term as a LIKE pattern, with the user's own % and _ taken literally. */
    private static function pattern(string $term): string
    {
        return '%' . addcslashes($term, '\\%_') . '%';
    }

    public function countVideos(string $term): int
    {
        $query = $this->conn->prepare("SELECT COUNT(*) FROM videos WHERE privacy=1 AND (title LIKE ? OR uploadedBy LIKE ?)");
        $query->execute([self::pattern($term), self::pattern($term)]);
        return (int)$query->fetchColumn();
    }

    /** @return Video[] one page of public videos whose title or uploader matches */
    public function getVideos($term, $orderBy, int $page = 1): array
    {
        $orderBy = $orderBy === 'uploadDate' ? 'uploadDate' : 'views';
        $query = $this->conn->prepare("SELECT * FROM videos WHERE privacy=1 AND (title LIKE ? OR uploadedBy LIKE ?)
                                        ORDER BY $orderBy DESC, id DESC LIMIT " . Pager::PAGE_SIZE . " OFFSET " . Pager::offset($page));
        $query->execute([self::pattern((string)$term), self::pattern((string)$term)]);

        $videos = [];
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $videos[] = new Video($this->conn, $row, $this->userLoggedInObj);
        }
        return $videos;
    }
}
