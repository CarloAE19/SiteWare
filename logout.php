<?php
session_start();
$_SESSION = array();
session_destroy();

// Redirect to the clean 'login' URL (forward timeout parameter if present)
$target = !empty($_GET['timeout']) ? "login?timeout=1" : "login";
header("Location: " . $target);
exit;
?>