<?php
require_once __DIR__ . '/../includes/ajax.php'; 
   
   require_once("../includes/config.php");

   if(isset($_POST["userTo"]) && isset($_POST["userFrom"])){
       $userTo = inputText('userTo',$_POST);
       $userFrom = requireLogin();
       if ($userTo === $userFrom) { http_response_code(422); exit; }
       $target=$conn->prepare('SELECT id FROM users WHERE username=?'); $target->execute([$userTo]);
       if (!$target->fetchColumn()) { http_response_code(404); exit; }

       /* 1) check wheather user is already subscribed
          2) If not subscribed insert into database
          3) else delete from the database / unsubscribe
          4) return number of subscriber 
       */
       $query = $conn->prepare("SELECT * FROM subscribers WHERE userTo = :userTo AND userFrom = :userFrom");
       $query->bindParam(":userTo", $userTo);
       $query->bindParam(":userFrom", $userFrom);
       $query->execute();

       if($query->rowCount() == 0){
           //insert
           $query = $conn->prepare("INSERT INTO subscribers(userTo, userFrom)
                                  VALUES(:userTo, :userFrom)"); 
           $query->bindParam(":userTo", $userTo);
           $query->bindParam(":userFrom", $userFrom);
           $query->execute();         
        }
       else {
           //DELETE
            $query = $conn->prepare("DELETE  FROM subscribers WHERE userTo = :userTo AND userFrom = :userFrom");
            $query->bindParam(":userTo", $userTo);
            $query->bindParam(":userFrom", $userFrom);
            $query->execute();
       }
       //RETURN NO. OF SUBS
       $query = $conn->prepare("SELECT * FROM subscribers WHERE userTo = :userTo ");
       $query->bindParam(":userTo", $userTo);
       $query->execute();

       echo $query->rowCount();

   }
   else{
       echo "one or more parameter is not passed to subscribe.php";
   }

?>