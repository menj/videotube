<?php
require_once __DIR__ . '/../includes/ajax.php'; 
   
   require_once("../includes/config.php");
   require_once("../includes/classes/Comment.php");
   require_once("../includes/classes/User.php");

   if(isset($_POST["commentText"]) && isset($_POST["videoId"])){
       $userLoggedInObj = new User($conn, ($_SESSION["userLoggedIn"] ?? ""));
       
       //inserting into database
       $query = $conn->prepare("INSERT INTO comments(postedBy, videoId, responseTo, body)
                                        VALUES(:postedBy, :videoId, :responseTo, :body)");
       $query->bindParam(":postedBy", $postedBy);
       $query->bindParam(":videoId", $videoId);
       $query->bindParam(":body", $commentText);
       $query->bindparam(":responseTo", $responseTo);
       
       $postedBy = requireLogin();
       $videoId = $_POST["videoId"];
       $responseTo = isset($_POST['responseTo']) ? $_POST['responseTo'] : 0;
       
       $commentText = inputText('commentText',$_POST);
       if (trim($commentText)==='' || strlen($commentText)>10000) { http_response_code(422); exit; }
       $responseTo = ($responseTo === '' || $responseTo === null) ? 0 : filter_var($responseTo,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
       if ($responseTo === false) { http_response_code(422); exit; }
       if ($responseTo > 0) {
           $parent=$conn->prepare('SELECT id FROM comments WHERE id=? AND videoId=? AND responseTo=0');
           $parent->execute([$responseTo,$videoId]);
           if (!$parent->fetchColumn()) { http_response_code(422); exit; }
       }

       $query->execute();

       $newComment = new Comment($conn, $conn->lastInsertId(), $userLoggedInObj, $videoId);
       echo $newComment->create();
    }
   else{
       http_response_code(400); echo 'Missing comment text or video.';
   }

if ($conn->inTransaction()) $conn->commit();
?>