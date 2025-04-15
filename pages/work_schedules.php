<?php
require_once dirname(__DIR__) . '/config/db.php';

$currentUser = checkPermission('employee');
$isAdmin = ($currentUser['role_name'] == 'Admin');
$isHrManager = ($currentUser['role_name'] == 'Admin' || $currentUser['role_name'] == 'Quản Lý Nhân Sự');
$isSale = false;

$currentMonth = isset($_GET['month']) ? $_GET['month'] : date('m');
$currentYear = isset($_GET['year']) ? $_GET['year'] : date('Y');
$selectedDepartment = isset($_GET['department_id']) ? $_GET['department_id'] : ($departments[0]['department_id'] ?? null);

$actualCurrentMonth = date('m');
$actualCurrentYear = date('Y');
$isCurrentMonth = ($currentMonth == $actualCurrentMonth && $currentYear == $actualCurrentYear);
$canEdit = $isHrManager && $isCurrentMonth;
$successMsg = null;
$isAlreadyWorked = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $isHrManager) {

    function delete_day($pdo, $departmentId, $dateToDelete,$reason) {
      
        $stmt = $pdo->prepare("SELECT employee_id FROM employees WHERE department_id = ? AND is_locked = 0");
        $stmt->execute([$departmentId]);
        $employees = $stmt->fetchAll();
    
  
        foreach ($employees as $employee) {
            $stmt = $pdo->prepare("DELETE FROM employee_work_schedules 
                                   WHERE employee_id = ? AND is_worked = 0 AND effective_date = ?");
            $stmt->execute([$employee['employee_id'], $dateToDelete]);
        }


        $stmt = $pdo->prepare("
        INSERT INTO delete_day_log (department_id, 	date_deleted, reason)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$departmentId, $dateToDelete,$reason]);


        
    }
    
   
    if (isset($_POST['deleteDay'])) {
        $ngayXoa = $_POST['deleteDay'];
        $departmentId = $_POST['department_id'] ?? null;
        $reason = $_POST['reason'] ?? null;
    
        if ($departmentId && $ngayXoa) {
            delete_day($pdo, $departmentId, $ngayXoa,$reason);
           
        } else {
            echo "Thiếu thông tin để xóa ngày làm việc.";
        }
    }
    


    if (isset($_POST['delete_schedule'])) {
        $employeeId = $_POST['employee_id'];
        $effectiveDate = $_POST['effective_date'];
        
        $stmt = $pdo->prepare("SELECT * FROM employee_work_schedules WHERE employee_id = ? AND effective_date = ?");
        $stmt->execute([$employeeId, $effectiveDate]);
        $scheduleToDelete = $stmt->fetch();
        
        if ($scheduleToDelete) {
            if ($scheduleToDelete['is_worked'] == 1) {
                echo "<script>alert('Không thể xóa lịch làm việc đã được hoàn thành!'); window.location.href=window.location.href;</script>";
                exit;
            } else {
                $stmt = $pdo->prepare("DELETE FROM employee_work_schedules WHERE employee_id = ? AND effective_date = ?");
                $stmt->execute([$employeeId, $effectiveDate]);
                $successMsg = "Đã xóa lịch làm việc thành công!";
            }
        } else {
            echo "<script>alert('Không tìm thấy lịch làm việc để xóa!'); window.location.href=window.location.href;</script>";
            exit;
        }
    }
    
    if (isset($_POST['update_schedule']) && !isset($_POST['delete_schedule'])) {
        $employeeId = $_POST['employee_id'];
        $scheduleTypeId = $_POST['schedule_type_id'];
        $startDate = $_POST['effective_date'];
        
        if ($scheduleTypeId == 1) { 
           
            $startTime = $_POST['start_time'];
            $endTime = $_POST['end_time'];
            $shiftId = null;
            
         
            $stmt = $pdo->prepare("SELECT * FROM employee_work_schedules WHERE employee_id = ? AND effective_date = ? AND schedule_type_id = 1");
            $stmt->execute([$employeeId, $startDate]);
            $existingSchedule = $stmt->fetch();
            
            if ($existingSchedule && $existingSchedule['is_worked'] == 1) {
                echo "<script>alert('Lịch ngày này của nhân viên đã được nhân viên hoàn thành, không thể chỉnh sửa'); window.location.href=window.location.href;</script>";
                exit;
            }
            
            if ($existingSchedule) {
                $stmt = $pdo->prepare("
                    UPDATE employee_work_schedules 
                    SET start_time = ?, end_time = ?, updated_at = NOW()
                    WHERE employee_id = ? AND effective_date = ? AND schedule_type_id = 1 AND is_worked = 0
                ");
                $stmt->execute([$startTime, $endTime, $employeeId, $startDate]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO employee_work_schedules (employee_id, schedule_type_id, start_time, end_time, shift_id, effective_date)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$employeeId, $scheduleTypeId, $startTime, $endTime, $shiftId, $startDate]);
            }
        } else { 
            // Xử lý cho ca làm việc theo shift (scheduleTypeId == 2)
            $startTime = null;
            $endTime = null;
            $shiftId = $_POST['shift_id'];
            
            // Kiểm tra xem nhân viên đã có ca làm này trong ngày chưa
            $checkDuplicateStmt = $pdo->prepare("
                SELECT COUNT(*) as duplicate_count 
                FROM employee_work_schedules 
                WHERE employee_id = ? 
                AND effective_date = ? 
                AND shift_id = ?
            ");
            $checkDuplicateStmt->execute([$employeeId, $startDate, $shiftId]);
            $duplicateResult = $checkDuplicateStmt->fetch();
            
            if ($duplicateResult['duplicate_count'] > 0) {
                echo "<script>alert('Nhân viên đã được phân công vào ca này trong ngày. Vui lòng chọn ca khác!'); window.location.href=window.location.href;</script>";
                exit;
            }
            
            $checkShiftStmt = $pdo->prepare("
                SELECT COUNT(*) as employee_count 
                FROM employee_work_schedules 
                WHERE shift_id = ? 
                AND effective_date = ?
            ");
            $checkShiftStmt->execute([$shiftId, $startDate]);
            $shiftCount = $checkShiftStmt->fetch();
            
            if ($shiftCount['employee_count'] >= 5) {
                echo "<script>alert('Ca này trong ngày đã đầy người (tối đa 5 người). Vui lòng chọn ca khác!'); window.location.href=window.location.href;</script>";
                exit;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO employee_work_schedules (employee_id, schedule_type_id, start_time, end_time, shift_id, effective_date)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$employeeId, $scheduleTypeId, $startTime, $endTime, $shiftId, $startDate]);
        }
        
        $successMsg = "Đã cập nhật lịch làm việc thành công!";
    }
    
    if (isset($_POST['update_department_schedule'])) {
        $departmentId = $_POST['department_id'];
        $scheduleTypeId = $_POST['department_schedule_type_id'];
        $effectiveDate = $_POST['department_effective_date'];
        
        if ($scheduleTypeId == 1) { 
            $startTime = $_POST['department_start_time'];
            $endTime = $_POST['department_end_time'];
            $shiftId = null;
        } else { 
            $shiftId = $_POST['department_shift_id'];
            
          
            $stmt = $pdo->prepare("SELECT start_time, end_time FROM shifts WHERE shift_id = ?");
            $stmt->execute([$shiftId]);
            $shiftData = $stmt->fetch();
            
            $startTime = $shiftData['start_time'];
            $endTime = $shiftData['end_time'];
        }
        
     
        $stmt = $pdo->prepare("
            UPDATE department_work_schedules 
            SET schedule_type_id = ?, start_time = ?, end_time = ?, updated_at = NOW()
            WHERE department_id = ?
        ");
        $stmt->execute([$scheduleTypeId, $startTime, $endTime, $departmentId]);
        
  
        $stmt = $pdo->prepare("
            SELECT employee_id FROM employees WHERE department_id = ?
        ");
        $stmt->execute([$departmentId]);
        $employees = $stmt->fetchAll();
        
       
        foreach ($employees as $employee) {
            $stmt = $pdo->prepare("SELECT * FROM employee_work_schedules WHERE employee_id = ? AND effective_date = ?");
            $stmt->execute([$employee['employee_id'], $effectiveDate]);
            $existingSchedule = $stmt->fetch();
        
            if ($existingSchedule) {
                if ($existingSchedule['is_worked'] == 1) {
                    $isAlreadyWorked = true;
                    continue;
                }
        
                $stmt = $pdo->prepare("
                    UPDATE employee_work_schedules 
                    SET schedule_type_id = ?, start_time = ?, end_time = ?, shift_id = ?, updated_at = NOW()
                    WHERE employee_id = ? AND effective_date = ?
                ");
                $stmt->execute([$scheduleTypeId, $startTime, $endTime, $shiftId, $employee['employee_id'], $effectiveDate]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO employee_work_schedules (employee_id, schedule_type_id, start_time, end_time, shift_id, effective_date)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$employee['employee_id'], $scheduleTypeId, $startTime, $endTime, $shiftId, $effectiveDate]);
            }
        }
        if ($isAlreadyWorked) {
            $successMsg .= " Đã cập nhật lịch làm việc cho toàn bộ phòng ban thành công! Một số nhân viên đã làm việc ngày này, không thể cập nhật cho họ.";
        }else{
        $successMsg .= " Đã cập nhật lịch làm việc cho toàn bộ phòng ban thành công!";
        }
    }

    if (isset($_POST['update_month_schedule'])) {
        $departmentId = $_POST['department_id'];
        $scheduleTypeId = $_POST['month_schedule_type_id'];
        $month = $_POST['month'];
        $year = $_POST['year'];
        
        if ($scheduleTypeId == 1) { 
            $startTime = $_POST['month_start_time'];
            $endTime = $_POST['month_end_time'];
            $shiftId = null;
        } else { 
            $shiftId = $_POST['month_shift_id'];
            
            $stmt = $pdo->prepare("SELECT start_time, end_time FROM shifts WHERE shift_id = ?");
            $stmt->execute([$shiftId]);
            $shiftData = $stmt->fetch();
            
            $startTime = $shiftData['start_time'];
            $endTime = $shiftData['end_time'];
        }
        
        $stmt = $pdo->prepare("SELECT employee_id FROM employees WHERE department_id = ?");
        $stmt->execute([$departmentId]);
        $employees = $stmt->fetchAll();
        
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        
        foreach ($employees as $employee) {
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $effectiveDate = sprintf('%s-%s-%02d', $year, $month, $day);
                
                $stmt = $pdo->prepare("SELECT * FROM employee_work_schedules WHERE employee_id = ? AND effective_date = ?");
                $stmt->execute([$employee['employee_id'], $effectiveDate]);
                $existingSchedule = $stmt->fetch();
                
                if ($existingSchedule) {
                    if ($existingSchedule['is_worked'] == 1) {
                        $isAlreadyWorked = true;
                        continue;
                    }
    
                    $stmt = $pdo->prepare("
                        UPDATE employee_work_schedules 
                        SET schedule_type_id = ?, start_time = ?, end_time = ?, shift_id = ?, updated_at = NOW()
                        WHERE employee_id = ? AND effective_date = ?
                    ");
                    $stmt->execute([$scheduleTypeId, $startTime, $endTime, $shiftId, $employee['employee_id'], $effectiveDate]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO employee_work_schedules (employee_id, schedule_type_id, start_time, end_time, shift_id, effective_date)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$employee['employee_id'], $scheduleTypeId, $startTime, $endTime, $shiftId, $effectiveDate]);
                }
            }
        }
        if ($isAlreadyWorked) {
            $successMsg .= " Đã cập nhật lịch làm việc cho toàn bộ tháng thành công! Một số nhân viên đã làm việc ở một số ngày nên thao tác này sẽ không áp dụng với các nhân viên đó.";
        }else{
        $successMsg .= " Đã cập nhật lịch làm việc cho toàn bộ tháng thành công!";
        }
      
    }
    

    if (isset($_POST['delete_schedulee'])) {
        $employeeId = $_POST['employee_id'];
        $employee_schedule_id = $_POST['employee_schedule_id'];
        
        $stmt = $pdo->prepare("SELECT * FROM employee_work_schedules WHERE employee_id = ? AND employee_schedule_id = ?");
        $stmt->execute([$employeeId, $employee_schedule_id]);
        $scheduleToDelete = $stmt->fetch();
        
        if ($scheduleToDelete) {
            if ($scheduleToDelete['is_worked'] == 1) {
                echo "<script>alert('Không thể xóa lịch làm việc đã được hoàn thành!'); window.location.href=window.location.href;</script>";
                exit;
            } else {
                $stmt = $pdo->prepare("DELETE FROM employee_work_schedules WHERE employee_id = ? AND employee_schedule_id = ?");
                $stmt->execute([$employeeId, $employee_schedule_id]);
                $successMsg = "Đã xóa lịch làm việc thành công!";
            }
        } else {
            echo "<script>alert('Không tìm thấy lịch làm việc để xóa!'); window.location.href=window.location.href;</script>";
            exit;
        }
    }
    
    
  
    
  
    
}
if ($isAdmin){$stmt = $pdo->query("SELECT * FROM departments where is_active = 1 ORDER BY department_name");}else{

    $stmt = $pdo->query("SELECT * FROM departments where is_active = 1 and department_id != 3 ORDER BY department_name");
}

$departments = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM work_schedule_types");
$scheduleTypes = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM shifts");
$shifts = $stmt->fetchAll();

$currentMonth = isset($_GET['month']) ? $_GET['month'] : date('m');
$currentYear = isset($_GET['year']) ? $_GET['year'] : date('Y');
$selectedDepartment = isset($_GET['department_id']) ? $_GET['department_id'] : ($departments[0]['department_id'] ?? null);
if ($selectedDepartment === "4"){
    $isSale = true;
}
else{
    $isSale = false;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soạn lịch làm việc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .calendar-day {
            height: 120px;
            overflow-y: auto;
        }
        .calendar-day:hover {
            background-color: #f8f9fa;
        }
        .calendar-header {
            background-color: #e9ecef;
        }
        .employee-schedule {
            font-size: 0.8rem;
            margin-bottom: 2px;
            padding: 2px;
            border-radius: 3px;
        }
        .fixed-schedule {
            background-color: #e7f5ff;
            border-left: 3px solid #4dabf7;
        }
        .shift-schedule {
            background-color: #fff9db;
            border-left: 3px solid #ffd43b;
        }
    </style>
</head>
<body>
<?php include dirname(__DIR__) . '/partials/header.php'; ?>

    <div class="container-fluid mt-4">
        <h2>Soạn lịch làm việc nhân viên</h2>
        
        <?php if (isset($successMsg)): ?>
            <div class="alert alert-success"><?php echo $successMsg; ?></div>
        <?php endif; ?>

        <?php if (!$isCurrentMonth): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> Bạn đang xem lịch làm việc của <?php echo ($currentMonth < $actualCurrentMonth && $currentYear <= $actualCurrentYear) || $currentYear < $actualCurrentYear ? 'tháng trước' : 'tháng sau'; ?>. Chỉ có thể xem, không thể chỉnh sửa.
            </div>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col-md-8">
                <form class="row g-3">
                    <div class="col-md-3">
                        <label for="month" class="form-label">Tháng</label>
                        <select name="month" id="month" class="form-select" onchange="this.form.submit()">
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $i == $currentMonth ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="year" class="form-label">Năm</label>
                        <select name="year" id="year" class="form-select" onchange="this.form.submit()">
                            <?php for ($i = date('Y') - 1; $i <= date('Y') + 1; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $i == $currentYear ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="department_id" class="form-label">Phòng Ban</label>
                        <select name="department_id" id="department_id" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($departments as $department): ?>
                                <option value="<?php echo $department['department_id']; ?>" 
                                        <?php echo $department['department_id'] == $selectedDepartment ? 'selected' : ''; ?>>
                                    <?php echo $department['department_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

            <?php if ($canEdit && $selectedDepartment): ?>
            <div class="col-md-4 text-end">
                <div class="btn-group" role="group" aria-label="Basic example">
                <?php if(!$isSale){ ?>
                    <button type="button" class="btn btn-sm btn-outline-primary px-2" data-bs-toggle="modal" data-bs-target="#departmentScheduleModal">
                        <i class="bi bi-calendar-plus me-1"></i> Cài đặt lịch cho phòng ban
                    </button>
                <?php } else { ?>
                    <button type="button" class="btn btn-sm btn-outline-primary px-2" data-bs-toggle="modal" data-bs-target="#departmentSaleModal">
                        <i class="bi bi-calendar-plus me-1"></i> Quản Lý Ca Làm Của Nhân Viên
                    </button>
                <?php } ?>

                <button type="button" class="btn btn-sm btn-outline-danger px-2" data-bs-toggle="modal" data-bs-target="#deleteModal">
                        <i class="bi bi-calendar-month me-1"></i> Xóa ngày làm viêc
                    </button>

                    
      
                <?php if(!$isSale){ ?>
                    <button type="button" class="btn btn-sm btn-outline-success px-2" data-bs-toggle="modal" data-bs-target="#monthScheduleModal">
                        <i class="bi bi-calendar-month me-1"></i> Cài đặt lịch cho cả tháng
                    </button>
                <?php } ?>
             

                </div>
            </div>
            <?php endif; ?>
            
         
        </div>
        
        <?php
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
        $firstDay = date('N', strtotime("$currentYear-$currentMonth-01"));
        
        $employeeStmt = $pdo->prepare("
            SELECT e.*, u.user_id 
            FROM employees e
            LEFT JOIN users u ON e.user_id = u.user_id
            WHERE e.department_id = ?
            and e.is_locked=0
            ORDER BY e.last_name, e.first_name
        ");
        $employeeStmt->execute([$selectedDepartment]);
        $employees = $employeeStmt->fetchAll();
        
        $schedulesStmt = $pdo->prepare("
            SELECT ews.*, e.first_name, e.last_name, wst.type_name, s.shift_name, s.start_time as shift_start, s.end_time as shift_end
            FROM employee_work_schedules ews
            JOIN employees e ON ews.employee_id = e.employee_id
            JOIN work_schedule_types wst ON ews.schedule_type_id = wst.type_id
            LEFT JOIN shifts s ON ews.shift_id = s.shift_id
            WHERE e.is_locked=0 AND e.department_id = ? AND MONTH(ews.effective_date) = ? AND YEAR(ews.effective_date) = ?
        ");
        $schedulesStmt->execute([$selectedDepartment, $currentMonth, $currentYear]);
        $schedules = $schedulesStmt->fetchAll();
        
        $calendarData = [];
        foreach ($schedules as $schedule) {
            $day = (int)date('d', strtotime($schedule['effective_date']));
            if (!isset($calendarData[$day])) {
                $calendarData[$day] = [];
            }
            $calendarData[$day][] = $schedule;
        }
        ?>
        
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr class="calendar-header text-center">
                                <th>Thứ Hai</th>
                                <th>Thứ Ba</th>
                                <th>Thứ Tư</th>
                                <th>Thứ Năm</th>
                                <th>Thứ Sáu</th>
                                <th>Thứ Bảy</th>
                                <th>Chủ Nhật</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $dayCount = 1;
                            $totalCells = ceil(($firstDay - 1 + $daysInMonth) / 7) * 7;
                            
                            for ($i = 0; $i < $totalCells; $i++) {
                                if ($i % 7 == 0) {
                                    echo '<tr>';
                                }
                                
                                if ($i < $firstDay - 1 || $dayCount > $daysInMonth) {
                                    echo '<td></td>';
                                } else {
                                    $currentDate = sprintf('%s-%s-%02d', $currentYear, $currentMonth, $dayCount);
                                    $isToday = (date('Y-m-d') == $currentDate);
                                    $bgClass = $isToday ? 'bg-light' : '';
                                    
                                    echo '<td class="calendar-day ' . $bgClass . '">';
                                    echo '<div class="d-flex justify-content-between">';
                                    echo '<strong>' . $dayCount . '</strong>';
                                    
                                         if ($canEdit) {
            echo '<button type="button" class="btn btn-sm btn-outline-primary add-schedule-btn" 
                    data-date="' . $currentDate . '" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                    <i class="bi bi-plus-circle"></i>
                  </button>';
        }
                                    
                                    echo '</div>';
                                    
if (isset($calendarData[$dayCount])) {
    $maxDisplayEmployees = 3;
    $totalEmployees = count($calendarData[$dayCount]);
    
    for ($e = 0; $e < min($maxDisplayEmployees, $totalEmployees); $e++) {
        $schedule = $calendarData[$dayCount][$e];
        $scheduleClass = $schedule['type_name'] == 'Ca cố định' ? 'fixed-schedule' : 'shift-schedule';
        
        echo '<div class="employee-schedule ' . $scheduleClass . '">';
        echo '<strong>' . $schedule['last_name'] . ' ' . $schedule['first_name'] . ':</strong> ';
        
        if ($schedule['type_name'] == 'Ca cố định') {
            echo date('H:i', strtotime($schedule['start_time'])) . '-' . 
                 date('H:i', strtotime($schedule['end_time']));
        } else {
            echo 'Ca ' . $schedule['shift_name'] . ' (' . 
                 date('H:i', strtotime($schedule['shift_start'])) . '-' . 
                 date('H:i', strtotime($schedule['shift_end'])) . ')';
        }
        
        echo '</div>';
    }
    
    if ($totalEmployees > $maxDisplayEmployees) {
        echo '<div class="text-center mt-1">';
        echo '<button type="button" class="btn btn-sm btn-outline-secondary view-more-btn" 
                data-bs-toggle="modal" data-bs-target="#dayScheduleModal" 
                data-date="' . sprintf('%s-%s-%02d', $currentYear, $currentMonth, $dayCount) . '">
                +' . ($totalEmployees - $maxDisplayEmployees) . ' người khác
              </button>';
        echo '</div>';
    }
}
                                    
                                    echo '</td>';
                                    $dayCount++;
                                }
                                
                                if (($i + 1) % 7 == 0) {
                                    echo '</tr>';
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php if ($isHrManager): ?>
        <div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scheduleModalLabel">Cài đặt lịch làm việc</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" id="scheduleForm">
                    <input type="hidden" name="update_schedule" value="1">
                    <input type="hidden" name="effective_date" id="effective_date" value="">
                    <input type="hidden" name="schedule_id" id="schedule_id" value="">
                    <input type="hidden" name="is_worked" id="is_worked" value="0">

                    <div class="mb-3">
                        <label for="employee_id" class="form-label">Nhân viên</label>
                        <select name="employee_id" id="employee_id" class="form-select" required>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['employee_id']; ?>">
                                    <?php echo $employee['last_name'] . ' ' . $employee['first_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="schedule_type_id" class="form-label">Loại lịch làm việc</label>
                        <select name="schedule_type_id" id="schedule_type_id" class="form-select">
                            <option value="<?php echo $isSale ? 2 : 1; ?>">
                                <?php echo $isSale ? 'Ca Linh Hoạt' : 'Ca Cố Định';?>
                            </option>
                        </select>
                    </div>

                    <?php if (!$isSale): ?>
                        <div id="fixed_time_fields">
                            <div class="mb-3">
                                <label for="start_time" class="form-label">Giờ bắt đầu</label>
                                <input type="time" class="form-control" id="start_time" name="start_time" required>
                            </div>
                            <div class="mb-3">
                                <label for="end_time" class="form-label">Giờ kết thúc</label>
                                <input type="time" class="form-control" id="end_time" name="end_time" required>
                            </div>
                        </div>
                    <?php else: ?>
                        <div id="shift_fields">
                            <div class="mb-3">
                                <label for="shift_id" class="form-label">Ca làm việc</label>
                                <select name="shift_id" id="shift_id" class="form-select" required>
                                    <?php foreach ($shifts as $shift): ?>
                                        <option value="<?php echo $shift['shift_id']; ?>">
                                            <?php echo $shift['shift_name'] . 
                                                ' (' . date('H:i', strtotime($shift['start_time'])) . 
                                                ' - ' . date('H:i', strtotime($shift['end_time'])) . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between">
                        <?php if ($isSale) { ?>
                        <button type="submit" class="btn btn-primary">Thêm ca làm việc</button>
                    <?php } else { ?>
                        <button type="submit" class="btn btn-primary">Lưu lịch làm việc</button>
                    <?php } ?>
                    <?php if (!$isSale) {  ?>
                    <button type="button" id="deleteBtn" class="btn btn-danger" onclick="deleteSchedule()">Xóa lịch</button>
                    <?php } ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function deleteSchedule() {
    const isWorked = document.getElementById('is_worked').value;
    if (isWorked == '1') {
        alert('Không thể xóa lịch làm việc đã được hoàn thành!');
        return false;
    }
    
    if (confirm('Bạn có chắc chắn muốn xóa lịch làm việc này?')) {
        const form = document.getElementById('scheduleForm');
        const deleteInput = document.createElement('input');
        deleteInput.type = 'hidden';
        deleteInput.name = 'delete_schedule';
        deleteInput.value = '1';
        form.appendChild(deleteInput);
        form.submit();
    }
}

function openScheduleModal(scheduleId, employeeId, scheduleTypeId, startTime, endTime, shiftId, effectiveDate, isWorked) {
    document.getElementById('schedule_id').value = scheduleId || '';
    document.getElementById('employee_id').value = employeeId;
    document.getElementById('schedule_type_id').value = scheduleTypeId;
    document.getElementById('effective_date').value = effectiveDate;
    document.getElementById('is_worked').value = isWorked || '0';
    
    if (scheduleTypeId == 1) {
        document.getElementById('start_time').value = startTime || '';
        document.getElementById('end_time').value = endTime || '';
    } else if (scheduleTypeId == 2 && shiftId) {
        document.getElementById('shift_id').value = shiftId;
    }
    

    const deleteBtn = document.getElementById('deleteBtn');
    if (isWorked == '1') {
        deleteBtn.classList.add('disabled');
        deleteBtn.setAttribute('title', 'Không thể xóa lịch làm việc đã hoàn thành');
    } else {
        deleteBtn.classList.remove('disabled');
        deleteBtn.removeAttribute('title');
    }
    
    const scheduleModal = new bootstrap.Modal(document.getElementById('scheduleModal'));
    scheduleModal.show();
}
</script>




<div class="modal fade" id="departmentScheduleModal" tabindex="-1" aria-labelledby="departmentScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="departmentScheduleModalLabel">Cài đặt lịch làm việc cho phòng ban</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            
            <div class="modal-body">
                <form method="post">
                    <input type="hidden" name="update_department_schedule" value="1">
                    <input type="hidden" name="department_id" value="<?php echo $selectedDepartment; ?>">
                    <input type="hidden" name="department_schedule_type_id" value="<?php echo $isSale ? 2 : 1; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Loại lịch làm việc</label>
                        <input type="text" class="form-control" value="<?php echo $isSale ? 'Ca linh hoạt' : 'Ca cố định'; ?>" readonly>
                    </div>
                    
                    <?php if ($isSale): ?>
                        <div class="mb-3" id="department_shift_container">
                            <label for="department_shift_id" class="form-label">Ca làm việc</label>
                            <select name="department_shift_id" id="department_shift_id" class="form-select" required>
                                <option value="">-- Chọn ca làm việc --</option>
                                <?php foreach ($shifts as $shift): ?>
                                    <option value="<?php echo $shift['shift_id']; ?>">
                                        <?php echo $shift['shift_name']; ?> (<?php echo $shift['start_time']; ?> - <?php echo $shift['end_time']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <div id="department_fixed_time_container">
                            <div class="mb-3">
                                <label for="department_start_time" class="form-label">Giờ bắt đầu</label>
                                <input type="time" class="form-control" id="department_start_time" name="department_start_time" required>
                            </div>
                            <div class="mb-3">
                                <label for="department_end_time" class="form-label">Giờ kết thúc</label>
                                <input type="time" class="form-control" id="department_end_time" name="department_end_time" required>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="department_effective_date" class="form-label">Ngày áp dụng</label>
                        <input type="date" class="form-control" id="department_effective_date" name="department_effective_date" required>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Lịch làm việc sẽ được áp dụng cho tất cả nhân viên trong phòng ban.
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Cập nhật lịch làm việc</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>



<div class="modal fade" id="monthScheduleModal" tabindex="-1" aria-labelledby="monthScheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="monthScheduleModalLabel">Cài đặt lịch làm việc cho cả tháng</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <input type="hidden" name="update_month_schedule" value="1">
                        <input type="hidden" name="department_id" value="<?php echo $selectedDepartment; ?>">
                        <input type="hidden" name="month" value="<?php echo $currentMonth; ?>">
                        <input type="hidden" name="year" value="<?php echo $currentYear; ?>">
                        
                        <div class="mb-3">
                            <label for="month_schedule_type_id" class="form-label">Loại lịch làm việc</label>
                            <select name="month_schedule_type_id" id="month_schedule_type_id" class="form-select">
                                <option value="<?php echo $isSale ? '2' : '1'; ?>">
                                    <?php echo $isSale ? 'Ca Linh Hoạt' : 'Ca Cố Định'; ?>
                                </option>
                            </select>
                        </div>

                        <div class="mb-3" id="month_shift_container" style="<?php echo $isSale ? '' : 'display: none;'; ?>">
                            <label for="month_shift_id" class="form-label">Ca làm việc</label>
                            <select name="month_shift_id" id="month_shift_id" class="form-select">
                                <option value="">-- Chọn ca làm việc --</option>
                                <?php foreach ($shifts as $shift): ?>
                                    <option value="<?php echo $shift['shift_id']; ?>">
                                        <?php echo $shift['shift_name']; ?> (<?php echo $shift['start_time']; ?> - <?php echo $shift['end_time']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="month_fixed_time_container" style="<?php echo $isSale ? 'display: none;' : ''; ?>">
                            <div class="mb-3">
                                <label for="month_start_time" class="form-label">Giờ bắt đầu</label>
                                <input type="time" class="form-control" id="month_start_time" name="month_start_time">
                            </div>
                            
                            <div class="mb-3">
                                <label for="month_end_time" class="form-label">Giờ kết thúc</label>
                                <input type="time" class="form-control" id="month_end_time" name="month_end_time">
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> <strong>Lưu ý:</strong> Thao tác này sẽ cài đặt lịch làm việc cho tất cả nhân viên trong phòng ban cho toàn bộ tháng <?php echo $currentMonth; ?>/<?php echo $currentYear; ?>. Lịch làm việc hiện tại của họ sẽ bị ghi đè.
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Cập nhật lịch làm việc</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>

<div class="modal fade" id="dayScheduleModal" tabindex="-1" aria-labelledby="dayScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dayScheduleModalLabel">Lịch làm việc ngày <span id="selectedDate"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Nhân viên</th>
                                <th>Loại lịch</th>
                                <th>Thời gian</th>
                                <?php if ($canEdit && $isCurrentMonth): ?>
                                <th>Thao tác</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="dayScheduleList">
                        
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>




<div class="modal fade" id="departmentSaleModal" tabindex="-1" aria-labelledby="departmentSaleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="departmentSaleModalLabel">Quản Lý Ca Làm Của Nhân Viên</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
           
                <div class="row mb-3">
                    <div class="col-md-6">
                        <form id="dateFilterForm" method="GET" class="d-flex">
                            <input type="hidden" name="department_id" value="<?php echo $selectedDepartment; ?>">
                            <input type="date" name="filter_date" class="form-control form-control-sm me-2" 
                                   value="<?php echo isset($_GET['filter_date']) ? $_GET['filter_date'] : date('Y-m-d'); ?>">
                            <button type="submit" class="btn btn-sm btn-primary">Lọc</button>
                        </form>
                    </div>
                </div>
                
           
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Nhân Viên</th>
                                <th>Loại Lịch</th>
                                <th>Thời Gian Bắt Đầu</th>
                                <th>Thời Gian Kết Thúc</th>
                                <th>Ca</th>
                                <th>Trạng Thái</th>
                                <th>Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $filterDate = isset($_GET['filter_date']) ? $_GET['filter_date'] : date('Y-m-d');
                            
                        
                            $stmt = $pdo->prepare("
                                SELECT ews.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, wst.type_name, s.shift_name,s.start_time,s.end_time 
                                FROM employee_work_schedules ews
                                JOIN employees e ON ews.employee_id = e.employee_id
                                JOIN work_schedule_types wst ON ews.schedule_type_id = wst.type_id
                                LEFT JOIN shifts s ON ews.shift_id = s.shift_id
                                WHERE e.department_id = ? AND ews.effective_date = ?
                               
                            ");
                            $stmt->execute([$selectedDepartment, $filterDate]);
                            $schedules = $stmt->fetchAll();
                            
                            if (count($schedules) > 0) {
                                foreach ($schedules as $schedule) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($schedule['employee_name']) . "</td>";
                                    echo "<td>" . htmlspecialchars($schedule['type_name']) . "</td>";
                                    echo "<td>" . htmlspecialchars($schedule['start_time']) . "</td>";
                                    echo "<td>" . htmlspecialchars($schedule['end_time']) . "</td>";
                                    echo "<td>" . ($schedule['shift_name'] ? htmlspecialchars($schedule['shift_name']) : 'N/A') . "</td>";
                                    echo "<td>" . ($schedule['is_worked'] == 1 ? '<span class="badge bg-success">Đã hoàn thành</span>' : '<span class="badge bg-warning text-dark">Chưa hoàn thành</span>') . "</td>";
                                    echo "<td>";
                                    if ($schedule['is_worked'] == 0 && $isHrManager) {
                                        echo "<form method='POST' onsubmit=\"return confirm('Bạn có chắc chắn muốn xóa lịch làm việc này?');\">";
                                        echo "<input type='hidden' name='employee_id' value='" . $schedule['employee_id'] . "'>";
                                        echo "<input type='hidden' name='employee_schedule_id' value='" . $schedule['employee_schedule_id'] . "'>";
                                        echo "<button type='submit' name='delete_schedulee' class='btn btn-sm btn-danger'><i class='bi bi-trash'></i> Xóa</button>";
                                        echo "</form>";
                                    } else {
                                        echo "<button disabled class='btn btn-sm btn-secondary'>Không thể xóa</button>";
                                    }
                                    echo "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='7' class='text-center'>Không có dữ liệu ca làm việc cho ngày này</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>
<!-- Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteModalLabel">Chọn ngày làm việc để xóa</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="" method="POST">
      <input type="hidden" name="department_id" value="<?php echo $selectedDepartment; ?>">
        <div class="modal-body">
          <label for="deleteDay" class="form-label">Chọn ngày:</label>
          <input type="date" id="deleteDay" name="deleteDay" class="form-control" required>
          <label for="reason" class="form-label">Lý do xóa:</label>
          <input type="text" id="reason" name="reason" class="form-control" required>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
          <button type="submit" class="btn btn-danger">Xác nhận</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  document.addEventListener("DOMContentLoaded", function () {
    let today = new Date();
    let firstDay = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    let lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    let deleteInput = document.getElementById("deleteDay");

    deleteInput.setAttribute("min", firstDay.toISOString().split("T")[0]);
    deleteInput.setAttribute("max", lastDay.toISOString().split("T")[0]);
  });
</script>

<script>
  document.addEventListener("DOMContentLoaded", function () {
    let today = new Date();
    let firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    let lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    let workDateInput = document.getElementById("workDate");
    
    workDateInput.setAttribute("min", firstDay.toISOString().split("T")[0]);
    workDateInput.setAttribute("max", lastDay.toISOString().split("T")[0]);
  });
</script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const scheduleModal = document.getElementById('scheduleModal');
        if (scheduleModal) {
            scheduleModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const date = button.getAttribute('data-date');
                document.getElementById('effective_date').value = date;
            });
        }
        
        function toggleScheduleFields() {
            const scheduleType = document.getElementById('schedule_type_id').value;
            const fixedFields = document.getElementById('fixed_time_fields');
            const shiftFields = document.getElementById('shift_fields');
            
            if (scheduleType == '1') { 
                fixedFields.style.display = 'block';
                shiftFields.style.display = 'none';
            } else { 
                fixedFields.style.display = 'none';
                shiftFields.style.display = 'block';
            }
        }


const dayScheduleModal = document.getElementById('dayScheduleModal');
if (dayScheduleModal) {
    dayScheduleModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const date = button.getAttribute('data-date');
        const formattedDate = new Date(date).toLocaleDateString('vi-VN');
        document.getElementById('selectedDate').textContent = formattedDate;
        
        const tableBody = document.getElementById('dayScheduleList');
        tableBody.innerHTML = '<tr><td colspan="4" class="text-center">Đang tải dữ liệu...</td></tr>';
        
        fetch('get_day_schedules.php?date=' + date + '&department_id=' + <?php echo $selectedDepartment; ?>)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                tableBody.innerHTML = '';
                
                if (data.error) {
                    tableBody.innerHTML = `<tr><td colspan="4" class="text-center text-danger">Lỗi: ${data.error}</td></tr>`;
                    return;
                }
                
                if (data.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="4" class="text-center">Không có dữ liệu lịch làm việc cho ngày này</td></tr>';
                    return;
                }
                
                data.forEach(schedule => {
                    const row = document.createElement('tr');
                    
                    const nameCell = document.createElement('td');
                    nameCell.textContent = schedule.last_name + ' ' + schedule.first_name;
                    row.appendChild(nameCell);
                    
                    const typeCell = document.createElement('td');
                    typeCell.textContent = schedule.type_name === 'fixed' ? 'Giờ cố định' : 'Theo ca';
                    row.appendChild(typeCell);
                    
                    const timeCell = document.createElement('td');
                    if (schedule.type_name === 'fixed') {
                        timeCell.textContent = schedule.start_time && schedule.end_time ? 
                            (schedule.start_time.substring(0, 5) + ' - ' + schedule.end_time.substring(0, 5)) : 
                            'Chưa cập nhật';
                    } else {
                        timeCell.textContent = schedule.shift_name ? 
                            ('Ca ' + schedule.shift_name + ' (' + 
                            (schedule.shift_start ? schedule.shift_start.substring(0, 5) : '--') + ' - ' + 
                            (schedule.shift_end ? schedule.shift_end.substring(0, 5) : '--') + ')') : 
                            'Chưa cập nhật ca';
                    }
                    row.appendChild(timeCell);
                    
                    <?php if ($canEdit && $isCurrentMonth): ?>
                    const actionCell = document.createElement('td');
                    const editBtn = document.createElement('button');
                    editBtn.className = 'btn btn-sm btn-outline-primary me-1';
                    editBtn.innerHTML = '<i class="bi bi-pencil"></i>';
                    editBtn.setAttribute('data-bs-toggle', 'modal');
                    editBtn.setAttribute('data-bs-target', '#scheduleModal');
                    editBtn.setAttribute('data-employee-id', schedule.employee_id);
                    editBtn.setAttribute('data-date', date);
                    editBtn.onclick = function() {
                        const currentModal = bootstrap.Modal.getInstance(dayScheduleModal);
                        currentModal.hide();
                        
                        setTimeout(() => {
                            document.getElementById('employee_id').value = schedule.employee_id;
                            document.getElementById('effective_date').value = date;
                            
                            const typeSelect = document.getElementById('schedule_type_id');
                            if (typeSelect) {
                                for (let i = 0; i < typeSelect.options.length; i++) {
                                    if (typeSelect.options[i].text.toLowerCase().includes(schedule.type_name.toLowerCase())) {
                                        typeSelect.selectedIndex = i;
                                        typeSelect.dispatchEvent(new Event('change'));
                                        break;
                                    }
                                }
                            }
                            
                            if (schedule.type_name === 'fixed') {
                                if (document.getElementById('start_time') && schedule.start_time) {
                                    document.getElementById('start_time').value = schedule.start_time.substring(0, 5);
                                }
                                if (document.getElementById('end_time') && schedule.end_time) {
                                    document.getElementById('end_time').value = schedule.end_time.substring(0, 5);
                                }
                            } else if (document.getElementById('shift_id') && schedule.shift_id) {
                                document.getElementById('shift_id').value = schedule.shift_id;
                            }
                        }, 500); 
                    };
                    actionCell.appendChild(editBtn);
                    
                    row.appendChild(actionCell);
                    <?php endif; ?>
                    
                    tableBody.appendChild(row);
                });
            })
            .catch(error => {
                console.error('Error fetching schedules:', error);
                tableBody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Đã xảy ra lỗi khi tải dữ liệu. Vui lòng thử lại.</td></tr>';
            });
    });
}
    </script>
    <script>
function toggleDepartmentScheduleFields() {
    const scheduleType = document.getElementById('department_schedule_type_id').value;
    const fixedTimeContainer = document.getElementById('department_fixed_time_container');
    const shiftContainer = document.getElementById('department_shift_container');
    
    if (scheduleType == '1') { 
        fixedTimeContainer.style.display = 'block';
        shiftContainer.style.display = 'none';
        document.getElementById('department_start_time').required = true;
        document.getElementById('department_end_time').required = true;
        document.getElementById('department_shift_id').required = false;
    } else { 
        fixedTimeContainer.style.display = 'none';
        shiftContainer.style.display = 'block';
        document.getElementById('department_start_time').required = false;
        document.getElementById('department_end_time').required = false;
        document.getElementById('department_shift_id').required = true;
    }
}

function toggleMonthScheduleFields() {
    const scheduleType = document.getElementById('month_schedule_type_id').value;
    const fixedTimeContainer = document.getElementById('month_fixed_time_container');
    const shiftContainer = document.getElementById('month_shift_container');
    
    if (scheduleType == '1') { 
        fixedTimeContainer.style.display = 'block';
        shiftContainer.style.display = 'none';
        document.getElementById('month_start_time').required = true;
        document.getElementById('month_end_time').required = true;
        document.getElementById('month_shift_id').required = false;
    } else {
        fixedTimeContainer.style.display = 'none';
        shiftContainer.style.display = 'block';
        document.getElementById('month_start_time').required = false;
        document.getElementById('month_end_time').required = false;
        document.getElementById('month_shift_id').required = true;
    }
}


document.addEventListener('DOMContentLoaded', function() {
    toggleDepartmentScheduleFields();
    toggleMonthScheduleFields();
    
    document.getElementById('departmentScheduleModal').addEventListener('shown.bs.modal', toggleDepartmentScheduleFields);
    document.getElementById('monthScheduleModal').addEventListener('shown.bs.modal', toggleMonthScheduleFields);
    
});
</script>
</body>
</html>