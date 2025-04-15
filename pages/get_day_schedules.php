<?php
require_once dirname(__DIR__) . '/config/db.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$departmentId = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;



try {
    $stmt = $pdo->prepare("
        SELECT ews.*, e.first_name, e.last_name, e.employee_id, wst.type_name, 
               s.shift_name, s.start_time as shift_start, s.end_time as shift_end
        FROM employee_work_schedules ews
        JOIN employees e ON ews.employee_id = e.employee_id
        JOIN work_schedule_types wst ON ews.schedule_type_id = wst.type_id
        LEFT JOIN shifts s ON ews.shift_id = s.shift_id
        WHERE e.department_id = ? AND ews.effective_date = ?
        ORDER BY e.last_name, e.first_name
    ");
    $stmt->execute([$departmentId, $date]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($schedules)) {
        echo json_encode([]);
        exit;
    }
    
    header('Content-Type: application/json');
    echo json_encode($schedules);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}