<?php
require_once dirname(__DIR__) . '/config/db.php';
checkPermission('hr_manager');
$isSuccess = 0;
$isError = 0;
$isUnauthorized = 0;
$isAuthorized =0;

function updateRemainingSchedules($employee_id, $month, $year) {
    global $pdo, $isUnauthorized;
    
    $start_date = "$year-$month-01";
    $current_date = date('Y-m-d');
    
    $end_date = ($year == date('Y') && $month == date('m')) 
        ? $current_date 
        : date('Y-m-t', strtotime($start_date)); 
    
    $update_stmt = $pdo->prepare("
        UPDATE employee_work_schedules
        SET check_in = NULL, 
            check_out = NULL, 
            is_allowance = 0, 
            is_authorized_absence = 0,
            is_worked = 1
        WHERE employee_id = ?
        AND effective_date BETWEEN ? AND ?
        AND is_worked = 0
        AND is_authorized_absence = 0
    ");

    $update2_stmt = $pdo->prepare("
    UPDATE employee_monthly_performance
    SET unauthorized_absences = ?
    WHERE employee_id = ?
    AND month = ?
    AND year = ?

");
    
    if ($update_stmt->execute([$employee_id, $start_date, $end_date])) {
        $affectedRows = $update_stmt->rowCount();
        $isUnauthorized = $affectedRows; 
        $update2_stmt->execute([$isUnauthorized, $employee_id, $month, $year]);
        return $affectedRows;
    } else {
        
        return 0;
    }
}

function updateAllSchedulesToCurrentDate() {
    global $pdo;
    $current_date = date('Y-m-d');
    
    $update_stmt = $pdo->prepare("
        UPDATE employee_work_schedules
        SET is_worked = 1
        WHERE effective_date <= ?
        AND is_worked = 0
        AND is_authorized_absence = 0
    ");
    
    $update_stmt->execute([$current_date]);
    
    return $update_stmt->rowCount();
}

function getEmployeeDetails($employee_code)
{
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT e.employee_id, e.department_id, d.department_name 
        FROM employees e
        JOIN departments d ON e.department_id = d.department_id
        WHERE e.employee_id = ?
    ");
    $stmt->execute([$employee_code]);
    return $stmt->fetch();
}

function checkEmployeeSchedule($employee_id, $effective_date)
{
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT * FROM employee_work_schedules 
        WHERE employee_id = ? AND effective_date = ?
    ");
    $stmt->execute([$employee_id, $effective_date]);
    return $stmt->fetchAll();
}

function getShiftDetails($shift_id)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM shifts WHERE shift_id = ?");
    $stmt->execute([$shift_id]);
    return $stmt->fetch();
}

function findBestMatchingShift($check_in, $check_out, $schedules)
{
    $best_match = null;
    $min_time_diff = PHP_INT_MAX;
    
    foreach ($schedules as $schedule) {
        if ($schedule['shift_id']) {
            $shift = getShiftDetails($schedule['shift_id']);
            $shift_start = strtotime($shift['start_time']);
            $shift_end = strtotime($shift['end_time']);
            
            $check_in_time = strtotime($check_in);
            $check_out_time = strtotime($check_out);
            
           
            $time_diff = abs($check_in_time - $shift_start) + abs($check_out_time - $shift_end);
            
            if ($time_diff < $min_time_diff) {
                $min_time_diff = $time_diff;
                $best_match = $schedule;
            }
        }
    }
    
    return $best_match;
}

function getEmployeeMonthsFromImport($file_path) {
    $employee_months = [];
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    
    $file_extension = pathinfo($file_path, PATHINFO_EXTENSION);
    if ($file_extension == 'csv') {
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
    } elseif ($file_extension == 'xls') {
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
    } else {
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
    }

    $spreadsheet = $reader->load($file_path);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();
    
    array_shift($rows); // Remove header row
    
    foreach ($rows as $row) {
        if (empty($row[0]) || empty($row[1])) {
            continue;
        }
        
        $attendance_date = $row[0];
        $employee_code = $row[1];
        
        try {
            // Try to parse date format dd/mm/yyyy
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $attendance_date)) {
                $date_parts = explode('/', $attendance_date);
                $day = $date_parts[0];
                $month = $date_parts[1];
                $year = $date_parts[2];
            } else {
                $date_obj = DateTime::createFromFormat('Y-m-d', $attendance_date);
                if (!$date_obj) {
                    $date_obj = DateTime::createFromFormat('m/d/Y', $attendance_date);
                    if (!$date_obj) {
                        continue;
                    }
                    $month = $date_obj->format('m');
                    $year = $date_obj->format('Y');
                } else {
                    $month = $date_obj->format('m');
                    $year = $date_obj->format('Y');
                }
            }
            
            $key = $employee_code . '-' . $month . '-' . $year;
            $employee_months[$key] = [
                'employee_code' => $employee_code,
                'month' => $month,
                'year' => $year
            ];
        } catch (Exception $e) {
            continue;
        }
    }
    
    return $employee_months;
}

function resetEmployeeWorkSchedules($employee_id, $month, $year) 
{
    global $pdo;
    $start_date = "$year-$month-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    $current_date = date('Y-m-d');
    
    // If the month includes the current month, only reset up to today
    if ($year == date('Y') && $month == date('m')) {
        $end_date = $current_date;
    }
    
    $reset_stmt = $pdo->prepare("
            UPDATE employee_work_schedules
            SET check_in = NULL, 
                check_out = NULL, 
                is_worked = 0, 
                is_allowance = 0, 
                is_late = 0,
                is_authorized_absence = 0
            WHERE employee_id = ? 
            AND effective_date BETWEEN ? AND ?
    ");
    $reset_stmt->execute([$employee_id, $start_date, $end_date]);
        
    return $reset_stmt->rowCount();
}

if (isset($_POST['submit'])) {
    $errors = [];
    $success = [];
    $skipped = [];
    
    $importSuccess = false;

    if (isset($_FILES['attendance_file']) && $_FILES['attendance_file']['error'] == 0) {
        $allowed_extensions = ['xls', 'xlsx', 'csv'];
        $file_extension = pathinfo($_FILES['attendance_file']['name'], PATHINFO_EXTENSION);

        if (in_array(strtolower($file_extension), $allowed_extensions)) {
            require_once dirname(__DIR__) . '/vendor/autoload.php';
            
            $tmpFile = $_FILES['attendance_file']['tmp_name'];
            
            // FIRST PASS: Collect employee-month combinations and reset their data
            $employee_months = getEmployeeMonthsFromImport($tmpFile);
            
            foreach ($employee_months as $key => $data) {
                $employee = getEmployeeDetails($data['employee_code']);
                if (!$employee) {
                    continue;
                }
                
                $employee_id = $employee['employee_id'];
                $month = $data['month'];
                $year = $data['year'];
                
                // Reset all data for this employee in this month
                $reset_count = resetEmployeeWorkSchedules($employee_id, $month, $year);
                if ($reset_count > 0) {
                    $success[] = "Đã reset lại dữ liệu chấm công cho nhân viên mã {$data['employee_code']} trong tháng $month/$year ($reset_count bản ghi).";
                    $isSuccess = true;
                }
            }
            
            // SECOND PASS: Import attendance data
            if ($file_extension == 'csv') {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
            } elseif ($file_extension == 'xls') {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
            } else {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            }

            $spreadsheet = $reader->load($tmpFile);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            array_shift($rows); 
            $successCount = 0;

            foreach ($rows as $index => $row) {
                $row_number = $index + 2;
                
                if (empty($row[0]) && empty($row[1]) && empty($row[2])) {
                    continue;
                }

                $attendance_date = $row[0]; 
                $employee_code = $row[1]; 
                $employee_name = $row[2]; 
                $check_in = $row[3]; 
                $check_out = $row[4]; 
                $note = $row[5]; 

                if (!empty($attendance_date)) {
                    try {
                        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $attendance_date)) {
                            $date_parts = explode('/', $attendance_date);
                            $day = $date_parts[0];
                            $month = $date_parts[1];
                            $year = $date_parts[2];
                            $attendance_date = "$year-$month-$day"; 
                        } else {
                            $date_obj = DateTime::createFromFormat('Y-m-d', $attendance_date);
                            if (!$date_obj) {
                                $date_obj = DateTime::createFromFormat('m/d/Y', $attendance_date);
                                if ($date_obj) {
                                    $attendance_date = $date_obj->format('Y-m-d');
                                } else {
                                    throw new Exception("Invalid date format");
                                }
                            }
                        }
                    } catch (Exception $e) {
                        $errors[] = "Dòng $row_number: Ngày chấm công không hợp lệ - $attendance_date.";
                        continue;
                    }
                }

                if (empty($employee_code)) {
                    $errors[] = "Dòng $row_number: Mã nhân viên không được để trống.";
                    continue;
                }

                $employee = getEmployeeDetails($employee_code);
                if (!$employee) {
                    $errors[] = "Dòng $row_number: Nhân viên với mã $employee_code không tồn tại.";
                    continue;
                }

                $schedules = checkEmployeeSchedule($employee['employee_id'], $attendance_date);
                if (empty($schedules)) {
                    $errors[] = "Dòng $row_number: Lịch làm việc của nhân viên $employee_name (mã $employee_code) vào ngày $attendance_date không tồn tại.";
                    continue;
                }

                $formatted_check_in = null;
                $formatted_check_out = null;

                if (!empty($check_in)) {
                    try {
                        $time_obj = DateTime::createFromFormat('H:i', $check_in);
                        if ($time_obj) {
                            $formatted_check_in = $time_obj->format('H:i:s');
                        }
                    } catch (Exception $e) {
                        $errors[] = "Dòng $row_number: Thời gian check in không hợp lệ.";
                        continue;
                    }
                }

                if (!empty($check_out)) {
                    try {
                        $time_obj = DateTime::createFromFormat('H:i', $check_out);
                        if ($time_obj) {
                            $formatted_check_out = $time_obj->format('H:i:s');
                        }
                    } catch (Exception $e) {
                        $errors[] = "Dòng $row_number: Thời gian check out không hợp lệ.";
                        continue;
                    }
                }

                $schedule_to_update = null;
                $is_late = 0;
                
                $shift_schedules = array_filter($schedules, function($s) {
                    return !empty($s['shift_id']);
                });
                
                if (!empty($shift_schedules) && !empty($formatted_check_in) && !empty($formatted_check_out)) {
                    $schedule_to_update = findBestMatchingShift($formatted_check_in, $formatted_check_out, $shift_schedules);
                    
                    if ($schedule_to_update) {
                        $shift = getShiftDetails($schedule_to_update['shift_id']);
                        $check_in_time = strtotime($formatted_check_in);
                        $shift_start = strtotime($shift['start_time']);
                        
                        if ($check_in_time > $shift_start) {
                            $is_late = 1;
                        }
                    }
                } else {
                    $null_shift_schedules = array_filter($schedules, function($s) {
                        return empty($s['shift_id']);
                    });
                    
                    if (!empty($null_shift_schedules)) {
                        $schedule_to_update = $null_shift_schedules[0]; 
                        
                        if (!empty($formatted_check_in) && !empty($schedule_to_update['start_time'])) {
                            $check_in_time = strtotime($formatted_check_in);
                            $start_time = strtotime($schedule_to_update['start_time']);
                            
                            if ($check_in_time > $start_time) {
                                $is_late = 1;
                            }
                        }
                    } else {
                        $schedule_to_update = $schedules[0];
                    }
                }
                
                if (!empty($note) && (stripos($note, 'Muộn') !== false || stripos($note, 'muộn') !== false || stripos($note, 'muon') !== false || stripos($note, 'Muon') !== false)) {
                    $is_late = 1;
                }
                
                $is_allowance = 0;
                if ($formatted_check_in && $formatted_check_out && $schedule_to_update['is_authorized_absence'] == 0) {
                    if ($employee['department_id'] == 4) {
                        if ($schedule_to_update['shift_id'] == 1 || $schedule_to_update['shift_id'] == 2) {
                            $is_allowance = 1;
                        }
                    } else {
                        $is_allowance = 1;
                    }
                }

                try {
                    $stmt = $pdo->prepare("
                        UPDATE employee_work_schedules
                        SET check_in = ?, check_out = ?, is_worked = 1, is_allowance = ?, is_late = ?
                        WHERE employee_schedule_id = ?
                    ");
                    $stmt->execute([
                        $formatted_check_in,
                        $formatted_check_out,
                        $is_allowance,
                        $is_late,
                        $schedule_to_update['employee_schedule_id']
                    ]);
                    $success[] = "Dòng $row_number: Cập nhật thành công chấm công cho nhân viên $employee_name ngày $attendance_date.";
                    $successCount++;
                } catch (PDOException $e) {
                    $errors[] = "Dòng $row_number: Lỗi khi cập nhật dữ liệu: " . $e->getMessage();
                }
            }
            
            if ($successCount > 0) {
                $importSuccess = true;
            }
            
            // THIRD PASS: Update remaining records for those months
            if ($importSuccess) {
                foreach ($employee_months as $key => $data) {
                    $employee = getEmployeeDetails($data['employee_code']);
                    if (!$employee) {
                        continue;
                    }
                    
                    $employee_id = $employee['employee_id'];
                    $month = $data['month'];
                    $year = $data['year'];
                    
                    // Update remaining records for this employee in this month
                    $updated_count = updateRemainingSchedules($employee_id, $month, $year);
                    if ($updated_count > 0) {
                        $success[] = "Đã cập nhật $updated_count bản ghi còn lại cho nhân viên mã {$data['employee_code']} trong tháng $month/$year.";
                      
                    }
                }
            }
        } else {
            $errors[] = "Định dạng file không được hỗ trợ. Vui lòng tải lên file Excel (.xls, .xlsx) hoặc CSV.";
        }
    } else {
        $errors[] = "Vui lòng chọn file để tải lên.";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Dữ Liệu Chấm Công</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .error-message {
            color: red;
        }

        .success-message {
            color: green;
        }
    </style>
</head>

<body>
    <?php include dirname(__DIR__) . '/partials/header.php'; ?>
    <div class="container mt-5">
        <h2>Import Dữ Liệu Chấm Công</h2>

        <div class="card mb-4">
            <div class="card-header">
                <h5>Hướng dẫn</h5>
            </div>
            <div class="card-body">
                <p>1. Tải lên file Excel với định dạng sau:</p>
                <ul>
                    <li>Cột A: Ngày chấm công (định dạng: dd/mm/yyyy)</li>
                    <li>Cột B: Mã nhân viên</li>
                    <li>Cột C: Tên nhân viên</li>
                    <li>Cột D: Check in (định dạng: HH:mm)</li>
                    <li>Cột E: Check out (định dạng: HH:mm)</li>
                    <li>Cột F: Ghi chú</li>
                </ul>
                <p>2. Hệ thống sẽ kiểm tra và cập nhật dữ liệu chấm công vào hệ thống.</p>
            </div>
        </div>

        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="attendance_file">Chọn file Excel:</label>
                <input type="file" class="form-control-file" id="attendance_file" name="attendance_file" required>
            </div>
            <button type="submit" name="submit" class="btn btn-primary">Import</button>
        </form>

        <?php if (isset($errors) && !empty($errors)): ?>
            <div class="mt-4">
                <h4>Lỗi:</h4>
                <ul class="error-message">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (isset($success) && !empty($success)): ?>
            <div class="mt-4 success-message">
                <h4>Thành công</h4>
          
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>

</html>