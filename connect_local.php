<?php
$host = '127.0.0.1';
$user = 'aarnauser';
$pass = 'aarnapass';
$db   = 'aarna';
$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die('Local DB connect error: ' . mysqli_connect_error());
}
?>
