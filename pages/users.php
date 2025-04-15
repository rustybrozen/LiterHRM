<?php
require_once dirname(__DIR__) . '/config/db.php';

$currentUser = checkPermission('hr_manager');

$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'users';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role_id = $_POST['role_id'];

        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $email, $password, $role_id]);

          
            if (isset($_POST['is_employee']) && $_POST['is_employee'] == 1) {
                $user_id = $pdo->lastInsertId();
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                $birth_date = $_POST['birth_date'];
                $address = trim($_POST['address']);
                $phone_number = trim($_POST['phone_number']);
                $id_card_number = trim($_POST['id_card_number']);
                $gender = $_POST['gender'];
                $department_id = $_POST['department_id'];

                $stmt = $pdo->prepare("INSERT INTO employees (user_id, first_name, last_name, birth_date, address, phone_number, id_card_number, gender, department_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $first_name, $last_name, $birth_date, $address, $phone_number, $id_card_number, $gender, $department_id]);
            }

            $success_message = "Đã thêm người dùng mới thành công!";
        } catch (PDOException $e) {
            $error_message = "Lỗi: " . $e->getMessage();
        }
    }

    if (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $user_id = $_POST['user_id'];
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $role_id = $_POST['role_id'];

        try {
    
            $sql = "UPDATE users SET username = ?, email = ?, role_id = ? WHERE user_id = ?";
            $params = [$username, $email, $role_id, $user_id];

         
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $sql = "UPDATE users SET username = ?, email = ?, password = ?, role_id = ? WHERE user_id = ?";
                $params = [$username, $email, $password, $role_id, $user_id];
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

      
            $stmt = $pdo->prepare("SELECT employee_id FROM employees WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $employee = $stmt->fetch();

            
         

            $success_message = "Đã cập nhật người dùng thành công!";
        } catch (PDOException $e) {
            $error_message = "Lỗi: " . $e->getMessage();
        }
    }




    if (isset($_POST['action']) && $_POST['action'] == 'lock') {
        $user_id = $_POST['user_id'];

        try {
          
            $pdo->beginTransaction();

     
            $stmt = $pdo->prepare("UPDATE employees SET is_locked = 1 WHERE user_id = ?");
            $stmt->execute([$user_id]);

      
            $stmt = $pdo->prepare("UPDATE users SET is_locked = 1 WHERE user_id = ?");
            $stmt->execute([$user_id]);

          
            $pdo->commit();

            $success_message = "Đã khóa tài khoản người dùng thành công!";
        } catch (PDOException $e) {
         
            $pdo->rollBack();
            $error_message = "Lỗi: " . $e->getMessage();
        }
    }

    if (isset($_POST['action']) && $_POST['action'] == 'unlock') {
        $user_id = $_POST['user_id'];
    
        try {
          
            $checkEmpStmt = $pdo->prepare("
                SELECT e.employee_id, e.department_id, d.max_employees, 
                      (SELECT COUNT(*) FROM employees WHERE department_id = e.department_id AND is_locked = 0) as current_count
                FROM employees e
                JOIN departments d ON e.department_id = d.department_id
                WHERE e.user_id = ?
            ");
            $checkEmpStmt->execute([$user_id]);
            $employeeInfo = $checkEmpStmt->fetch();
            
       
            if ($employeeInfo && $employeeInfo['current_count'] >= $employeeInfo['max_employees']) {
                $error_message = "Không thể mở khóa tài khoản này. Phòng ban đã đạt số lượng nhân viên tối đa (" . $employeeInfo['max_employees'] . ").";
            } else {
        
                $pdo->beginTransaction();
    
             
                $stmt = $pdo->prepare("UPDATE employees SET is_locked = 0 WHERE user_id = ?");
                $stmt->execute([$user_id]);
    
                $stmt = $pdo->prepare("UPDATE users SET is_locked = 0 WHERE user_id = ?");
                $stmt->execute([$user_id]);
    
       
                $pdo->commit();
    
                $success_message = "Đã mở khóa tài khoản người dùng thành công!";
            }
             
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = "Lỗi: " . $e->getMessage();
        }
    }

    if (isset($_POST['action']) && $_POST['action'] == 'create_account') {
        $employee_id = $_POST['employee_id'];
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role_id = $_POST['role_id'];

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $email, $password, $role_id]);
            $user_id = $pdo->lastInsertId();

            $stmt = $pdo->prepare("UPDATE employees SET user_id = ? WHERE employee_id = ?");
            $stmt->execute([$user_id, $employee_id]);

            $pdo->commit();

            $success_message = "Đã tạo tài khoản cho nhân viên thành công!";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = "Lỗi: " . $e->getMessage();
        }
    }

    if (isset($_POST['action']) && $_POST['action'] == 'add_employee') {
        try {
            $department_id = $_POST['department_id'];
            
        
            $checkCapacityStmt = $pdo->prepare("
                SELECT d.max_employees, COUNT(e.employee_id) as current_count
                FROM departments d
                LEFT JOIN employees e ON d.department_id = e.department_id AND e.is_locked = 0
                WHERE d.department_id = ?
                GROUP BY d.department_id
            ");
            $checkCapacityStmt->execute([$department_id]);
            $capacityInfo = $checkCapacityStmt->fetch();
            
            if ($capacityInfo && $capacityInfo['current_count'] >= $capacityInfo['max_employees']) {
                $error_message = "Phòng ban đã đạt số lượng nhân viên tối đa (" . $capacityInfo['max_employees'] . "). Không thể thêm nhân viên mới.";
            } else {
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                $birth_date = $_POST['birth_date'];
                $address = trim($_POST['address']);
                $phone_number = trim($_POST['phone_number']);
                $id_card_number = trim($_POST['id_card_number']);
                $gender = $_POST['gender'];
    
                $stmt = $pdo->prepare("INSERT INTO employees (first_name, last_name, birth_date, address, phone_number, id_card_number, gender, department_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$first_name, $last_name, $birth_date, $address, $phone_number, $id_card_number, $gender, $department_id]);
    
                $success_message = "Đã thêm nhân viên mới thành công!";
            }
        } catch (PDOException $e) {
            $error_message = "Lỗi: " . $e->getMessage();
        }
    }
}


$query = "
    SELECT u.*, r.role_name,
           e.employee_id, e.first_name, e.last_name, e.birth_date, 
           e.address, e.phone_number, e.id_card_number, e.gender, 
           e.department_id, d.department_name
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    LEFT JOIN employees e ON u.user_id = e.user_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE 1=1
    and u.role_id != 11
";

$params = [];

if (isset($_GET['department_filter']) && $_GET['department_filter'] != '') {
    $query .= " AND e.department_id = ?";
    $params[] = $_GET['department_filter'];
}

if (isset($_GET['status_filter'])) {
    if ($_GET['status_filter'] == 'active') {
        $query .= " AND (u.is_locked = 0 OR u.is_locked IS NULL)";
    } elseif ($_GET['status_filter'] == 'locked') {
        $query .= " AND u.is_locked = 1";
    } elseif ($_GET['status_filter'] == 'all') {
        $query .= " AND (u.is_locked = 0 OR u.is_locked IS NULL OR u.is_locked = 1)";
    }
} else {
    $query .= " AND (u.is_locked = 0 OR u.is_locked IS NULL)";
}

$query .= " ORDER BY u.user_id";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT e.*, d.department_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE e.user_id IS NULL
    ORDER BY e.last_name, e.first_name
");
$employeesWithoutAccount = $stmt->fetchAll();


if ($currentUser['role_name'] == 'Admin') {
    $stmt = $pdo->query("SELECT * FROM roles where role_id !=11 ORDER BY role_id");
} else {
    $stmt = $pdo->query("SELECT * FROM roles where role_id !=11 and role_id !=12 ORDER BY role_id");
}

$roles = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT d.*, 
           (SELECT COUNT(*) FROM employees e WHERE e.department_id = d.department_id AND e.is_locked = 0) as current_employees
    FROM departments d
    WHERE d.is_active = 1
    HAVING current_employees < d.max_employees
    ORDER BY d.department_name
");
$departments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý tài khoản</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        .user-actions {
            white-space: nowrap;
        }

        .required-field::after {
            content: " *";
            color: red;
        }

        .nav-tabs .nav-link {
            font-weight: 500;
        }

        .nav-tabs .nav-link.active {
            border-bottom: 3px solid #0d6efd;
        }
    </style>
</head>

<body>
<?php include dirname(__DIR__) . '/partials/header.php'; ?>



    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h2><i class="fas fa-users me-2"></i>Quản lý người dùng và nhân viên</h2>
            </div>
         
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        

        <div class="tab-content" id="myTabContent">
            <div class="tab-pane fade <?php echo $activeTab == 'users' ? 'show active' : ''; ?>" id="users-tab-pane"
                role="tabpanel" aria-labelledby="users-tab" tabindex="0">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">Danh sách người dùng</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="mb-4" id="filterForm">
                            <input type="hidden" name="tab" value="users">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="department_filter" class="form-label">Phòng ban</label>
                                    <select name="department_filter" id="department_filter" class="form-select">
                                        <option value="">Tất cả phòng ban</option>
                                        <?php foreach ($departments as $department): ?>
                                            <option value="<?php echo $department['department_id']; ?>" <?php echo (isset($_GET['department_filter']) && $_GET['department_filter'] == $department['department_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($department['department_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="status_filter" class="form-label">Trạng thái tài khoản</label>
                                    <select name="status_filter" id="status_filter" class="form-select">
                                        <option value="all">Tất cả trạng thái</option>
                                        <option value="active" <?php echo (!isset($_GET['status_filter']) || $_GET['status_filter'] == 'active') ? 'selected' : ''; ?>>Hoạt động</option>
                                        <option value="locked" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter'] == 'locked') ? 'selected' : ''; ?>>Đã khóa</option>
                                    </select>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">Lọc</button>
                                    <a href="?tab=users" class="btn btn-outline-secondary">Đặt lại</a>
                                </div>
                            </div>
                        </form>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Tên đăng nhập</th>
                                        <th>Email</th>
                                        <th>Quyền</th>
                                        <th>Thông tin nhân viên</th>
                                        <th>Phòng ban</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày tạo</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($users) > 0): ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo $user['user_id']; ?></td>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td><span
                                                        class="badge bg-<?php echo $user['role_name'] == 'Admin' ? 'danger' : ($user['role_name'] == 'Quản Lý Nhân Sự' ? 'warning' : 'success'); ?>"><?php echo htmlspecialchars($user['role_name']); ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($user['employee_id']): ?>
                                                        <?php echo htmlspecialchars($user['last_name'] . ' ' . $user['first_name']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Không phải nhân viên</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($user['department_name']): ?>
                                                        <?php echo htmlspecialchars($user['department_name']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (isset($user['is_locked']) && $user['is_locked'] == 1): ?>
                                                        <span class="badge bg-danger">Đã khóa</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Hoạt động</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>

                                                <?php if ($currentUser['role_name'] === 'Quản Lý Nhân Sự' && $user['role_name'] != "Quản Lý Nhân Sự" ||$currentUser['role_name'] === 'Admin' ): ?>
                                                  
                                                <td class="user-actions">
                                                    <button type="button" class="btn btn-sm btn-outline-primary edit-user-btn"
                                                        data-bs-toggle="modal" data-bs-target="#editUserModal"
                                                        data-user='<?php echo json_encode($user); ?>'>
                                                        <i class="fas fa-edit"></i>
                                                    </button>

                                                    <?php if (isset($user['is_locked']) && $user['is_locked'] == 1): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-success unlock-user-btn"
                                                            data-bs-toggle="modal" data-bs-target="#unlockUserModal"
                                                            data-user-id="<?php echo $user['user_id']; ?>"
                                                            data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                                            <i class="fas fa-unlock"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger delete-user-btn"
                                                            data-bs-toggle="modal" data-bs-target="#deleteUserModal"
                                                            data-user-id="<?php echo $user['user_id']; ?>"
                                                            data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                                            <i class="fas fa-lock"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">Không có người dùng nào</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

          
        </div>
    </div>
    <div class="modal fade" id="unlockUserModal" tabindex="-1" aria-labelledby="unlockUserModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="unlockUserModalLabel">Xác nhận khôi phục tài khoản</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Bạn có chắc chắn muốn khôi phục tài khoản người dùng <strong id="unlock-username"></strong>?</p>
                    <form id="unlockUserForm" method="post">
                        <input type="hidden" name="action" value="unlock">
                        <input type="hidden" name="user_id" id="unlock-user-id">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="button" class="btn btn-success" id="confirm-unlock">Xác nhận khôi phục </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Thêm người dùng mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="post">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h5>Thông tin đăng nhập</h5>
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

                            <div class="col-md-6">
                                <h5>Thông tin nhân viên</h5>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="is_employee" name="is_employee"
                                        value="1">
                                    <label class="form-check-label" for="is_employee">
                                        Đây là nhân viên
                                    </label>
                                </div>

                                <div id="employee_info" style="display: none;">
                                    <div class="row mb-3">
                                        <div class="col">
                                            <label for="first_name" class="form-label required-field">Tên</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name">
                                        </div>
                                        <div class="col">
                                            <label for="last_name" class="form-label required-field">Họ</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="birth_date" class="form-label required-field">Ngày sinh</label>
                                        <input type="date" class="form-control" id="birth_date" name="birth_date"
                                            min="1950-01-01" max="2010-12-31">
                                    </div>
                                    <div class="mb-3">
                                        <label for="gender" class="form-label required-field">Giới tính</label>
                                        <select class="form-select" id="gender" name="gender">
                                            <option value="male">Nam</option>
                                            <option value="female">Nữ</option>
                                            <option value="other">Khác</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Địa chỉ</label>
                                        <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="phone_number" class="form-label required-field">Số điện
                                            thoại</label>
                                        <input type="text" class="form-control" id="phone_number" name="phone_number">
                                    </div>
                                    <div class="mb-3">
                                        <label for="id_card_number" class="form-label required-field">Số CCCD</label>
                                        <input type="text" class="form-control" id="id_card_number"
                                            name="id_card_number">
                                    </div>
                                    <div class="mb-3">
                                        <label for="department_id" class="form-label required-field">Phòng ban</label>
                                        <select class="form-select" id="department_id" name="department_id">
                                            <?php foreach ($departments as $department): ?>
                                                <option value="<?php echo $department['department_id']; ?>">
                                                    <?php echo htmlspecialchars($department['department_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">Thêm người dùng</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-labelledby="addEmployeeModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addEmployeeModalLabel">Thêm nhân viên mới</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <form action="" method="post">
                    <input type="hidden" name="action" value="add_employee">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <label for="add_employee_first_name" class="form-label required-field">Tên</label>
                                <input type="text" class="form-control" id="add_employee_first_name" name="first_name"
                                    required>
                            </div>
                            <div class="col-6">
                                <label for="add_employee_last_name" class="form-label required-field">Họ</label>
                                <input type="text" class="form-control" id="add_employee_last_name" name="last_name"
                                    required>
                            </div>
                        </div>
                        <div class="row g-3 mt-3">
                            <div class="col-6">
                                <label for="add_employee_birth_date" class="form-label required-field">Năm sinh</label>
                                <input type="date" class="form-control" id="add_employee_birth_date" name="birth_date"
                                    min="1950-01-01" max="2010-12-31" required>
                            </div>
                            <div class="col-6">
                                <label for="add_employee_gender" class="form-label required-field">Giới tính</label>
                                <select class="form-select" id="add_employee_gender" name="gender" required>
                                    <option value="male">Nam</option>
                                    <option value="female">Nữ</option>
                                    <option value="other">Khác</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3 mt-3">
                            <label for="add_employee_address" class="form-label">Địa chỉ</label>
                            <textarea class="form-control" id="add_employee_address" name="address" rows="2"></textarea>
                        </div>
                        <div class="row g-3 mt-3">
                            <div class="col-6">
                                <label for="add_employee_phone_number" class="form-label required-field">Số điện
                                    thoại</label>
                                <input type="text" class="form-control" id="add_employee_phone_number"
                                    name="phone_number" required>
                            </div>
                            <div class="col-6">
                                <label for="add_employee_id_card_number" class="form-label required-field">Số
                                    CCCD</label>
                                <input type="text" class="form-control" id="add_employee_id_card_number"
                                    name="id_card_number" required>
                            </div>
                        </div>
                        <div class="mb-3 mt-3">
                            <label for="add_employee_department_id" class="form-label required-field">Phòng ban</label>
                            <select class="form-select" id="add_employee_department_id" name="department_id" required>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo $department['department_id']; ?>">
                                        <?php echo htmlspecialchars($department['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">Thêm nhân viên</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </div>

    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Sửa thông tin người dùng</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="post">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <h5>Thông tin đăng nhập</h5>
                                <div class="mb-3">
                                    <label for="edit_username" class="form-label required-field">Tên đăng nhập</label>
                                    <input type="text" class="form-control" id="edit_username" name="username" required>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_email" class="form-label required-field">Email</label>
                                    <input type="email" class="form-control" id="edit_email" name="email" required>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_password" class="form-label">Mật khẩu (để trống nếu không thay
                                        đổi)</label>
                                    <input type="password" class="form-control" id="edit_password" name="password">
                                </div>
                                <div class="mb-3">
                                    <label for="edit_role_id" class="form-label required-field">Quyền</label>
                                    <select class="form-select" id="edit_role_id" name="role_id" required>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?php echo $role['role_id']; ?>">
                                                <?php echo htmlspecialchars($role['role_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
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

    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteUserModalLabel">Xác nhận khóa người dùng</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Bạn có chắc chắn muốn khóa người dùng <strong id="delete_username"></strong>?</p>

                </div>
                <form action="" method="post">
                    <input type="hidden" name="action" value="lock">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-danger">khóa người dùng</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createAccountModal" tabindex="-1" aria-labelledby="createAccountModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createAccountModalLabel">Tạo tài khoản cho nhân viên</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="post">
                    <input type="hidden" name="action" value="create_account">
                    <input type="hidden" name="employee_id" id="create_account_employee_id">
                    <div class="modal-body">
                        <p>Tạo tài khoản cho nhân viên: <strong id="create_account_employee_name"></strong></p>

                        <div class="mb-3">
                            <label for="create_account_username" class="form-label required-field">Tên đăng nhập</label>
                            <input type="text" class="form-control" id="create_account_username" name="username"
                                required>
                        </div>
                        <div class="mb-3">
                            <label for="create_account_email" class="form-label required-field">Email</label>
                            <input type="email" class="form-control" id="create_account_email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="create_account_password" class="form-label required-field">Mật khẩu</label>
                            <input type="password" class="form-control" id="create_account_password" name="password"
                                required>
                        </div>
                        <div class="mb-3">
                            <label for="create_account_role_id" class="form-label required-field">Quyền</label>
                            <select class="form-select" id="create_account_role_id" name="role_id" required>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['role_id']; ?>">
                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-success">Tạo tài khoản</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.getElementById('is_employee').addEventListener('change', function () {
            document.getElementById('employee_info').style.display = this.checked ? 'block' : 'none';

            const employeeFields = document.querySelectorAll('#employee_info input:not([type="checkbox"]), #employee_info select');
            employeeFields.forEach(field => {
                field.required = this.checked;
            });
        });

        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');

            if (tab) {
                const triggerEl = document.querySelector(`button[data-bs-target="#${tab}-tab-pane"]`);
                if (triggerEl) {
                    const tabInstance = new bootstrap.Tab(triggerEl);
                    tabInstance.show();
                }
            }
        });

        const editUserBtns = document.querySelectorAll('.edit-user-btn');
        editUserBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                const userData = JSON.parse(this.getAttribute('data-user'));

                document.getElementById('edit_user_id').value = userData.user_id;
                document.getElementById('edit_username').value = userData.username;
                document.getElementById('edit_email').value = userData.email;
                document.getElementById('edit_role_id').value = userData.role_id;

                const isEmployee = userData.employee_id ? true : false;
                document.getElementById('edit_is_employee').checked = isEmployee;
                document.getElementById('edit_employee_info').style.display = isEmployee ? 'block' : 'none';

                if (isEmployee) {
                    document.getElementById('edit_first_name').value = userData.first_name;
                    document.getElementById('edit_last_name').value = userData.last_name;
                    document.getElementById('edit_birth_date').value = userData.birth_date;
                    document.getElementById('edit_gender').value = userData.gender;
                    document.getElementById('edit_address').value = userData.address;
                    document.getElementById('edit_phone_number').value = userData.phone_number;
                    document.getElementById('edit_id_card_number').value = userData.id_card_number;
                    document.getElementById('edit_department_id').value = userData.department_id;
                }
            });
        });

        document.getElementById('edit_is_employee').addEventListener('change', function () {
            document.getElementById('edit_employee_info').style.display = this.checked ? 'block' : 'none';

            const employeeFields = document.querySelectorAll('#edit_employee_info input:not([type="checkbox"]), #edit_employee_info select');
            employeeFields.forEach(field => {
                field.required = this.checked;
            });
        });

        const deleteUserBtns = document.querySelectorAll('.delete-user-btn');
        deleteUserBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                const userId = this.getAttribute('data-user-id');
                const username = this.getAttribute('data-username');

                document.getElementById('delete_user_id').value = userId;
                document.getElementById('delete_username').textContent = username;
            });
        });

        const createAccountBtns = document.querySelectorAll('.create-account-btn');
        createAccountBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                const employeeData = JSON.parse(this.getAttribute('data-employee'));

                document.getElementById('create_account_employee_id').value = employeeData.employee_id;
                document.getElementById('create_account_employee_name').textContent = employeeData.last_name + ' ' + employeeData.first_name;

                const defaultUsername = (employeeData.first_name.toLowerCase() + employeeData.last_name.toLowerCase().replace(/\s+/g, '')).normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                document.getElementById('create_account_username').value = defaultUsername;
                document.getElementById('create_account_email').value = defaultUsername + '@company.com';
            });
        });

        $('.unlock-user-btn').click(function () {
            var userId = $(this).data('user-id');
            var username = $(this).data('username');
            $('#unlock-user-id').val(userId);
            $('#unlock-username').text(username);
        });

        $('#confirm-unlock').click(function () {
            $('#unlockUserForm').submit();
        });
    </script>

</body>

</html>