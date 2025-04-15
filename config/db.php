<?php

$host = 'localhost';
$dbname = 'qlns';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    die("Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage());
}

function getCurrentUser() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['user_id'])) {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT u.*, r.role_name 
            FROM users u 
            JOIN roles r ON u.role_id = r.role_id 
            WHERE u.user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    
    return false;
}

function checkPermission($requiredRole) {
    $user = getCurrentUser();
    
    if (!$user) {
        header("Location: login.php");
        exit;
    }
    
    $hasPermission = false;
    
    switch ($requiredRole) {
        case 'admin':
            $hasPermission = ($user['role_name'] == 'Admin');
            break;
        case 'hr_manager':
            $hasPermission = ($user['role_name'] == 'Admin' || $user['role_name'] == 'Quản Lý Nhân Sự');
            break;
        case 'employee':
            $hasPermission = true; 
            break;
    }
    
    if (!$hasPermission) {
        echo "<script>window.location.href = 'llv.php';</script>";
        exit;
    }
    
    return $user;
}
?>