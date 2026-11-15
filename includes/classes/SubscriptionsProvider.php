<?php
class SubscriptionsProvider {
    private $conn, $userLoggedInObj;
    public function __construct($conn, $userLoggedInObj) {
        $this->conn=$conn; $this->userLoggedInObj=$userLoggedInObj;
    }
    public function getVideos(): array {
        $username=$this->userLoggedInObj->getUsername();
        if ($username === '') return [];
        $query=$this->conn->prepare('SELECT v.* FROM videos v INNER JOIN subscribers s ON s.userTo=v.uploadedBy WHERE s.userFrom=? AND v.privacy=1 ORDER BY v.uploadDate DESC, v.id DESC LIMIT 100');
        $query->execute([$username]);
        $videos=[];
        while ($row=$query->fetch(PDO::FETCH_ASSOC)) $videos[]=new Video($this->conn,$row,$this->userLoggedInObj);
        return $videos;
    }
}
