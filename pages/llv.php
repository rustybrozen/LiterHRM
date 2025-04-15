<?php
require_once dirname(__DIR__) . '/config/db.php';

$currentUser = checkPermission('employee');

$currentMonth = isset($_GET['month']) ? $_GET['month'] : date('m');
$currentYear = isset($_GET['year']) ? $_GET['year'] : date('Y');

$actualCurrentMonth = date('m');
$actualCurrentYear = date('Y');
$isCurrentMonth = ($currentMonth == $actualCurrentMonth && $currentYear == $actualCurrentYear);

$stmt = $pdo->prepare("
    SELECT e.* 
    FROM employees e
    WHERE e.user_id = ? AND e.is_locked = 0
");
$stmt->execute([$currentUser['user_id']]);
$employee = $stmt->fetch();

if (!$employee) {
    die("Employee profile not found. Please contact HR.");
}

$stmt = $pdo->prepare("SELECT * FROM departments WHERE department_id = ? AND is_active = 1");
$stmt->execute([$employee['department_id']]);
$department = $stmt->fetch();

$stmt = $pdo->query("SELECT * FROM work_schedule_types");
$scheduleTypes = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM shifts");
$shifts = $stmt->fetchAll();


$schedulesStmt = $pdo->prepare("
    SELECT ews.*, wst.type_name, s.shift_name, s.start_time as shift_start, s.end_time as shift_end,
           ews.is_worked, ews.is_authorized_absence, ews.check_in, ews.check_out, ews.is_late
    FROM employee_work_schedules ews
    JOIN work_schedule_types wst ON ews.schedule_type_id = wst.type_id
    LEFT JOIN shifts s ON ews.shift_id = s.shift_id
    WHERE ews.employee_id = ? AND MONTH(ews.effective_date) = ? AND YEAR(ews.effective_date) = ?
");
$schedulesStmt->execute([$employee['employee_id'], $currentMonth, $currentYear]);
$schedules = $schedulesStmt->fetchAll();

$calendarData = [];
foreach ($schedules as $schedule) {
    $day = (int) date('d', strtotime($schedule['effective_date']));
    $calendarData[$day][] = $schedule;
}


$nextMonday = date('Y-m-d', strtotime('next monday'));
$nextWeekDates = [];
for ($i = 0; $i < 7; $i++) {
    $date = date('Y-m-d', strtotime($nextMonday . " +$i days"));
    $nextWeekDates[] = $date;
}


$employeeNextWeekShiftsStmt = $pdo->prepare("
    SELECT effective_date, shift_id 
    FROM employee_work_schedules
    WHERE employee_id = ? AND effective_date BETWEEN ? AND ?
");
$employeeNextWeekShiftsStmt->execute([
    $employee['employee_id'], 
    $nextWeekDates[0], 
    $nextWeekDates[6]
]);
$employeeNextWeekShifts = $employeeNextWeekShiftsStmt->fetchAll(PDO::FETCH_ASSOC);


$assignedShifts = [];
foreach ($employeeNextWeekShifts as $shift) {
    $day = date('w', strtotime($shift['effective_date']));
    
    $day = $day == 0 ? 7 : $day;
    $assignedShifts[$day][$shift['shift_id']] = true;
}


$shiftCountsStmt = $pdo->prepare("
    SELECT effective_date, shift_id, COUNT(*) as count
    FROM employee_work_schedules
    WHERE effective_date BETWEEN ? AND ?
    GROUP BY effective_date, shift_id
");
$shiftCountsStmt->execute([$nextWeekDates[0], $nextWeekDates[6]]);
$shiftCountsData = $shiftCountsStmt->fetchAll(PDO::FETCH_ASSOC);


$shiftCounts = [];
foreach ($shiftCountsData as $data) {
    $day = date('w', strtotime($data['effective_date']));

    $day = $day == 0 ? 7 : $day;
    $shiftCounts[$day][$data['shift_id']] = $data['count'];
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_shifts'])) {
    try {
        $pdo->beginTransaction();
        
        $message = '';
        $hasErrors = false;
        
        
        $deleteStmt = $pdo->prepare("
            DELETE FROM employee_work_schedules 
            WHERE employee_id = ? AND effective_date BETWEEN ? AND ?
        ");
        $deleteStmt->execute([
            $employee['employee_id'],
            $nextWeekDates[0],
            $nextWeekDates[6]
        ]);
        
       
        foreach ($_POST['shifts'] as $dayIndex => $shifts) {
       
            $date = $nextWeekDates[$dayIndex];
            
            foreach ($shifts as $shiftId) {
            
                $checkStmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM employee_work_schedules 
                    WHERE effective_date = ? AND shift_id = ?
                ");
                $checkStmt->execute([$date, $shiftId]);
                $count = $checkStmt->fetchColumn();
                
                if ($count >= 5) {
                    $dayName = ['thứ hai', 'thứ ba', 'thứ tư', 'thứ năm', 'thứ sáu', 'thứ bảy', 'chủ nhật'][$dayIndex];
                    $shiftNames = [1 => 'Sáng', 2 => 'Chiều', 3 => 'Tối'];
                    $message .= "Ca {$shiftNames[$shiftId]} vào {$dayName} đã đầy.";
                    $hasErrors = true;
                    continue;
                }
                
              
                $insertStmt = $pdo->prepare("
                    INSERT INTO employee_work_schedules 
                    (employee_id, schedule_type_id, shift_id, effective_date) 
                    VALUES (?, 2, ?, ?)
                ");
                $insertStmt->execute([$employee['employee_id'], $shiftId, $date]);
            }
        }
        
        if ($hasErrors) {
            $pdo->rollBack();
            echo "<script>alert('Có lỗi xảy ra: {$message}');</script>";
        } else {
            $pdo->commit();
            echo "<script>
                alert('Đăng ký ca làm thành công cho tuần sau thành công.');
                window.location.href = window.location.pathname; 
            </script>";
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('An error occurred: " . addslashes($e->getMessage()) . "');</script>";
    }
}


if (isset($_GET['selected_date']) && isset($_GET['shift_id'])) {
    $request = [
        'employee_id' => $employee['employee_id'],
        'shift_id' => $_GET['shift_id'],
        'start_date' => $_GET['selected_date'],
    ];

    $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM employee_work_schedules 
            WHERE employee_id = ? AND effective_date = ? AND shift_id =?
        ");
    $stmt->execute([$request['employee_id'], $request['start_date'], $request['shift_id']]);
    $employeeShiftCount = $stmt->fetchColumn();

    if ($employeeShiftCount > 0) {
        echo "<script>alert('Bạn này đã đăng ký ca trùng lặp trong ngày hôm nay.');</script>";
        exit;
    }

    $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM employee_work_schedules 
            WHERE effective_date = ? AND shift_id = ?
        ");
    $stmt->execute([$request['start_date'], $request['shift_id']]);
    $totalShiftCount = $stmt->fetchColumn();

    if ($totalShiftCount >= 5) {
        echo "<script>alert('Bạn không thể yêu cầu do ca này trong ngày hôm nay đã đầy người!');</script>";
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO employee_work_schedules 
        (employee_id, schedule_type_id, shift_id, effective_date) 
        VALUES (?, 2, ?, ?)
    ");
    $stmt->execute([$request['employee_id'], $request['shift_id'], $request['start_date']]);
    echo "<script>alert('Đăng ký ca thành công!');</script>";
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch làm việc của tôi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .calendar-day {
            height: 100px;
            overflow-y: auto;
        }

        .calendar-day:hover {
            background-color: #f8f9fa;
        }

        .calendar-header {
            background-color: #e9ecef;
        }

        .schedule-item {
            padding: 5px;
            margin-top: 5px;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .fixed-schedule {
            background-color: #e7f5ff;
            border-left: 3px solid #4dabf7;
        }

        .shift-schedule {
            background-color: #fff9db;
            border-left: 3px solid #ffd43b;
        }

        .today {
            background-color: #e9ecef;
            border: 2px solid #0d6efd;
        }

     
        .authorized-absence {
            background-color: #d4edda;
          
            border: 2px solid #28a745;
        }

        .worked-day {
            background-color: #cce5ff;
     
            border: 2px solid #007bff;
        }

        .missed-day {
            background-color: #f8d7da;
      
            border: 2px solid #dc3545;
        }

        .month-nav {
            font-size: 1.2rem;
        }

        .no-schedule {
            color: #6c757d;
            font-style: italic;
            font-size: 0.85rem;
        }

        .attendance-status {
            font-size: 0.8rem;
            padding: 2px 5px;
            border-radius: 3px;
            margin-top: 3px;
            display: inline-block;
        }

        .status-present {
            background-color: #cce5ff;
            color: #004085;
        }

        .status-authorized {
            background-color: #d4edda;
            color: #155724;
        }

        .status-absent {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-late {
            background-color: rgb(245, 187, 139);
            color: rgb(127, 81, 21);
        }

        .shift-table td {
            text-align: center;
            vertical-align: middle;
            padding: 8px 15px;
        }

        .day-label {
            font-weight: 500;
            color: #d35400;
        }

        .shift-label {
            font-weight: 500;
        }

        .ca-sang {
            color: #3498db;
        }

        .ca-chieu {
            color: #9b59b6;
        }

        .ca-toi {
            color: #e74c3c;
        }

        .modal-title {
            font-weight: bold;
        }

        #exitbtn {
            background-color: #34495e;
            color: white;
        }

        #registerbtn {
            background-color:  #34495e;
            color: white;
        }
    </style>
</head>

<body>
    <?php include dirname(__DIR__) . '/partials/header.php'; ?>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h2>Lịch làm việc của tôi</h2>
                <p class="lead">
                    <strong><?php echo htmlspecialchars($employee['last_name'] . ' ' . $employee['first_name']); ?></strong>
                    <?php if ($department): ?>
                        - Phòng: <?php echo htmlspecialchars($department['department_name']); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">Tháng <?php echo $currentMonth; ?>/<?php echo $currentYear; ?></h3>
                        <div>
                            <form method="get" class="d-flex">
                                <select name="month" class="form-select form-select-sm me-2">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?php echo $m; ?>" <?php echo $m == $currentMonth ? 'selected' : ''; ?>>
                                            Tháng <?php echo $m; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <select name="year" class="form-select form-select-sm me-2">
                                    <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                                        <option value="<?php echo $y; ?>" <?php echo $y == $currentYear ? 'selected' : ''; ?>>
                                            <?php echo $y; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-primary">Xem</button>
                               
<?php if ($employee['department_id'] == 4): ?>
<button type="button" class="btn btn-sm btn-primary ms-1" style="width:550px"
data-bs-toggle="modal" data-bs-target="#shiftRegistrationModal">
    Đăng ký ca làm tuần sau
</button>
<?php endif; ?>




                            </form>
                        </div>
                    </div>
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
                                    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
                                    $firstDay = date('N', strtotime("$currentYear-$currentMonth-01"));
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
                                            $todayClass = $isToday ? 'today' : '';


                                            $statusClass = '';
                                            $statusLabel = '';

                                            if (isset($calendarData[$dayCount])) {
                                                $schedule = $calendarData[$dayCount];

                                                if (isset($schedule['is_worked']) && $schedule['is_worked'] == 1) {
                                                    if ($schedule['is_authorized_absence'] == 1) {
                                                        $statusClass = 'authorized-absence';
                                                        $statusLabel = '<span class="attendance-status status-authorized">Vắng có phép</span>';
                                                    } elseif ($schedule['check_in'] && $schedule['check_out']) {
                                                        if ($schedule['is_late'] == true) {
                                                            $statusClass = 'missed-day';
                                                            $statusLabel = '<span class="attendance-status status-late">Đi muộn</span>';
                                                        } else {
                                                            $statusClass = 'worked-day';
                                                            $statusLabel = '<span class="attendance-status status-present">Đã làm việc</span>';
                                                        }
                                                        ;







                                                    } else {
                                                        $statusClass = 'missed-day';
                                                        $statusLabel = '<span class="attendance-status status-absent">Vắng không phép</span>';
                                                    }
                                                }
                                            }

                                            echo '<td class="calendar-day ' . $todayClass . ' ' . $statusClass . '">';
                                            echo '<div class="d-flex justify-content-between">';
                                            echo '<strong>' . $dayCount . '</strong>';
                                            if ($isCurrentMonth && strtotime($currentDate) >= strtotime(date('Y-m-d')) && $employee['department_id'] == 4) {
                                                echo '<a href="#" class="add-shift" data-bs-toggle="modal" data-bs-target="#addShiftModal" 
                                                data-date="' . $currentDate . '"></a>';
                                            }
                                            echo '</div>';






                                            if (isset($calendarData[$dayCount])) {
                                                $daySchedules = $calendarData[$dayCount];

                                                foreach ($daySchedules as $schedule) {
                                                    $scheduleClass = $schedule['type_name'] == 'Ca cố định' ? 'fixed-schedule' : 'shift-schedule';

                                                    echo '<div class="schedule-item ' . $scheduleClass . '">';

                                                    if ($schedule['type_name'] == 'Ca cố định') {
                                                        echo '<i class="bi bi-clock"></i> ';
                                                        echo date('H:i', strtotime($schedule['start_time'])) . ' - ' .
                                                            date('H:i', strtotime($schedule['end_time']));
                                                    } else {
                                                        echo '<i class="bi bi-calendar-check"></i> ';
                                                        echo 'Ca ' . $schedule['shift_name'] . '<br>';
                                                        echo date('H:i', strtotime($schedule['shift_start'])) . ' - ' .
                                                            date('H:i', strtotime($schedule['shift_end']));
                                                    }

                                                    echo '</div>';


                                                    $statusLabel = '';
                                                    if ($schedule['is_worked'] == 1) {
                                                        if ($schedule['is_authorized_absence'] == 1) {
                                                            $statusLabel = '<span class="attendance-status status-authorized">Vắng có phép</span>';
                                                        } elseif ($schedule['check_in'] && $schedule['check_out']) {
                                                            if ($schedule['is_late'] == true) {
                                                                $statusLabel = '<span class="attendance-status status-late">Đi muộn</span>';
                                                            } else {
                                                                $statusLabel = '<span class="attendance-status status-present">Đã làm việc</span>';
                                                            }




                                                        } else {
                                                            $statusLabel = '<span class="attendance-status status-absent">Vắng không phép</span>';
                                                        }
                                                        echo $statusLabel;
                                                    }


                                                    if ($schedule['check_in'] && $schedule['check_out']) {
                                                        echo '<div style="font-size: 0.8rem; margin-top: 3px;">';
                                                        echo '<i class="bi bi-arrow-right-circle"></i> ' . date('H:i', strtotime($schedule['check_in']));
                                                        echo ' <i class="bi bi-arrow-left-circle"></i> ' . date('H:i', strtotime($schedule['check_out']));
                                                        echo '</div>';
                                                    }
                                                }
                                            } else {
                                                echo '<div class="no-schedule">Không có ca làm việc</div>';
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
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Chú thích</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <div class="me-2 fixed-schedule" style="width: 20px; height: 20px;"></div>
                            <div>Ca cố định</div>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <div class="me-2 shift-schedule" style="width: 20px; height: 20px;"></div>
                            <div>Ca luân phiên</div>
                        </div>
                        <hr>
                        <h6>Trạng thái chấm công:</h6>
                        <div class="d-flex align-items-center mb-2">
                            <div class="me-2"
                                style="width: 20px; height: 20px; background-color: #cce5ff; border: 2px solid #007bff;">
                            </div>
                            <div>Đã làm việc</div>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <div class="me-2"
                                style="width: 20px; height: 20px; background-color: #d4edda; border: 2px solid #28a745;">
                            </div>
                            <div>Vắng có phép</div>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <div class="me-2"
                                style="width: 20px; height: 20px; background-color: #f8d7da; border: 2px solid #dc3545;">
                            </div>
                            <div>Vắng không phép</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Thống kê tháng</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $totalDays = count($schedules);
                        $workedDays = 0;
                        $authorizedAbsences = 0;
                        $unauthorizedAbsences = 0;
                        $dayy = "ngày";

                        foreach ($schedules as $schedule) {
                            if ($schedule['is_worked'] == 1) {
                                if ($schedule['is_authorized_absence'] == 1) {
                                    $authorizedAbsences++;
                                } elseif ($schedule['check_in'] && $schedule['check_out']) {
                                    $workedDays++;
                                } else {
                                    $unauthorizedAbsences++;
                                }
                            }
                        }
                        if ($employee['department_id'] == 4){
$dayy = "ca";
                        }

                            
                        ?>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Tổng số <?php echo $dayy; ?> trong lịch:</strong> <?php echo $totalDays; ?></p>
                                <p><strong>Số <?php echo $dayy; ?> đã làm việc:</strong> <?php echo $workedDays; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Số ngày vắng có phép:</strong> <?php echo $authorizedAbsences; ?></p>
                                <p><strong>Số <?php echo $dayy; ?> vắng không phép:</strong> <?php echo $unauthorizedAbsences; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="shiftRegistrationModal" tabindex="-1" aria-labelledby="shiftRegistrationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="shiftRegistrationModalLabel">
                    Đăng ký ca làm (<?php echo date('d/m', strtotime($nextWeekDates[0])); ?>-<?php echo date('d/m/Y', strtotime($nextWeekDates[6])); ?>)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="shiftRegistrationForm" method="post">
                    <table class="table table-borderless shift-table">
                        <thead>
                            <tr>
                                <th></th>
                                <th class="shift-label ca-sang" style="background:rgb(230,243,250)">Ca sáng</th>
                                <th class="shift-label ca-chieu" style="background:rgb(253,229,253)">Ca chiều</th>
                                <th class="shift-label ca-toi" style="background:rgb(253,229,253)">Ca tối</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $dayLabels = ['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'Chủ nhật'];
                            $assigned = false;
                            for ($i = 0; $i < 7; $i++) {
                                $dayIndex = $i + 1; 
                                $date = $nextWeekDates[$i];
                                $border='';
                                if ($i >=1 && $i <= 5) {
                                     $border = 'style="border-right: 2px solid grey;"';
                                }
                                echo '<tr>';
                                echo "<td $border class='day-label'>{$dayLabels[$i]}</td>";
                                
                            
                                foreach ([1, 3, 2] as $shift) {

                                    $isAssigned = isset($assignedShifts[$dayIndex][$shift]);
                                    $count = isset($shiftCounts[$dayIndex][$shift]) ? $shiftCounts[$dayIndex][$shift] : 0;
                                    $isFull = $count >= 5;
                             $assigned = $assigned || $isAssigned;
                                    $checked = $isAssigned ? 'checked' : '';
                                    $disabled = $isFull && !$isAssigned || $checked === 'checked' || $assigned ? 'disabled' : '';
                                    
                                    echo "<td $border>";
                                    echo "<div class='position-relative'>";
                                    echo "<input type='checkbox' name='shifts[$i][]' value='$shift' class='form-check-input' $checked $disabled>";
                                    
                            
                                    $colorClass = $isFull ? 'text-danger' : 'text-success';
                                    if ($isFull) {
                                        echo "<small class='d-block mt-1 $colorClass'>Đã đầy</small>";
                                    }
                                  
                                    
                                    echo "</div>";
                                    echo "</td>";
                                }
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                    <?php 
                    if (!$assigned) {
                        echo '<div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Lưu ý: Lịch đăng ký không thể thay đổi sau khi xác nhận!
                    </div>
                    <div class="d-flex justify-content-center mt-3">
                        <button id="exitbtn" type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal" style="width: 120px;">Thoát</button>
                        <button id="registerbtn" type="submit" name="register_shifts" class="btn btn-primary" style="width: 120px;">Đăng ký lịch</button>
                    </div>';
                      
                    
                    }
                    ?>
                   
                  
                </form>
            </div>
        </div>
    </div>
</div>

 <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('shiftRegistrationForm');
    const checkboxes = form.querySelectorAll('input[type="checkbox"]');
    form.addEventListener('submit', function(e) {
        
        if (!validateShiftCount()) {
            e.preventDefault();
            alert('Bạn đã đăng ký quá nhiều ca trong tuần. Vui lòng giảm số lượng ca đăng ký.');
            return false;
        }
        
        if (!confirm('Bạn chắc chắn muốn đăng ký lịch làm việc này? Lịch không thể thay đổi sau khi đăng ký!')) {
            e.preventDefault();
            return false;
        }
    });
  
    function updateShiftCounts() {
        const dayShiftCounts = {};
        
  
        for (let day = 0; day < 7; day++) {
            dayShiftCounts[day] = {};
            for (let shift = 1; shift <= 3; shift++) {
              
                const countElement = form.querySelector(`[name="shifts[${day}][]"][value="${shift}"]`)
                    .closest('div').querySelector('small');
                const currentCount = parseInt(countElement.textContent.split('/')[0]);
                dayShiftCounts[day][shift] = currentCount;
            }
        }
        
        return dayShiftCounts;
    }
    
 
    function validateShiftCount() {
 
        return true;
    }
    
   
    const shiftCounts = updateShiftCounts();
    


    
 
    checkboxes.forEach(checkbox => {
        const countElement = checkbox.closest('div').querySelector('small');
        const [current, max] = countElement.textContent.split('/').map(Number);
        
        if (current >= max && !checkbox.checked) {
            checkbox.disabled = true;
            countElement.classList.remove('text-success');
            countElement.classList.add('text-danger');
        }
    });


    const addButtons = document.querySelectorAll('.add-shift');


addButtons.forEach(button => {
    button.addEventListener('click', function () {
        const selectedDate = this.getAttribute('data-date');
        document.getElementById('selected_date').value = selectedDate;
    });
});
});
</script>

 

   

</body>

</html>