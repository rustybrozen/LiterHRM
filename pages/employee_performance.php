<?php
require_once dirname(__DIR__) . '/config/db.php';

$currentUser = checkPermission('hr_manager');

$currentMonth = date('n');
$currentYear = date('Y');

$selectedMonth = isset($_GET['month']) ? (int) $_GET['month'] : $currentMonth;
$selectedYear = isset($_GET['year']) ? (int) $_GET['year'] : $currentYear;
$selectedDepartment = isset($_GET['department']) ? (int) $_GET['department'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_performance'])) {
        try {
            $pdo->beginTransaction();

            foreach ($_POST['employee'] as $employeeId => $data) {
                $checkStmt = $pdo->prepare("
                    SELECT performance_id FROM employee_monthly_performance 
                    WHERE employee_id = ? AND month = ? AND year = ?
                ");
                $checkStmt->execute([$employeeId, $selectedMonth, $selectedYear]);
                $performanceExists = $checkStmt->fetch();

                if ($performanceExists) {
                    $stmt = $pdo->prepare("
                        UPDATE employee_monthly_performance 
                        SET kpi_achieved = ?
     
                        WHERE employee_id = ? AND month = ? AND year = ?
                    ");
                    $stmt->execute([
                        $data['kpi_achieved'],
                        $employeeId,
                        $selectedMonth,
                        $selectedYear
                    ]);
                } else {
                    $empStmt = $pdo->prepare("SELECT department_id FROM employees WHERE employee_id = ?");
                    $empStmt->execute([$employeeId]);
                    $employee = $empStmt->fetch();

                    $settingsStmt = $pdo->prepare("
                        SELECT kpi_target, base_salary 
                        FROM monthly_department_settings
                        WHERE department_id = ? AND month = ? AND year = ?
                    ");
                    $settingsStmt->execute([$employee['department_id'], $selectedMonth, $selectedYear]);
                    $deptSettings = $settingsStmt->fetch();

                    if (!$deptSettings) {
                        $deptDefaultStmt = $pdo->prepare("
                            SELECT default_kpi, default_salary 
                            FROM departments
                            WHERE department_id = ?
                        ");
                        $deptDefaultStmt->execute([$employee['department_id']]);
                        $deptDefault = $deptDefaultStmt->fetch();

                        $kpiTarget = $deptDefault['default_kpi'];
                        $baseSalary = $deptDefault['default_salary'];

                        $createSettingsStmt = $pdo->prepare("
                            INSERT INTO monthly_department_settings
                            (department_id, month, year, kpi_target, base_salary, daily_meal_allowance, 
                              unauthorized_absence_penalty, is_locked)
                            VALUES (?, ?, ?, ?, ?, 0, 0, FALSE)
                        ");
                        $createSettingsStmt->execute([
                            $employee['department_id'],
                            $selectedMonth,
                            $selectedYear,
                            $kpiTarget,
                            $baseSalary
                        ]);
                    } else {
                        $kpiTarget = $deptSettings['kpi_target'];
                        $baseSalary = $deptSettings['base_salary'];
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO employee_monthly_performance
                        (employee_id, department_id, month, year, individual_kpi_target, 
                         individual_base_salary, kpi_achieved, authorized_absences, 
                         unauthorized_absences, final_salary, salary_calculated)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, FALSE)
                    ");
                    $stmt->execute([
                        $employeeId,
                        $employee['department_id'],
                        $selectedMonth,
                        $selectedYear,
                        $kpiTarget,
                        $baseSalary,
                        $data['kpi_achieved'],
                        isset($data['authorized_absences']) ? $data['authorized_absences'] : 0,
                        isset($data['unauthorized_absences']) ? $data['unauthorized_absences'] : 0
                    ]);
                }
            }

            $pdo->commit();
            $updateSuccess = true;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMessage = "Lỗi: " . $e->getMessage();
        }
    }


}

$isCurrentMonth = ($selectedMonth == $currentMonth && $selectedYear == $currentYear);

if ($currentUser['role_name'] === "Admin"){$departmentStmt = $pdo->query("SELECT department_id, department_name FROM departments where is_active = 1 ORDER BY department_name");
}else{
    $departmentStmt = $pdo->query("SELECT department_id, department_name FROM departments where is_active = 1 and department_id !=3 ORDER BY department_name");


}



$departments = $departmentStmt->fetchAll();

$employees = [];
if ($selectedDepartment) {
    $lockCheckStmt = $pdo->prepare("
        SELECT is_locked 
        FROM monthly_department_settings 
        WHERE department_id = ? AND month = ? AND year = ?
    ");
    $lockCheckStmt->execute([$selectedDepartment, $selectedMonth, $selectedYear]);
    $lockInfo = $lockCheckStmt->fetch();
    $isLocked = ($lockInfo && $lockInfo['is_locked']) || !$isCurrentMonth;

    $employeeStmt = $pdo->prepare("
        SELECT 
            e.employee_id,
            e.first_name,
            e.last_name,
            emp.individual_kpi_target,
            emp.individual_base_salary,
            emp.kpi_achieved,
            emp.authorized_absences,
            emp.unauthorized_absences,
            emp.final_salary,
            emp.salary_calculated
        FROM 
            employees e
        LEFT JOIN 
            employee_monthly_performance emp ON e.employee_id = emp.employee_id 
            AND emp.month = ? AND emp.year = ?
        WHERE 
            e.department_id = ?

        and
            e.is_locked = 0
        ORDER BY 
            e.last_name, e.first_name
    ");
    $employeeStmt->execute([$selectedMonth, $selectedYear, $selectedDepartment]);
    $employees = $employeeStmt->fetchAll();

    $deptSettingsStmt = $pdo->prepare("
       SELECT 
    mds.*, 
    d.kpi_name, 
    d.kpi_unit
FROM monthly_department_settings mds
JOIN departments d ON mds.department_id = d.department_id
WHERE mds.department_id = ? AND mds.month = ? AND mds.year = ?;

    ");
    $deptSettingsStmt->execute([$selectedDepartment, $selectedMonth, $selectedYear]);
    $departmentSettings = $deptSettingsStmt->fetch();

    if (!$departmentSettings && $isCurrentMonth) {
        $deptDefaultStmt = $pdo->prepare("
            SELECT default_kpi, default_salary 
            FROM departments
            WHERE department_id = ?
        ");
        $deptDefaultStmt->execute([$selectedDepartment]);
        $departmentDefaults = $deptDefaultStmt->fetch();
    }
}

$months = [];
for ($m = 1; $m <= 12; $m++) {
    $months[$m] = date('F', mktime(0, 0, 0, $m, 1));
}

$years = range(date('Y') - 2, date('Y') + 1);
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý hiệu suất nhân viên</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        .table-responsive {
            overflow-x: auto;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .alert {
            margin-top: 1rem;
        }

        .performance-table th {
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
        }

        .performance-card {
            margin-bottom: 2rem;
        }

        .department-info {
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .progress {
            height: 20px;
        }

        .progress-bar-success {
            background-color: #28a745;
        }

        .progress-bar-warning {
            background-color: #ffc107;
        }

        .progress-bar-danger {
            background-color: #dc3545;
        }

        .kpi-high {
            background-color: rgba(40, 167, 69, 0.1);
        }

        .kpi-medium {
            background-color: rgba(255, 193, 7, 0.1);
        }

        .kpi-low {
            background-color: rgba(220, 53, 69, 0.1);
        }

        .dashboard-summary {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .summary-card {
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
        }

        .summary-card:hover {
            transform: translateY(-5px);
        }

        .summary-card-green {
            background-color: rgba(40, 167, 69, 0.1);
            border-left: 4px solid #28a745;
        }

        .summary-card-yellow {
            background-color: rgba(255, 193, 7, 0.1);
            border-left: 4px solid #ffc107;
        }

        .summary-card-red {
            background-color: rgba(220, 53, 69, 0.1);
            border-left: 4px solid #dc3545;
        }

        .summary-card-blue {
            background-color: rgba(0, 123, 255, 0.1);
            border-left: 4px solid #007bff;
        }

        .summary-value {
            font-size: 24px;
            font-weight: bold;
        }

        .absence-badge {
            font-size: 14px;
            padding: 5px 10px;
            border-radius: 15px;
        }
    </style>
</head>

<body>
<?php include dirname(__DIR__) . '/partials/header.php'; ?>


    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-chart-line me-2"></i>Quản lý hiệu suất nhân viên</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($updateSuccess) && $updateSuccess): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle me-2"></i>Cập nhật dữ liệu hiệu suất thành công!
                            </div>
                        <?php endif; ?>



                        <?php if (isset($errorMessage)): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $errorMessage; ?>
                            </div>
                        <?php endif; ?>

                        <form method="GET" action="" class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label for="department" class="form-label">Phòng ban</label>
                                <select name="department" id="department" class="form-select" required>
                                    <option value="">-- Chọn phòng ban --</option>
                                    <?php foreach ($departments as $department): ?>
                                        <option value="<?php echo $department['department_id']; ?>" <?php echo ($selectedDepartment == $department['department_id']) ? 'selected' : ''; ?>>
                                            <?php echo $department['department_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="month" class="form-label">Tháng</label>
                                <select name="month" id="month" class="form-select" required>
                                    <?php foreach ($months as $num => $name): ?>
                                        <option value="<?php echo $num; ?>" <?php echo ($selectedMonth == $num) ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="year" class="form-label">Năm</label>
                                <select name="year" id="year" class="form-select" required>
                                    <?php foreach ($years as $year): ?>
                                        <option value="<?php echo $year; ?>" <?php echo ($selectedYear == $year) ? 'selected' : ''; ?>>
                                            <?php echo $year; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Xem
                                </button>
                            </div>
                        </form>

                        <?php if ($selectedDepartment && !empty($employees)): ?>
                            <?php if (isset($isLocked) && $isLocked): ?>
                                <div class="alert alert-warning mb-4" role="alert">
                                    <i class="fas fa-lock me-2"></i>
                                    <strong>Lưu ý:</strong> Dữ liệu của tháng này đã bị khóa hoặc là dữ liệu lịch sử. Bạn chỉ có
                                    thể xem nhưng không thể chỉnh sửa.
                                </div>
                            <?php endif; ?>

                            <?php if (isset($departmentSettings) && is_array($departmentSettings)): ?>
    <div class="department-info">
        <h5><i class="fas fa-building me-2"></i>Thông tin phòng ban - Tháng
            <?php echo $selectedMonth; ?>/<?php echo $selectedYear; ?>
        </h5>
        <div class="row">
            <div class="col-md-3">
                <p><strong><?php echo $departmentSettings['kpi_name']; ?> mặc định:</strong> 
                    <?php echo isset($departmentSettings['kpi_target']) ? $departmentSettings['kpi_target'] : '0'; ?>
                </p>
            </div>
            <div class="col-md-3">
                <p><strong>Lương cơ bản:</strong>
                    <?php echo isset($departmentSettings['base_salary']) ? 
                        number_format($departmentSettings['base_salary']) : '0'; ?> VNĐ</p>
            </div>
            <div class="col-md-3">
                <p><strong>Trợ cấp ăn uống:</strong>
                    <?php echo isset($departmentSettings['daily_meal_allowance']) ? 
                        number_format($departmentSettings['daily_meal_allowance']) : '0'; ?>
                    VNĐ/ngày</p>
            </div>
            <div class="col-md-3">
                <p><strong>Phạt nghỉ không phép:</strong>
                    <?php echo isset($departmentSettings['unauthorized_absence_penalty']) ? 
                        number_format($departmentSettings['unauthorized_absence_penalty']) : '0'; ?>
                    VNĐ/ngày</p>
            </div>
        </div>
    </div>
<?php elseif (isset($departmentDefaults) && is_array($departmentDefaults) && $isCurrentMonth): ?>
    <div class="department-info1">
        <h5><i class="fas fa-building me-2"></i>Thông tin phòng ban (Mặc định) - Tháng
            <?php echo $selectedMonth; ?>/<?php echo $selectedYear; ?>
        </h5>
        <div class="row">
            <div class="col-md-6">
                <p><strong>KPI mặc định:</strong> 
                    <?php echo isset($departmentDefaults['default_kpi']) ? 
                        $departmentDefaults['default_kpi'] : '0'; ?>
                </p>
            </div>
            <div class="col-md-6">
                <p><strong>Lương cơ bản:</strong>
                    <?php echo isset($departmentDefaults['default_salary']) ? 
                        number_format($departmentDefaults['default_salary']) : '0'; ?> VNĐ</p>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>
        Không có thông tin cài đặt cho phòng ban này trong tháng đã chọn.
    </div>
<?php endif; ?>
                            <div class="dashboard-summary mb-4">
                                <h5 class="mb-4"><i class="fas fa-tachometer-alt me-2"></i>Tổng quan hiệu suất</h5>
                                <div class="row">
                                    <?php
                                    $totalEmployees = count($employees);
                                    $highPerformers = 0;
                                    $mediumPerformers = 0;
                                    $lowPerformers = 0;
                                    $totalKpi = 0;

                                    foreach ($employees as $emp) {
                                        $kpiTarget = (isset($employee['individual_kpi_target']) && $employee['individual_kpi_target']) ? 
                                        $employee['individual_kpi_target'] : 
                                        (isset($departmentSettings) && is_array($departmentSettings) && isset($departmentSettings['kpi_target']) ? 
                                            $departmentSettings['kpi_target'] : 
                                            (isset($departmentDefaults) && is_array($departmentDefaults) && isset($departmentDefaults['default_kpi']) ? 
                                                $departmentDefaults['default_kpi'] : 0));

                                        $kpiAchieved = $emp['kpi_achieved'] ?? 0;
                                        $kpiPercentage = $kpiTarget > 0 ? ($kpiAchieved / $kpiTarget) * 100 : 0;

                                        $totalKpi += $kpiPercentage;

                                        if ($kpiPercentage >= 90) {
                                            $highPerformers++;
                                        } elseif ($kpiPercentage >= 70) {
                                            $mediumPerformers++;
                                        } else {
                                            $lowPerformers++;
                                        }
                                    }

                                    $avgKpi = $totalEmployees > 0 ? $totalKpi / $totalEmployees : 0;
                                    ?>

                                    <div class="col-md-3">
                                        <div class="summary-card summary-card-green">
                                            <h6><i class="fas fa-trophy me-2"></i>Hiệu suất cao (>=90%)</h6>
                                            <div class="summary-value"><?php echo $highPerformers; ?></div>
                                            <div class="text-muted">
                                                <?php echo $totalEmployees > 0 ? round(($highPerformers / $totalEmployees) * 100) : 0; ?>%
                                                nhân viên
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="summary-card summary-card-yellow">
                                            <h6><i class="fas fa-chart-line me-2"></i>Hiệu suất trung bình</h6>
                                            <div class="summary-value"><?php echo $mediumPerformers; ?></div>
                                            <div class="text-muted">
                                                <?php echo $totalEmployees > 0 ? round(($mediumPerformers / $totalEmployees) * 100) : 0; ?>%
                                                nhân viên
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="summary-card summary-card-red">
                                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Hiệu suất thấp (<70%)</h6>
                                                    <div class="summary-value"><?php echo $lowPerformers; ?></div>
                                                    <div class="text-muted">
                                                        <?php echo $totalEmployees > 0 ? round(($lowPerformers / $totalEmployees) * 100) : 0; ?>%
                                                        nhân viên
                                                    </div>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="summary-card summary-card-blue">
                                            <h6><i class="fas fa-percentage me-2"></i>
                                                <?php echo isset($departmentSettings['kpi_name']) ? $departmentSettings['kpi_name'] . ' trung bình' : '(Cần cài đặt lại thông số phòng ban tháng này)'; ?>
                                            </h6>
                                            <div class="summary-value"><?php echo round($avgKpi, 1); ?>%</div>
                                            <div class="text-muted">
                                                <?php if ($avgKpi >= 90): ?>
                                                    <span class="text-success">Xuất sắc</span>
                                                <?php elseif ($avgKpi >= 70): ?>
                                                    <span class="text-warning">Đạt yêu cầu</span>
                                                <?php else: ?>
                                                    <span class="text-danger">Cần cải thiện</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h5 class="card-title mb-0"><i class="fas fa-chart-bar me-2"></i>Biểu đồ <?php echo isset($departmentSettings['kpi_name']) ? $departmentSettings['kpi_name'] : '(Cần cài đặt lại thông số phòng ban tháng này)'; ?></h5>
                                </div>
                                <div class="card-body">
                                    <div style="height: 300px;">
                                        <canvas id="performanceChart"></canvas>
                                    </div>
                                </div>
                            </div>
                            <form method="POST" action="" id="performanceForm">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover performance-table">
                                        <thead class="table-light">
                                            <tr>
                                                <th>STT</th>
                                                <th>Họ tên</th>
                                                <th>Mục tiêu <?php echo isset($departmentSettings['kpi_name']) ? $departmentSettings['kpi_name'] : '(Cần cài đặt lại thông số phòng ban tháng này)'; ?> </th>
                                                <th><?php echo isset($departmentSettings['kpi_name']) ? $departmentSettings['kpi_name'] : '(Cần cài đặt lại thông số phòng ban tháng này)'; ?> đạt được</th>
                                                <th>Số ngày nghỉ có phép</th>
                                                <th>Số ngày nghỉ không phép</th>
                                                <th>Lương cơ bản</th>
                                                <th>Tổng lương</th>

                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $counter = 1; 
                                            
                                            if (!isset($departmentSettings)) {
                                                $departmentSettings = [];
                                            }
                                            if (!isset($departmentDefaults)) {
                                                $departmentDefaults = [];
                                            }
                                            
                                            
                                            ?>
                                            
                                            <?php foreach ($employees as $employee): ?>
    <?php
    $departmentSettingsValid = is_array($departmentSettings) || (is_object($departmentSettings) && !empty($departmentSettings));
    $departmentDefaultsValid = is_array($departmentDefaults) || (is_object($departmentDefaults) && !empty($departmentDefaults));
    
    $kpiTarget = isset($employee['individual_kpi_target']) ? $employee['individual_kpi_target'] : 
        ($departmentSettingsValid && isset($departmentSettings['kpi_target']) ? $departmentSettings['kpi_target'] : 
            ($departmentDefaultsValid && isset($departmentDefaults['default_kpi']) ? $departmentDefaults['default_kpi'] : 0));

    $kpiAchieved = isset($employee['kpi_achieved']) ? $employee['kpi_achieved'] : 0;
    $kpiPercentage = $kpiTarget > 0 ? ($kpiAchieved / $kpiTarget) * 100 : 0;

    $rowClass = '';
    if ($kpiPercentage >= 90) {
        $rowClass = 'kpi-high';
    } elseif ($kpiPercentage >= 70) {
        $rowClass = 'kpi-medium';
    } else {
        $rowClass = 'kpi-low';
    }
    ?>
    <tr class="<?php echo $rowClass; ?>"></tr>
    <tr>
        <td><?php echo $counter++; ?></td>
        <td><?php echo $employee['last_name'] . ' ' . $employee['first_name']; ?></td>
        <td>
            <?php echo isset($employee['individual_kpi_target']) ? $employee['individual_kpi_target'] : 
                ($departmentSettingsValid && isset($departmentSettings['kpi_target']) ? $departmentSettings['kpi_target'] : 
                    ($departmentDefaultsValid && isset($departmentDefaults['default_kpi']) ? $departmentDefaults['default_kpi'] : '0')); ?>
        </td>
        <td>
            <?php
            if ($kpiPercentage >= 90) {
                $progressClass = "progress-bar-success";
                $textClass = "text-success";
            } elseif ($kpiPercentage >= 70) {
                $progressClass = "progress-bar-warning";
                $textClass = "text-warning";
            } else {
                $progressClass = "progress-bar-danger";
                $textClass = "text-danger";
            }
            ?>

            <?php if (!$isLocked): ?>
                <input type="number" class="form-control mb-2"
                    name="employee[<?php echo $employee['employee_id']; ?>][kpi_achieved]"
                    value="<?php echo isset($employee['kpi_achieved']) ? $employee['kpi_achieved'] : 0; ?>" min="0"
                    step="0.01" required>
            <?php else: ?>
                <div class="fw-bold <?php echo $textClass; ?>">
                    <?php echo $kpiAchieved; ?> / <?php echo $kpiTarget; ?>
                </div>
            <?php endif; ?>

            <div class="progress mt-1">
                <div class="progress-bar <?php echo $progressClass; ?>"
                    role="progressbar"
                    style="width: <?php echo min($kpiPercentage, 100); ?>%"
                    aria-valuenow="<?php echo $kpiPercentage; ?>" aria-valuemin="0"
                    aria-valuemax="100">
                    <?php echo round($kpiPercentage); ?>%
                </div>
            </div>
        </td>
        <td>
            <?php
            $authorizedAbsences = isset($employee['authorized_absences']) ? $employee['authorized_absences'] : 0;
            if ($authorizedAbsences > 0) {
                echo '<span class="badge bg-info absence-badge">' . $authorizedAbsences . ' ngày</span>';
            } else {
                echo '<span class="text-muted">0</span>';
            }
            ?>
        </td>

        <td>
            <?php
            $unauthorizedAbsences = isset($employee['unauthorized_absences']) ? $employee['unauthorized_absences'] : 0;
            if ($unauthorizedAbsences > 0) {
                echo '<span class="badge bg-danger absence-badge">' . $unauthorizedAbsences . ' ngày</span>';
            } else {
                echo '<span class="text-success">0</span>';
            }
            ?>
        </td>
        <td>
            <?php 
            $baseSalary = isset($employee['individual_base_salary']) ? $employee['individual_base_salary'] : 
                ($departmentSettingsValid && isset($departmentSettings['base_salary']) ? $departmentSettings['base_salary'] : 
                    ($departmentDefaultsValid && isset($departmentDefaults['default_salary']) ? $departmentDefaults['default_salary'] : 0));
            echo number_format($baseSalary); ?> VNĐ
        </td>
        <td>
            <?php echo (isset($employee['final_salary']) && isset($employee['salary_calculated']) && $employee['final_salary'] && $employee['salary_calculated']) 
                ? number_format($employee['final_salary']) . ' VNĐ' 
                : 'Chưa tính'; ?>
        </td>
    </tr>
<?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php if (!$isLocked): ?>
                                    <div class="mt-3">
                                        <button type="submit" name="update_performance" class="btn btn-success">
                                            <i class="fas fa-save me-2"></i>Lưu thay đổi
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </form>

                        <?php elseif ($selectedDepartment && empty($employees)): ?>
                            <div class="alert alert-info" role="alert">
                                <i class="fas fa-info-circle me-2"></i>Không có nhân viên nào trong phòng ban này.
                            </div>
                        <?php elseif (!$selectedDepartment): ?>
                            <div class="alert alert-info" role="alert">
                                <i class="fas fa-info-circle me-2"></i>Vui lòng chọn phòng ban để xem danh sách nhân viên.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="absenceModal" tabindex="-1" aria-labelledby="absenceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="absenceModalLabel">Thêm ngày nghỉ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="absence_employee_id" id="absence_employee_id">

                        <div class="form-group mb-3">
                            <label>Nhân viên:</label>
                            <div class="fw-bold" id="absence_employee_name"></div>
                        </div>

                        <div class="form-group mb-3">
                            <label for="absence_date">Ngày nghỉ:</label>
                            <input type="date" class="form-control" id="absence_date" name="absence_date" required>
                        </div>

                        <div class="form-group mb-3">
                            <label for="absence_type">Loại nghỉ phép:</label>
                            <select class="form-select" id="absence_type" name="absence_type" required>
                                <option value="authorized">Nghỉ có phép</option>
                                <option value="unauthorized">Nghỉ không phép</option>
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label for="absence_reason">Lý do:</label>
                            <textarea class="form-control" id="absence_reason" name="absence_reason" rows="3"
                                required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                        <button type="submit" name="add_absence" class="btn btn-primary">Lưu</button>
                    </div>
                </form>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var absenceModal = document.getElementById('absenceModal');
                if (absenceModal) {
                    absenceModal.addEventListener('show.bs.modal', function (event) {
                        var button = event.relatedTarget;
                        var employeeId = button.getAttribute('data-employee-id');
                        var employeeName = button.getAttribute('data-employee-name');

                        document.getElementById('absence_employee_id').value = employeeId;
                        document.getElementById('absence_employee_name').textContent = employeeName;

                        var today = new Date();
                        var dd = String(today.getDate()).padStart(2, '0');
                        var mm = String(today.getMonth() + 1).padStart(2, '0');
                        var yyyy = today.getFullYear();
                        today = yyyy + '-' + mm + '-' + dd;
                        document.getElementById('absence_date').value = today;
                    });
                }
            });
        </script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var chartElement = document.getElementById('performanceChart');
                if (chartElement) {
                    var ctx = chartElement.getContext('2d');

                    var employees = <?php echo json_encode($employees ?? []); ?>;
                    var departmentSettings = <?php echo json_encode($departmentSettings ?? null); ?>;
                    var departmentDefaults = <?php echo json_encode($departmentDefaults ?? null); ?>;

                    var labels = [];
                    var kpiTargetData = [];
                    var kpiAchievedData = [];

                    employees.forEach(function (employee) {
                        var fullName = employee.last_name + ' ' + employee.first_name;
                        labels.push(fullName);

                        var kpiTarget = employee.individual_kpi_target ||
                            (departmentSettings ? departmentSettings.kpi_target :
                                (departmentDefaults ? departmentDefaults.default_kpi : 0));

                        kpiTargetData.push(kpiTarget);
                        kpiAchievedData.push(employee.kpi_achieved || 0);
                    });

                    var myChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [
                                {
                                    label: 'Mục tiêu <?php echo $departmentSettings['kpi_name']; ?>',
                                    data: kpiTargetData,
                                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                                    borderColor: 'rgba(54, 162, 235, 1)',
                                    borderWidth: 1
                                },
                                {
                                    label: '<?php echo $departmentSettings['kpi_name']; ?> Đạt được',
                                    data: kpiAchievedData,
                                    backgroundColor: 'rgba(255, 99, 132, 0.5)',
                                    borderColor: 'rgba(255, 99, 132, 1)',
                                    borderWidth: 1
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }
            });
        </script>

        <?php include 'footer.php'; ?>
</body>

</html>