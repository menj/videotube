<?php
require 'includes/config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<form method="post"><button>Log out</button></form>'; exit;
}
$_SESSION=[]; session_destroy(); header('Location: index.php');
