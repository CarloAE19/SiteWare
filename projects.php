<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit;
}
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: index");
    exit;
}
header("Location: settings?tab=projects", true, 302);
exit;