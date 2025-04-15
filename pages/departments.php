<?php
require_once dirname(__DIR__) . '/config/db.php';


$currentUser = checkPermission('hr_manager');

if (isset($_POST['add_department'])) {
    $department_code = $_POST['department_code'];
    $department_name = $_POST['department_name'];
    $max_employees = 9999;
    $default_kpi = $_POST['default_kpi'];
    $default_salary = $_POST['default_salary'];
    $kpi_unit = $_POST['kpi_unit'];
    $kpi_name =  $_POST['kpi_name'];
    
    try {
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE department_code = ?");
        $checkStmt->execute([$department_code]);
        
        if ($checkStmt->fetchColumn() > 0) {
            $error = "Mã phòng ban đã tồn tại. Vui lòng chọn mã khác.";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO departments 
                (department_code, department_name, max_employees, default_kpi, default_salary, kpi_name, kpi_unit) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $department_code, 
                $department_name, 
                $max_employees, 
                $default_kpi, 
                $default_salary,
                $kpi_name,
                $kpi_unit
            ]);
            
            $success = "Thêm phòng ban thành công!";
            
            $currentMonth = date('n');
            $currentYear = date('Y');
            
            $monthlySettingStmt = $pdo->prepare("
                INSERT INTO monthly_department_settings 
                (department_id, month, year, kpi_target, base_salary, daily_meal_allowance, unauthorized_absence_penalty) 
                VALUES (?, ?, ?, ?, ?, 0, 0)
            ");
            
            $departmentId = $pdo->lastInsertId();
            $monthlySettingStmt->execute([
                $departmentId,
                $currentMonth,
                $currentYear,
                $default_kpi,
                $default_salary
            ]);
        }
    } catch (PDOException $e) {
        $error = "Lỗi khi thêm phòng ban: " . $e->getMessage();
    }
}

if (isset($_POST['update_department'])) {
    $department_id = $_POST['department_id'];
    $department_name = $_POST['department_name'];
    $max_employees = $_POST['max_employees'];
    $default_kpi = $_POST['default_kpi'];
    $default_salary = $_POST['default_salary'];
    $kpi_name =  $_POST['kpi_name'];
    $kpi_unit = $_POST['kpi_unit'];
    
    try {
    
        $checkEmployeeCountStmt = $pdo->prepare("
            SELECT COUNT(*) as employee_count 
            FROM employees 
            WHERE department_id = ? AND is_locked = 0
        ");
        $checkEmployeeCountStmt->execute([$department_id]);
        $currentEmployeeCount = $checkEmployeeCountStmt->fetchColumn();

        
        if ($max_employees < $currentEmployeeCount) {
            $error = "Không thể giảm số lượng nhân viên tối đa xuống " . $max_employees . " vì hiện tại đã có " . $currentEmployeeCount . " nhân viên trong phòng ban này!";
        } else {
            $stmt = $pdo->prepare("
                UPDATE departments 
                SET department_name = ?, 
                    max_employees = ?, 
                    default_kpi = ?, 
                    default_salary = ?,
                    kpi_name = ?,
                    kpi_unit = ? 
                WHERE department_id = ?
            ");
            
            $stmt->execute([
                $department_name, 
                $max_employees, 
                $default_kpi, 
                $default_salary, 
                $kpi_name,
                $kpi_unit,
                $department_id
            ]);
            
            $success = "Cập nhật phòng ban thành công!";
        }
    } catch (PDOException $e) {
        $error = "Lỗi khi cập nhật phòng ban: " . $e->getMessage();
    }
}

if (isset($_POST['delete_department'])) {
    $department_id = $_POST['department_id'];
    
    try {
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE department_id = ? and is_locked = 0");
        $checkStmt->execute([$department_id]);
        
        if ($checkStmt->fetchColumn() > 0) {
            $error = "Không thể xóa phòng ban vì còn nhân viên thuộc phòng ban này!";
        } else {
            $deleteSettingsStmt = $pdo->prepare("
                DELETE FROM monthly_department_settings WHERE department_id = ?
            ");
            $deleteSettingsStmt->execute([$department_id]);
            
            $deleteSchedulesStmt = $pdo->prepare("
                DELETE FROM department_work_schedules WHERE department_id = ?
            ");
            $deleteSchedulesStmt->execute([$department_id]);
            
            $updateStmt = $pdo->prepare("UPDATE departments SET is_active = 0 WHERE department_id = ?");
            $updateStmt->execute([$department_id]);
            
            $success = "Xóa phòng ban thành công!";
        }
    } catch (PDOException $e) {
        $error = "Lỗi khi xóa phòng ban: " . $e->getMessage();
    }
}

try {
    $stmt = $pdo->query("
        SELECT d.*, 
               (SELECT COUNT(*) FROM employees e WHERE e.department_id = d.department_id and e.is_locked = 0) as employee_count,
               (SELECT COUNT(*) FROM monthly_department_settings mds WHERE mds.department_id = d.department_id) as settings_count
        FROM departments d
        where d.is_active = 1
        ORDER BY d.department_name
    ");
    $departments = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Lỗi khi lấy danh sách phòng ban: " . $e->getMessage();
    $departments = [];
}

$departmentToEdit = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM departments WHERE department_id = ?");
        $stmt->execute([$_GET['edit']]);
        $departmentToEdit = $stmt->fetch();
    } catch (PDOException $e) {
        $error = "Lỗi khi lấy thông tin phòng ban: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý phòng ban</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .department-card {
            transition: all 0.3s;
        }
        .department-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .card-header-custom {
            background-color: #f8f9fa;
            border-bottom: 1px solid #eee;
            padding: 0.75rem;
        }
        .stats-box {
            background-color: #f8f9fa;
            border-radius: 0.25rem;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
<?php include dirname(__DIR__) . '/partials/header.php'; ?>


 
    
    
    <div class="container mt-4">
        <h1 class="mb-4">
            <i class="fas fa-building me-2"></i>
            Quản lý phòng ban
        </h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
        <?php 
if ($currentUser['role_name'] === 'Admin'): ?>
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <?= $departmentToEdit ? 'Cập nhật phòng ban' : 'Thêm phòng ban mới' ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <?php if ($departmentToEdit): ?>
                        <input type="hidden" name="department_id" value="<?= $departmentToEdit['department_id'] ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="department_code" class="form-label">Mã phòng ban</label>
                        <input type="text" class="form-control" id="department_code" name="department_code" 
                               value="<?= $departmentToEdit ? $departmentToEdit['department_code'] : '' ?>" 
                               <?= $departmentToEdit ? 'readonly' : 'required' ?>>
                        <div class="form-text">Ví dụ: IT-DEV, HR-DEP, MKT-DEP</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="department_name" class="form-label">Tên phòng ban</label>
                        <input type="text" class="form-control" id="department_name" name="department_name" 
                               value="<?= $departmentToEdit ? $departmentToEdit['department_name'] : '' ?>" required>
                    </div>
                    
                
                    
                    <div class="mb-3">
                        <label for="default_kpi" class="form-label">KPI mặc định (Theo tháng)</label>
                        <input type="number" step="1" class="form-control" id="default_kpi" name="default_kpi" 
                               value="<?= $departmentToEdit ? $departmentToEdit['default_kpi'] : '100' ?>" required min="0">
                    </div>
                    
                    <div class="mb-3">
                        <label for="default_salary" class="form-label">Lương cơ bản mặc định (Theo tháng)</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="default_salary" name="default_salary" 
                                   value="<?= $departmentToEdit ? $departmentToEdit['default_salary'] : '10000000' ?>" required min="0">
                            <span class="input-group-text">VNĐ</span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="kpi_name" class="form-label">Tên KPI</label>
                        <input type="text" class="form-control" id="kpi_name" name="kpi_name" 
                        value="<?= $departmentToEdit ? $departmentToEdit['kpi_name'] : '' ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="kpi_unit" class="form-label">Đơn vị KPI</label>
                        <input type="text" class="form-control" id="kpi_unit" name="kpi_unit" 
                        value="<?= $departmentToEdit ? $departmentToEdit['kpi_unit'] : '' ?>" required>
                    </div>
                    
                    <?php if ($departmentToEdit): ?>
                        <button type="submit" name="update_department" class="btn btn-success">
                            <i class="fas fa-save me-1"></i> Cập nhật phòng ban
                        </button>
                        <a href="departments.php" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i> Hủy
                        </a>
                    <?php else: ?>
                        <button type="submit" name="add_department" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i> Thêm phòng ban
                        </button>
                    <?php endif; ?>
                </form>

            </div>
        </div>
    </div>
<?php endif; ?>

            
            
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Danh sách phòng ban</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($departments)): ?>
                            <div class="alert alert-info">
                                Chưa có phòng ban nào. Hãy thêm phòng ban mới!
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Mã</th>
                                            <th>Tên phòng ban</th>
                                            <th>Nhân viên</th>
                                            <th>KPI mặc định</th>
                                            <th>Lương mặc định</th>
                                            <?php if ($currentUser['role_name'] === 'Admin'): ?>
                                            <th>Thao tác</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($departments as $department): ?>
                                            <tr>
                                                <td><span class="badge bg-secondary"><?= htmlspecialchars($department['department_code']) ?></span></td>
                                                <td><?= htmlspecialchars($department['department_name']) ?></td>
                                                <td>
                                                    <span class="badge <?= $department['employee_count'] >= $department['max_employees'] ? 'bg-danger' : 'bg-success' ?>">
                                                        <?= $department['employee_count']?>
                                                    </span>
                                                </td>
                                                <td><?= number_format($department['default_kpi']) ?></td>
                                                <td><?= number_format($department['default_salary'], 0, ',', '.') ?> VNĐ</td>
                                                <?php if ($currentUser['role_name'] === 'Admin'): ?>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="departments.php?edit=<?= $department['department_id'] ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#deleteModal<?= $department['department_id'] ?>">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- Modal xác nhận xóa -->
                                                    <div class="modal fade" id="deleteModal<?= $department['department_id'] ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header bg-danger text-white">
                                                                    <h5 class="modal-title">Xác nhận xóa</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <p>Bạn có chắc chắn muốn xóa phòng ban <strong><?= htmlspecialchars($department['department_name']) ?></strong>?</p>
                                                                    
                                                                    <?php if ($department['employee_count'] > 0): ?>
                                                                        <div class="alert alert-warning">
                                                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                                                            Phòng ban này đang có <strong><?= $department['employee_count'] ?> nhân viên</strong>. 
                                                                            Bạn cần chuyển các nhân viên này sang phòng ban khác trước khi xóa.
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                                                    
                                                                    <?php if ($department['employee_count'] == 0): ?>
                                                                        <form method="post" action="">
                                                                            <input type="hidden" name="department_id" value="<?= $department['department_id'] ?>">
                                                                            <button type="submit" name="delete_department" class="btn btn-danger">
                                                                                <i class="fas fa-trash me-1"></i> Xóa
                                                                            </button>
                                                                        </form>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="card text-white bg-primary mb-3 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">Tổng số phòng ban</h5>
                                <p class="card-text display-4"><?= count($departments) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card text-white bg-success mb-3 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">Tổng nhân viên</h5>
                                <p class="card-text display-4">
                                    <?php
                                    $totalEmployees = 0;
                                    foreach ($departments as $department) {
                                        $totalEmployees += $department['employee_count'];
                                    }
                                    echo $totalEmployees;
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
            
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
 

</body>
</html>