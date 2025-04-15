<?php
require_once dirname(__DIR__) . '/config/db.php';

$currentUser = checkPermission('hr_manager');

$employeeId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$employeeId) {
    die('ID nhân viên không hợp lệ');
}

$stmt = $pdo->prepare("
    SELECT e.*, d.department_name, u.username, u.email
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN users u ON e.user_id = u.user_id
    WHERE e.employee_id = ?
");
$stmt->execute([$employeeId]);
$employee = $stmt->fetch();

if (!$employee) {
    die('Không tìm thấy nhân viên');
}

$stmt = $pdo->prepare("
    SELECT emp.*, d.department_name
    FROM employee_monthly_performance emp
    JOIN departments d ON emp.department_id = d.department_id
    WHERE emp.employee_id = ?
    ORDER BY emp.year DESC, emp.month DESC
");
$stmt->execute([$employeeId]);
$performances = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT r.*, 
           CONCAT(u.username) as reviewer_name
    FROM employee_requests r
    LEFT JOIN users u ON r.reviewed_by = u.user_id
    LEFT JOIN employees e ON u.user_id = e.user_id
    WHERE r.employee_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$employeeId]);
$requests = $stmt->fetchAll();

$currentMonth = date('n');
$currentYear = date('Y');
$stmt = $pdo->prepare("
    SELECT emp.*, mds.kpi_target as dept_kpi, mds.base_salary as dept_salary,
           mds.daily_meal_allowance,
           mds.unauthorized_absence_penalty
    FROM employee_monthly_performance emp
    JOIN monthly_department_settings mds ON emp.department_id = mds.department_id
        AND emp.month = mds.month AND emp.year = mds.year
    WHERE emp.employee_id = ? AND emp.month = ? AND emp.year = ?
");
$stmt->execute([$employeeId, $currentMonth, $currentYear]);
$currentPerformance = $stmt->fetch();

function formatCurrency($amount)
{
    return number_format($amount, 0, ',', '.') . ' VNĐ';
}

function formatMonth($month, $year)
{
    return "Tháng $month/$year";
}

function getStatusClass($status)
{
    switch ($status) {
        case 1:
            return 'success';
        case 0:
            return 'danger';
        default:
            return 'warning';
    }
}

function getStatusText($status)
{
    switch ($status) {
        case 1:
            return 'Có Phép';
        case 0:
            return 'Không Phép';
        default:
            return 'Đang chờ';
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết nhân viên - <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .section-title {
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 8px;
            margin-bottom: 20px;
        }

        .performance-card {
            transition: all 0.2s;
        }

        .performance-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>
<?php include dirname(__DIR__) . '/partials/header.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-0"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h1>
                <p class="text-muted">
                    <?= htmlspecialchars($employee['department_name'] ?? 'Chưa phân công phòng ban') ?></p>
            </div>
            <div>
            
                <a href="employees.php" class="btn btn-outline-secondary ms-2">
                    <i class="bi bi-arrow-left"></i> Quay lại
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="m-0">Thông tin cá nhân</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Mã nhân viên:</span>
                                <span class="fw-bold"><?= $employee['employee_id'] ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Năm sinh:</span>
                                <span class="fw-bold"><?= date('d-m-Y', strtotime($employee['birth_date'])) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Giới tính:</span>
                                <span class="fw-bold">
                                    <?php
                                    switch ($employee['gender']) {
                                        case 'male':
                                            echo 'Nam';
                                            break;
                                        case 'female':
                                            echo 'Nữ';
                                            break;
                                        default:
                                            echo 'Khác';
                                    }
                                    ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Số CCCD:</span>
                                <span class="fw-bold"><?= htmlspecialchars($employee['id_card_number']) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Số điện thoại:</span>
                                <span class="fw-bold"><?= htmlspecialchars($employee['phone_number']) ?></span>
                            </li>
                            <li class="list-group-item">
                                <span>Địa chỉ:</span>
                                <p class="mt-1 mb-0 fw-bold"><?= htmlspecialchars($employee['address']) ?></p>
                            </li>
                        </ul>
                    </div>
                </div>

                <?php if ($employee['user_id']): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="m-0">Thông tin tài khoản</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Tên đăng nhập:</span>
                                    <span class="fw-bold"><?= htmlspecialchars($employee['username']) ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Email:</span>
                                    <span class="fw-bold"><?= htmlspecialchars($employee['email']) ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="m-0">Hiệu suất tháng hiện tại (<?= formatMonth($currentMonth, $currentYear) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($currentPerformance): ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card border-0 mb-3">
                                        <div class="card-body">
                                            <h6 class="card-title text-muted">KPI mục tiêu</h6>
                                            <div class="d-flex align-items-baseline">
                                                <h3 class="mb-0">
                                                    <?= number_format($currentPerformance['individual_kpi_target'], 1) ?>
                                                </h3>
                                                <span class="ms-2 text-muted">(Phòng ban:
                                                    <?= number_format($currentPerformance['dept_kpi'], 1) ?>)</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border-0 mb-3">
                                        <div class="card-body">
                                            <h6 class="card-title text-muted">KPI đạt được</h6>
                                            <div class="d-flex align-items-baseline">
                                                <h3 class="mb-0">
                                                    <?= number_format($currentPerformance['kpi_achieved'], 1) ?></h3>
                                                <span
                                                    class="ms-2 text-<?= $currentPerformance['kpi_achieved'] >= $currentPerformance['individual_kpi_target'] ? 'success' : 'danger' ?>">
                                                    (<?= number_format($currentPerformance['kpi_achieved'] / $currentPerformance['individual_kpi_target'] * 100, 1) ?>%)
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="card border-0 mb-3">
                                        <div class="card-body">
                                            <h6 class="card-title text-muted">Lương cơ bản</h6>
                                            <div class="d-flex align-items-baseline">
                                                <h3 class="mb-0">
                                                    <?= formatCurrency($currentPerformance['individual_base_salary']) ?>
                                                </h3>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border-0 mb-3">
                                        <div class="card-body">
                                            <h6 class="card-title text-muted">Trợ cấp ăn</h6>
                                            <div class="d-flex align-items-baseline">
                                                <h3 class="mb-0">
                                                    <?= formatCurrency($currentPerformance['daily_meal_allowance']) ?></h3>
                                                <span class="ms-2 text-muted">/ngày</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="card border-0 mb-3">
                                        <div class="card-body">
                                            <h6 class="card-title text-muted">Nghỉ phép</h6>
                                            <div class="d-flex align-items-baseline">
                                                <h3 class="mb-0"><?= $currentPerformance['authorized_absences'] ?></h3>
                                                <span class="ms-2 text-muted">ngày</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border-0 mb-3">
                                        <div class="card-body">
                                            <h6 class="card-title text-muted">Nghỉ không phép</h6>
                                            <div class="d-flex align-items-baseline">
                                                <h3 class="mb-0"><?= $currentPerformance['unauthorized_absences'] ?></h3>
                                                <span class="ms-2 text-muted">ngày</span>
                                                <?php if ($currentPerformance['unauthorized_absences'] > 0): ?>
                                                    <span class="ms-2 text-danger">
                                                        (-<?= formatCurrency($currentPerformance['unauthorized_absences'] * $currentPerformance['unauthorized_absence_penalty']) ?>)
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if ($currentPerformance['salary_calculated']): ?>
                                <div class="alert alert-success mt-3">
                                    <h5>Lương tháng này: <?= formatCurrency($currentPerformance['final_salary']) ?></h5>
                                    <p class="mb-0">Đã tính lương cho tháng này</p>
                                </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <div class="alert alert-warning">
                                <p class="mb-0">Chưa có dữ liệu hiệu suất cho tháng hiện tại</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="m-0">Yêu cầu nghỉ phép gần đây</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($requests) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>

                                            <th>Ngày</th>

                                            <th>Lý do</th>
                                            <th>Trạng thái</th>
                                            <th>Người duyệt</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($requests, 0, 5) as $request): ?>
                                            <tr>

                                                <td><?= date('d/m/Y', strtotime($request['start_date'])) ?></td>

                                                <td><?= htmlspecialchars($request['reason']) ?></td>
                                                <td><span
                                                        class="badge bg-<?= getStatusClass($request['is_absence_authorized']) ?>"><?= getStatusText($request['is_absence_authorized']) ?></span>
                                                </td>
                                                <td><?= $request['reviewer_name'] ?? '-' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (count($requests) > 5): ?>
                                <div class="text-center mt-3">
                                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#allRequests">
                                        Xem tất cả yêu cầu
                                    </button>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="text-muted">Không có yêu cầu nào gần đây</p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (count($requests) > 5): ?>
                    <div class="collapse" id="allRequests">
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="m-0">Tất cả yêu cầu</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>

                                                <th>Ngày</th>
                                                <th>Lý do</th>
                                                <th>Trạng thái</th>
                                                <th>Người duyệt</th>
                                                <th>Ngày tạo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($requests as $request): ?>
                                                <tr>

                                                    <td><?= date('d/m/Y', strtotime($request['start_date'])) ?></td>

                                                    <td><?= htmlspecialchars($request['reason']) ?></td>
                                                    <td><span
                                                            class="badge bg-<?= getStatusClass($request['is_absence_authorized']) ?>"><?= getStatusText($request['is_absence_authorized']) ?></span>
                                                    </td>
                                                    <td><?= $request['reviewer_name'] ?? '-' ?></td>
                                                    <td><?= date('d/m/Y', strtotime($request['created_at'])) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <h3 class="section-title mt-4">Lịch sử hiệu suất và lương</h3>

        <?php if (count($performances) > 0): ?>
            <div class="row">
                <?php foreach ($performances as $perf): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 performance-card">
                            <div class="card-header bg-<?= $perf['salary_calculated'] ? 'success' : 'secondary' ?> text-white">
                                <h5 class="m-0"><?= formatMonth($perf['month'], $perf['year']) ?></h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">KPI mục tiêu:</span>
                                    <span class="fw-bold"><?= number_format($perf['individual_kpi_target'], 1) ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">KPI đạt được:</span>
                                    <span
                                        class="fw-bold text-<?= $perf['kpi_achieved'] >= $perf['individual_kpi_target'] ? 'success' : 'danger' ?>">
                                        <?= number_format($perf['kpi_achieved'], 1) ?>
                                        (<?= number_format($perf['kpi_achieved'] / $perf['individual_kpi_target'] * 100, 1) ?>%)
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Lương cơ bản:</span>
                                    <span class="fw-bold"><?= formatCurrency($perf['individual_base_salary']) ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Nghỉ phép:</span>
                                    <span class="fw-bold"><?= $perf['authorized_absences'] ?> ngày</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Nghỉ không phép:</span>
                                    <span class="fw-bold"><?= $perf['unauthorized_absences'] ?> ngày</span>
                                </div>
                                <?php if ($perf['salary_calculated']): ?>
                                    <div class="d-flex justify-content-between mt-3 pt-2 border-top">
                                        <span class="text-muted">Tổng lương:</span>
                                        <span class="fw-bold text-success"><?= formatCurrency($perf['final_salary']) ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning mt-3 mb-0 py-2 text-center">
                                        Chưa tính lương
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <p class="mb-0">Chưa có dữ liệu hiệu suất</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>