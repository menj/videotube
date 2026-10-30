<?php
class Account {

    private $conn;
    private $errorArray = array();

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function login($un, $pw) {
        $query = $this->conn->prepare('SELECT password FROM users WHERE username=?');
        $query->execute([$un]);
        $stored = $query->fetchColumn();
        if (is_string($stored) && (password_verify($pw,$stored)
            || (preg_match('/^[a-f0-9]{128}$/D',$stored) && hash_equals($stored,hash('sha512',$pw))))) {
            if (password_needs_rehash($stored,PASSWORD_DEFAULT)) {
                $q=$this->conn->prepare('UPDATE users SET password=? WHERE username=?');
                $q->execute([password_hash($pw,PASSWORD_DEFAULT),$un]);
            }
            return true;
        }
        $this->errorArray[] = Constants::$UserLoginFailed;
        return false;
    }

    public function register($fn, $ln, $un, $em, $em2, $pw, $pw2) {
        $this->validateFirstName($fn);
        $this->validateLastName($ln);
        $this->validateUsername($un);
        $this->validateEmails($em, $em2);
        $this->validatePasswords($pw, $pw2);

        if(empty($this->errorArray)) {
            return $this->insertUserDetails($fn, $ln, $un, $em, $pw);
        }
        else {
            return false;
        }
    }

    public function updateDetails($fn, $ln, $em, $un) {
        $this->validateFirstName($fn);
        $this->validateLastName($ln);
        $this->validateNewEmail($em, $un);

        if(empty($this->errorArray)) {
            $query = $this->conn->prepare("UPDATE users SET firstName=:fn, lastName=:ln, email=:em WHERE username=:un");
            $query->bindParam(":fn", $fn);
            $query->bindParam(":ln", $ln);
            $query->bindParam(":em", $em);
            $query->bindParam(":un", $un);

            return $query->execute();
        }
        else {
            return false;
        }
    }

    public function updatePassword($oldPw, $pw, $pw2, $un) {
        $this->validateOldPassword($oldPw, $un);
        $this->validatePasswords($pw, $pw2);

        if(empty($this->errorArray)) {
            $query = $this->conn->prepare("UPDATE users SET password=:pw WHERE username=:un");
            $pw = password_hash($pw, PASSWORD_DEFAULT);
            $query->bindParam(":pw", $pw);
            $query->bindParam(":un", $un);

            return $query->execute();
        }
        else {
            return false;
        }
    }

    private function validateOldPassword($oldPw, $un) {
        $query = $this->conn->prepare('SELECT password FROM users WHERE username=?');
        $query->execute([$un]);
        $stored = $query->fetchColumn();
        if (!is_string($stored) || !(password_verify($oldPw,$stored)
            || (preg_match('/^[a-f0-9]{128}$/D',$stored) && hash_equals($stored,hash('sha512',$oldPw))))) {
            $this->errorArray[] = Constants::$passwordIncorrect;
        }
    }

    public function insertUserDetails($fn, $ln, $un, $em, $pw) {
        
        $pw = password_hash($pw, PASSWORD_DEFAULT);
        $profilePic = "assets/images/profilePictures/default.png";

        $query = $this->conn->prepare("INSERT INTO users (firstName, lastName, username, email, password, profilePic)
                                        VALUES(:fn, :ln, :un, :em, :pw, :pic)");

        $query->bindParam(":fn", $fn);
        $query->bindParam(":ln", $ln);
        $query->bindParam(":un", $un);
        $query->bindParam(":em", $em);
        $query->bindParam(":pw", $pw);
        $query->bindParam(":pic", $profilePic);
        
        return $query->execute();
    }
    
    private function validateFirstName($fn) {
        if(strlen($fn) > 25 || strlen($fn) < 2) {
            array_push($this->errorArray, Constants::$firstNameCharacters);
        }
    }

    private function validateLastName($ln) {
        if(strlen($ln) > 25 || strlen($ln) < 2) {
            array_push($this->errorArray, Constants::$lastNameCharacters);
        }
    }

    private function validateUsername($un) {
        if(!preg_match('/^[A-Za-z0-9_]{5,25}$/D',$un)) {
            array_push($this->errorArray, Constants::$usernameCharacters);
            return;
        }

        $query = $this->conn->prepare("SELECT username FROM users WHERE username=:un");
        $query->bindParam(":un", $un);
        $query->execute();

        if($query->rowCount() != 0) {
            array_push($this->errorArray, Constants::$usernameTaken);
        }

    }

    private function validateEmails($em, $em2) {
        if($em != $em2) {
            array_push($this->errorArray, Constants::$emailsDoNotMatch);
            return;
        }

        if(strlen($em)>191 || !filter_var($em, FILTER_VALIDATE_EMAIL)) {
            array_push($this->errorArray, Constants::$emailInvalid);
            return;
        }

        $query = $this->conn->prepare("SELECT email FROM users WHERE email=:em");
        $query->bindParam(":em", $em);
        $query->execute();

        if($query->rowCount() != 0) {
            array_push($this->errorArray, Constants::$emailTaken);
        }

    }

    private function validateNewEmail($em, $un) {

        if(strlen($em)>191 || !filter_var($em, FILTER_VALIDATE_EMAIL)) {
            array_push($this->errorArray, Constants::$emailInvalid);
            return;
        }

        $query = $this->conn->prepare("SELECT email FROM users WHERE email=:em AND username != :un");
        $query->bindParam(":em", $em);
        $query->bindParam(":un", $un);
        $query->execute();

        if($query->rowCount() != 0) {
            array_push($this->errorArray, Constants::$emailTaken);
        }

    }

    private function validatePasswords($pw, $pw2) {
        if($pw != $pw2) {
            array_push($this->errorArray, Constants::$passwordDoNotMatch);
            return;
        }

        if(strlen($pw) > 72 || strlen($pw) < 12) $this->errorArray[] = Constants::$passwordLength;
    }

    public function getError($error) {
        if(in_array($error, $this->errorArray)) {
            return "<span class='errorMessage'>$error</span>";
        }
    }

    public function getFirstError() {
        if(!empty($this->errorArray)) {
            return $this->errorArray[0];
        }
        else {
            return "";
        }
    }

}
?>