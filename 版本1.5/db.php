<?php
$host = 'localhost';
$user = 'root';
$pass = ''; // XAMPP 預設密碼為空
$dbname = 'targame';

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}
// 設定編碼避免中文亂碼
$conn->set_charset("utf8");
?>