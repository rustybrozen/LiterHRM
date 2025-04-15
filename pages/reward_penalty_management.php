<?php
require_once dirname(__DIR__) . '/config/db.php';
$user = checkPermission('hr_manager');


$currentMonth = date('n');
$currentYear = date('Y');
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : $currentMonth;
$selectedYear = isset($_GET['year']) ? $_GET['year'] : $currentYear;
$selectedDepartment = isset($_GET['department_id']) ? $_GET['department_id'] : '';

if ($user['role_id'] == 12) {$stmt = $pdo->prepare("SELECT * FROM departments where department_id !=3 ORDER BY department_name");  }
else{
    $stmt = $pdo->prepare("SELECT * FROM departments ORDER BY department_name");
}

$stmt->execute();
$departments = $stmt->fetchAll();


$employees = [];
if ($selectedDepartment) {
if ($user['role_id'] == 12) {   $stmt = $pdo->prepare("
    SELECT CONCAT(e.first_name, ' ', e.last_name) as fullname, e.employee_id,    d.department_name, p.performance_id, p.kpi_achieved, p.individual_base_salary
    FROM employees e
    JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN employee_monthly_performance p ON e.employee_id = p.employee_id
        AND p.month = ? AND p.year = ? AND p.department_id = ?
    WHERE e.department_id = ? AND e.is_locked = 0 and e.department_id !=3
    ORDER BY e.last_name, e.first_name
");}
else{
    $stmt = $pdo->prepare("
    SELECT CONCAT(e.first_name, ' ', e.last_name) as fullname, e.employee_id,    d.department_name, p.performance_id, p.kpi_achieved, p.individual_base_salary
    FROM employees e
    JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN employee_monthly_performance p ON e.employee_id = p.employee_id
        AND p.month = ? AND p.year = ? AND p.department_id = ?
    WHERE e.department_id = ? AND e.is_locked = 0
    ORDER BY e.last_name, e.first_name
");
}

 
    $stmt->execute([$selectedMonth, $selectedYear, $selectedDepartment, $selectedDepartment]);
    $employees = $stmt->fetchAll();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_bonus_penalty') {

   if ($selectedMonth != $currentMonth || $selectedYear != $currentYear) {
   
    $_SESSION['error_message'] = "Chỉ có thể thêm thưởng phạt trong tháng hiện tại.";
    header("Location: reward_penalty_management.php?department_id=$selectedDepartment&month=$selectedMonth&year=$selectedYear&employee_id=" . $_POST['employee_id']);
    exit;
}

$performanceId = $_POST['performance_id'];
$employeeId = $_POST['employee_id'];
$isBonus = $_POST['is_bonus'];
$reason = $_POST['reason'];
$amount = $_POST['amount'];
$date = $_POST['date'];

    
 
    $stmt = $pdo->prepare("SELECT performance_id FROM employee_monthly_performance WHERE performance_id = ?");
    $stmt->execute([$performanceId]);
    $performance = $stmt->fetch();
    
    if (!$performance) {
     
        $stmt = $pdo->prepare("
            INSERT INTO employee_monthly_performance 
            (employee_id, department_id, month, year, individual_kpi_target, individual_base_salary)
            SELECT employee_id, department_id, ?, ?, 100.00, salary
            FROM employees WHERE employee_id = ?
        ");
        $stmt->execute([$selectedMonth, $selectedYear, $employeeId]);
        $performanceId = $pdo->lastInsertId();
    }
    
 
    $stmt = $pdo->prepare("
        INSERT INTO employee_bonuses_penalties 
        (performance_id, employee_id, is_bonus, reason, amount, date)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$performanceId, $employeeId, $isBonus, $reason, $amount, $date]);
    
  
    header("Location: reward_penalty_management.php?department_id=$selectedDepartment&month=$selectedMonth&year=$selectedYear&employee_id=$employeeId");
    exit;
}


if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
   
    if ($selectedMonth != $currentMonth || $selectedYear != $currentYear) {
    
        $_SESSION['error_message'] = "Chỉ có thể xóa thưởng phạt trong tháng hiện tại.";
        header("Location: reward_penalty_management.php?department_id=$selectedDepartment&month=$selectedMonth&year=$selectedYear&employee_id=" . $_GET['employee_id']);
        exit;
    }
    
    $id = $_GET['id'];
    $employeeId = $_GET['employee_id'];
    
    $stmt = $pdo->prepare("DELETE FROM employee_bonuses_penalties WHERE id = ?");
    $stmt->execute([$id]);
    
    header("Location: reward_penalty_management.php?department_id=$selectedDepartment&month=$selectedMonth&year=$selectedYear&employee_id=$employeeId");
    exit;
}

$selectedEmployeeId = isset($_GET['employee_id']) ? $_GET['employee_id'] : '';
$bonusPenalties = [];
$totalBonus = 0;
$totalPenalty = 0;

if ($selectedEmployeeId) {
    $stmt = $pdo->prepare("
        SELECT bp.*, CONCAT(e.first_name, ' ', e.last_name) as fullname
        FROM employee_bonuses_penalties bp
        JOIN employees e ON bp.employee_id = e.employee_id
        JOIN employee_monthly_performance p ON bp.performance_id = p.performance_id
        WHERE bp.employee_id = ? AND p.month = ? AND p.year = ?
        ORDER BY bp.date DESC
    ");
    $stmt->execute([$selectedEmployeeId, $selectedMonth, $selectedYear]);
    $bonusPenalties = $stmt->fetchAll();
    

    foreach ($bonusPenalties as $item) {
        if ($item['is_bonus'] == 1) {
            $totalBonus += $item['amount'];
        } else {
            $totalPenalty += $item['amount'];
        }
    }

 
    $stmt = $pdo->prepare("
        SELECT e.*, p.performance_id,CONCAT(e.first_name, ' ', e.last_name) as fullname
        FROM employees e
        LEFT JOIN employee_monthly_performance p ON e.employee_id = p.employee_id
            AND p.month = ? AND p.year = ?
        WHERE e.employee_id = ?
    ");
    $stmt->execute([$selectedMonth, $selectedYear, $selectedEmployeeId]);
    $selectedEmployee = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý thưởng phạt</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css">
    <style>
        .bonus-amount { color: green; }
        .penalty-amount { color: red; }
        .text-bonus { color: green; font-weight: bold; }
        .text-penalty { color: red; font-weight: bold; }
    </style>
</head>
<body>
<?php include dirname(__DIR__) . '/partials/header.php'; ?>

    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <?= $_SESSION['error_message'] ?>
            <?php unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>
    
    <div class="container-fluid mt-4">
        <h1 class="mb-4">Quản lý thưởng phạt</h1>
        
        <form method="GET" class="mb-4 row">
            <div class="col-md-3">
                <label for="department_id">Phòng ban:</label>
                <select name="department_id" id="department_id" class="form-control" required>
                    <option value="">-- Chọn phòng ban --</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?= $department['department_id'] ?>" <?= $selectedDepartment == $department['department_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($department['department_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="month">Tháng:</label>
                <select name="month" id="month" class="form-control">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= $selectedMonth == $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="year">Năm:</label>
                <select name="year" id="year" class="form-control">
                    <?php for ($i = $currentYear - 2; $i <= $currentYear + 1; $i++): ?>
                        <option value="<?= $i ?>" <?= $selectedYear == $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary form-control">Lọc</button>
            </div>
        </form>
        
        <div class="row">
            <?php if ($selectedDepartment): ?>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Danh sách nhân viên</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($employees) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($employees as $employee): ?>
                                    <a href="?department_id=<?= $selectedDepartment ?>&month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>&employee_id=<?= $employee['employee_id'] ?>" 
                                       class="list-group-item list-group-item-action <?= $selectedEmployeeId == $employee['employee_id'] ? 'active' : '' ?>">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <strong><?= htmlspecialchars($employee['fullname']) ?></strong><br>
                                                <small>Mã NV: <?= htmlspecialchars($employee['employee_id']) ?></small>
                                            </div>
                                            <div>
                                                <?php 
                                                    $employeeBonus = 0;
                                                    $employeePenalty = 0;
                                                    
                                                    $stmt = $pdo->prepare("
                                                        SELECT SUM(CASE WHEN is_bonus = 1 THEN amount ELSE 0 END) as total_bonus,
                                                               SUM(CASE WHEN is_bonus = 0 THEN amount ELSE 0 END) as total_penalty
                                                        FROM employee_bonuses_penalties bp
                                                        JOIN employee_monthly_performance p ON bp.performance_id = p.performance_id
                                                        WHERE bp.employee_id = ? AND p.month = ? AND p.year = ?
                                                    ");
                                                    $stmt->execute([$employee['employee_id'], $selectedMonth, $selectedYear]);
                                                    $totals = $stmt->fetch();
                                                    
                                                    if ($totals) {
                                                        $employeeBonus = $totals['total_bonus'] ?: 0;
                                                        $employeePenalty = $totals['total_penalty'] ?: 0;
                                                    }
                                                ?>
                                                <span class="text-bonus">+<?= number_format($employeeBonus, 0, ',', '.') ?></span><br>
                                                <span class="text-penalty">-<?= number_format($employeePenalty, 0, ',', '.') ?></span>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-center">Không có nhân viên nào</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($selectedEmployeeId): ?>
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Thông tin thưởng phạt: <?= $selectedEmployee['fullname'] ?></h5>
                        <div>
                            <span class="badge badge-success">Tổng thưởng: <?= number_format($totalBonus, 0, ',', '.') ?>đ</span>
                            <span class="badge badge-danger ml-2">Tổng phạt: <?= number_format($totalPenalty, 0, ',', '.') ?>đ</span>
                            <span class="badge badge-primary ml-2">Còn lại: <?= number_format($totalBonus - $totalPenalty, 0, ',', '.') ?>đ</span>
                        </div>
                    </div>
                    <div class="card-body">
                    <?php if ($selectedMonth == $currentMonth && $selectedYear == $currentYear): ?>
                    <form method="POST" class="mb-4">
          
            <input type="hidden" name="action" value="add_bonus_penalty">
            <input type="hidden" name="employee_id" value="<?= $selectedEmployeeId ?>">
            <input type="hidden" name="performance_id" value="<?= $selectedEmployee['performance_id'] ?: 0 ?>">
                            <div class="form-row">
                                <div class="form-group col-md-2">
                                    <label>Loại:</label>
                                    <select name="is_bonus" class="form-control" required>
                                        <option value="1">Thưởng</option>
                                        <option value="0">Phạt</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Số tiền:</label>
                                    <input type="number" name="amount" class="form-control" min="0" step="1000" required>
                                </div>
                                <?php
$currentYear = date('Y');
$currentMonth = date('m');
$firstDay = "$currentYear-$currentMonth-01";
$lastDay = date("Y-m-t", strtotime($firstDay)); 
?>
<div class="form-group col-md-3">
    <label>Ngày:</label>
    <input type="date" name="date" class="form-control" 
           value="<?= date('Y-m-d') ?>" 
           min="<?= $firstDay ?>" 
           max="<?= $lastDay ?>" 
           required>
</div>
                                <div class="form-group col-md-4">
                                    <label>Lý do:</label>
                                    <input type="text" name="reason" class="form-control" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success">Thêm mới</button>
                            </form>
                            
                            <?php else: ?>
        <div class="alert alert-warning mb-4">
            Chỉ có thể thêm thưởng phạt trong tháng hiện tại (<?= $currentMonth ?>/<?= $currentYear ?>).
        </div>
    <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Ngày</th>
                                        <th>Loại</th>
                                        <th>Lý do</th>
                                        <th>Số tiền</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($bonusPenalties) > 0): ?>
                                        <?php foreach ($bonusPenalties as $item): ?>
                                            <tr>
                                                <td><?= date('d/m/Y', strtotime($item['date'])) ?></td>
                                                <td>
                                                    <?php if ($item['is_bonus'] == 1): ?>
                                                        <span class="badge badge-success">Thưởng</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger">Phạt</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($item['reason']) ?></td>
                                                <td class="text-right <?= $item['is_bonus'] == 1 ? 'bonus-amount' : 'penalty-amount' ?>">
                                                    <?= number_format($item['amount'], 0, ',', '.') ?>đ
                                                </td>
                                                <td class="text-center">
    <?php if ($selectedMonth == $currentMonth && $selectedYear == $currentYear): ?>
        <a href="?department_id=<?= $selectedDepartment ?>&month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>&employee_id=<?= $selectedEmployeeId ?>&action=delete&id=<?= $item['id'] ?>" 
           class="btn btn-sm btn-danger" 
           onclick="return confirm('Bạn có chắc chắn muốn xóa mục này?');">
            <i class="bi bi-trash"></i> Xóa
        </a>
    <?php else: ?>
        <button class="btn btn-sm btn-secondary" disabled>
            <i class="bi bi-trash"></i> Xóa
        </button>
    <?php endif; ?>
</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">Không có dữ liệu thưởng phạt</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php elseif ($selectedDepartment): ?>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body text-center">
                        <p class="mb-0">Vui lòng chọn nhân viên để xem thông tin thưởng phạt</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>