<?php 
  class ButtonProvider{

    public static function createButton($text, $imageSrc, $action, $class){
      $text = htmlspecialchars((string)$text,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');
      $imageSrc = htmlspecialchars((string)$imageSrc,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');
      $action = htmlspecialchars((string)$action,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');
      $class = htmlspecialchars((string)$class,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');

        $image = ($imageSrc == null) ? "" : "<img src='$imageSrc'>";
        
        // Change action if needed.
        return "
            <button class = '$class' onclick = '$action' >
               $image
            <span class='text'>$text</span> 
            </button>
            "
            ;

    }

    
    public static function createHyperlinkButton($text, $imageSrc, $href, $class){
      $text = htmlspecialchars((string)$text,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');
      $imageSrc = htmlspecialchars((string)$imageSrc,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');
      $href = htmlspecialchars((string)$href,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');
      $class = htmlspecialchars((string)$class,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');

      $image = ($imageSrc == null) ? "" : "<img src='$imageSrc'>";
      
      // Change action if needed.
      return "
      <a href='$href'>
          <button class = '$class'  >
             $image
            <span class='text'>$text</span> 
          </button>
      </a>    
          "
          ;

  }

    public static function createProfileButton($conn, $uploadedBy){
      $userObj = new User($conn, $uploadedBy);
      $profilePic = htmlspecialchars($userObj->getUserProfilePic(),ENT_QUOTES,'UTF-8');
      $link = 'profile.php?username=' . rawurlencode($uploadedBy);

      return "
              <a href = '$link'>
                <img src = '$profilePic' class='profilePicture' >
              </a>
            ";
    }

     public static function createEditButton($videoId){
       $href = "editVideo.php?videoId=$videoId";

       $button = ButtonProvider::createHyperlinkButton("EDIT VIDEO", null, $href, "edit button");

       return "<div class='editVideoButtonContainer'>
                 $button
               </div>";
     }

     public static function createSubscriberButton($conn, $userToObj, $userLoggedInObj){
           $userTo = $userToObj->getUsername();
           $userLoggedIn = $userLoggedInObj->getUsername();

           $isSubscribedTo = $userLoggedInObj->isSubscribedTo($userTo);
           $buttonText = $isSubscribedTo ? "SUBSCRIBED" : "SUBSCRIBE";
           $buttonText .=" ". $userToObj->getSubscriberCount(); 
           
           $buttonClass = $isSubscribedTo ? "unsubscribe button" : "subscribe button";
           $action = 'subscribe(' . json_encode($userTo) . ',' . json_encode($userLoggedIn) . ',this)';

           $button = ButtonProvider::createButton($buttonText, null, $action, $buttonClass);

           return "<div class = subscribeButtonContainer>
                     $button
                   </div>";
     }

         
    public static function createUserProfileNavigationButton($conn, $username) {
      if(User::isLoggedIn()) {
          return ButtonProvider::createProfileButton($conn, $username);
      }
      else {
          return "<a href='signIn.php'>
                      <span class='signInLink'>SIGN IN</span>
                  </a>";
      }
  }

  }

?>