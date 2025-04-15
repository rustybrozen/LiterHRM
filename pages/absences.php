<?php
require_once dirname(__DIR__) . '/config/db.php';


$user = checkPermission('hr_manager');


$action = isset($_POST['action']) ? $_POST['action'] : '';
$message = '';
$messageType = '';


$currentMonth = date('m');
$currentYear = date('Y');
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : $currentMonth;
$selectedYear = isset($_GET['year']) ? $_GET['year'] : $currentYear;

$isCurrentMonth = ($selectedMonth == $currentMonth && $selectedYear == $currentYear);

$selectedDepartment = isset($_GET['department_id']) ? $_GET['department_id'] : null;

$departments = [];
try {
if ($user['role_name'] === 'Admin') {    $stmt = $pdo->query("SELECT department_id, department_name FROM departments where is_active = 1 ORDER BY department_name");
}else{
    $stmt = $pdo->query("SELECT department_id, department_name FROM departments where is_active = 1 and department_id !=3 ORDER BY department_name");
  
}


    $departments = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = "Lỗi: " . $e->getMessage();
    $messageType = "danger";
}

if ($action === 'add_leave' && $isCurrentMonth) {
    try {
        $employeeId = $_POST['employee_id'];
        $leaveDate = $_POST['leave_date'];
        $selectedDay= date('d', strtotime($leaveDate));
        $selectedMonth= date('m', strtotime($leaveDate));
        $selectedYear= date('Y', strtotime($leaveDate));
        $leaveType = $_POST['leave_type'];
        $reason = $_POST['reason'];
        $convertLeaveType = ($leaveType === 'authorized') ? 1 : 0;
        
        $checkStmt = $pdo->prepare("SELECT * FROM employee_requests WHERE employee_id = ? AND start_date = ? AND request_type = 'absence'");
        $checkStmt->execute([$employeeId, $leaveDate]);
        
        if ($checkStmt->rowCount() > 0) {
            $message = "Ngày nghỉ này đã tồn tại!";
            $messageType = "warning";
        } else {

            // -------------------------
            $stmt = $pdo->prepare("
            SELECT e.* FROM employees e 
            WHERE e.employee_id  = ?
        ");
        $stmt->execute([$employeeId]);
        $employee = $stmt->fetch();
$stmt = $pdo->prepare("
SELECT ews.*, s.start_time as shift_start, s.end_time as shift_end
FROM employee_work_schedules ews
LEFT JOIN shifts s ON ews.shift_id = s.shift_id
WHERE ews.employee_id = ? AND ews.effective_date = ?
");
$stmt->execute([$employee['employee_id'], $leaveDate]);
$schedule = $stmt->fetch();

if ($schedule) {
    if ($schedule['shift_id']) {
        $startTime = $schedule['shift_start'];
        $endTime = $schedule['shift_end'];
    } else {
        $startTime = $schedule['start_time'];
        $endTime = $schedule['end_time'];
    }
} else {
    echo "<script>alert('ngày này chưa có lịch làm việc sắp xếp bên trên, vui lòng cài đặt lại lịch làm việc cho nhân viên'); window.location.href='absences.php?department_id=" . $employee['department_id'] . "&month=" . $selectedMonth . "&year=" . $selectedYear . "&employee_id=" . $employee['employee_id'] . "';</script>";
    exit;
}

if ($convertLeaveType ===1){

$stmt = $pdo->prepare("
    UPDATE employee_work_schedules 
    SET check_in = NULL, check_out = NULL, is_authorized_absence = 1, is_worked = 1
    WHERE employee_id = ?
    and effective_date = ?
");
$stmt->execute([$schedule['employee_id'], $leaveDate]);


}elseif ($convertLeaveType ===0){
    $stmt = $pdo->prepare("
    UPDATE employee_work_schedules 
    SET check_in = NULL, check_out = NULL, is_authorized_absence = 0, is_worked = 1
     WHERE employee_id = ?
    and effective_date = ?
");
$stmt->execute([$schedule['employee_id'], $leaveDate]);
}
//----------------------------------

            $stmt = $pdo->prepare("INSERT INTO employee_requests (employee_id, request_type, start_date, end_date, reason, status, reviewed_by,is_absence_authorized) VALUES (?, ?, ?, ?, ?, ?, ?,?)");
            $stmt->execute([$employeeId, 'absence', $leaveDate, $leaveDate, $reason, "approved", $user['user_id'], $convertLeaveType]);
            
            updateMonthlyLeaveCount($pdo, $employeeId, $leaveDate, $leaveType);
            
            $message = "Đã thêm ngày nghỉ thành công!";
            $messageType = "success";
        }
    } catch (PDOException $e) {
        $message = "Lỗi: " . $e->getMessage();
        $messageType = "danger";
    }
}

if ($action === 'update_leave' && $isCurrentMonth) {
    try {
        $requestId = $_POST['leave_id'];
        $leaveType = $_POST['leave_type'];
        $reason = $_POST['reason'];
        $employeeId = $_POST['employee_id'];
        
        $getOldLeaveStmt = $pdo->prepare("SELECT employee_id, start_date, is_absence_authorized FROM employee_requests WHERE request_id = ?");
        $getOldLeaveStmt->execute([$requestId]);
        $oldLeave = $getOldLeaveStmt->fetch();
        
        $newStatus = ($leaveType === 'authorized') ? '1' : '0';
        
        $stmt = $pdo->prepare("UPDATE employee_requests SET reason = ?, status = ?, reviewed_by = ?,is_absence_authorized = ? WHERE request_id = ?");
        $stmt->execute([$reason, "approved", $user['user_id'], $newStatus,$requestId  ]);
        
        if ($oldLeave) {
            $oldLeaveType = ($oldLeave['is_absence_authorized'] === 1) ? 'authorized' : 'unauthorized';

                        // -------------------------
                        $stmt = $pdo->prepare("
    SELECT e.* FROM employees e 
    WHERE e.employee_id  = ?
");
$stmt->execute([$employeeId]);
$employee = $stmt->fetch();
$stmt = $pdo->prepare("
SELECT ews.*, s.start_time as shift_start, s.end_time as shift_end
FROM employee_work_schedules ews
LEFT JOIN shifts s ON ews.shift_id = s.shift_id
WHERE ews.employee_id = ? AND ews.effective_date = ?
");
$stmt->execute([$employee['employee_id'], $oldLeave['start_date']]);
$schedule = $stmt->fetch();


if ($schedule) {

    if ($schedule['shift_id']) {
        $startTime = $schedule['shift_start'];
        $endTime = $schedule['shift_end'];
   
    } else {
        $startTime = $schedule['start_time'];
        $endTime = $schedule['end_time'];

    }
} else {
 
    // Create a new schedule entry
    $stmt = $pdo->prepare("
        SELECT dws.schedule_type_id, dws.start_time, dws.end_time
        FROM department_work_schedules dws
        WHERE dws.department_id = ?
        LIMIT 1
    ");
    $stmt->execute([$employee['department_id']]);
    $deptSchedule = $stmt->fetch();
    
    if (!$deptSchedule) {
        // Default schedule
        $scheduleTypeId = 1;
        $startTime = "08:00:00";
        $endTime = "17:00:00";
    } else {
        $scheduleTypeId = $deptSchedule['schedule_type_id'];
        $startTime = $deptSchedule['start_time'];
        $endTime = $deptSchedule['end_time'];
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO employee_work_schedules 
        (employee_id, schedule_type_id, start_time, end_time, effective_date, is_authorized_absence, is_worked)
        VALUES (?, ?, ?, ?, ?, 0, 1)
    ");
    $stmt->execute([
        $employee['employee_id'], 
        $scheduleTypeId, 
        $startTime, 
        $endTime, 
        $oldLeave['start_date']
    ]);
    
    $schedule_id = $pdo->lastInsertId();
    echo $schedule_id;
    
    // Fetch the newly created schedule
    $stmt = $pdo->prepare("
        SELECT * FROM employee_work_schedules 
        WHERE employee_id = ?
        and effective_date = ?
    ");
    $stmt->execute([$employee['employee_id'], $oldLeave['start_date']]);
    $schedule = $stmt->fetch();
}


if ($newStatus){
$stmt = $pdo->prepare("
    UPDATE employee_work_schedules 
    SET check_in = NULL, check_out = NULL, is_authorized_absence = 1, is_worked = 1
    WHERE employee_id = ?
    and effective_date = ?
");
$stmt->execute([$schedule['employee_id'], $oldLeave['start_date']]);


}else{
 
    $stmt = $pdo->prepare("
    UPDATE employee_work_schedules 
    SET check_in = NULL, check_out = NULL, is_authorized_absence = 0, is_worked = 1
    WHERE employee_id = ?
    and effective_date = ?
");
$stmt->execute([$schedule['employee_id'], $oldLeave['start_date']]);
}
//----------------------------------
            
            
            updateMonthlyLeaveCount($pdo, $oldLeave['employee_id'], $oldLeave['start_date'], $leaveType, 1, $oldLeaveType);
        }
        
        $message = "Đã cập nhật ngày nghỉ thành công!";
        $messageType = "success";
    } catch (PDOException $e) {
        $message = "Lỗi: " . $e->getMessage();
        $messageType = "danger";
    }
}



function updateMonthlyLeaveCount($pdo, $employeeId, $leaveDate, $leaveType, $increment = 1, $oldLeaveType = null) {
    $leaveMonth = date('m', strtotime($leaveDate));
    $leaveYear = date('Y', strtotime($leaveDate));

    $stmt = $pdo->prepare("SELECT performance_id, authorized_absences, unauthorized_absences FROM employee_monthly_performance WHERE employee_id = ? AND month = ? AND year = ?");
    $stmt->execute([$employeeId, $leaveMonth, $leaveYear]);
    $performance = $stmt->fetch();

    if ($performance) {
        if ($oldLeaveType !== null && $oldLeaveType !== $leaveType) {
            $oldField = ($oldLeaveType === 'authorized') ? 'authorized_absences' : 'unauthorized_absences';
            $updateStmt = $pdo->prepare("UPDATE employee_monthly_performance SET $oldField = GREATEST(0, $oldField - 1), updated_at = NOW() WHERE performance_id = ?");
            $updateStmt->execute([$performance['performance_id']]);
        }

        if ($oldLeaveType === null || $oldLeaveType !== $leaveType) {
            $field = ($leaveType === 'authorized') ? 'authorized_absences' : 'unauthorized_absences';
            $updateStmt = $pdo->prepare("UPDATE employee_monthly_performance SET $field = $field + ?, updated_at = NOW() WHERE performance_id = ?");
            $updateStmt->execute([$increment, $performance['performance_id']]);
        }
    } else {
        $deptStmt = $pdo->prepare("SELECT department_id FROM employees WHERE employee_id = ?");
        $deptStmt->execute([$employeeId]);
        $employeeInfo = $deptStmt->fetch();

        if ($employeeInfo) {
            $settingsStmt = $pdo->prepare("SELECT default_kpi, default_salary FROM departments WHERE department_id = ?");
            $settingsStmt->execute([$employeeInfo['department_id']]);
            $departmentSettings = $settingsStmt->fetch();

            $authorizedAbsences = ($leaveType === 'authorized') ? $increment : 0;
            $unauthorizedAbsences = ($leaveType === 'unauthorized') ? $increment : 0;

            $insertStmt = $pdo->prepare("INSERT INTO employee_monthly_performance 
                (employee_id, department_id, month, year, individual_kpi_target, individual_base_salary, authorized_absences, unauthorized_absences) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insertStmt->execute([
                $employeeId,
                $employeeInfo['department_id'],
                $leaveMonth,
                $leaveYear,
                $departmentSettings['default_kpi'],
                $departmentSettings['default_salary'],
                $authorizedAbsences,
                $unauthorizedAbsences
            ]);
        }
    }
}


$employees = [];
if ($selectedDepartment) {
    try {
        $stmt = $pdo->prepare("
            SELECT e.employee_id, e.first_name, e.last_name, e.id_card_number, d.department_name,
                   COALESCE(emp.authorized_absences, 0) as authorized_absences,
                   COALESCE(emp.unauthorized_absences, 0) as unauthorized_absences
            FROM employees e
            JOIN departments d ON e.department_id = d.department_id
            LEFT JOIN employee_monthly_performance emp ON e.employee_id = emp.employee_id 
                AND emp.month = ? AND emp.year = ?
            WHERE e.department_id = ?
            and e.is_locked=0
            ORDER BY e.last_name, e.first_name
        ");
        $stmt->execute([$selectedMonth, $selectedYear, $selectedDepartment]);
        $employees = $stmt->fetchAll();
    } catch (PDOException $e) {
        $message = "Lỗi: " . $e->getMessage();
        $messageType = "danger";
    }
}

$leaveDetails = [];
$selectedEmployee = isset($_GET['employee_id']) ? $_GET['employee_id'] : null;
if ($selectedEmployee) {
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                   CONCAT(u.username) as created_by_name,
                   CASE 
                       WHEN r.is_absence_authorized = 1 THEN 'authorized'
                       ELSE 'unauthorized'
                   END as leave_type
            FROM employee_requests r
            JOIN employees e ON r.employee_id = e.employee_id
            LEFT JOIN users u ON r.reviewed_by = u.user_id
            WHERE r.employee_id = ? and r.status = 'approved' AND MONTH(r.start_date) = ? AND YEAR(r.start_date) = ? 
            AND r.request_type = 'absence'
            ORDER BY r.start_date DESC
        ");
        $stmt->execute([$selectedEmployee, $selectedMonth, $selectedYear]);
        $leaveDetails = $stmt->fetchAll();
    } catch (PDOException $e) {
        $message = "Lỗi: " . $e->getMessage();
        $messageType = "danger";
    }
}


?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý ngày nghỉ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
<?php include dirname(__DIR__) . '/partials/header.php'; ?>

    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="bi bi-calendar-x"></i> Quản lý ngày nghỉ</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="get" class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label for="department_id" class="form-label">Chọn phòng ban:</label>
                                <select class="form-select" id="department_id" name="department_id" required onchange="this.form.submit()">
                                    <option value="">-- Chọn phòng ban --</option>
                                    <?php foreach ($departments as $department): ?>
                                        <option value="<?php echo $department['department_id']; ?>" <?php echo ($selectedDepartment == $department['department_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($department['department_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="month" class="form-label">Tháng:</label>
                                <select class="form-select" id="month" name="month" onchange="this.form.submit()">
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?php echo sprintf('%02d', $i); ?>" <?php echo ($selectedMonth == sprintf('%02d', $i)) ? 'selected' : ''; ?>>
                                            Tháng <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="year" class="form-label">Năm:</label>
                                <select class="form-select" id="year" name="year" onchange="this.form.submit()">
                                    <?php for ($i = (int)$currentYear - 2; $i <= (int)$currentYear; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($selectedYear == $i) ? 'selected' : ''; ?>>
                                            <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <?php if (isset($_GET['employee_id'])): ?>
                                <input type="hidden" name="employee_id" value="<?php echo $_GET['employee_id']; ?>">
                            <?php endif; ?>
                        </form>
                        
                        <?php if (!$isCurrentMonth): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle-fill"></i> Bạn đang xem dữ liệu của tháng <?php echo $selectedMonth; ?>/<?php echo $selectedYear; ?>. Dữ liệu lịch sử chỉ có thể xem, không thể chỉnh sửa.
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($selectedDepartment): ?>
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header bg-info text-white">
                                            <h5 class="mb-0">Danh sách nhân viên</h5>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-hover table-striped mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Họ và tên</th>
                                                            <th>CCCD/CMND</th>
                                                            <th>Nghỉ có phép</th>
                                                            <th>Nghỉ không phép</th>
                                                            <th>Thao tác</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (count($employees) > 0): ?>
                                                            <?php foreach ($employees as $employee): ?>
                                                                <tr class="<?php echo ($selectedEmployee == $employee['employee_id']) ? 'table-primary' : ''; ?>">
                                                                    <td><?php echo htmlspecialchars($employee['last_name'] . ' ' . $employee['first_name']); ?></td>
                                                                    <td><?php echo htmlspecialchars($employee['id_card_number']); ?></td>
                                                                    <td><?php echo $employee['authorized_absences']; ?></td>
                                                                    <td><?php echo $employee['unauthorized_absences']; ?></td>
                                                                    <td>
                                                                        <a href="?department_id=<?php echo $selectedDepartment; ?>&month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>&employee_id=<?php echo $employee['employee_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                            Chi tiết
                                                                        </a>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <tr>
                                                                <td colspan="5" class="text-center">Không có nhân viên trong phòng ban này</td>
                                                            </tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($selectedEmployee): ?>
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                                                <h5 class="mb-0">Chi tiết ngày nghỉ</h5>
                                                <span></span>
                                         
                                            </div>
                                            <div class="card-body p-0">
                                                <div class="table-responsive">
                                                    <table class="table table-hover table-striped mb-0">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>Ngày</th>
                                                                <th>Loại nghỉ</th>
                                                                <th>Lý do</th>
                                                                <th>Người tạo</th>
                                                           
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if (count($leaveDetails) > 0): ?>
                                                                <?php foreach ($leaveDetails as $leave): ?>
                                                                    <tr>
    <td><?php echo date('d/m/Y', strtotime($leave['start_date'])); ?></td>
    <td>
        <?php if ($leave['is_absence_authorized'] ===1): ?>
            <span class="badge bg-success">Có phép</span>
        <?php else: ?>
            <span class="badge bg-danger">Không phép</span>
        <?php endif; ?>
    </td>
    <td><?php echo htmlspecialchars($leave['reason']); ?></td>
    <td><?php echo htmlspecialchars($leave['created_by_name']); ?></td>
            
</tr>
                                                                <?php endforeach; ?>
                                                            <?php else: ?>
                                                                <tr>
                                                                    <td colspan="<?php echo $isCurrentMonth ? '5' : '4'; ?>" class="text-center">Không có dữ liệu ngày nghỉ</td>
                                                                </tr>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php elseif (!$selectedDepartment): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle-fill"></i> Vui lòng chọn phòng ban để xem danh sách nhân viên.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($isCurrentMonth && $selectedEmployee): ?>
    <div class="modal fade" id="addLeaveModal" tabindex="-1" aria-labelledby="addLeaveModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="addLeaveModalLabel">Thêm ngày nghỉ</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_leave">
                        <input type="hidden" name="employee_id" value="<?php echo $selectedEmployee; ?>">
                        
                        <div class="mb-3">
                            <label for="leave_date" class="form-label">Ngày nghỉ</label>
                            <input type="date" class="form-control" id="leave_date" name="leave_date" required
                                   min="<?php echo $selectedYear.'-'.$selectedMonth.'-01'; ?>"
                                   max="<?php echo $selectedYear.'-'.$selectedMonth.'-'.date('t', strtotime($selectedYear.'-'.$selectedMonth.'-01')); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="leave_type" class="form-label">Loại nghỉ</label>
                            <select class="form-select" id="leave_type" name="leave_type" required>
                                <option value="authorized">Có phép</option>
                                <option value="unauthorized">Không phép</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reason" class="form-label">Lý do</label>
                            <textarea class="form-control" id="reason" name="reason" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">Thêm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="editLeaveModal" tabindex="-1" aria-labelledby="editLeaveModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title" id="editLeaveModalLabel">Sửa ngày nghỉ</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_leave">
                        <input type="hidden" name="leave_id" id="edit_leave_id">
                        <input type="hidden" name="employee_id" value="<?php echo $selectedEmployee; ?>">
                      
                        
                        
                        <div class="mb-3">
                            <label class="form-label">Ngày nghỉ</label>
                            <input type="text" class="form-control" id="edit_leave_date" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_leave_type" class="form-label">Loại nghỉ</label>
                            <select class="form-select" id="edit_leave_type" name="leave_type" required>
                                <option value="authorized">Có phép</option>
                                <option value="unauthorized">Không phép</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_reason" class="form-label">Lý do</label>
                            <textarea class="form-control" id="edit_reason" name="reason" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-warning">Cập nhật</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    

    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.edit-leave').forEach(function(button) {
            button.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                var type = this.getAttribute('data-type');
                var reason = this.getAttribute('data-reason');
                var date = this.getAttribute('data-date');
                
                document.getElementById('edit_leave_id').value = id;
                document.getElementById('edit_leave_type').value = type;
                document.getElementById('edit_reason').value = reason;
                document.getElementById('edit_leave_date').value = date;


         
            });
        });
        
  
    </script>
</body>
</html>