<?php 


class Video {
    private $conn, $sqlData, $userLoggedInObj;

    public function __construct($conn, $input, $userLoggedInObj){
        $this->userLoggedInObj = $userLoggedInObj;
        $this->conn = $conn;
        
        if(is_array($input)){
            $this->sqlData = $input;
        }
        else{
            $query = $this->conn->prepare("SELECT * FROM videos WHERE id = :id");
            $query->bindParam(":id", $input);
            $query->execute();
            $this->sqlData = $query->fetch(PDO::FETCH_ASSOC);
            if ($this->sqlData === false) throw new OutOfBoundsException('Video not found');
        }           
    }

    public function canView(string $username = ''): bool {
        return (int)$this->sqlData['privacy'] === 1 || ($username !== '' && $username === $this->sqlData['uploadedBy']);
    }
    public function getVideoId(){
        return $this->sqlData["id"];
    }
    
    
    public function getVideoUploadedBy(){
        return $this->sqlData["uploadedBy"];
    }

    
    public function getVideoTitle(){
        return $this->sqlData["title"];
    }

    
    public function getVideoDescription(){
        return $this->sqlData["description"];
    }
    
    public function getVideoCategory(){
        return $this->sqlData["category"];
    }

    
    public function getVideoPrivacy(){
        return $this->sqlData["privacy"];
    }

    
    public function getVideoFilePath(){
        return $this->sqlData["filePath"];
    }

    
    public function getVideoUploadDate(){
        $date = $this->sqlData["uploadDate"];
        return date("M j, Y", strtotime($date));
    }

    public function getVideoTimeStamp(){
        $date = $this->sqlData["uploadDate"];
        return date("M jS,  Y", strtotime($date));
    }

    
    public function getVideoViews(){
        return $this->sqlData["views"];
    }

    
    public function getVideoDuration(){
        return $this->sqlData["duration"];
    }

    public function getVideoThumbnail() {
          // thumbnail.php falls back to the site logo, so listings need no query per video.
          return 'thumbnail.php?id=' . (int)$this->getVideoId();
    }

    /**
     * Count a view unless this viewer (account, or address for guests) was already counted for
     * this video in the current six-hour window. Uploaders do not count on their own videos.
     */
    public function recordView(string $viewer): void {
        if ($viewer !== '' && $viewer === $this->sqlData['uploadedBy']) return;
        $subject = $viewer !== '' ? 'user:' . $viewer : 'address:' . clientAddress();
        $key = rateLimitKey('view|' . (int)$this->getVideoId(), $subject, 21600);
        if (rateLimitHit($this->conn, $key, 21600) === 1) $this->increamentViews();
    }

    public function increamentViews(){
         $videoId = $this->getVideoId();

         $query = $this->conn->prepare("UPDATE videos set views = views + 1 WHERE id = :id");
         $query->bindParam(":id",$videoId);

         $query->execute();

         $this->sqlData["views"] = $this->sqlData["views"] + 1;

    }

    public function getVideoLikes(){
       $query = $this->conn->prepare("SELECT COUNT(*) AS 'count' from likes WHERE videoId = :videoId");
       $query->bindParam(":videoId",$videoId);

       $videoId = $this->getVideoId();

       $query->execute();

       $getData = $query->fetch(PDO::FETCH_ASSOC);

       return $getData["count"];
    }

    public function getVideoDisLikes(){ 
        $query = $this->conn->prepare("SELECT COUNT(*) AS 'count' from dislikes WHERE videoId = :videoId");
        $query->bindParam(":videoId",$videoId);
 
        $videoId = $this->getVideoId();
 
        $query->execute();
 
        $getData = $query->fetch(PDO::FETCH_ASSOC);
 
        return $getData["count"];
     }

     public function like(){
        if ($this->userLoggedInObj->getUsername() === '') {
            http_response_code(403);
            return json_encode(['error' => 'Sign in to vote.']);
        }
        $videoId = $this->getVideoId();
        $username = $this->userLoggedInObj->getUsername();
        if($this->wasLiked()){
            $query = $this->conn->prepare("DELETE FROM likes WHERE username = :username AND videoId = :videoId");
            $query->bindParam(":username", $username);
            $query->bindParam(":videoId", $videoId);

            $query->execute();
            
            $result = array(
                "likes" => -1,
                "disLikes" => 0
            );
            return json_encode($result);
        }
        else{
            $query = $this->conn->prepare("DELETE FROM dislikes WHERE username = :username AND videoId = :videoId");
            $query->bindParam(":username", $username);
            $query->bindParam(":videoId", $videoId);

            $query->execute();
            $count = $query->rowCount();

            $query = $this->conn->prepare("INSERT INTO likes(username,videoId) VALUES(:username, :videoId)");
            $query->bindParam(":username", $username);
            $query->bindParam(":videoId", $videoId);
            
            $query->execute();

            $result = array(
                "likes" => 1,
                "disLikes" => 0 - $count
            );
            return json_encode($result);
        }
     }

     public function disLike(){
        if ($this->userLoggedInObj->getUsername() === '') {
            http_response_code(403);
            return json_encode(['error' => 'Sign in to vote.']);
        }
        $videoId = $this->getVideoId();
        $username = $this->userLoggedInObj->getUsername();
        if($this->wasDisLiked()){
            $query = $this->conn->prepare("DELETE FROM dislikes WHERE username = :username AND videoId = :videoId");
            $query->bindParam(":username", $username);
            $query->bindParam(":videoId", $videoId);

            $query->execute();
            
            $result = array(
                "likes" => 0,
                "disLikes" => -1
            );
            return json_encode($result);
        }
        else{
            $query = $this->conn->prepare("DELETE FROM likes WHERE username = :username AND videoId = :videoId");
            $query->bindParam(":username", $username);
            $query->bindParam(":videoId", $videoId);

            $query->execute();
            $count = $query->rowCount();

            $query = $this->conn->prepare("INSERT INTO dislikes(username,videoId) VALUES(:username, :videoId)");
            $query->bindParam(":username", $username);
            $query->bindParam(":videoId", $videoId);
            
            $query->execute();

            $result = array(
                "likes" => 0 - $count,
                "disLikes" => 1
            );
            return json_encode($result);
        }
     }

     public function wasLiked(){
        $videoId = $this->getVideoId();
        $username = $this->userLoggedInObj->getUsername();
        
        $query = $this->conn->prepare("SELECT * FROM likes WHERE username=:username AND videoId=:videoId");
        $query->bindParam(':username', $username);
        $query->bindParam(':videoId', $videoId);
    
        $query->execute();
        
        return $query->fetch(PDO::FETCH_ASSOC) !== false;
     }

  public function wasDisLiked(){
    $videoId = $this->getVideoId();
    $username = $this->userLoggedInObj->getUsername();
    
    $query = $this->conn->prepare("SELECT * FROM dislikes WHERE username=:username AND videoId=:videoId");
    $query->bindParam(':username', $username);
    $query->bindParam(':videoId', $videoId);

    $query->execute();
    
    return $query->fetch(PDO::FETCH_ASSOC) !== false;
   }

   public function getNumOfComments(){
        $videoId = $this->getVideoId();
        
        $query = $this->conn->prepare("SELECT COUNT(*) FROM comments WHERE videoId = :videoId");
        $query->bindParam(':videoId', $videoId);   
        
        $query->execute();
        
        return (int)$query->fetchColumn();
   }
 }
?>