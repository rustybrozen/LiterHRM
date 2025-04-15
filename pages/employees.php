<?php
require_once dirname(__DIR__) . '/config/db.php';

$currentUser = checkPermission('hr_manager');

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';
$error = '';

function getDepartments($currentUser) {
    global $pdo;

    if ($currentUser['role_name'] === "Admin") {
        $stmt = $pdo->query("SELECT * FROM departments where is_active = 1 ORDER BY department_name");
    } else {
        $stmt = $pdo->query("SELECT * FROM departments where is_active = 1 and department_id != 3 ORDER BY department_name");
    }
    
    return $stmt->fetchAll();
}



function getEmployee($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE employee_id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

if ($action == 'add' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
     
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $birthDate = $_POST['birth_date'];
        $address = trim($_POST['address']);
        $phoneNumber = trim($_POST['phone_number']);
        $idCardNumber = trim($_POST['id_card_number']);
        $gender = $_POST['gender'];
        $departmentId = $_POST['department_id'];
      
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE id_card_number = ?");
        $stmt->execute([$idCardNumber]);
        if ($stmt->fetchColumn() > 0) {
            $error = "Số CCCD đã tồn tại trong hệ thống";
        } else {
          
            $checkCapacityStmt = $pdo->prepare("
                SELECT d.max_employees, COUNT(e.employee_id) as current_count
                FROM departments d
                LEFT JOIN employees e ON d.department_id = e.department_id AND e.is_locked = 0
                WHERE d.department_id = ?
                GROUP BY d.department_id
            ");
            $checkCapacityStmt->execute([$departmentId]);
            $capacityInfo = $checkCapacityStmt->fetch();
            
            if ($capacityInfo && $capacityInfo['current_count'] >= $capacityInfo['max_employees']) {
                $error = "Phòng ban đã đạt số lượng nhân viên tối đa (" . $capacityInfo['max_employees'] . "). Không thể thêm nhân viên mới.";
            } else {
              
                $pdo->beginTransaction();
       
                $userId = null;
                
            
                if (isset($_POST['create_account']) && $_POST['create_account'] == 1) {
                 
                    $username = trim($_POST['username']);
                    $email = trim($_POST['email']);
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $roleId = $_POST['role_id'];
                    
                
                    $checkUsername = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                    $checkUsername->execute([$username]);
                    if ($checkUsername->fetchColumn() > 0) {
                        $pdo->rollBack();
                        $error = "Tên đăng nhập đã tồn tại trong hệ thống";
                     
                        throw new Exception($error);
                    }
              
                    $checkEmail = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                    $checkEmail->execute([$email]);
                    if ($checkEmail->fetchColumn() > 0) {
                        $pdo->rollBack();
                        $error = "Email đã tồn tại trong hệ thống";
                     
                        throw new Exception($error);
                    }
                    
                   
                    $insertUser = $pdo->prepare("INSERT INTO users (username, email, password, role_id) VALUES (?, ?, ?, ?)");
                    $insertUser->execute([$username, $email, $password, $roleId]);
                    
       
                    $userId = $pdo->lastInsertId();
                }
                
             
                if ($userId) {
                  
                    $insertEmployee = $pdo->prepare("INSERT INTO employees (user_id, first_name, last_name, birth_date, address, phone_number, id_card_number, gender, department_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $insertEmployee->execute([$userId, $firstName, $lastName, $birthDate, $address, $phoneNumber, $idCardNumber, $gender, $departmentId]);
                } else {
                   
                    $insertEmployee = $pdo->prepare("INSERT INTO employees (first_name, last_name, birth_date, address, phone_number, id_card_number, gender, department_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $insertEmployee->execute([$firstName, $lastName, $birthDate, $address, $phoneNumber, $idCardNumber, $gender, $departmentId]);
                }
                
        
                $pdo->commit();
                
                $message = "Thêm nhân viên thành công!";
                if ($userId) {
                    $message .= " Đã tạo tài khoản cho nhân viên.";
                }
                
                header("Location: employees.php?message=" . urlencode($message));
                exit;
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Lỗi: " . $e->getMessage();
    }
}

if ($action == 'edit' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $employeeId = $_POST['employee_id'];
        $firstName = $_POST['first_name'];
        $lastName = $_POST['last_name'];
        $birthYear = $_POST['birth_date'];
        $address = $_POST['address'];
        $phoneNumber = $_POST['phone_number'];
        $idCardNumber = $_POST['id_card_number'];
        $gender = $_POST['gender'];
        $departmentId = $_POST['department_id'];
        $currentMonthYear = date('Y-m-d H:i:s');
     
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE id_card_number = ? AND employee_id <> ?");
        $stmt->execute([$idCardNumber, $employeeId]);
        if ($stmt->fetchColumn() > 0) {
            $error = "Số CCCD đã tồn tại trong hệ thống";
        } else {
           
            $stmt = $pdo->prepare("SELECT department_id FROM employees WHERE employee_id = ?");
            $stmt->execute([$employeeId]);
            $currentDeptId = $stmt->fetchColumn();
            
            $canUpdate = true;
            
            
            if ($currentDeptId != $departmentId) {
                $checkCapacityStmt = $pdo->prepare("
                    SELECT d.max_employees, COUNT(e.employee_id) as current_count
                    FROM departments d
                    LEFT JOIN employees e ON d.department_id = e.department_id AND e.is_locked = 0
                    WHERE d.department_id = ?
                    GROUP BY d.department_id
                ");
                $checkCapacityStmt->execute([$departmentId]);
                $capacityInfo = $checkCapacityStmt->fetch();
                
                if ($capacityInfo && $capacityInfo['current_count'] >= $capacityInfo['max_employees']) {
                    $error = "Phòng ban mới đã đạt số lượng nhân viên tối đa (" . $capacityInfo['max_employees'] . "). Không thể chuyển nhân viên đến phòng ban này.";
                    $canUpdate = false;
                } else {
                   
                    $currentMonth = date('m');
                    $currentYear = date('Y');
                    
                    try {
                       
                        $pdo->beginTransaction();
                        
                     
                        $stmt1 = $pdo->prepare("DELETE FROM employee_bonuses_penalties 
                                              WHERE employee_id = ? 
                                              AND MONTH(date) = ? 
                                              AND YEAR(date) = ?");
                        $stmt1->execute([$employeeId, $currentMonth, $currentYear]);
                        
   
                        $stmt2 = $pdo->prepare("DELETE FROM employee_monthly_performance 
                                              WHERE employee_id = ? 
                                              AND month = ? 
                                              AND year = ?");
                        $stmt2->execute([$employeeId, $currentMonth, $currentYear]);
                     
                        $stmt3 = $pdo->prepare("DELETE FROM employee_requests 
                                              WHERE employee_id = ? 
                                              AND (MONTH(start_date) = ? AND YEAR(start_date) = ?)");
                        $stmt3->execute([$employeeId, $currentMonth, $currentYear]);
                        
                      
                        $stmt4 = $pdo->prepare("DELETE FROM employee_work_schedules 
                                              WHERE employee_id = ? 
                                              AND MONTH(effective_date) = ? 
                                              AND YEAR(effective_date) = ?");
                        $stmt4->execute([$employeeId, $currentMonth, $currentYear]);
                        
                       
                        $pdo->commit();
                        
                    } catch (Exception $e) {
                     
                        $pdo->rollBack();
                        $error = "Lỗi khi xóa dữ liệu: " . $e->getMessage();
                        $canUpdate = false;
                    }
                }
            }
            
            if ($canUpdate) {
               
                $stmt = $pdo->prepare("UPDATE employees SET 
                    first_name = ?, 
                    last_name = ?, 
                    birth_date = ?, 
                    address = ?, 
                    phone_number = ?, 
                    id_card_number = ?, 
                    gender = ?, 
                    department_id = ? 
                    WHERE employee_id = ?");
                $stmt->execute([$firstName, $lastName, $birthYear, $address, $phoneNumber, $idCardNumber, $gender, $departmentId, $employeeId]);
                
                $message = "Cập nhật thông tin nhân viên thành công!";
            
                header("Location: employees.php?message=" . urlencode($message));
                exit;
            }
        }
    } catch (PDOException $e) {
        $error = "Lỗi: " . $e->getMessage();
    }
}

if ($action == 'toggle_lock' && isset($_GET['id'])) {
    try {
        $employeeId = $_GET['id'];
        $employee = getEmployee($employeeId);
        
        if (!$employee) {
            $error = "Không tìm thấy nhân viên!";
        } else {
            $newStatus = $employee['is_locked'] ? 0 : 1;
            
          
            if ($newStatus == 0) {
                $checkCapacityStmt = $pdo->prepare("
                    SELECT d.max_employees, COUNT(e.employee_id) as current_count
                    FROM departments d
                    LEFT JOIN employees e ON d.department_id = e.department_id AND e.is_locked = 0
                    WHERE d.department_id = ?
                    GROUP BY d.department_id
                ");
                $checkCapacityStmt->execute([$employee['department_id']]);
                $capacityInfo = $checkCapacityStmt->fetch();
                
                if ($capacityInfo && $capacityInfo['current_count'] >= $capacityInfo['max_employees']) {
                    $message = "Không thể Khôi phục nhân viên này. Phòng ban đã đạt số lượng nhân viên tối đa (" . $capacityInfo['max_employees'] . ").";
                    header("Location: employees.php?message=" . urlencode($message));
                    exit;
                }
            }
            
            $stmt = $pdo->prepare("UPDATE employees SET is_locked = ? WHERE employee_id = ?");
            $stmt->execute([$newStatus, $employeeId]);
            
            if ($employee['user_id']) {
                $stmt = $pdo->prepare("UPDATE users SET is_locked = ? WHERE user_id = ?");
                $stmt->execute([$newStatus, $employee['user_id']]);
            }
            
            $statusText = $newStatus ? "Xóa" : "Khôi phục";
            $message = "Đã $statusText nhân viên thành công!";
            
            header("Location: employees.php?message=" . urlencode($message));
            exit;
        }
    } catch (PDOException $e) {
        $error = "Lỗi: " . $e->getMessage();
    }
}
function getEmployees($currentUser,$departmentId = null, $search = null, $lockStatus = null) {
    global $pdo;
    if ($currentUser['role_name'] === "Admin"){    $sql = "SELECT e.*, d.department_name 
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.department_id
            WHERE 1=1";}
else{
    $sql = "SELECT e.*, d.department_name 
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE 1=1
    and e.department_id != 3";
}
    

    
    $params = [];
    
    if ($departmentId) {
        $sql .= " AND e.department_id = ?";
        $params[] = $departmentId;
    }
    
    if ($search) {
        $sql .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.id_card_number LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
  
    if ($lockStatus !== null) {
        $sql .= " AND e.is_locked = ?";
        $params[] = $lockStatus;
    }
    
    $sql .= " ORDER BY e.last_name, e.first_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}


$departmentFilter = isset($_GET['department_id']) ? $_GET['department_id'] : null;
$searchTerm = isset($_GET['search']) ? $_GET['search'] : null;
$lockStatus = isset($_GET['status']) 
    ? ($_GET['status'] === 'all' ? null 
        : ($_GET['status'] === 'locked' ? 1 
            : ($_GET['status'] === 'active' ? 0 : 0))) 
    : 0;

$employees = getEmployees($currentUser,$departmentFilter, $searchTerm, $lockStatus);

$departments = getDepartments($currentUser);

if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

$employeeToEdit = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $employeeToEdit = getEmployee($_GET['id']);
    if (!$employeeToEdit) {
        $error = "Không tìm thấy nhân viên!";
        $action = 'list';
    }
}

if ($currentUser['role_name'] == 'Admin') {
    $stmt = $pdo->query("SELECT * FROM roles where role_id !=11 ORDER BY role_id");
} else {
    $stmt = $pdo->query("SELECT * FROM roles where role_id !=11 and role_id !=12 ORDER BY role_id");
}

$roles = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý nhân viên</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .table-responsive {
            overflow-x: auto;
        }
        .actions-column {
            min-width: 120px;
        }
    </style>
</head>
<body>
<?php include dirname(__DIR__) . '/partials/header.php'; ?>

    
    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h1>Quản lý nhân viên</h1>
            </div>
            <div class="col-auto">
                <a href="employees.php?action=add" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Thêm nhân viên
                </a>
            </div>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($action == 'list'): ?>
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="employees.php" class="row g-3">
            <div class="col-md-3">
                <label for="department_id" class="form-label">Phòng ban</label>
                <select name="department_id" id="department_id" class="form-select">
                    <option value="">Tất cả phòng ban</option>
                    <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo $dept['department_id']; ?>" <?php echo ($departmentFilter == $dept['department_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($dept['department_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Trạng thái</label>
                <select name="status" id="status" class="form-select">
                    <option value="all">Tất cả trạng thái</option>
                    <option value="active" <?php echo (!isset($_GET['status']) || $_GET['status'] == 'active') ? 'selected' : ''; ?>>
                        Đang hoạt động
                    </option>
                    <option value="locked" <?php echo (isset($_GET['status']) && $_GET['status'] == 'locked') ? 'selected' : ''; ?>>
                        Đã nghỉ việc
                    </option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="search" class="form-label">Tìm kiếm</label>
                <input type="text" name="search" id="search" class="form-control" 
                    placeholder="Tìm theo tên hoặc CCCD" 
                    value="<?php echo htmlspecialchars($searchTerm ?? ''); ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-secondary w-100">
                    <i class="fas fa-search"></i> Lọc
                </button>
            </div>
        </form>
    </div>
</div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Danh sách nhân viên (<?php echo count($employees); ?> nhân viên)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                        <thead>
    <tr>
        <th>ID</th>
        <th>Họ và tên</th>
        <th>Năm sinh</th>
        <th>Giới tính</th>
        <th>SĐT</th>
        <th>CCCD</th>
        <th>Phòng ban</th>
        <th>Trạng thái</th>
        <th class="actions-column">Thao tác</th>
    </tr>
</thead>
                            <tbody>
    <?php if (count($employees) > 0): ?>
        <?php foreach ($employees as $employee): ?>
        <tr <?php echo $employee['is_locked'] ? 'class="table-secondary"' : ''; ?>>
            <td><?php echo $employee['employee_id']; ?></td>
            <td><?php echo htmlspecialchars($employee['last_name'] . ' ' . $employee['first_name']); ?></td>
            <td><?= date('d-m-Y', strtotime($employee['birth_date'])) ?></td>
            <td>
                <?php 
                switch($employee['gender']) {
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
            </td>
            <td><?php echo htmlspecialchars($employee['phone_number']); ?></td>
            <td><?php echo htmlspecialchars($employee['id_card_number']); ?></td>
            <td><?php echo htmlspecialchars($employee['department_name'] ?? 'Chưa phân công'); ?></td>
            <td>
                <span class="badge <?php echo $employee['is_locked'] ? 'bg-danger' : 'bg-success'; ?>">
                    <?php echo $employee['is_locked'] ? 'Đã nghỉ việc' : 'Đang hoạt động'; ?>
                </span>
            </td>
            <td>
                <div class="btn-group btn-group-sm">
                    <a href="employees.php?action=edit&id=<?php echo $employee['employee_id']; ?>" 
                        class="btn btn-primary" title="Sửa">
                        <i class="fas fa-edit"></i>
                    </a>
                    <a href="#" class="btn <?php echo $employee['is_locked'] ? 'btn-success' : 'btn-danger'; ?>" 
                        data-bs-toggle="modal" 
                        data-bs-target="#lockModal<?php echo $employee['employee_id']; ?>"
                        title="<?php echo $employee['is_locked'] ? 'Khôi phục' : 'Xóa'; ?>">
                        <i class="fas <?php echo $employee['is_locked'] ? 'fa-unlock' : 'fa-trash'; ?>"></i>
                    </a>
                </div>
                
                <div class="modal fade" id="lockModal<?php echo $employee['employee_id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Xác nhận <?php echo $employee['is_locked'] ? 'Khôi phục' : 'Xóa'; ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                Bạn có chắc chắn muốn <?php echo $employee['is_locked'] ? 'Khôi phục' : 'Xóa'; ?> nhân viên <strong><?php echo htmlspecialchars($employee['last_name'] . ' ' . $employee['first_name']); ?></strong>?
                                <?php if ($employee['user_id']): ?>
                                <p class="mt-2 text-info">
                                    <i class="fas fa-info-circle"></i> 
                                    Tài khoản người dùng liên kết cũng sẽ được <?php echo $employee['is_locked'] ? 'Khôi phục' : 'xóa'; ?>.
                                </p>
                                <?php endif; ?>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                <a href="employees.php?action=toggle_lock&id=<?php echo $employee['employee_id']; ?>" 
                                   class="btn <?php echo $employee['is_locked'] ? 'btn-success' : 'btn-danger'; ?>">
                                    <?php echo $employee['is_locked'] ? 'Khôi phục' : 'Xóa'; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="9" class="text-center">Không có nhân viên nào</td>
        </tr>
    <?php endif; ?>
</tbody>
                        </table>
                    </div>
                </div>
            </div>
        
            <?php elseif ($action == 'add' || $action == 'edit'): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <?php echo ($action == 'add') ? 'Thêm nhân viên mới' : 'Cập nhật thông tin nhân viên'; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="employees.php?action=<?php echo $action; ?><?php echo ($action == 'edit' && $employeeToEdit) ? '&id=' . $employeeToEdit['employee_id'] : ''; ?>">
                        
                        <?php if ($action == 'edit' && $employeeToEdit): ?>
                            <input type="hidden" name="employee_id" value="<?php echo $employeeToEdit['employee_id']; ?>">
                        <?php endif; ?>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">Tên</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required
                                    value="<?php echo htmlspecialchars($employeeToEdit['first_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Họ</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required
                                    value="<?php echo htmlspecialchars($employeeToEdit['last_name'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="birth_date" class="form-label">Năm sinh</label>
                          
                                <input type="date" class="form-control" id="birth_date" name="birth_date" required
                                    min="1950-01-01" max="<?php echo date('Y') - 18; ?>-12-31"
                                    value="<?php echo htmlspecialchars($employeeToEdit['birth_date'] ?? date('Y') - 25 . '-01-01'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="gender" class="form-label">Giới tính</label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="male" <?php echo (isset($employeeToEdit) && $employeeToEdit['gender'] == 'male') ? 'selected' : ''; ?>>Nam</option>
                                    <option value="female" <?php echo (isset($employeeToEdit) && $employeeToEdit['gender'] == 'female') ? 'selected' : ''; ?>>Nữ</option>
                                    <option value="other" <?php echo (isset($employeeToEdit) && $employeeToEdit['gender'] == 'other') ? 'selected' : ''; ?>>Khác</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="department_id" class="form-label">Phòng ban</label>
                                <select class="form-select" id="department_id" name="department_id">
                                    <option value="">-- Chọn phòng ban --</option>
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department_id']; ?>" 
                                        <?php echo (isset($employeeToEdit) && $employeeToEdit['department_id'] == $dept['department_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="phone_number" class="form-label">Số điện thoại</label>
                                <input type="text" class="form-control" id="phone_number" name="phone_number"
                                    value="<?php echo htmlspecialchars($employeeToEdit['phone_number'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="id_card_number" class="form-label">Số CCCD</label>
                                <input type="text" class="form-control" id="id_card_number" name="id_card_number" required
                                    value="<?php echo htmlspecialchars($employeeToEdit['id_card_number'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Địa chỉ</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($employeeToEdit['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <?php if ($action == 'add'): ?>
                       
                        <div class="card mt-4 mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Thông tin tài khoản</h5>
                            </div>
                            <div class="card-body">
                             
                                <input type="hidden" name="create_account" value="1">
                                
                                <div class="mb-3">
                                    <label for="username" class="form-label required-field">Tên đăng nhập</label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label required-field">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label required-field">Mật khẩu</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="role_id" class="form-label required-field">Quyền</label>
                                    <select class="form-select" id="role_id" name="role_id" required>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?php echo $role['role_id']; ?>">    
                                                <?php echo htmlspecialchars($role['role_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <a href="employees.php" class="btn btn-secondary">Hủy</a>
                            <button type="submit" class="btn btn-primary">
                                <?php echo ($action == 'add') ? 'Thêm nhân viên' : 'Cập nhật'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>