<?php
require 'includes/config.php';
require 'includes/classes/VideoUploadData.php';
require 'includes/classes/VideoProcessor.php';
$username=requireLogin();
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit; }
$data=new VideoUploadData($_FILES['fileInput'] ?? [],inputText('titleInput',$_POST),inputText('descriptionInput',$_POST),
    filter_var($_POST['categoryInput'] ?? null,FILTER_VALIDATE_INT) ?: 0,inputText('privacyInput',$_POST),$username);
if (!(new VideoProcessor($conn))->upload($data)) { http_response_code(422); exit('Upload failed. Verify the file and details, or contact the site owner.'); }
header('Location: profile.php?username=' . rawurlencode($username));
