<?php

class VideoGrid
{
    private $conn;
    private $userLoggedInObj;
    private $largeMode = false;
    private $gridClass = "videoGrid";
    private const RECOMMENDED = 15;

    public function __construct($conn, $userLoggedInObj)
    {
        $this->conn = $conn;
        $this->userLoggedInObj = $userLoggedInObj;
    }

    public function create($videos, $title, $showFilter, int $excludeVideoId = 0)
    {

        if ($videos === null) {
            $gridItem = $this->generateItems($excludeVideoId);
        } else {
            $gridItem = $this->generateItemsFromVideos($videos);
        }

        $header = "";

        if ($title != null) {
            $header = $this->createGridHeader($title, $showFilter);
        }

        return "$header
                <div class = '$this->gridClass'>
                     $gridItem
                </div>";
    }

    public function generateItems(int $excludeVideoId = 0)
    {
        // ORDER BY RAND() sorts the whole table. Start at a random id instead, read the next public
        // videos by primary key, wrap around if the end is reached, then shuffle the handful found.
        $max = (int)$this->conn->query("SELECT MAX(id) FROM videos")->fetchColumn();
        if ($max < 1) {
            return "";
        }
        $pivot = random_int(1, $max);
        $query = $this->conn->prepare("SELECT * FROM videos WHERE privacy=1 AND id >= ? ORDER BY id LIMIT " . self::RECOMMENDED);
        $query->execute([$pivot]);
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) < self::RECOMMENDED) {
            $query = $this->conn->prepare("SELECT * FROM videos WHERE privacy=1 AND id < ? ORDER BY id DESC LIMIT " . (self::RECOMMENDED - count($rows)));
            $query->execute([$pivot]);
            $rows = array_merge($rows, $query->fetchAll(PDO::FETCH_ASSOC));
        }
        $rows = array_filter($rows, static fn(array $row): bool => (int)$row["id"] !== $excludeVideoId);
        shuffle($rows);

        $elementHtml = "";
        foreach ($rows as $row) {
            $video = new Video($this->conn, $row, $this->userLoggedInObj);
            $item = new VideoGridItem($video, $this->largeMode);
            $elementHtml .= $item->create();
        }

        return $elementHtml;
    }

    public function generateItemsFromVideos($videos)
    {
        $elementsHtml = "";

        foreach ($videos as $video) {
              $item = new VideoGridItem($video, $this->largeMode);
              $elementsHtml .= $item->create();
        }

           return $elementsHtml;
    }

    public function createGridHeader($title, $showFilter)
    {
        $filter = "";


        if ($showFilter) {
              $params = ['term' => is_string($_GET['term'] ?? null) ? $_GET['term'] : ''];
              $newUrl = 'search.php?' . htmlspecialchars(http_build_query($params), ENT_QUOTES, 'UTF-8');
              $filter = "<div class='right'>
                            <span>Order by:</span>
                            <a href='$newUrl&orderBy=uploadDate'>Upload date</a>
                            <a href='$newUrl&orderBy=views'>Most viewed</a>
                        </div>";
        }
           return "<div class='videoGridHeader'>
                        <div class='left'>
                            $title
                        </div>
                        $filter
                    </div>";
    }

    public function createLarge($videos, $title, $showFilter)
    {
           $this->gridClass .= " large";
           $this->largeMode = true;
           return $this->create($videos, $title, $showFilter);
    }
}
