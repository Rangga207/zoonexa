<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'Rangga';
// Set environment mock
putenv("DB_HOST=127.0.0.1"); // Use 127.0.0.1 so it connects via TCP instead of socket on mac
require 'index.php';
