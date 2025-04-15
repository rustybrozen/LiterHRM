<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/db.php';

$currentUser = isset($_SESSION['user_id']) ? getCurrentUser() : false;
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hệ thống Quản lý Nhân sự</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --sidebar-bg: #343a40;
            --sidebar-link: rgba(255, 255, 255, 0.8);
            --sidebar-hover: #fff;
            --sidebar-active-bg: rgba(255, 255, 255, 0.1);
        }

        .sidebar {
            min-height: calc(100vh - 56px);
            background-color: var(--sidebar-bg);
            padding-top: 1rem;
        }

        .sidebar .nav-link {
            color: var(--sidebar-link);
            padding: 0.5rem 1rem;
            margin-bottom: 0.25rem;
            border-radius: 0.25rem;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
        }

        .sidebar .nav-link:hover {
            color: var(--sidebar-hover);
            background-color: rgba(255, 255, 255, 0.05);
        }

        .sidebar .nav-link.active {
            color: var(--sidebar-hover);
            background-color: var(--sidebar-active-bg);
            font-weight: 500;
        }

        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .content {
            padding: 20px;
        }

        .menu-section {
            margin-bottom: 15px;
        }

        .menu-section-title {
            color: #adb5bd;
            text-transform: uppercase;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.5rem 1rem;
            margin-top: 1rem;
        }

        .dropdown-menu {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-users-cog me-2"></i>Quản lý Nhân sự
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if ($currentUser): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                                data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($currentUser['username']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-id-card me-2"></i>Trang cá nhân</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../controllers/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Đăng xuất</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <?php if ($currentUser): ?>
                <!-- Sidebar -->
                <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                    <div class="position-sticky">
                        <ul class="nav flex-column">
                            <?php
                            // Hàm kiểm tra menu item có active không
                            function isActive($pageName) {
                                global $currentPage;
                                return $currentPage == $pageName ? 'active' : '';
                            }
                            
                            // Hàm render menu item
                            function renderMenuItem($icon, $title, $link, $activePage = null) {
                                $activeClass = $activePage ? isActive($activePage) : '';
                                echo '<li class="nav-item">
                                    <a class="nav-link ' . $activeClass . '" href="' . $link . '">
                                        <i class="' . $icon . '"></i> ' . $title . '
                                    </a>
                                </li>';
                            }
                            
                            // Hàm render section title
                            function renderSectionTitle($title) {
                                echo '<div class="menu-section-title">' . $title . '</div>';
                            }
                            
                            // Dashboard - cho tất cả người dùng
                            if ($currentUser['role_name'] == 'Admin' || $currentUser['role_name'] == 'Quản Lý Nhân Sự'):
                            renderMenuItem('fas fa-home', 'Dashboard', 'dashboard.php', 'dashboard.php');
                        endif;
                            renderMenuItem('fas fa-id-card', 'Trang cá nhân', 'profile.php', 'profile.php');
                            
                            // Menu cho Admin và Quản lý Nhân sự
                            if ($currentUser['role_name'] == 'Admin' || $currentUser['role_name'] == 'Quản Lý Nhân Sự'):
                                renderSectionTitle('Quản lý hệ thống');
                                renderMenuItem('fas fa-building', 'Quản lý Phòng ban', 'departments.php', 'departments.php');
                                renderMenuItem('fas fa-users-cog', 'Quản lý Tài khoản', 'users.php', 'users.php');
                                renderMenuItem('fas fa-user-tie', 'Quản lý Nhân viên', 'employees.php', 'employees.php');
                                renderMenuItem('fas fa-clock', 'Quản lý Lịch làm việc', 'work_schedules.php', 'work_schedules.php');
                                
                                renderSectionTitle('Quản lý nghỉ phép');
                                renderMenuItem('fas fa-bed', 'Quản lý Ngày nghỉ', 'absences.php', 'absences.php');
                                renderMenuItem('fas fa-tasks', 'Quản lý Yêu cầu', 'requests_management.php', 'requests_management.php');
                                
                                renderSectionTitle('KPI & Lương');
                                renderMenuItem('fas fa-chart-line', 'Quản lý KPI & Lương', 'kpi_settings.php', 'kpi_settings.php');
                                renderMenuItem('fas fa-balance-scale', 'Quản lý Thưởng Phạt', 'reward_penalty_management.php', 'reward_penalty_management.php');
                                renderMenuItem('fas fa-calculator', 'Tính lương', 'salary_calculation.php', 'salary_calculation.php');
                                renderMenuItem('fas fa-sync', 'Tải dữ liệu chấm công', 'update_attendance_data.php', 'update_attendance_data.php');
                                
                                renderSectionTitle('Báo cáo');
                                renderMenuItem('fas fa-chart-pie', 'Hiệu suất Nhân viên', 'employee_performance.php', 'employee_performance.php');
                                renderMenuItem('fas fa-chart-bar', 'Báo cáo Hiệu suất', 'performance_reports.php', 'performance_reports.php');
                            endif;
                            
                            // Menu chỉ dành cho Admin
                            if ($currentUser['role_name'] != 'Nhân viên'):
                                renderMenuItem('fas fa-hourglass-start', 'Quản lý Giờ làm', 'work_hours.php', 'work_hours.php');
                            endif;
                            
                            // Menu cho Nhân viên và Quản lý Nhân sự
                            if ($currentUser['role_name'] == 'Nhân viên' || $currentUser['role_name'] == 'Quản Lý Nhân Sự'):
                                renderSectionTitle('Công việc');
                                renderMenuItem('fas fa-calendar-alt', 'Lịch làm việc của tôi', 'llv.php', 'llv.php');
                            endif;
                            
                            // Menu Tạo yêu cầu
                            if ($currentUser['role_name'] == 'Nhân viên' || $currentUser['role_name'] == 'Quản Lý Nhân Sự'):
                                renderMenuItem('fas fa-paper-plane', 'Tạo yêu cầu', 'create_request.php', 'create_request.php');
                            endif;
                            ?>
                        </ul>
                    </div>
                </div>
                <!-- Main content -->
                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content">
            <?php else: ?>
                <!-- Main content khi chưa đăng nhập -->
                <main class="col-12 content">
            <?php endif; ?>