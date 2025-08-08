<?php
// config.php — DB + session + tiny auth helpers
session_start();

$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";          // set if you have one
$DB_NAME = "phase2";    // <-- your DB name

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) { die("DB connection failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

// --- Dummy creds (change if you want)
define('DUMMY_USER', 'admin');
define('DUMMY_PASS', 'password123');

function require_login() {
  if (empty($_SESSION['user'])) {
    header("Location: index.php?msg=Please+log+in");
    exit;
  }
}
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
