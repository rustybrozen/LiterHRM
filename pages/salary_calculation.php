<?php
require_once dirname(__DIR__) . '/config/db.php';

$user = checkPermission('hr_manager');

$currentMonth = date('n');
$currentYear = date('Y');


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['temp_calculate_salary'])) {
        $monthYear = explode('-', $_POST['month_year']);
        $month = $monthYear[0];
        $year = $monthYear[1];
        $department_id = $_POST['department_id'];


        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                    SELECT emp.employee_id, emp.first_name, emp.last_name, 
                           emp_perf.individual_kpi_target, emp_perf.individual_base_salary,
                           emp_perf.kpi_achieved, emp_perf.authorized_absences, 
                           emp_perf.unauthorized_absences,
                           emp.department_id, emp_perf.allowance_shift_count
                    FROM employees emp
                    JOIN employee_monthly_performance emp_perf ON emp.employee_id = emp_perf.employee_id
                    WHERE emp.department_id = ? AND emp_perf.month = ? 
                    and emp.is_locked = 0
                    AND emp_perf.year = ? AND emp_perf.salary_calculated = FALSE
                ");
            $stmt->execute([$department_id, $month, $year]);
            $employees = $stmt->fetchAll();

            $stmt = $pdo->prepare("
                    SELECT * FROM monthly_department_settings
                    WHERE department_id = ? AND month = ? AND year = ?
                ");
            $stmt->execute([$department_id, $month, $year]);
            $settings = $stmt->fetch();

            if (!$settings) {
                throw new Exception("Không tìm thấy cài đặt cho phòng ban trong tháng đã chọn.");
            }
            function getActualWorkedDaysForEmployee($employeeId, $month, $year, $pdo)
            {
                $sql = "SELECT COUNT(*) as worked_days 
                            FROM employee_work_schedules 
                            WHERE employee_id = ? 
                            AND MONTH(effective_date) = ? 
                            AND YEAR(effective_date) = ?
                            AND is_worked = 1";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$employeeId, $month, $year]);

                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result) {
                    return $result['worked_days'];
                }

                return 0;
            }



            function getLateDay($employeeId, $month, $year, $pdo)
            {
                $sql = "SELECT COUNT(*) as late_days 
                            FROM employee_work_schedules 
                            WHERE employee_id = ? 
                            AND MONTH(effective_date) = ? 
                            AND YEAR(effective_date) = ?
                            AND is_worked = 1
                            and is_late = 1";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$employeeId, $month, $year]);

                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result) {
                    return $result['late_days'];
                }

                return 0;
            }


            function getShiftDayNightCount($employeeId, $month, $year, $pdo)
            {
                $sql = "SELECT COUNT(*) as ShiftDayNightCount 
FROM employee_work_schedules ews
JOIN employees e ON ews.employee_id = e.employee_id
WHERE ews.employee_id = ? 
AND MONTH(ews.effective_date) = ? 
AND YEAR(ews.effective_date) = ? 
AND ews.is_worked = 1 
AND e.department_id = 4
AND (ews.shift_id = 1 OR ews.shift_id = 2)";


                $stmt = $pdo->prepare($sql);
                $stmt->execute([$employeeId, $month, $year]);

                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result) {
                    return $result['ShiftDayNightCount'];
                }

                return 0;
            }
            foreach ($employees as $employee) {

                $employeeId = $employee['employee_id'];
                $defaultWorkingDays = getActualWorkedDaysForEmployee($employeeId, $month, $year, $pdo);
                
                $lateDays = getLateDay($employeeId, $month, $year, $pdo);
                $shift_count_allowance = getShiftDayNightCount($employeeId, $month, $year, $pdo);
                $workingDays = $defaultWorkingDays - $employee['authorized_absences'] - $employee['unauthorized_absences'];

                $mealAllowance = 0;
                if ($employee['department_id'] == 4) {
                    $mealAllowance = $shift_count_allowance * $settings['daily_meal_allowance'];

                } else {
                    $mealAllowance = $workingDays * $settings['daily_meal_allowance'];
                }
                $unauthorizedPenalty = $employee['unauthorized_absences'] * $settings['unauthorized_absence_penalty'];
                $latePenalty = $lateDays * $settings['late_arrival_penalty'];
                $kpiDifference = $employee['kpi_achieved'] - $employee['individual_kpi_target'];
                $finalSalary = 0;
                $finalPenalty = $unauthorizedPenalty + $latePenalty;
                $actualKpi = $employee['kpi_achieved'] / $employee['individual_kpi_target'];
                $tempFinalSalary = $employee['individual_base_salary'] * $actualKpi;

                $finalSalary += $tempFinalSalary + $mealAllowance - $finalPenalty;
                if ($finalSalary < 0) {
                    $finalSalary = 0;
                }
                if ($employee['department_id'] == 4) {
                    $updateStmt = $pdo->prepare("
            UPDATE employee_monthly_performance
            SET final_salary = ?, 
            working_day = ?,
            late_day=?,
            allowance_shift_count=?,
            total_allowance=?
            WHERE employee_id = ? AND month = ? AND year = ?
        ");
                    $updateStmt->execute([
                        $finalSalary,
                        $workingDays,
                        $lateDays,
                        $shift_count_allowance,
                        $mealAllowance,
                        $employee['employee_id'],
                        $month,
                        $year
                    ]);
                } else {
                    $updateStmt = $pdo->prepare("
            UPDATE employee_monthly_performance
            SET final_salary = ?, 
            working_day = ?,
            late_day=?,
            total_allowance=?
            WHERE employee_id = ? AND month = ? AND year = ?
        ");
                    $updateStmt->execute([
                        $finalSalary,
                        $workingDays,
                        $lateDays,
                        $mealAllowance,
                        $employee['employee_id'],
                        $month,
                        $year
                    ]);
                }

            }
            $pdo->commit();
            $successMessage = "Đã tính lương thành công cho " . count($employees) . " nhân viên của phòng ban.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMessage = "Lỗi khi tính lương: " . $e->getMessage();
        }

    }
    if (isset($_POST['calculate_salary'])) {
        $monthYear = explode('-', $_POST['month_year']);
        $month = $monthYear[0];
        $year = $monthYear[1];
        $department_id = $_POST['department_id'];

        if ($month == $currentMonth && $year == $currentYear) {
            $errorMessage = "Không thể tính lương cho tháng hiện tại. Vui lòng đợi đến tháng sau.";
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    SELECT emp.employee_id, emp.first_name, emp.last_name, 
                           emp_perf.individual_kpi_target, emp_perf.individual_base_salary,
                           emp_perf.kpi_achieved, emp_perf.authorized_absences, 
                           emp_perf.unauthorized_absences,
                           emp.department_id, emp_perf.allowance_shift_count
                    FROM employees emp
                    JOIN employee_monthly_performance emp_perf ON emp.employee_id = emp_perf.employee_id
                    WHERE emp.department_id = ? AND emp_perf.month = ? 
                    and emp.is_locked = 0
                    AND emp_perf.year = ? AND emp_perf.salary_calculated = FALSE
                ");
                $stmt->execute([$department_id, $month, $year]);
                $employees = $stmt->fetchAll();

                $stmt = $pdo->prepare("
                    SELECT * FROM monthly_department_settings
                    WHERE department_id = ? AND month = ? AND year = ?
                ");
                $stmt->execute([$department_id, $month, $year]);
                $settings = $stmt->fetch();

                if (!$settings) {
                    throw new Exception("Không tìm thấy cài đặt cho phòng ban trong tháng đã chọn.");
                }
                function getActualWorkedDaysForEmployee($employeeId, $month, $year, $pdo)
                {
                    $sql = "SELECT COUNT(*) as worked_days 
                            FROM employee_work_schedules 
                            WHERE employee_id = ? 
                            AND MONTH(effective_date) = ? 
                            AND YEAR(effective_date) = ?
                            AND is_worked = 1";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$employeeId, $month, $year]);

                    $result = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($result) {
                        return $result['worked_days'];
                    }

                    return 0;
                }



                function getLateDay($employeeId, $month, $year, $pdo)
                {
                    $sql = "SELECT COUNT(*) as late_days 
                            FROM employee_work_schedules 
                            WHERE employee_id = ? 
                            AND MONTH(effective_date) = ? 
                            AND YEAR(effective_date) = ?
                            AND is_worked = 1
                            and is_late = 1";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$employeeId, $month, $year]);

                    $result = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($result) {
                        return $result['late_days'];
                    }

                    return 0;
                }


                function getShiftDayNightCount($employeeId, $month, $year, $pdo)
                {
                    $sql = "SELECT COUNT(*) as ShiftDayNightCount 
FROM employee_work_schedules ews
JOIN employees e ON ews.employee_id = e.employee_id
WHERE ews.employee_id = ? 
AND MONTH(ews.effective_date) = ? 
AND YEAR(ews.effective_date) = ? 
AND ews.is_worked = 1 
AND e.department_id = 4
AND (ews.shift_id = 1 OR ews.shift_id = 2)";


                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$employeeId, $month, $year]);

                    $result = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($result) {
                        return $result['ShiftDayNightCount'];
                    }

                    return 0;
                }
                foreach ($employees as $employee) {

                    $employeeId = $employee['employee_id'];
                    $defaultWorkingDays = getActualWorkedDaysForEmployee($employeeId, $month, $year, $pdo);
                    $lateDays = getLateDay($employeeId, $month, $year, $pdo);
                    $shift_count_allowance = getShiftDayNightCount($employeeId, $month, $year, $pdo);
                    $workingDays = $defaultWorkingDays - $employee['authorized_absences'] - $employee['unauthorized_absences'];

                    $mealAllowance = 0;
                    if ($employee['department_id'] == 4) {
                        $mealAllowance = $shift_count_allowance * $settings['daily_meal_allowance'];

                    } else {
                        $mealAllowance = $workingDays * $settings['daily_meal_allowance'];
                    }
                    $unauthorizedPenalty = $employee['unauthorized_absences'] * $settings['unauthorized_absence_penalty'];
                    $latePenalty = $lateDays * $settings['late_arrival_penalty'];
                    $kpiDifference = $employee['kpi_achieved'] - $employee['individual_kpi_target'];
                    $finalSalary = 0;
                    $finalPenalty = $unauthorizedPenalty + $latePenalty;
                    $actualKpi = $employee['kpi_achieved'] / $employee['individual_kpi_target'];
                    $tempFinalSalary = $employee['individual_base_salary'] * $actualKpi;

                    $finalSalary += $tempFinalSalary + $mealAllowance - $finalPenalty;
                    if ($finalSalary < 0) {
                        $finalSalary = 0;
                    }
                    if ($employee['department_id'] == 4) {
                        $updateStmt = $pdo->prepare("
            UPDATE employee_monthly_performance
            SET final_salary = ?, 
            working_day = ?,
            late_day=?,
            allowance_shift_count=?,
            total_allowance=?,
            salary_calculated = TRUE
            WHERE employee_id = ? AND month = ? AND year = ?
        ");
                        $updateStmt->execute([
                            $finalSalary,
                            $workingDays,
                            $lateDays,
                            $shift_count_allowance,
                            $mealAllowance,
                            $employee['employee_id'],
                            $month,
                            $year
                        ]);
                    } else {
                        $updateStmt = $pdo->prepare("
            UPDATE employee_monthly_performance
            SET final_salary = ?, 
            working_day = ?,
            late_day=?,
            total_allowance=?,
            salary_calculated = TRUE
            WHERE employee_id = ? AND month = ? AND year = ?
        ");
                        $updateStmt->execute([
                            $finalSalary,
                            $workingDays,
                            $lateDays,
                            $mealAllowance,
                            $employee['employee_id'],
                            $month,
                            $year
                        ]);
                    }

                }
                $pdo->commit();
                $successMessage = "Đã tính lương thành công cho " . count($employees) . " nhân viên của phòng ban.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $errorMessage = "Lỗi khi tính lương: " . $e->getMessage();
            }
        }
    }
}

if ($user['role_name'] === "Admin") {
    $stmt = $pdo->query("SELECT * FROM departments where is_active = 1 ORDER BY department_name");
} else {

    $stmt = $pdo->query("SELECT * FROM departments where is_active = 1 and department_id !=3 ORDER BY department_name");
}

$departments = $stmt->fetchAll();

if ($user['role_name'] === "Admin") {
    $stmt = $pdo->prepare("
        SELECT DISTINCT emp_perf.month, emp_perf.year, d.department_id, d.department_name
        FROM employee_monthly_performance emp_perf
        JOIN departments d ON emp_perf.department_id = d.department_id
        WHERE emp_perf.salary_calculated = FALSE
        AND (emp_perf.year < ? OR (emp_perf.year = ? AND emp_perf.month < ?))
        ORDER BY emp_perf.year DESC, emp_perf.month DESC
    ");
    $stmt->execute([$currentYear, $currentYear, $currentMonth]);
} else {
    $stmt = $pdo->prepare("
        SELECT DISTINCT emp_perf.month, emp_perf.year, d.department_id, d.department_name
        FROM employee_monthly_performance emp_perf
        JOIN departments d ON emp_perf.department_id = d.department_id
        WHERE emp_perf.salary_calculated = FALSE
        AND (emp_perf.year < ? OR (emp_perf.year = ? AND emp_perf.month < ?))
        AND d.department_id != 3
        ORDER BY emp_perf.year DESC, emp_perf.month DESC
    ");
    $stmt->execute([$currentYear, $currentYear, $currentMonth]);
}
$stmt->execute([$currentYear, $currentYear, $currentMonth]);
$unprocessedMonths = $stmt->fetchAll();

if ($user['role_name'] === "Admin") {
    $stmt = $pdo->prepare("
        SELECT DISTINCT emp_perf.month, emp_perf.year, d.department_id, d.department_name
        FROM employee_monthly_performance emp_perf
        JOIN departments d ON emp_perf.department_id = d.department_id
        WHERE emp_perf.month = ? AND emp_perf.year = ?
        ORDER BY d.department_name
    ");
    $stmt->execute([$currentMonth, $currentYear]);
} else {
    $stmt = $pdo->prepare("
        SELECT DISTINCT emp_perf.month, emp_perf.year, d.department_id, d.department_name
        FROM employee_monthly_performance emp_perf
        JOIN departments d ON emp_perf.department_id = d.department_id
        WHERE emp_perf.month = ? AND emp_perf.year = ?
        AND d.department_id != 3
        ORDER BY d.department_name
    ");
    $stmt->execute([$currentMonth, $currentYear]);
}
$stmt->execute([$currentMonth, $currentYear]);
$currentMonthData = $stmt->fetchAll();

if ($user['role_name'] === "Admin") {
    $stmt = $pdo->prepare("
        SELECT DISTINCT emp_perf.month, emp_perf.year, d.department_id, d.department_name
        FROM employee_monthly_performance emp_perf
        JOIN departments d ON emp_perf.department_id = d.department_id
        WHERE emp_perf.salary_calculated = TRUE
        ORDER BY emp_perf.year DESC, emp_perf.month DESC
    ");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("
        SELECT DISTINCT emp_perf.month, emp_perf.year, d.department_id, d.department_name
        FROM employee_monthly_performance emp_perf
        JOIN departments d ON emp_perf.department_id = d.department_id
        WHERE emp_perf.salary_calculated = TRUE
        AND d.department_id != 3
        ORDER BY emp_perf.year DESC, emp_perf.month DESC
    ");
    $stmt->execute();
}
$stmt->execute();
$historyMonths = $stmt->fetchAll();

$departmentMonths = [];
foreach ($unprocessedMonths as $month) {
    $key = $month['department_id'];
    if (!isset($departmentMonths[$key])) {
        $departmentMonths[$key] = [
            'department_id' => $month['department_id'],
            'department_name' => $month['department_name'],
            'months' => []
        ];
    }
    $departmentMonths[$key]['months'][] = [
        'month' => $month['month'],
        'year' => $month['year'],
        'label' => $month['month'] . '-' . $month['year']
    ];
}

$currentMonthDepartments = [];
foreach ($currentMonthData as $month) {
    $key = $month['department_id'];
    if (!isset($currentMonthDepartments[$key])) {
        $currentMonthDepartments[$key] = [
            'department_id' => $month['department_id'],
            'department_name' => $month['department_name'],
            'month' => $month['month'],
            'year' => $month['year']
        ];
    }
}

$historyDepartmentMonths = [];
foreach ($historyMonths as $month) {
    $key = $month['department_id'];
    if (!isset($historyDepartmentMonths[$key])) {
        $historyDepartmentMonths[$key] = [
            'department_id' => $month['department_id'],
            'department_name' => $month['department_name'],
            'months' => []
        ];
    }
    $historyDepartmentMonths[$key]['months'][] = [
        'month' => $month['month'],
        'year' => $month['year'],
        'label' => $month['month'] . '-' . $month['year']
    ];
}

$selectedDepartment = null;
$employees = [];
$departmentSettings = null;
$isCurrentMonth = false;

if (isset($_GET['view_department']) && isset($_GET['month']) && isset($_GET['year'])) {
    $viewDepartmentId = $_GET['view_department'];
    $viewMonth = $_GET['month'];
    $viewYear = $_GET['year'];

    $isCurrentMonth = ($viewMonth == $currentMonth && $viewYear == $currentYear);

    $stmt = $pdo->prepare("SELECT * FROM departments WHERE department_id = ?");
    $stmt->execute([$viewDepartmentId]);
    $selectedDepartment = $stmt->fetch();

    $stmt = $pdo->prepare("
      SELECT 
    mds.*, 
    d.kpi_name, 
    d.kpi_unit
FROM monthly_department_settings mds
JOIN departments d ON mds.department_id = d.department_id
WHERE mds.department_id = ? AND mds.month = ? AND mds.year = ?;

    ");
    $stmt->execute([$viewDepartmentId, $viewMonth, $viewYear]);
    $departmentSettings = $stmt->fetch();

    $stmt = $pdo->prepare("
    SELECT e.employee_id, e.first_name, e.last_name, 
           emp.individual_kpi_target, emp.kpi_achieved, 
           emp.authorized_absences, emp.unauthorized_absences,
           emp.individual_base_salary, emp.final_salary, emp.salary_calculated,
           emp.late_day,emp.working_day, emp.total_allowance, emp.penalty_more, emp.bonus_more
    FROM employees e
    INNER JOIN employee_monthly_performance emp ON e.employee_id = emp.employee_id 
                                            AND emp.month = ? AND emp.year = ?
                                            AND emp.department_id = ?
                                            and e.is_locked = 0
    WHERE e.department_id = ?
    ORDER BY e.last_name, e.first_name
");
    $stmt->execute([$viewMonth, $viewYear, $viewDepartmentId, $viewDepartmentId]);
    $employees = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tính Lương - Hệ Thống Quản Lý Nhân Sự</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .department-card {
            transition: all 0.3s;
            cursor: pointer;
        }

        .department-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .unprocessed-month {
            cursor: pointer;
            transition: all 0.2s;
        }

        .unprocessed-month:hover {
            background-color: #f8f9fa;
        }

        .status-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }

        .nav-tabs .nav-link {
            border-radius: 0.5rem 0.5rem 0 0;
        }

        .nav-tabs .nav-link.active {
            font-weight: bold;
        }
    </style>
</head>

<body>

    <?php include dirname(__DIR__) . '/partials/header.php'; ?>


    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col">
                <h2><i class="fas fa-calculator me-2"></i>Tính Lương</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Trang chủ</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Tính Lương</li>
                    </ol>
                </nav>
            </div>
        </div>

        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $successMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $errorMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-3">
                <ul class="nav nav-tabs mb-3 nav-tabs-sm" id="salaryTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fs-6 py-2" id="unprocessed-tab" data-bs-toggle="tab"
                            data-bs-target="#unprocessed" type="button" role="tab" aria-controls="unprocessed"
                            aria-selected="true">
                            <i class="fas fa-clock me-1"></i>Chờ Tính Lương
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fs-6 py-2" id="current-tab" data-bs-toggle="tab"
                            data-bs-target="#current" type="button" role="tab" aria-controls="current"
                            aria-selected="false">
                            <i class="fas fa-calendar-day me-1"></i>Tháng Hiện Tại
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fs-6 py-2" id="history-tab" data-bs-toggle="tab"
                            data-bs-target="#history" type="button" role="tab" aria-controls="history"
                            aria-selected="false">
                            <i class="fas fa-history me-1"></i>Lịch Sử
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="salaryTabsContent">
                    <div class="tab-pane fade show active" id="unprocessed" role="tabpanel"
                        aria-labelledby="unprocessed-tab">
                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white py-2">
                                <h6 class="card-title mb-0"><i class="fas fa-calendar-alt me-1"></i>Tháng Chưa Tính
                                    Lương</h6>
                            </div>
                            <div class="card-body p-2">
                                <?php if (empty($departmentMonths)): ?>
                                    <div class="alert alert-info py-2 mb-0">
                                        <i class="fas fa-info-circle me-1"></i> Tất cả các tháng đã được tính lương.
                                    </div>
                                <?php else: ?>
                                    <div class="accordion accordion-flush" id="departmentAccordion">
                                        <?php foreach ($departmentMonths as $index => $dept): ?>
                                            <div class="accordion-item">
                                                <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                                                    <button
                                                        class="accordion-button py-2 px-3 fs-6 <?php echo $index > 0 ? 'collapsed' : ''; ?>"
                                                        type="button" data-bs-toggle="collapse"
                                                        data-bs-target="#collapse<?php echo $index; ?>"
                                                        aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>"
                                                        aria-controls="collapse<?php echo $index; ?>">
                                                        <span
                                                            class="fw-bold"><?php echo htmlspecialchars($dept['department_name']); ?></span>
                                                        <span
                                                            class="badge bg-primary ms-2"><?php echo count($dept['months']); ?></span>
                                                    </button>
                                                </h2>
                                                <div id="collapse<?php echo $index; ?>"
                                                    class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>"
                                                    aria-labelledby="heading<?php echo $index; ?>"
                                                    data-bs-parent="#departmentAccordion">
                                                    <div class="accordion-body p-0">
                                                        <ul class="list-group list-group-flush">
                                                            <?php foreach ($dept['months'] as $monthData): ?>
                                                                <li
                                                                    class="list-group-item py-2 px-3 d-flex justify-content-between align-items-center unprocessed-month">
                                                                    <a href="?view_department=<?php echo $dept['department_id']; ?>&month=<?php echo $monthData['month']; ?>&year=<?php echo $monthData['year']; ?>"
                                                                        class="text-decoration-none text-dark d-block w-100 fs-6">
                                                                        <i class="fas fa-calendar-week me-1"></i> Tháng
                                                                        <?php echo $monthData['month']; ?>/<?php echo $monthData['year']; ?>
                                                                    </a>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="current" role="tabpanel" aria-labelledby="current-tab">
                        <div class="card mb-3">
                            <div class="card-header bg-warning text-dark py-2">
                                <h6 class="card-title mb-0"><i class="fas fa-calendar-day me-1"></i>Tháng Hiện Tại
                                    (<?php echo $currentMonth; ?>/<?php echo $currentYear; ?>)</h6>
                            </div>
                            <div class="card-body p-2">
                                <?php if (empty($currentMonthDepartments)): ?>
                                    <div class="alert alert-info py-2 mb-0">
                                        <i class="fas fa-info-circle me-1"></i> Không có dữ liệu cho tháng hiện tại.
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($currentMonthDepartments as $dept): ?>
                                            <a href="?view_department=<?php echo $dept['department_id']; ?>&month=<?php echo $dept['month']; ?>&year=<?php echo $dept['year']; ?>"
                                                class="list-group-item list-group-item-action py-2 px-3">
                                                <div class="d-flex w-100 justify-content-between align-items-center">
                                                    <h6 class="mb-0 fs-6"><i
                                                            class="fas fa-building me-1"></i><?php echo htmlspecialchars($dept['department_name']); ?>
                                                    </h6>
                                                    <span class="badge bg-warning text-dark">Chỉ xem</span>
                                                </div>
                                                <small class="text-muted">Tháng
                                                    <?php echo $dept['month']; ?>/<?php echo $dept['year']; ?></small>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="history" role="tabpanel" aria-labelledby="history-tab">
                        <div class="card mb-3">
                            <div class="card-header bg-success text-white py-2">
                                <h6 class="card-title mb-0"><i class="fas fa-history me-1"></i>Lịch Sử Tính Lương</h6>
                            </div>
                            <div class="card-body p-2">
                                <?php if (empty($historyDepartmentMonths)): ?>
                                    <div class="alert alert-info py-2 mb-0">
                                        <i class="fas fa-info-circle me-1"></i> Chưa có lịch sử tính lương.
                                    </div>
                                <?php else: ?>
                                    <div class="accordion accordion-flush" id="historyAccordion">
                                        <?php foreach ($historyDepartmentMonths as $index => $dept): ?>
                                            <div class="accordion-item">
                                                <h2 class="accordion-header" id="historyHeading<?php echo $index; ?>">
                                                    <button class="accordion-button collapsed py-2 px-3 fs-6" type="button"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#historyCollapse<?php echo $index; ?>"
                                                        aria-expanded="false"
                                                        aria-controls="historyCollapse<?php echo $index; ?>">
                                                        <span
                                                            class="fw-bold"><?php echo htmlspecialchars($dept['department_name']); ?></span>
                                                        <span
                                                            class="badge bg-success ms-2"><?php echo count($dept['months']); ?></span>
                                                    </button>
                                                </h2>
                                                <div id="historyCollapse<?php echo $index; ?>"
                                                    class="accordion-collapse collapse"
                                                    aria-labelledby="historyHeading<?php echo $index; ?>"
                                                    data-bs-parent="#historyAccordion">
                                                    <div class="accordion-body p-0">
                                                        <ul class="list-group list-group-flush">
                                                            <?php foreach ($dept['months'] as $monthData): ?>
                                                                <li
                                                                    class="list-group-item py-2 px-3 d-flex justify-content-between align-items-center unprocessed-month">
                                                                    <a href="?view_department=<?php echo $dept['department_id']; ?>&month=<?php echo $monthData['month']; ?>&year=<?php echo $monthData['year']; ?>"
                                                                        class="text-decoration-none text-dark d-block w-100 fs-6">
                                                                        <i class="fas fa-calendar-check me-1"></i> Tháng
                                                                        <?php echo $monthData['month']; ?>/<?php echo $monthData['year']; ?>
                                                                    </a>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-info text-white py-2">
                        <h6 class="card-title mb-0"><i class="fas fa-info-circle me-1"></i>Hướng Dẫn</h6>
                    </div>
                    <div class="card-body p-2">
                        <ul class="list-unstyled mb-0 fs-6">
                            <li class="mb-1"><i class="fas fa-exclamation-triangle text-danger me-1"></i> <span
                                    class="fw-bold text-danger">Lưu ý:</span> Phải chắc chắn rằng bạn đã set thông tin
                                quản lý KPI & Lương và toàn bộ nhân viên cho phòng ban đã được chọn để hiển thị được
                                thông tin cho tháng hiện tại</li>
                            <hr class="my-2" />
                            <li class="mb-1"><i class="fas fa-check-circle text-success me-1"></i> Chọn phòng ban và
                                tháng cần tính lương từ tab "Chờ Tính Lương".</li>
                            <li class="mb-1"><i class="fas fa-calendar-day text-warning me-1"></i> Tab "Tháng Hiện Tại"
                                chỉ cho phép xem dữ liệu, không tính lương.</li>
                            <li class="mb-1"><i class="fas fa-history text-success me-1"></i> Tab "Lịch Sử" hiển thị các
                                tháng đã tính lương.</li>
                            <li class="mb-1"><i class="fas fa-eye text-primary me-1"></i> Xem chi tiết thông tin lương
                                của nhân viên.</li>
                            <li class="mb-1"><i class="fas fa-calculator text-warning me-1"></i> Nhấn nút "Tính Lương"
                                để tính lương cho toàn bộ nhân viên trong phòng ban.</li>
                            <li class="mb-0"><i class="fas fa-exclamation-triangle text-danger me-1"></i> Lưu ý: Sau khi
                                tính lương, dữ liệu sẽ được khóa và không thể thay đổi.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-9">
                <?php
                $text = "Ngày";
                if ($selectedDepartment && $departmentSettings): ?>
                <?php
                if ($selectedDepartment['department_id'] == 4) {
                    $text = "Ca";
                }
                
                ?>
                    <div class="card mb-4">
                        <div
                            class="card-header <?php echo $isCurrentMonth ? 'bg-warning text-dark' : 'bg-primary text-white'; ?> d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-building me-2"></i>
                                <?php echo htmlspecialchars($selectedDepartment['department_name']); ?> -
                                Tháng <?php echo $_GET['month']; ?>/<?php echo $_GET['year']; ?>
                                <?php if ($isCurrentMonth): ?>
                                    <span class="badge bg-light text-dark ms-2">Tháng Hiện Tại - Chỉ Xem</span>
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6 class="fw-bold">Thông Tin Phòng Ban:</h6>
                                    <p><strong>Mã phòng ban:</strong>
                                        <?php echo htmlspecialchars($selectedDepartment['department_code']); ?></p>
                                    <p><strong>Số nhân viên hiện tại:</strong> <?php echo count($employees); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="fw-bold">Cài Đặt Lương Tháng
                                        <?php echo $_GET['month']; ?>/<?php echo $_GET['year']; ?>:
                                    </h6>
                                    <p><strong><?php echo $departmentSettings['kpi_name']; ?> mặc định:</strong>
                                        <?php echo number_format($departmentSettings['kpi_target'], 2); ?></p>
                                    <p><strong>Lương cơ bản:</strong>
                                        <?php echo number_format($departmentSettings['base_salary']); ?> đ</p>
                                    <p><strong>Trợ cấp ăn uống:</strong>
                                        <?php echo number_format($departmentSettings['daily_meal_allowance']); ?> đ/ngày
                                    </p>

                                    <p><strong>Phạt nghỉ không phép:</strong>
                                        <?php echo number_format($departmentSettings['unauthorized_absence_penalty']); ?>
                                        đ/ngày</p>
                                    <p><strong>Phạt đi muộn:</strong>
                                        <?php echo number_format($departmentSettings['late_arrival_penalty']); ?>
                                        đ/ngày</p>
                                </div>
                            </div>

                            <h6 class="fw-bold mb-3">Danh Sách Nhân Viên:</h6>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" style="font-size: 0.8rem;">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Mã</th>
                                            <th>Tên</th>

                                            <th><?php echo $departmentSettings['kpi_name']; ?> đạt Được</th>
                                            <th>Nghỉ Phép / Không / muộn</th>


                                            <th><?php echo $text; ?> làm</th>
                                            <th>Tổng phạt</th>

                                            <th>Trợ cấp / thưởng thêm</th>
                                            <th>Lương CB</th>
                                            <th>Lương Cuối</th>
                                            <th>Trạng Thái</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($employees)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center">Không có nhân viên nào trong phòng ban.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($employees as $index => $employee): ?>
                                                <tr>
                                                    <td><?php echo $index + 1; ?></td>
                                                    <td><?php echo htmlspecialchars($employee['last_name'] . ' ' . $employee['first_name']); ?>
                                                    </td>

                                                    <td>
                                                        <?php echo number_format($employee['kpi_achieved'], 2); ?>
                                                        <?php
                                                        $kpiDiff = $employee['kpi_achieved'] - $employee['individual_kpi_target'];
                                                        if ($kpiDiff > 0) {
                                                            echo '<span class="badge bg-success ms-2">+' . number_format($kpiDiff, 2) . '</span>';
                                                        } elseif ($kpiDiff < 0) {
                                                            echo '<span class="badge bg-danger ms-2">' . number_format($kpiDiff, 2) . '</span>';
                                                        }
                                                        ?> /
                                                        <?php echo number_format($employee['individual_kpi_target'], 2); ?>
                                                    </td>
                                                    <td><?php echo $employee['authorized_absences']; ?> /
                                                        <?php echo $employee['unauthorized_absences']; ?> /
                                                        <?php echo $employee['late_day']; ?>
                                                    </td>


                                                    <td><?php echo $employee['working_day']; ?></td>
                                                    <td class="text-danger">
                                                        <?php
                                                        $totalPenalty = $employee['penalty_more'] +
                                                            ($employee['late_day'] * $departmentSettings['late_arrival_penalty']) +
                                                            ($employee['unauthorized_absences'] * $departmentSettings['unauthorized_absence_penalty']);
                                                        echo number_format($totalPenalty);
                                                        ?> đ
                                                    </td>

                                                    <td class="text-success">
                                                        <?php echo number_format($employee['total_allowance']); ?> đ /
                                                        <?php echo number_format($employee['bonus_more']); ?> đ
                                                    </td>
                                                    <td><?php echo number_format($employee['individual_base_salary']); ?> đ</td>
                                                    <td>
                                                        <?php if ($employee['final_salary'] !== null): ?>
                                                            <?php echo number_format($employee['final_salary']); ?> đ
                                                        <?php elseif ($employee['salary_calculated']): ?>
                                                            <?php echo number_format($employee['final_salary']); ?> đ
                                                        <?php else: ?>
                                                            <span class="text-muted">Chưa tính</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($employee['salary_calculated']): ?>
                                                            <span class="badge bg-success">Đã tính lương</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning text-dark">Chưa tính lương</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($isCurrentMonth && !empty($employees)): ?>
                                <form method="POST" class="mt-3">
                                    <input type="hidden" name="department_id"
                                        value="<?php echo $selectedDepartment['department_id']; ?>">
                                    <input type="hidden" name="month_year"
                                        value="<?php echo $_GET['month'] . '-' . $_GET['year']; ?>">
                                    <button type="submit" name="temp_calculate_salary" class="btn btn-secondary">
                                        <i class="fas fa-calculator me-2"></i>Lấy dữ liệu lương tạm thời
                                    </button>

                                </form>
                            <?php endif; ?>

                            <?php if (!$isCurrentMonth && !empty($employees)): ?>
                                <?php
                                $allCalculated = true;
                                foreach ($employees as $employee) {
                                    if (!$employee['salary_calculated']) {
                                        $allCalculated = false;
                                        break;
                                    }
                                }
                                ?>

                                <?php if (!$allCalculated): ?>

                                    <form method="POST" class="mt-3">
                                        <input type="hidden" name="department_id"
                                            value="<?php echo $selectedDepartment['department_id']; ?>">
                                        <input type="hidden" name="month_year"
                                            value="<?php echo $_GET['month'] . '-' . $_GET['year']; ?>">
                                        <button type="submit" name="temp_calculate_salary" class="btn btn-secondary">
                                            <i class="fas fa-calculator me-2"></i>Tạm Tính lương
                                        </button>

                                    </form>
                                    <hr />
                                    <form method="POST" class="mt-3">
                                        <input type="hidden" name="department_id"
                                            value="<?php echo $selectedDepartment['department_id']; ?>">
                                        <input type="hidden" name="month_year"
                                            value="<?php echo $_GET['month'] . '-' . $_GET['year']; ?>">
                                        <button type="submit" name="calculate_salary" class="btn btn-warning">
                                            <i class="fas fa-calculator me-2"></i>Tính Lương Cho Tất Cả Nhân Viên
                                        </button>
                                        <small class="d-block mt-2 text-danger">
                                            <i class="fas fa-exclamation-circle"></i> Lưu ý: Sau khi tính lương, dữ liệu sẽ được
                                            khóa và không thể thay đổi.
                                        </small>
                                    </form>


                                <?php else: ?>
                                    <div class="alert alert-success mt-3">
                                        <i class="fas fa-check-circle me-2"></i> Đã tính lương cho tất cả nhân viên trong phòng ban.
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-building fa-4x mb-3 text-muted"></i>
                            <h4 class="text-muted">Chọn Phòng Ban và Tháng</h4>
                            <p class="text-muted">Vui lòng chọn phòng ban và tháng từ danh sách bên trái để xem chi tiết và
                                tính lương.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('view_department')) {
                const month = urlParams.get('month');
                const year = urlParams.get('year');
                const currentMonth = <?php echo $currentMonth; ?>;
                const currentYear = <?php echo $currentYear; ?>;

                if (month == currentMonth && year == currentYear) {
                    const currentTab = document.getElementById('current-tab');
                    if (currentTab) {
                        const tab = new bootstrap.Tab(currentTab);
                        tab.show();
                    }
                }

                const viewingHistory = document.querySelector(`.tab-pane a[href="?view_department=${urlParams.get('view_department')}&month=${month}&year=${year}"]`);
                if (viewingHistory && viewingHistory.closest('.tab-pane').id === 'history') {
                    const historyTab = document.getElementById('history-tab');
                    if (historyTab) {
                        const tab = new bootstrap.Tab(historyTab);
                        tab.show();
                    }
                }
            }
        });
    </script>

    <?php include dirname(__DIR__) . '/partials/footer.php'; ?>
</body>

</html>