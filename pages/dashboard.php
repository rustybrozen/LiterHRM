<?php

include dirname(__DIR__) . '/partials/header.php';

$user = checkPermission('hr_manager');


$currentMonth = date('n');
$currentYear = date('Y');


function getEmployeeCount($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees WHERE is_locked = 0");
    return $stmt->fetch()['count'];
}


function getDepartmentCount($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM departments WHERE is_active = 1");
    return $stmt->fetch()['count'];
}


function getAdminCount($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role_id = 3 AND is_locked = 0");
    return $stmt->fetch()['count'];
}


function getHRManagerCount($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role_id = 2 AND is_locked = 0");
    return $stmt->fetch()['count'];
}


function getLockedAccountsCount($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_locked = 1");
    return $stmt->fetch()['count'];
}


function getPendingRequestsCount($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employee_requests WHERE status = 'pending'");
    return $stmt->fetch()['count'];
}


function getDepartmentPerformance($pdo, $month, $year) {
    $stmt = $pdo->prepare("
        SELECT 
            d.department_id,
            d.department_name,
            d.department_code,
            d.max_employees,
            COUNT(DISTINCT e.employee_id) as current_employees,
            IFNULL(SUM(emp.kpi_achieved), 0) as total_kpi_achieved,
            IFNULL(SUM(emp.individual_kpi_target), 0) as total_kpi_target
        FROM 
            departments d
        LEFT JOIN 
            employees e ON d.department_id = e.department_id
        LEFT JOIN 
            employee_monthly_performance emp ON e.employee_id = emp.employee_id 
            AND emp.month = ? AND emp.year = ?
        WHERE 
            d.is_active = 1
        GROUP BY 
            d.department_id
        ORDER BY 
            d.department_name
    ");
    $stmt->execute([$month, $year]);
    return $stmt->fetchAll();
}


function getPendingRequests($pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            er.request_id,
            er.request_type,
            er.start_date,
            er.end_date,
            er.reason,
            CONCAT(e.first_name, ' ', e.last_name) as employee_name,
            d.department_name
        FROM 
            employee_requests er
        JOIN 
            employees e ON er.employee_id = e.employee_id
        LEFT JOIN 
            departments d ON e.department_id = d.department_id
        WHERE 
            er.status = 'pending'
        ORDER BY 
            er.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}


$employeeCount = getEmployeeCount($pdo);
$departmentCount = getDepartmentCount($pdo);
$adminCount = getAdminCount($pdo);
$hrManagerCount = getHRManagerCount($pdo);
$lockedAccountsCount = getLockedAccountsCount($pdo);
$pendingRequestsCount = getPendingRequestsCount($pdo);


$departmentPerformance = getDepartmentPerformance($pdo, $currentMonth, $currentYear);


$totalKpiAchieved = 0;
$totalKpiTarget = 0;
foreach ($departmentPerformance as $dept) {
    $totalKpiAchieved += $dept['total_kpi_achieved'];
    $totalKpiTarget += $dept['total_kpi_target'];
}
$overallKpiPercentage = ($totalKpiTarget > 0) ? ($totalKpiAchieved / $totalKpiTarget * 100) : 0;


$pendingRequests = getPendingRequests($pdo);


function getVietnameseMonth($month) {
    $months = [
        1 => 'Tháng 1', 2 => 'Tháng 2', 3 => 'Tháng 3',
        4 => 'Tháng 4', 5 => 'Tháng 5', 6 => 'Tháng 6',
        7 => 'Tháng 7', 8 => 'Tháng 8', 9 => 'Tháng 9',
        10 => 'Tháng 10', 11 => 'Tháng 11', 12 => 'Tháng 12'
    ];
    return $months[$month];
}


function getTypeRequest($type) {
    $types = [
        'leave' => 'Nghỉ việc',
        'shift_change' => 'Đổi ca',
        'absence' => 'Nghỉ phép',
        'other' => 'Khác'
    ];
    return $types[$type] ?? $type;
}
?>


<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2>Bảng Điều Khiển</h2>
            <p class="text-muted">Tổng quan hệ thống quản lý nhân sự - <?php echo getVietnameseMonth($currentMonth); ?> <?php echo $currentYear; ?></p>
        </div>
    </div>

  
    <div class="row mb-4">
        <div class="col-md-4 col-lg-2 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="text-primary">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                        <h5 class="card-title ms-3 mb-0">Nhân Viên</h5>
                    </div>
                    <h3 class="card-text"><?php echo $employeeCount; ?></h3>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 col-lg-2 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="text-success">
                            <i class="fas fa-building fa-2x"></i>
                        </div>
                        <h5 class="card-title ms-3 mb-0">Phòng Ban</h5>
                    </div>
                    <h3 class="card-text"><?php echo $departmentCount; ?></h3>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 col-lg-2 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="text-danger">
                            <i class="fas fa-user-shield fa-2x"></i>
                        </div>
                        <h5 class="card-title ms-3 mb-0">Admin</h5>
                    </div>
                    <h3 class="card-text"><?php echo $adminCount; ?></h3>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 col-lg-2 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="text-info">
                            <i class="fas fa-user-tie fa-2x"></i>
                        </div>
                        <h5 class="card-title ms-3 mb-0">Quản Lý NS</h5>
                    </div>
                    <h3 class="card-text"><?php echo $hrManagerCount; ?></h3>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 col-lg-2 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="text-warning">
                            <i class="fas fa-user-lock fa-2x"></i>
                        </div>
                        <h5 class="card-title ms-3 mb-0">TK Bị Khóa</h5>
                    </div>
                    <h3 class="card-text"><?php echo $lockedAccountsCount; ?></h3>
                </div>
            </div>
        </div>
        <?php if ($user['role_name'] == 'Admin'): ?>
        <div class="col-md-4 col-lg-2 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="text-secondary">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                        <h5 class="card-title ms-3 mb-0">Chờ Duyệt</h5>
                    </div>
                    <h3 class="card-text"><?php echo $pendingRequestsCount; ?></h3>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="row mb-4">
        <div class="col-lg-8 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0">Năng Suất Phòng Ban - <?php echo getVietnameseMonth($currentMonth); ?> <?php echo $currentYear; ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Phòng Ban</th>
                                    <th>Mã</th>
                                    <th>Nhân Viên</th>
                                    <th>KPI Đạt</th>
                                    <th>KPI Mục Tiêu</th>
                                    <th>Tiến Độ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departmentPerformance as $dept): ?>
                                    <?php 
                                        $kpiPercentage = ($dept['total_kpi_target'] > 0) ? 
                                            ($dept['total_kpi_achieved'] / $dept['total_kpi_target'] * 100) : 0;
                                        
                                        $progressColor = 'bg-danger';
                                        if ($kpiPercentage >= 90) {
                                            $progressColor = 'bg-success';
                                        } elseif ($kpiPercentage >= 70) {
                                            $progressColor = 'bg-info';
                                        } elseif ($kpiPercentage >= 50) {
                                            $progressColor = 'bg-warning';
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($dept['department_name']); ?></td>
                                        <td><?php echo htmlspecialchars($dept['department_code']); ?></td>
                                        <td><?php echo $dept['current_employees']; ?>/<?php echo $dept['max_employees']; ?></td>
                                        <td><?php echo number_format($dept['total_kpi_achieved'], 2); ?></td>
                                        <td><?php echo number_format($dept['total_kpi_target'], 2); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1" style="height: 10px;">
                                                    <div class="progress-bar <?php echo $progressColor; ?>" 
                                                         role="progressbar" 
                                                         style="width: <?php echo min(100, $kpiPercentage); ?>%;" 
                                                         aria-valuenow="<?php echo $kpiPercentage; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100"></div>
                                                </div>
                                                <span class="ms-2"><?php echo number_format($kpiPercentage, 1); ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="progress" style="height: 15px;">
                                <div class="progress-bar bg-primary" 
                                     role="progressbar" 
                                     style="width: <?php echo min(100, $overallKpiPercentage); ?>%;" 
                                     aria-valuenow="<?php echo $overallKpiPercentage; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100"></div>
                            </div>
                        </div>
                        <div class="ms-3">
                            <strong>Tổng KPI: <?php echo number_format($overallKpiPercentage, 1); ?>%</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($user['role_name'] == 'Admin'): ?>
 
        <div class="col-lg-4 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Yêu Cầu Chờ Duyệt</h5>
                    <span class="badge bg-primary rounded-pill"><?php echo $pendingRequestsCount; ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (count($pendingRequests) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($pendingRequests as $request): ?>
                                <div class="list-group-item list-group-item-action">
                                    <div class="d-flex justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($request['employee_name']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($request['department_name']); ?></small>
                                    </div>
                                    <p class="mb-1">
                                        <span class="badge <?php echo getRequestTypeBadgeClass($request['request_type']); ?>">
                                            <?php echo getTypeRequest($request['request_type']); ?>
                                        </span> Vào ngày
                                        <?php echo date('d/m/Y', strtotime($request['start_date'])); ?> 
                                    
                                    </p>
                                    <?php if (!empty($request['reason'])): ?>
                                        <small><?php echo htmlspecialchars(substr($request['reason'], 0, 50)); ?><?php echo (strlen($request['reason']) > 50) ? '...' : ''; ?></small>
                                    <?php endif; ?>
                              
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle fa-3x text-muted mb-3"></i>
                            <p>Không có yêu cầu nào đang chờ duyệt</p>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (count($pendingRequests) > 0): ?>
                    <div class="card-footer bg-white text-center">
                        <a href="requests_management.php" class="btn btn-outline-primary btn-sm">Xem tất cả yêu cầu</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php include dirname(__DIR__) . '/partials/footer.php'; ?>

<?php
function getRequestTypeBadgeClass($type) {
    switch ($type) {
        case 'leave':
            return 'bg-info';
        case 'shift_change':
            return 'bg-warning';
        case 'absence':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}



?>