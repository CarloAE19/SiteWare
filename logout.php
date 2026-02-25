<?php
session_start();
$_SESSION = array();
session_destroy();

// Redirect to the clean 'login' URL
header("Location: login");
exit;
?>