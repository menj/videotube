<?php 
   require_once("ButtonProvider.php");
   require_once("CommentControls.php");
   
   class Comment{
       private $conn, $sqlData, $userLoggedInObj, $videoId;

       public function __construct($conn, $input, $userLoggedInObj, $videoId){

          if(!is_array($input)){
              $query = $conn->prepare("SELECT * FROM comments where id = :id");
              $query->bindParam(":id",$input);
              $query->execute();

              $input = $query->fetch(PDO::FETCH_ASSOC);
          }

          $this->sqlData = $input;
          $this->conn = $conn;
          $this->userLoggedInObj = $userLoggedInObj;
          $this->videoId = $videoId;
       }

       public function create(){
           $id = $this->sqlData["id"];
           $videoId = $this->getVideoId();
           $body = htmlspecialchars($this->sqlData['body'],ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');
           $postedBy = htmlspecialchars($this->sqlData['postedBy'],ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');
           $profileButton = ButtonProvider::createProfileButton($this->conn, $postedBy);
           $timespan = $this->time_elapsed_string($this->sqlData["datePosted"]);
           
           $commentControlsObj = new CommentControls($this->conn, $this, $this->userLoggedInObj);
           $commentControls = $commentControlsObj->create();
           
           $numResponses = $this->getNumberOfReplies();

           if($numResponses > 0) {
               $viewRepliesText = "<span class='repliesSection viewReplies' onclick='getReplies($id, this, $videoId)'>
                                    View all $numResponses replies</span>
                                    ";
           }
           else {
               $viewRepliesText = "<div class='repliesSection'></div>";
           }
           return "
                 <div class = 'itemContainer'>
                    <div class = 'comment'>
                      $profileButton
                        <div class = 'mainContainer'>
                            <div class='commentHeader'>
                               <a href='profile.php?username=$postedBy'>
                                    <span class = 'username'>$postedBy</span>
                               </a>
                               <span class = 'timestamp'>$timespan</span>
                            </div>

                            <div class = 'body'>
                              $body
                            </div>
                        </div>
                    </div>
                    $commentControls
                    $viewRepliesText
                 </div>
              
              ";
       }
       
       public function getNumberOfReplies(){
           $query = $this->conn->prepare("SELECT count(*) FROM comments WHERE responseTo = :responseTo");
           $query->bindParam(":responseTo", $id);
           $id = $this->sqlData["id"];
           $query->execute();

           return $query->fetchColumn();
       }

       public function time_elapsed_string($datetime, $full = false) {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);
    
        $parts = [
            'y' => $diff->y, 'm' => $diff->m, 'w' => intdiv($diff->d, 7),
            'd' => $diff->d % 7, 'h' => $diff->h, 'i' => $diff->i, 's' => $diff->s,
        ];
    
        $string = array(
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        );
        foreach ($string as $k => &$v) {
            if ($parts[$k]) {
                $v = $parts[$k] . ' ' . $v . ($parts[$k] > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }
    
        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }

       public function getId(){          
           return $this->sqlData["id"];
       }

       public function getVideoId(){
           return $this->videoId;
       }
       
       public function wasLiked(){
        $id = $this->getId();
        $username = $this->userLoggedInObj->getUsername();
        
        $query = $this->conn->prepare("SELECT * FROM likes WHERE username=:username AND commentId  =:commentId");
        $query->bindParam(':username', $username);
        $query->bindParam(':commentId', $id);
    
        $query->execute();
        
        return $query->fetch(PDO::FETCH_ASSOC) !== false;
     }

        public function wasDisLiked() {
            $id = $this->getId();
            $username = $this->userLoggedInObj->getUsername();
            
            $query = $this->conn->prepare("SELECT * FROM dislikes WHERE username=:username AND commentId=:commentId");
            $query->bindParam(':username', $username);
            $query->bindParam(':commentId', $id);

            $query->execute();
            
            return $query->fetch(PDO::FETCH_ASSOC) !== false;
        }

       public function getLikes(){
           $query = $this->conn->prepare("SELECT count(*) as 'count' FROM likes WHERE commentId = :commentId");
           $query->bindParam(":commentId", $commentId);
           $commentId = $this->getId();
           $query->execute();

           $data = $query->fetch(PDO::FETCH_ASSOC);
           $numLikes = $data["count"];

           $query = $this->conn->prepare("SELECT count(*) as 'count' FROM dislikes WHERE commentId = :commentId");
           $query->bindParam(":commentId", $commentId);
           $query->execute();

           $data = $query->fetch(PDO::FETCH_ASSOC);
           $numDisLikes = $data["count"];

           return $numLikes - $numDisLikes;
           
       }

       public function like(){
        if ($this->userLoggedInObj->getUsername() === '') {
            http_response_code(403);
            return json_encode(['error' => 'Sign in to vote.']);
        }
        $commentId = $this->getId();
        $username = $this->userLoggedInObj->getUsername();
        if($this->wasLiked()){
            $query = $this->conn->prepare("DELETE FROM likes WHERE username = :username AND commentId = :commentId");
            $query->bindParam(":username", $username);
            $query->bindParam(":commentId", $commentId);

            $query->execute();
            
            return -1;
        }
        else{
            $commentId = $this->getId();
            $query = $this->conn->prepare("DELETE FROM dislikes WHERE username = :username AND commentId = :commentId");
            $query->bindParam(":username", $username);
            $query->bindParam(":commentId", $commentId);

            $query->execute();
            $count = $query->rowCount();
           // echo $count;
            $query = $this->conn->prepare("INSERT INTO likes(username,commentId) VALUES(:username, :commentId)");
            $query->bindParam(":username", $username);
            $query->bindParam(":commentId", $commentId);
            
            $query->execute();
            
            return 1 + $count;
        }
     }

     public function disLike(){
        if ($this->userLoggedInObj->getUsername() === '') {
            http_response_code(403);
            return json_encode(['error' => 'Sign in to vote.']);
        }
        $commentId = $this->getId();
        $username = $this->userLoggedInObj->getUsername();
        if($this->wasDisLiked()){
            $query = $this->conn->prepare("DELETE FROM dislikes WHERE username = :username AND commentId = :commentId");
            $query->bindParam(":username", $username);
            $query->bindParam(":commentId", $commentId);

            $query->execute();
            
            return 1;
        }
        else{
            $query = $this->conn->prepare("DELETE FROM likes WHERE username = :username AND commentId = :commentId");
            $query->bindParam(":username", $username);
            $query->bindParam(":commentId", $commentId);

            $query->execute();
            $count = $query->rowCount();

            $query = $this->conn->prepare("INSERT INTO dislikes(username,commentId) VALUES(:username, :commentId)");
            $query->bindParam(":username", $username);
            $query->bindParam(":commentId", $commentId);
            
            $query->execute();

            return -1 - $count;
        }
     }

      
    public function getReplies() {
        $query = $this->conn->prepare("SELECT * FROM comments WHERE responseTo=:commentId ORDER BY datePosted ASC");
        $query->bindParam(":commentId", $id);

        $id = $this->getId();

        $query->execute();

        $comments = "";
        $videoId = $this->getVideoId();
        while($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $comment = new Comment($this->conn, $row, $this->userLoggedInObj, $videoId);
            $comments .= $comment->create();
        }

        return $comments;
    }

   }

?>