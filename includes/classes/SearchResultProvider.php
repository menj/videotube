<?php
class SearchResultProvider {

    private $conn, $userLoggedInObj;

    public function __construct($conn, $userLoggedInObj) {
        $this->conn = $conn;
        $this->userLoggedInObj = $userLoggedInObj;
    }

    public function getVideos($term, $orderBy) {
        $orderBy = $orderBy === 'uploadDate' ? 'uploadDate' : 'views';
        $query = $this->conn->prepare("SELECT * FROM videos WHERE privacy=1 AND (title LIKE CONCAT('%', :term, '%')
                                        OR uploadedBy LIKE CONCAT('%', :creator, '%')) ORDER BY $orderBy DESC LIMIT 100");
        $query->bindValue(':creator',$term);
        $query->bindParam(":term", $term);
        $query->execute();

        $videos = array();
        while($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $video = new Video($this->conn, $row, $this->userLoggedInObj);
            array_push($videos, $video);
        }

        return $videos;

    }

}
?>