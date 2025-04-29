<?php
require_once dirname(__DIR__) . '/config/db.php';
$user = getCurrentUser();

if (!$user) {
    header("Location: login.php");
    exit;
}

$errorMsg = $successMsg = '';
$userData = [];
$performanceData = [];
$performanceHistory = [];

$stmt = $pdo->prepare("
    SELECT e.*, d.department_name 
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE e.user_id = ?
");
$stmt->execute([$user['user_id']]);
$userData = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);
    $storedHash = $stmt->fetchColumn();

    if (!password_verify($currentPassword, $storedHash)) {
        $errorMsg = "Mật khẩu hiện tại không đúng";
    } elseif ($newPassword !== $confirmPassword) {
        $errorMsg = "Mật khẩu mới không khớp";
    } elseif (strlen($newPassword) < 6) {
        $errorMsg = "Mật khẩu mới phải có ít nhất 6 ký tự";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->execute([$hashedPassword, $user['user_id']]);
        $successMsg = "Đổi mật khẩu thành công";
    }
}

if ($user['role_name'] != 'Admin' && $userData) {
    $currentMonth = date('n');
    $currentYear = date('Y');

    $stmt = $pdo->prepare("
    SELECT 
        emp.*, 
        mds.*, 
        d.kpi_name, 
        d.kpi_unit
    FROM employee_monthly_performance emp
    LEFT JOIN monthly_department_settings mds 
        ON emp.department_id = mds.department_id 
        AND emp.month = mds.month 
        AND emp.year = mds.year
    LEFT JOIN departments d 
        ON emp.department_id = d.department_id
    WHERE emp.employee_id = ? 
        AND emp.month = ? 
        AND emp.year = ?
");

    $stmt->execute([$userData['employee_id'], $currentMonth, $currentYear]);
    $performanceData = $stmt->fetch();

    if ($performanceData && $performanceData['salary_calculated'] == 0) {
        $performanceData['absence_penalty'] = $performanceData['unauthorized_absences'] * $performanceData['unauthorized_absence_penalty'];
        $performanceData['meal_allowance_total'] = $performanceData['total_allowance'];

        $performanceData['estimated_salary'] =
            $performanceData['final_salary'];
    }

    $stmt = $pdo->prepare("
        SELECT emp.*, mds.daily_meal_allowance, mds.unauthorized_absence_penalty,mds.late_arrival_penalty 
        FROM employee_monthly_performance emp
        LEFT JOIN monthly_department_settings mds ON emp.department_id = mds.department_id 
            AND emp.month = mds.month AND emp.year = mds.year
        WHERE emp.employee_id = ? 
        ORDER BY emp.year DESC, emp.month DESC
        LIMIT 6
    ");
    $stmt->execute([$userData['employee_id']]);
    $performanceHistory = $stmt->fetchAll();
}
if (isset($userData['department_id'])) {
    $text = "Ngày";

    if ($userData['department_id'] == 4) {
        $text = "Ca";
    } else {
        $text = "Ngày";
    }
}

include dirname(__DIR__) . '/partials/header.php';

?>

<div class="container mt-4">
    <h2>Thông tin cá nhân</h2>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger"><?= $errorMsg ?></div>
    <?php endif; ?>

    <?php if ($successMsg): ?>
        <div class="alert alert-success"><?= $successMsg ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Thông tin tài khoản</h5>
                </div>
                <div class="card-body">
                    <p><strong>Tên người dùng:</strong> <?= htmlspecialchars($user['username']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($user['email'] ?? 'Chưa cập nhật') ?></p>
                    <p><strong>Vai trò:</strong> <?= htmlspecialchars($user['role_name']) ?></p>

                    <button class="btn btn-primary" type="button" data-bs-toggle="collapse"
                        data-bs-target="#changePasswordForm">
                        Đổi mật khẩu
                    </button>

                    <div class="collapse mt-3" id="changePasswordForm">
                        <form method="post">
                            <div class="form-group">
                                <label>Mật khẩu hiện tại</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Mật khẩu mới</label>
                                <input type="password" name="new_password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Xác nhận mật khẩu mới</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            <button type="submit" name="change_password" class="btn btn-success">Lưu thay đổi</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($userData && $user['role_name'] != 'Admin'): ?>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Thông tin nhân viên</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Họ tên:</strong> <?= htmlspecialchars($userData['last_name']) ?>
                            <?= htmlspecialchars($userData['first_name']) ?>
                        </p>
                        <p><strong>Năm sinh:</strong>
                            <?= date('d-m-Y', strtotime(htmlspecialchars($userData['birth_date']))) ?></p>
                        <p><strong>Giới tính:</strong>
                            <?= htmlspecialchars($userData['gender'] == 'male' ? 'Nam' : ($userData['gender'] == 'female' ? 'Nữ' : 'Khác')) ?>
                        </p>
                        <p><strong>Địa chỉ:</strong> <?= htmlspecialchars($userData['address'] ?? 'Chưa cập nhật') ?></p>
                        <p><strong>Số điện thoại:</strong>
                            <?= htmlspecialchars($userData['phone_number'] ?? 'Chưa cập nhật') ?></p>
                        <p><strong>CMND/CCCD:</strong>
                            <?= htmlspecialchars($userData['id_card_number'] ?? 'Chưa cập nhật') ?></p>
                        <p><strong>Phòng ban:</strong>
                            <?= htmlspecialchars($userData['department_name'] ?? 'Chưa phân công') ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($performanceData) && $user['role_name'] !== 'Admin'): ?>
        <div class="row">
            <div class="col-md-12">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Hiệu suất tháng <?= date('m/Y') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Thông tin KPI</h6>
                                <p><strong>Mục tiêu <?php echo $performanceData['kpi_name'] ?>:</strong>
                                    <?= number_format($performanceData['individual_kpi_target'] ?? 0, 2) ?>
                                    <?php echo $performanceData['kpi_unit'] ?>
                                </p>
                                <p><strong><?php echo $performanceData['kpi_name'] ?> đạt được:</strong>
                                    <?= number_format($performanceData['kpi_achieved'] ?? 0, 2) ?>
                                    <?php echo $performanceData['kpi_unit'] ?>
                                </p>
                                <p><strong>Tỷ lệ đạt <?php echo $performanceData['kpi_name'] ?>:</strong>
                                    <?= $performanceData['individual_kpi_target'] > 0 ? number_format(($performanceData['kpi_achieved'] / $performanceData['individual_kpi_target']) * 100, 2) . '%' : '0%' ?>
                                </p>

                            </div>
                            <div class="col-md-6">
                                <h6>Thông tin chuyên cần</h6>

                                <p><strong>Nghỉ có phép:</strong> <?= $performanceData['authorized_absences'] ?? 0 ?> ngày
                                </p>
                                <p><strong>Nghỉ không phép:</strong> <?= $performanceData['unauthorized_absences'] ?? 0 ?>
                                    <?php echo $text; ?></p>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-12">
                                <h6>Thông tin lương</h6>
                                <?php if (!empty($performanceData['salary_calculated'])): ?>
                                <?php else: ?>
                                    <p class="text-danger">Đây là các thông số lương cơ bản, để có thông tin đầy đủ, chi tiết về
                                        lương, nhân viên cần hoàn thành tháng này và đợi bên trên tính lương để có cái nhìn tổng
                                        thể</p>
                                    <p class="text-info">Sang tháng và đã bấm chốt lương, các thông số cụ thể như thưởng KPI,
                                        tiền phạt KPI, phạt số ngày không phép, phụ cấp và nhiều hơn sẽ được hiển thị</p>
                                    <p><strong>Lương cơ bản:</strong>
                                        <?= number_format($performanceData['individual_base_salary'] ?? 0, 0) ?> VNĐ</p>


                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($performanceHistory) && $user['role_name'] != 'Admin'): ?>


        <div class="row">
            <div class="col-md-12">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Lịch sử hiệu suất</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm text-center align-middle">
                                <thead class="bg-light fw-bold">
                                    <tr>
                                        <th>Ngày</th>
                                        <th>Lương CS</th>
                                        <th>KPI đạt được / Mục tiêu KPI</th>
                                        </th>

                                        <th><?php echo $text ?> đã làm</th>

                                        <th>Nghỉ phép / không phép / đi muộn</th>



                                        <th>Trợ cấp</th>
                                        <th>Thưởng thêm / Tổng Phạt</th>
                                        <th>Tạm tính</th>
                                        <th>Lương cuối cùng</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($performanceHistory as $record): ?>
                                        <?php if ($record['month'] == date('n') && $record['year'] == date('Y'))
                                            continue; ?>
                                        <tr>
                                            <td><?= $record['month'] ?>/<?= $record['year'] ?></td>
                                            <td><?= number_format($record['individual_base_salary']) ?> đ</td>
                                            <td><?= number_format($record['kpi_achieved']) ?> /
                                                <?= number_format($record['individual_kpi_target']) ?>
                                            </td>

                                            <td><?= $record['working_day'] ?: 0 ?></td>
                                            <td><?= $record['authorized_absences'] ?: 0 ?> /
                                                <?= $record['unauthorized_absences'] ?: 0 ?> / <?= $record['late_day'] ?: 0 ?>

                                            </td>



                                            <td>
                                                <?= number_format($record['total_allowance']) ?> đ
                                            </td>
                                            <td><?= number_format($record['bonus_more']) ?> đ / <?= number_format(
                                                                                                    $record['penalty_more'] +
                                                                                                        ($record['late_day'] * $record['late_arrival_penalty']) +
                                                                                                        ($record['unauthorized_absences'] * $record['unauthorized_absence_penalty'])
                                                                                                ) ?> đ</td>

                                            <td><?= number_format($record['final_salary']) ?> đ</td>
                                            <td><?= $record['salary_calculated'] ? number_format($record['final_salary']) . ' đ' : '<span class="text-warning">Chưa tính</span>' ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>


                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"
    integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
<script>
    $('[data-bs-toggle="collapse"]').on('click', function() {
        var target = $(this).data('bs-target');
        $(target).collapse('toggle');
    });
</script>