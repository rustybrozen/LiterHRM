<?php
require_once dirname(__DIR__) . '/config/db.php';

$currentUser = checkPermission('hr_manager');

$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$selectedDepartment = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;

$departments = [];
try {
    $stmt = $pdo->query("SELECT department_id, department_name FROM departments where is_active = 1 ORDER BY department_name");
    $departments = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Lỗi truy vấn dữ liệu: " . $e->getMessage();
}

try {
    $query = "
        SELECT 
            d.department_name,
            e.employee_id,
            e.first_name,
            e.last_name,
            emp.individual_kpi_target,
            emp.kpi_achieved,
            ROUND((emp.kpi_achieved / emp.individual_kpi_target) * 100, 2) AS kpi_percent,
            emp.authorized_absences,
            emp.unauthorized_absences,
            emp.final_salary,
            emp.salary_calculated,
            mds.kpi_target AS department_kpi,
            mds.base_salary AS department_salary,
            mds.daily_meal_allowance,
            d.kpi_name,
        d.kpi_unit  
        FROM 
            employee_monthly_performance emp
        JOIN 
            employees e ON emp.employee_id = e.employee_id
        JOIN 
            departments d ON emp.department_id = d.department_id
        LEFT JOIN 
            monthly_department_settings mds ON 
                emp.department_id = mds.department_id AND 
                emp.month = mds.month AND 
                emp.year = mds.year
        WHERE 
            emp.month = ? AND 
            emp.year = ?
        and e.is_locked=0
    ";

    $params = [$selectedMonth, $selectedYear];

    if ($selectedDepartment > 0) {
        $query .= " AND emp.department_id = ?";
        $params[] = $selectedDepartment;
    }

    $query .= " ORDER BY d.department_name, e.last_name, e.first_name";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $performanceData = $stmt->fetchAll();

    $departmentSummary = [];
    foreach ($performanceData as $employee) {
        $deptName = $employee['department_name'];

        if (!isset($departmentSummary[$deptName])) {
            $departmentSummary[$deptName] = [
                'total_employees' => 0,
                'total_kpi_target' => 0,
                'total_kpi_achieved' => 0,
                'total_salary' => 0,
                'avg_kpi_percent' => 0,
                'department_kpi' => $employee['department_kpi'],
                'department_salary' => $employee['department_salary'],
                'kpi_name' => $employee['kpi_name'],
                'kpi_unit' => $employee['kpi_unit'],
            ];
        }

        $departmentSummary[$deptName]['total_employees']++;
        $departmentSummary[$deptName]['total_kpi_target'] += $employee['individual_kpi_target'];
        $departmentSummary[$deptName]['total_kpi_achieved'] += $employee['kpi_achieved'];
        $departmentSummary[$deptName]['total_salary'] += $employee['final_salary'];
        $departmentSummary[$deptName]['kpi_name']=$employee['kpi_name'];
        $departmentSummary[$deptName]['kpi_unit']=$employee['kpi_unit'];
    }

    foreach ($departmentSummary as $deptName => $data) {
        if ($data['total_kpi_target'] > 0) {
            $departmentSummary[$deptName]['avg_kpi_percent'] =
                round(($data['total_kpi_achieved'] / $data['total_kpi_target']) * 100, 2);
        }
    }

} catch (PDOException $e) {
    $error = "Lỗi truy vấn dữ liệu: " . $e->getMessage();
}

try {
    $stmt = $pdo->query("SELECT DISTINCT year FROM employee_monthly_performance ORDER BY year DESC");
    $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $years = [date('Y')];
}

function formatCurrency($amount)
{
    return number_format($amount, 0, ',', '.') . ' VNĐ';
}

function getKpiStatus($kpiPercent)
{
    if ($kpiPercent >= 100) {
        return 'success';
    } elseif ($kpiPercent >= 80) {
        return 'warning';
    } else {
        return 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo hiệu suất nhân viên</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .card {
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .department-header {
            background-color: #f8f9fa;
            padding: 10px 15px;
            margin-top: 20px;
            margin-bottom: 10px;
            border-radius: 5px;
            border-left: 5px solid #0d6efd;
        }

        .progress {
            height: 20px;
        }

        .table th {
            background-color: #f1f1f1;
        }

        .sticky-top {
            top: 20px;
        }
    </style>
</head>

<body>
    <?php include dirname(__DIR__) . '/partials/header.php'; ?>


    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2><i class="fas fa-chart-line"></i> Báo cáo hiệu suất nhân viên</h2>
                <p class="text-muted">Xem hiệu suất, KPI và chi tiết lương theo tháng</p>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print"></i> In báo cáo
                </button>

            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="month" class="form-label">Tháng</label>
                        <select name="month" id="month" class="form-select">
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>" <?= $selectedMonth == $i ? 'selected' : '' ?>>
                                    Tháng <?= $i ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="year" class="form-label">Năm</label>
                        <select name="year" id="year" class="form-select">
                            <?php foreach ($years as $year): ?>
                                <option value="<?= $year ?>" <?= $selectedYear == $year ? 'selected' : '' ?>>
                                    <?= $year ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="department_id" class="form-label">Phòng ban</label>
                        <select name="department_id" id="department_id" class="form-select">
                            <option value="0">Tất cả phòng ban</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['department_id'] ?>" <?= $selectedDepartment == $dept['department_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['department_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter"></i> Lọc
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?= $error ?>
            </div>
        <?php elseif (empty($performanceData)): ?>
            <div class="alert alert-info">
                Không có dữ liệu hiệu suất cho khoảng thời gian đã chọn.
            </div>
        <?php else: ?>

            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Hiệu suất trung bình theo phòng ban</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="departmentPerformanceChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Tổng quan tháng <?= $selectedMonth ?>/<?= $selectedYear ?></h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $totalEmployees = 0;
                            $totalSalary = 0;
                            $totalKpiTarget = 0;
                            $totalKpiAchieved = 0;

                            foreach ($departmentSummary as $data) {
                                $totalEmployees += $data['total_employees'];
                                $totalSalary += $data['total_salary'];
                                $totalKpiTarget += $data['total_kpi_target'];
                                $totalKpiAchieved += $data['total_kpi_achieved'];
                            }

                            $avgKpiPercent = $totalKpiTarget > 0 ? round(($totalKpiAchieved / $totalKpiTarget) * 100, 2) : 0;
                            $kpiStatus = getKpiStatus($avgKpiPercent);
                            ?>

                            <div class="mb-3">
                                <h6>Tổng số nhân viên</h6>
                                <h2><?= $totalEmployees ?> nhân viên</h2>
                            </div>

                            <div class="mb-3">
                                <h6>Tổng tiền lương</h6>
                                <h4><?= formatCurrency($totalSalary) ?></h4>
                            </div>

                            <div class="mb-3">
                                <h6>KPI trung bình đạt được</h6>
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1 me-3">
                                        <div class="progress">
                                            <div class="progress-bar bg-<?= $kpiStatus ?>" role="progressbar"
                                                style="width: <?= min($avgKpiPercent, 100) ?>%"
                                                aria-valuenow="<?= $avgKpiPercent ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?= $avgKpiPercent ?>%
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="badge bg-<?= $kpiStatus ?>">
                                            <?= $avgKpiPercent ?>%
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h6>Số phòng ban</h6>
                                <h4><?= count($departmentSummary) ?> phòng ban</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php foreach ($departmentSummary as $deptName => $summary): ?>
                <div class="department-header">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h4 class="mb-0"><?= htmlspecialchars($deptName) ?></h4>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <span class="badge bg-primary me-2">
                                <?= $summary['total_employees'] ?> nhân viên
                            </span>
                            <span class="badge bg-<?= getKpiStatus($summary['avg_kpi_percent']) ?>">
                                KPI trung bình: <?= $summary['avg_kpi_percent'] ?>%
                            </span>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h6 class="card-title">mục tiêu <?php echo $summary['kpi_name'] ?> phòng ban</h6>
                                <h3><?= $summary['department_kpi'] ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h6 class="card-title"><?php echo $summary['kpi_name'] ?> đạt được</h6>
                                <h3><?= $summary['total_kpi_achieved'] ?></h3>
                                <div class="progress mt-2">
                                    <div class="progress-bar bg-<?= getKpiStatus($summary['avg_kpi_percent']) ?>"
                                        role="progressbar" style="width: <?= min($summary['avg_kpi_percent'], 100) ?>%"
                                        aria-valuenow="<?= $summary['avg_kpi_percent'] ?>" aria-valuemin="0"
                                        aria-valuemax="100">
                                        <?= $summary['avg_kpi_percent'] ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h6 class="card-title">Lương cơ bản phòng ban</h6>
                                <h3><?= formatCurrency($summary['department_salary']) ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h6 class="card-title">Tổng lương chi trả</h6>
                                <h3><?= formatCurrency($summary['total_salary']) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th>STT</th>
                                        <th>Họ và tên</th>
                                        <th>Mục tiêu <?php echo $summary['kpi_name'] ?></th>
                                        <th><?php echo $summary['kpi_name'] ?> đạt được</th>
                                        <th>% hoàn thành</th>
                                        <th>Nghỉ phép</th>
                                        <th>Nghỉ không phép</th>
                                        <th>Lương cá nhân</th>
                                        <th>Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $count = 1;
                                    foreach ($performanceData as $employee):
                                        if ($employee['department_name'] == $deptName):
                                            $kpiStatus = getKpiStatus($employee['kpi_percent']);
                                            ?>
                                            <tr>
                                                <td><?= $count++ ?></td>
                                                <td>
                                                    <a href="employee_detail.php?id=<?= $employee['employee_id'] ?>">
                                                        <?= htmlspecialchars($employee['last_name'] . ' ' . $employee['first_name']) ?>
                                                    </a>
                                                </td>
                                                <td><?= $employee['individual_kpi_target'] ?></td>
                                                <td><?= $employee['kpi_achieved'] ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                            <div class="progress-bar bg-<?= $kpiStatus ?>" role="progressbar"
                                                                style="width: <?= min($employee['kpi_percent'], 100) ?>%"
                                                                aria-valuenow="<?= $employee['kpi_percent'] ?>" aria-valuemin="0"
                                                                aria-valuemax="100">
                                                            </div>
                                                        </div>
                                                        <span class="badge bg-<?= $kpiStatus ?>">
                                                            <?= $employee['kpi_percent'] ?>%
                                                        </span>
                                                    </div>
                                                </td>
                                                <td><?= $employee['authorized_absences'] ?></td>
                                                <td>
                                                    <?php if ($employee['unauthorized_absences'] > 0): ?>
                                                        <span class="badge bg-danger">
                                                            <?= $employee['unauthorized_absences'] ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <?= $employee['unauthorized_absences'] ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= formatCurrency($employee['final_salary']) ?></td>
                                                <td>
                                                    <?php if ($employee['salary_calculated']): ?>
                                                        <span class="badge bg-success">Đã tính lương</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark">Chưa tính lương</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php
                                        endif;
                                    endforeach;
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <?php if (!empty($departmentSummary)): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const ctx = document.getElementById('departmentPerformanceChart').getContext('2d');

                const departmentNames = <?= json_encode(array_keys($departmentSummary)) ?>;
                const kpiPercents = <?= json_encode(array_values(array_map(function ($item) {
                    return $item['avg_kpi_percent'];
                }, $departmentSummary))) ?>;

                const kpiPercentArray = Array.isArray(kpiPercents) ? kpiPercents : Object.values(kpiPercents);

                const backgroundColors = kpiPercentArray.map(percent => {
                    if (percent >= 100) return 'rgba(40, 167, 69, 0.7)';
                    if (percent >= 80) return 'rgba(255, 193, 7, 0.7)';
                    return 'rgba(220, 53, 69, 0.7)';
                });

                const borderColors = kpiPercentArray.map(percent => {
                    if (percent >= 100) return 'rgb(40, 167, 69)';
                    if (percent >= 80) return 'rgb(255, 193, 7)';
                    return 'rgb(220, 53, 69)';
                });

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: departmentNames,
                        datasets: [{
                            label: 'Tỷ lệ hoàn thành KPI (%)',
                            data: kpiPercentArray,
                            backgroundColor: backgroundColors,
                            borderColor: borderColors,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 120,
                                ticks: {
                                    callback: function (value) {
                                        return value + '%';
                                    }
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        return context.dataset.label + ': ' + context.raw + '%';
                                    }
                                }
                            }
                        }
                    }
                });
            });
        </script>
    <?php endif; ?>
</body>

</html>