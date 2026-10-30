<?php
  
  class FormSanitizer{
    public static function sanitizingFormString($inputString){
      if (!is_string($inputString)) return '';
        $inputString  = strip_tags($inputString);
        $inputString =  str_replace(" " , "", $inputString);
        $inputString = strtolower($inputString);
        $inputString= ucfirst($inputString);
  
        return $inputString;
    }

    public static function sanitizingFormUser($inputString){
      if (!is_string($inputString)) return '';
      $inputString  = strip_tags($inputString);
      $inputString =  str_replace(" " , "", $inputString);

      return $inputString;
  }

  public static function sanitizingFormEmail($inputString){
      if (!is_string($inputString)) return '';
    $inputString  = strip_tags($inputString);
    $inputString =  str_replace(" " , "", $inputString);
    
    return $inputString;
}

    public static function sanitizingFormPassword($inputString){
      if (!is_string($inputString)) return '';
      return $inputString;
    }



  } 
?>
