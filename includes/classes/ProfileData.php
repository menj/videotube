<?php

class ProfileData
{
    private $conn;
    private $profileUserObj;
    private $profileData;

    public function __construct($conn, $profileUsername)
    {
        $this->conn = $conn;
        $this->profileUserObj = new User($conn, $profileUsername);
    }

    public function getProfileUserObj()
    {
        return $this->profileUserObj;
    }

    public function getProfileUsername()
    {
        return $this->profileUserObj->getUsername();
    }

    public function userExists()
    {
        $query = $this->conn->prepare("SELECT * FROM users WHERE username = :username");
        $query->bindParam(":username", $profileUsername);
        $profileUsername = $this->getProfileUsername();
        $query->execute();

        return $query->fetch(PDO::FETCH_ASSOC) !== false;
    }

    public function getCoverPhoto()
    {
        return "assets/images/coverPhotos/default-cover-photo.jpg";
    }


    public function getProfileUserFullName()
    {
        return $this->profileUserObj->getName();
    }


    public function getProfilePic()
    {
        return $this->profileUserObj->getUserProfilePic();
    }

    public function getSubscriberCount()
    {
        return $this->profileUserObj->getSubscriberCount();
    }

    /** One page of the channel's videos, plus one extra row when a further page exists. */
    public function getUsersVideos(int $page = 1)
    {
        $visibility = ($_SESSION['userLoggedIn'] ?? '') === $this->getProfileUsername() ? '' : 'privacy=1 AND ';
        $query = $this->conn->prepare("SELECT * FROM videos WHERE " . $visibility . "uploadedBy=:uploadedBy ORDER BY uploadDate DESC, id DESC LIMIT " . (Pager::PAGE_SIZE + 1) . " OFFSET " . Pager::offset($page));
        $query->bindParam(":uploadedBy", $username);
        $username = $this->getProfileUsername();
        $query->execute();

        $videos = array();
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $videos[] = new Video($this->conn, $row, $this->profileUserObj);
        }
        return $videos;
    }
    public function getAllUserDetails()
    {
        return array(
        "Name" => $this->getProfileUserFullName(),
        "Username" => $this->getProfileUsername(),
        "Subscribers" => $this->getSubscriberCount(),
        "Total views" => $this->getTotalViews(),
        "Sign up date" => $this->getSignUpDate()
        );
    }

    private function getTotalViews()
    {
        $query = $this->conn->prepare("SELECT sum(views) FROM videos WHERE uploadedBy=:uploadedBy");
        $query->bindParam(":uploadedBy", $username);
        $username = $this->getProfileUsername();
        $query->execute();

        return $query->fetchColumn();
    }

    private function getSignUpDate()
    {
        $date = $this->profileUserObj->getSignUpDate();
        return date("F jS, Y", strtotime($date));
    }
}
