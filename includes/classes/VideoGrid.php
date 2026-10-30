<?php 
   class VideoGrid{
    
    private $conn, $userLoggedInObj;
    private $largeMode = false;
    private $gridClass = "videoGrid";

    public function __construct($conn, $userLoggedInObj) {
        $this->conn = $conn;
        $this->userLoggedInObj = $userLoggedInObj;
    }

    public function create($videos, $title, $showFilter) {
         
        if($videos === null) {
            $gridItem = $this->generateItems();
        }
        else{
            $gridItem = $this->generateItemsFromVideos($videos);
        }
        
        $header = "";

        if($title != null){
            $header = $this->createGridHeader($title, $showFilter);
        }

        return "$header
                <div class = '$this->gridClass'>
                     $gridItem
                </div>";
    }

      public function generateItems() {
        $query = $this->conn->prepare("SELECT * FROM videos WHERE privacy=1 ORDER BY RAND() LIMIT 15");
        $query->execute();

        $elementHtml = "";
        while($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $video = new Video($this->conn, $row, $this->userLoggedInObj);
            $item = new VideoGridItem($video, $this->largeMode);
            $elementHtml .= $item->create();
        }

        return $elementHtml;
      }

      public function generateItemsFromVideos($videos) {
        $elementsHtml = "";

        foreach($videos as $video) {
            $item = new VideoGridItem($video, $this->largeMode);
            $elementsHtml .= $item->create();
        }

        return $elementsHtml;
      }
      
      public function createGridHeader($title, $showFilter) {
        $filter = "";

     
        if($showFilter) {
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
    
    public function createLarge($videos, $title, $showFilter) {
        $this->gridClass .= " large";
        $this->largeMode = true;
        return $this->create($videos, $title, $showFilter);
    }
   }
?>