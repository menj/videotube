<?php
require 'includes/config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<form method="post"><button>Log out</button></form>'; exit;
}
$_SESSION=[];
$params=session_get_cookie_params();
setcookie(session_name(), '', ['expires'=>time()-3600,'path'=>$params['path'],'domain'=>$params['domain'],'secure'=>$params['secure'],'httponly'=>true,'samesite'=>'Lax']);
session_destroy(); header('Location: index.php');
