<?php
require_once dirname(__DIR__) . '/config/db.php';

$user = checkPermission('hr_manager');
$departments = [];
try {
if ($user['role_name'] === 'Admin') {
    $stmt = $pdo->prepare("SELECT * FROM departments where is_active = 1 ORDER BY department_name");}else{
        $stmt = $pdo->prepare("SELECT * FROM departments where is_active = 1 and department_id !=3 ORDER BY department_name");
    }
    $stmt->execute();
    $departments = $stmt->fetchAll();
} catch (PDOException $e) {
    $errorMessage = "Lỗi lấy danh sách phòng ban: " . $e->getMessage();
}

$currentMonth = date('n');
$currentYear = date('Y');

$selectedMonth = isset($_GET['month']) ? $_GET['month'] : $currentMonth;
$selectedYear = isset($_GET['year']) ? $_GET['year'] : $currentYear;
$selectedDepartment = isset($_GET['department_id']) ? $_GET['department_id'] : 
                     (count($departments) > 0 ? $departments[0]['department_id'] : 0);

$departmentSettings = null;
if ($selectedDepartment) {
    try {
        $stmt = $pdo->prepare("
        SELECT mds.*, d.kpi_name, d.kpi_unit 
        FROM monthly_department_settings AS mds
        INNER JOIN departments AS d ON mds.department_id = d.department_id
        WHERE mds.department_id = ? AND mds.month = ? AND mds.year = ?
    ");
    $stmt->execute([$selectedDepartment, $selectedMonth, $selectedYear]);
    $departmentSettings = $stmt->fetch();
    

        if (!$departmentSettings) {
            $stmt = $pdo->prepare("SELECT * FROM departments WHERE department_id = ?");
            $stmt->execute([$selectedDepartment]);
            $departmentInfo = $stmt->fetch();
           
            
            if ($departmentInfo) {
                $departmentSettings = [
                    'department_id' => $departmentInfo['department_id'],
                    'month' => $selectedMonth,
                    'year' => $selectedYear,
                    'kpi_target' => $departmentInfo['default_kpi'],
                    'base_salary' => $departmentInfo['default_salary'],
                    'daily_meal_allowance' => 0,
                    'kpi_bonus_rate' => 0,
                    'kpi_penalty_rate' => 0,
                    'unauthorized_absence_penalty' => 0,
                    'is_locked' => false,
                    'kpi_name' => $departmentInfo['kpi_name'],
                    'kpi_unit' => $departmentInfo['kpi_unit']
                ];
            }
        }
    } catch (PDOException $e) {
        $errorMessage = "Lỗi lấy thông tin cài đặt phòng ban: " . $e->getMessage();
    }
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
if ($_POST['action'] == 'bulk_update_employees') {
    $departmentId = $_POST['department_id'];
    $month = $_POST['month'];
    $year = $_POST['year'];
    $kpiTarget = $_POST['kpi_target'];
    $baseSalary = $_POST['base_salary'];
    $dailyMealAllowance =  $departmentSettings['daily_meal_allowance'];

    $unauthorizedAbsencePenalty =  $departmentSettings['unauthorized_absence_penalty'];
    
    try {
        $stmt = $pdo->prepare("SELECT employee_id FROM employees WHERE department_id = ? and is_locked = 0");
        $stmt->execute([$departmentId]);
        $employees = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $pdo->beginTransaction();
        
        foreach ($employees as $employeeId) {
            $stmt = $pdo->prepare("SELECT performance_id FROM employee_monthly_performance 
                              WHERE employee_id = ? AND month = ? AND year = ?");
            $stmt->execute([$employeeId, $month, $year]);
            $existingPerformance = $stmt->fetch();
            
            if ($existingPerformance) {
                $stmt = $pdo->prepare("UPDATE employee_monthly_performance 
                                  SET individual_kpi_target = ?, individual_base_salary = ?
                               
                                  WHERE employee_id = ? AND month = ? AND year = ?");
                $stmt->execute([
                    $kpiTarget, $baseSalary,
                    $employeeId, $month, $year
                ]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO employee_monthly_performance 
                                  (employee_id, department_id, month, year, individual_kpi_target, 
                                   individual_base_salary, kpi_achieved, authorized_absences, 
                                   unauthorized_absences, final_salary, salary_calculated) 
                                  VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0, 0, false)");
                $stmt->execute([
                    $employeeId, $departmentId, $month, $year, $kpiTarget, $baseSalary
                ]);
            }
        }
        
        $pdo->commit();
        $successMessage = "Đã áp dụng cài đặt cho tất cả nhân viên thành công!";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $errorMessage = "Lỗi khi áp dụng cài đặt cho nhân viên: " . $e->getMessage();
    }
}
    if ($_POST['action'] == 'update_department_settings') {
        $departmentId = $_POST['department_id'];
        $month = $_POST['month'];
        $year = $_POST['year'];
        $kpiTarget = $_POST['kpi_target'];
        $baseSalary = $_POST['base_salary'];
        $dailyMealAllowance = $_POST['daily_meal_allowance'];
        $unauthorizedAbsencePenalty = $_POST['unauthorized_absence_penalty'];
        $late_arrival_penalty= $_POST['late_arrival_penalty'];
        
        try {
            $stmt = $pdo->prepare("SELECT setting_id FROM monthly_department_settings 
                              WHERE department_id = ? AND month = ? AND year = ?");
            $stmt->execute([$departmentId, $month, $year]);
            $existingSetting = $stmt->fetch();
            
            if ($existingSetting) {
                $stmt = $pdo->prepare("UPDATE monthly_department_settings 
                                  SET kpi_target = ?, base_salary = ?, daily_meal_allowance = ?, 
                                      unauthorized_absence_penalty = ? , late_arrival_penalty = ?
                                  WHERE department_id = ? AND month = ? AND year = ?");
                $stmt->execute([
                    $kpiTarget, $baseSalary, $dailyMealAllowance, 
                    $unauthorizedAbsencePenalty, $late_arrival_penalty,
                    $departmentId, $month, $year
                ]);
                $successMessage = "Cập nhật cài đặt thành công!";
                echo "<script>alert('$successMessage');window.location.href='kpi_settings.php?month=$month&year=$year&department_id=$departmentId';</script>";
                exit;
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO monthly_department_settings 
                                      (department_id, month, year, kpi_target, base_salary, daily_meal_allowance, 
                                        unauthorized_absence_penalty, late_arrival_penalty, is_locked) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, false)");
                    $stmt->execute([
                        $departmentId, $month, $year, $kpiTarget, $baseSalary, $dailyMealAllowance, 
                        $unauthorizedAbsencePenalty, $late_arrival_penalty
                    ]);
                    $successMessage = "Thêm mới cài đặt thành công!";
                    echo "<script>alert('$successMessage');window.location.href='kpi_settings.php?month=$month&year=$year&department_id=$departmentId';</script>";
                    exit;
                } catch (PDOException $e) {
                    $errorMessage = "Lỗi thêm mới cài đặt: " . $e->getMessage();
                }
            }
        } catch (PDOException $e) {
            $errorMessage = "Lỗi cập nhật cài đặt: " . $e->getMessage();
        }
    }
    
    if ($_POST['action'] == 'update_employee_performance') {
        $employeeId = $_POST['employee_id'];
        $departmentId = $_POST['department_id'];
        $month = $_POST['month'];
        $year = $_POST['year'];
        $kpiTarget = $_POST['kpi_target'];
        $baseSalary = $_POST['base_salary'];

      
    
        
        try {
            $stmt = $pdo->prepare("SELECT performance_id FROM employee_monthly_performance 
                              WHERE employee_id = ? AND month = ? AND year = ?");
            $stmt->execute([$employeeId, $month, $year]);
            $existingPerformance = $stmt->fetch();
            
            if ($existingPerformance) {
                $stmt = $pdo->prepare("UPDATE employee_monthly_performance 
                                  SET individual_kpi_target = ?, individual_base_salary = ?
                                  WHERE employee_id = ? AND month = ? AND year = ?");
                $stmt->execute([
                    $kpiTarget, $baseSalary,
                    $employeeId, $month, $year
                ]);
                $successMessage = "Cập nhật KPI & lương cho nhân viên thành công!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO employee_monthly_performance 
                                  (employee_id, department_id, month, year, individual_kpi_target, 
                                   individual_base_salary, kpi_achieved, authorized_absences, 
                                   unauthorized_absences, final_salary, salary_calculated) 
                                  VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0, 0, false)");
                $stmt->execute([
                    $employeeId, $departmentId, $month, $year, $kpiTarget, $baseSalary
                ]);
                $successMessage = "Thêm mới KPI & lương cho nhân viên thành công!";
            }
        } catch (PDOException $e) {
            $errorMessage = "Lỗi cập nhật KPI & lương cho nhân viên: " . $e->getMessage();
        }
    }
}


$employees = [];
if ($selectedDepartment) {
    try {
        $stmt = $pdo->prepare("SELECT e.*, 
                          emp.individual_kpi_target, emp.individual_base_salary,
                          emp.kpi_achieved, emp.authorized_absences, emp.unauthorized_absences
                          FROM employees e
                          LEFT JOIN employee_monthly_performance emp ON e.employee_id = emp.employee_id
                          AND emp.month = ? AND emp.year = ?
                          WHERE e.department_id = ? 
                          and e.is_locked = 0
                          ORDER BY e.last_name, e.first_name");
        $stmt->execute([$selectedMonth, $selectedYear, $selectedDepartment]);
        $employees = $stmt->fetchAll();
    } catch (PDOException $e) {
        $errorMessage = "Lỗi lấy danh sách nhân viên: " . $e->getMessage();
    }
}

$isLocked = false;
if ($departmentSettings && $departmentSettings['is_locked']) {
    $isLocked = true;
}

if ($selectedYear < $currentYear || ($selectedYear == $currentYear && $selectedMonth < $currentMonth)) {
    $isLocked = true;
}

if ($selectedYear > $currentYear || ($selectedYear == $currentYear && $selectedMonth > $currentMonth)) {
    $isLocked = true;
}

$months = [];
for ($i = 1; $i <= 12; $i++) {
    $monthName = date('F', mktime(0, 0, 0, $i, 1));
    $months[$i] = $monthName;
}

$years = [];
for ($i = $currentYear - 1; $i <= $currentYear + 1; $i++) {
    $years[$i] = $i;
}

function getWorkingDaysInMonth($month, $year) {
    $workingDays = 0;
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $timestamp = mktime(0, 0, 0, $month, $day, $year);
        $weekday = date('N', $timestamp);
        
        if ($weekday <= 6) {
            $workingDays++;
        }
    }
    
    return $workingDays;
}

$workingDays = getWorkingDaysInMonth($selectedMonth, $selectedYear);

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý KPI & Lương - Hệ thống Quản lý Nhân sự</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .form-label {
            font-weight: 500;
        }
        .card-header {
            background-color: #f8f9fa;
        }
        .table th {
            background-color: #f8f9fa;
        }
        .alert {
            margin-top: 20px;
        }
    </style>
</head>
<body>
<?php include dirname(__DIR__) . '/partials/header.php'; ?>

    
    <div class="container-fluid py-4">
        
        <div class="row mb-4">
            <div class="col">
                <h1 class="h3 mb-0 text-gray-800">Quản lý KPI & Lương</h1>
                <p class="mb-0">Cài đặt KPI và lương cho phòng ban và từng nhân viên</p>
            </div>
        </div>
        
        <?php if (isset($successMessage)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $successMessage; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <?php if (isset($errorMessage)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $errorMessage; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Chọn phòng ban và thời gian</h6>
            </div>
            
            <div class="card-body">
            <?php if ($selectedMonth == $currentMonth && $selectedYear == $currentYear): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle"></i> Bạn đang chỉnh sửa cài đặt cho tháng hiện tại.
</div>
<?php else: ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i> Bạn đang xem dữ liệu lịch sử. Không thể chỉnh sửa.
</div>
<?php endif; ?>
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label for="department_id" class="form-label">Phòng ban</label>
                        <select class="form-select" id="department_id" name="department_id" required>
                            <?php foreach ($departments as $department): ?>
                            <option value="<?php echo $department['department_id']; ?>" 
                                    <?php echo ($selectedDepartment == $department['department_id']) ? 'selected' : ''; ?>>
                                <?php echo $department['department_name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
    <label for="month" class="form-label">Tháng</label>
    <select class="form-select" id="month" name="month" required>
        <?php foreach ($months as $num => $name): ?>
        <option value="<?php echo $num; ?>" 
                <?php echo ($selectedMonth == $num) ? 'selected' : ''; ?>>
            <?php echo $name; ?>
            <?php if ($currentYear == $selectedYear && $num == $currentMonth): ?>
            (Tháng hiện tại)
            <?php elseif ($currentYear == $selectedYear && $num > $currentMonth): ?>
            (Chỉ xem)
            <?php elseif ($currentYear == $selectedYear && $num < $currentMonth): ?>
            (Lịch sử)
            <?php endif; ?>
        </option>
        <?php endforeach; ?>
    </select>
</div>

                    <div class="col-md-3">
                        <label for="year" class="form-label">Năm</label>
                        <select class="form-select" id="year" name="year" required>
                            <?php foreach ($years as $year): ?>
                            <option value="<?php echo $year; ?>" 
                                    <?php echo ($selectedYear == $year) ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Xem
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($departmentSettings): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Cài đặt KPI & Lương cho phòng ban</h6>
                <?php if ($isLocked): ?>
                <span class="badge bg-secondary">Đã khóa</span>
                <?php endif; ?>
            </div>
            <div class="card-body p-4">
    <form method="POST" action="" class="row g-4">
        <input type="hidden" name="action" value="update_department_settings">
        <input type="hidden" name="department_id" value="<?php echo $selectedDepartment; ?>">
        <input type="hidden" name="month" value="<?php echo $selectedMonth; ?>">
        <input type="hidden" name="year" value="<?php echo $selectedYear; ?>">
        
        <div class="col-md-6">
            <div class="card shadow-sm mb-3 border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Cài đặt KPI và lương cơ bản</h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-4">
                        <label for="kpi_target" class="form-label fw-bold">Chỉ tiêu <?php echo $departmentSettings['kpi_name']; ?></label>
                        <div class="input-group">
                            <input type="number" class="form-control form-control-lg" id="kpi_target" name="kpi_target" 
                                   value="<?php echo $departmentSettings['kpi_target']; ?>" step="0.01" required
                                   <?php echo $isLocked ? 'readonly' : ''; ?>>
                            <span class="input-group-text bg-light"><?php echo $departmentSettings['kpi_unit'] ?></span>
                        </div>
                     
                    </div>
                    
                    <div class="mb-4">
                        <label for="base_salary" class="form-label fw-bold">Lương cơ bản</label>
                        <div class="input-group">
                            <input type="number" class="form-control form-control-lg" id="base_salary" name="base_salary" 
                                   value="<?php echo $departmentSettings['base_salary']; ?>" step="1000" required
                                   <?php echo $isLocked ? 'readonly' : ''; ?>>
                            <span class="input-group-text bg-light">VNĐ</span>
                        </div>
                        <div class="form-text mt-2">
                            <i class="bi bi-currency-exchange"></i> Lương nhận được khi đạt đủ <?php echo $departmentSettings['kpi_target']; ?> <?php echo $departmentSettings['kpi_unit'] ?>
                        </div>
                        <div class="form-text">
                            <i class="bi bi-exclamation-circle"></i> Chưa bao gồm các khoản phụ cấp
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="daily_meal_allowance" class="form-label fw-bold">Phụ cấp ăn uống hàng ngày / theo ca</label>
                        <div class="input-group">
                            <input type="number" class="form-control form-control-lg" id="daily_meal_allowance" name="daily_meal_allowance" 
                                   value="<?php echo $departmentSettings['daily_meal_allowance']; ?>" step="1000"
                                   <?php echo $isLocked ? 'readonly' : ''; ?>>
                            <span class="input-group-text bg-light">VNĐ</span>
                        </div>
                     
                        <div class="form-text">
                            <i class="bi bi-asterisk"></i> Nhân viên sale: Trợ cấp ăn sẽ dược tính cho mỗi ca (sáng, tối) làm 
                            <br>
                            <i class="bi bi-asterisk"></i> Nhân viên còn lại: Sẽ tính trợ cấp theo ngày (1 trợ cấp / ngày)
                        </div>
                        <div class="form-text">
                            <i class="bi bi-plus-circle"></i> Được cộng thêm vào lương cơ bản
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card shadow-sm mb-3 border-0">
                <div class="card-header bg-success text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-award"></i> Chính sách thưởng phạt</h5>
                </div>
                <div class="card-body p-4">
               
                    
                    <div class="mb-3">
                        <label for="unauthorized_absence_penalty" class="form-label fw-bold">Mức phạt nghỉ không phép</label>
                        <div class="input-group">
                            <input type="number" class="form-control form-control-lg" id="unauthorized_absence_penalty" name="unauthorized_absence_penalty" 
                                   value="<?php echo $departmentSettings['unauthorized_absence_penalty']; ?>" step="1000"
                                   <?php echo $isLocked ? 'readonly' : ''; ?>>
                            <span class="input-group-text bg-light">VNĐ</span>
                        </div>
                        <div class="form-text mt-2 text-danger">
                            <i class="bi bi-dash-circle"></i> Số tiền trừ cho mỗi ngày vắng mặt không có phép
                        </div>
                        <div class="form-text">
                            <i class="bi bi-calculator"></i> Tổng phạt = Mức phạt × Số ngày nghỉ không phép
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="late_arrival_penalty" class="form-label fw-bold">Mức phạt đi muộn</label>
                        <div class="input-group">
                            <input type="number" class="form-control form-control-lg" id="late_arrival_penalty" name="late_arrival_penalty" 
                                   value="<?php echo isset($departmentSettings['late_arrival_penalty']) ? $departmentSettings['late_arrival_penalty'] : 0.00; ?>" step="1000"
                                   <?php echo $isLocked ? 'readonly' : ''; ?>>
                            <span class="input-group-text bg-light">VNĐ</span>
                        </div>
                        <div class="form-text mt-2 text-danger">
                            <i class="bi bi-dash-circle"></i> Số tiền trừ cho mỗi lần đi muộn
                        </div>
                        <div class="form-text">
                            <i class="bi bi-calculator"></i> Tổng phạt = Mức phạt × Số lần đi muộn
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (!$isLocked): ?>
        <div class="col-12 text-center mt-4">
            <button type="submit" class="btn btn-primary btn-lg px-5">
                <i class="bi bi-save"></i> Lưu thay đổi
            </button>
        </div>
        <?php endif; ?>
    </form>
</div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($employees)): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">KPI & Lương của nhân viên</h6>
                <?php if ($isLocked): ?>
                <span class="badge bg-secondary">Đã khóa</span>
                <?php else: ?>
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#bulkUpdateModal">
                    <i class="bi bi-lightning"></i> Áp dụng cài đặt cho tất cả nhân viên
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Nhân viên</th>
                                <th>Chỉ tiêu <?php echo $departmentSettings['kpi_name']; ?></th>
                                <th>Lương cơ bản</th>
                      
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?php echo $employee['last_name'] . ' ' . $employee['first_name']; ?></div>
                                </td>
                                <td>
                                    <?php if (isset($employee['individual_kpi_target'])): ?>
                                        <?php echo $employee['individual_kpi_target'] . ' ' . $departmentSettings['kpi_unit']; ?> 
                                    <?php else: ?>
                                        <span class="text-muted">Chưa thiết lập</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($employee['individual_base_salary'])): ?>
                                        <?php echo number_format($employee['individual_base_salary']); ?> VNĐ
                                    <?php else: ?>
                                        <span class="text-muted">Chưa thiết lập</span>
                                    <?php endif; ?>
                                </td>
                               
                                <td>
                                    <?php if (!$isLocked): ?>
                                    <button type="button" class="btn btn-sm btn-primary" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editEmployeeModal"
                                            data-employee-id="<?php echo $employee['employee_id']; ?>"
                                            data-employee-name="<?php echo $employee['last_name'] . ' ' . $employee['first_name']; ?>"
                                            data-kpi-target="<?php echo isset($employee['individual_kpi_target']) ? $employee['individual_kpi_target'] : $departmentSettings['kpi_target']; ?>"
                                            data-base-salary="<?php echo isset($employee['individual_base_salary']) ? $employee['individual_base_salary'] : $departmentSettings['base_salary']; ?>">
                                        <i class="bi bi-pencil-square"></i> Chỉnh sửa
                                    </button>
                                    <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-secondary" disabled>
                                        <i class="bi bi-lock"></i> Đã khóa
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="modal fade" id="editEmployeeModal" tabindex="-1" aria-labelledby="editEmployeeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_employee_performance">
                    <input type="hidden" name="employee_id" id="employee_id">
                    <input type="hidden" name="department_id" value="<?php echo $selectedDepartment; ?>">
                    <input type="hidden" name="month" value="<?php echo $selectedMonth; ?>">
                    <input type="hidden" name="year" value="<?php echo $selectedYear; ?>">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="editEmployeeModalLabel">Cập nhật KPI & Lương cho nhân viên</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nhân viên</label>
                            <input type="text" class="form-control" id="employee_name" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="kpi_target_employee" class="form-label">Chỉ tiêu KPI cá nhân</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="kpi_target_employee" name="kpi_target" step="0.01" required>
                                <span class="input-group-text">điểm</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="base_salary_employee" class="form-label">Lương cơ bản cá nhân</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="base_salary_employee" name="base_salary" step="1000" required>
                                <span class="input-group-text">VNĐ</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="bulkUpdateModal" tabindex="-1" aria-labelledby="bulkUpdateModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkUpdateModalLabel">Áp dụng cài đặt cho tất cả nhân viên</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Bạn có chắc chắn muốn áp dụng các cài đặt mặc định của phòng ban cho tất cả nhân viên không?</p>
                    <ul>
                        <li>Chỉ tiêu <?php echo $departmentSettings['kpi_name'] ?>: <strong><?php echo $departmentSettings['kpi_target']; ?> <?php echo $departmentSettings['kpi_unit'] ?></strong></li>
                        <li>Lương cơ bản: <strong><?php echo number_format($departmentSettings['base_salary']); ?> VNĐ</strong></li>
                        <li>Phụ cấp ăn uống hàng ngày: <strong><?php echo number_format($departmentSettings['daily_meal_allowance']); ?> VNĐ</strong></li>
                        <li>Mức phạt nghỉ không phép: <strong><?php echo number_format($departmentSettings['unauthorized_absence_penalty']); ?> VNĐ/ngày</strong></li>
                        <li>Mức phạt đi muộn: <strong><?php echo number_format($departmentSettings['late_arrival_penalty']); ?> VNĐ/ngày</strong></li>
     
                    </ul>
                    <p class="text-danger">Lưu ý: Thao tác này sẽ ghi đè lên các giá trị cá nhân của tất cả nhân viên!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="button" class="btn btn-primary" id="confirmBulkUpdate">Xác nhận áp dụng</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const editEmployeeModal = document.getElementById('editEmployeeModal');
            if (editEmployeeModal) {
                editEmployeeModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const employeeId = button.getAttribute('data-employee-id');
                    const employeeName = button.getAttribute('data-employee-name');
                    const kpiTarget = button.getAttribute('data-kpi-target');
                    const baseSalary = button.getAttribute('data-base-salary');
                    
                    const modal = this;
                    modal.querySelector('#employee_id').value = employeeId;
                    modal.querySelector('#employee_name').value = employeeName;
                    modal.querySelector('#kpi_target_employee').value = kpiTarget;
                    modal.querySelector('#base_salary_employee').value = baseSalary;
                });
            }
            
            const confirmBulkUpdateBtn = document.getElementById('confirmBulkUpdate');
            if (confirmBulkUpdateBtn) {
                confirmBulkUpdateBtn.addEventListener('click', function() {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '';
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'bulk_update_employees';
                    
                    const departmentInput = document.createElement('input');
                    departmentInput.type = 'hidden';
                    departmentInput.name = 'department_id';
                    departmentInput.value = '<?php echo $selectedDepartment; ?>';
                    
                    const monthInput = document.createElement('input');
                    monthInput.type = 'hidden';
                    monthInput.name = 'month';
                    monthInput.value = '<?php echo $selectedMonth; ?>';
                    
                    const yearInput = document.createElement('input');
                    yearInput.type = 'hidden';
                    yearInput.name = 'year';
                    yearInput.value = '<?php echo $selectedYear; ?>';
                    
                    const kpiInput = document.createElement('input');
                    kpiInput.type = 'hidden';
                    kpiInput.name = 'kpi_target';
                    kpiInput.value = '<?php echo $departmentSettings['kpi_target']; ?>';
                    
                    const salaryInput = document.createElement('input');
                    salaryInput.type = 'hidden';
                    salaryInput.name = 'base_salary';
                    salaryInput.value = '<?php echo $departmentSettings['base_salary']; ?>';
                    
                    form.appendChild(actionInput);
                    form.appendChild(departmentInput);
                    form.appendChild(monthInput);
                    form.appendChild(yearInput);
                    form.appendChild(kpiInput);
                    form.appendChild(salaryInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                });
            }
        });
    </script>
    
    <?php include dirname(__DIR__) . '/partials/footer.php'; ?>
</body>
</html>