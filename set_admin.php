<?php
require 'config.php';
requireLogin();
if (!isAdmin()) die('Akses ditolak.');

$ids = [15, 14, 13, 12, 8];
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));

$stmt = $mysqli->prepare("UPDATE users SET role = 'admin' WHERE id IN ($placeholders)");
$stmt->bind_param($types, ...$ids);
$stmt->execute();

echo "Berhasil! {$stmt->affected_rows} user diubah menjadi admin.";
$stmt->close();
