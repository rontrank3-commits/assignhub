<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'AI'); // Fix: khớp với CREATE DATABASE trong AI.sql
define('DB_USER', 'root');
define('DB_PASS', '');
// Fix: dùng __DIR__ thay vì hardcode path C:/wamp64/... → portable trên mọi máy
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 20 * 1024 * 1024);

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch (PDOException $e) {
            die('Lỗi kết nối database: ' . $e->getMessage());
        }
    }
    return $pdo;
}
