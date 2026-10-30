<?php
class LikedVideosProvider {
    private $conn, $userLoggedInObj;
    public function __construct($conn, $userLoggedInObj) {
        $this->conn=$conn; $this->userLoggedInObj=$userLoggedInObj;
    }
    public function getVideos(): array {
        $username=$this->userLoggedInObj->getUsername();
        if ($username === '') return [];
        $query=$this->conn->prepare('SELECT v.* FROM videos v INNER JOIN likes l ON l.videoId=v.id WHERE l.username=? AND l.commentId=0 AND (v.privacy=1 OR v.uploadedBy=?) ORDER BY l.id DESC LIMIT 100');
        $query->execute([$username,$username]);
        $videos=[];
        while ($row=$query->fetch(PDO::FETCH_ASSOC)) $videos[]=new Video($this->conn,$row,$this->userLoggedInObj);
        return $videos;
    }
}
