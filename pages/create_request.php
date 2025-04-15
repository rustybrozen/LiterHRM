<?php
require_once dirname(__DIR__) . '/config/db.php';
$user = checkPermission('employee');

$stmt = $pdo->prepare("SELECT * FROM employees WHERE user_id = ?");
$stmt->execute([$user['user_id']]);
$employee = $stmt->fetch();
$isSale = false;
if($employee['department_id'] == 4){
    $isSale=true;
}

if (!$employee) {
    die("Không tìm thấy thông tin nhân viên");
}

$shifts = $pdo->query("SELECT * FROM shifts")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $requestType = $_POST['request_type'];
        $startDate = $_POST['start_date'];
        $endDate = $startDate;
        $reason = $_POST['reason'];
        $isAuthorized = 1;
        
        $shiftId = null;
        $startTime = null;
        $endTime = null;

        if ($requestType === 'shift_change') {
            $scheduleType = $_POST['schedule_type'];

            if ($scheduleType === 'shift' && isset($_POST['shift_id'])) {
                $shiftId = $_POST['shift_id'];

           
                if ($employee['department_id'] == 4) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM employee_work_schedules WHERE effective_date = ? AND shift_id = ?");
                    $stmt->execute([$startDate, $shiftId]);
                    $count = $stmt->fetchColumn();
                    
                    if ($count >= 5) {
                        $errorMessage = "Ca làm hôm nay đã đủ người, không thể đăng ký thêm.";
                    } else {
                   
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM employee_requests WHERE employee_id = ? AND start_date = ? AND shift_id = ?");
                        $stmt->execute([$employee['employee_id'], $startDate, $shiftId]);
                        $existingShiftRequest = $stmt->fetchColumn();
                        
                        if ($existingShiftRequest > 0) {
                            $errorMessage = "Bạn đã đăng ký ca này trong ngày rồi!";
                        }
                    }
                }
            } elseif ($scheduleType === 'fixed' && isset($_POST['start_time']) && isset($_POST['end_time'])) {
                $startTime = $_POST['start_time'];
                $endTime = $_POST['end_time'];
            }
        }


        if (!isset($errorMessage)) {
            $stmt = $pdo->prepare("INSERT INTO employee_requests (employee_id, request_type, is_absence_authorized, start_date, end_date, shift_id, start_time, end_time, reason, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([
                $employee['employee_id'],
                $requestType,
                $isAuthorized,
                $startDate,
                $endDate,
                $shiftId,
                $startTime,
                $endTime,
                $reason
            ]);
            $successMessage = "Yêu cầu của bạn đã được gửi thành công!";
        }
    } catch (PDOException $e) {
        $errorMessage = "Lỗi: " . $e->getMessage();
    }
}

$stmt = $pdo->prepare("SELECT er.*, s.shift_name, CASE WHEN er.status = 'pending' THEN 'Đang chờ duyệt' WHEN er.status = 'approved' THEN 'Đã duyệt' WHEN er.status = 'rejected' THEN 'Đã từ chối' END as status_text, CASE WHEN er.request_type = 'absence' THEN 'Xin nghỉ phép' WHEN er.request_type = 'shift_change' THEN 'Xin đổi ca' WHEN er.request_type = 'leave' THEN 'Xin nghỉ việc' ELSE 'Khác' END as request_type_text FROM employee_requests er LEFT JOIN shifts s ON er.shift_id = s.shift_id WHERE er.employee_id = ? ORDER BY er.created_at DESC");
$stmt->execute([$employee['employee_id']]);
$requests = $stmt->fetchAll();
?>


<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yêu cầu của nhân viên</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card {
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .nav-tabs .nav-link.active {
            font-weight: bold;
            border-bottom: 3px solid #0d6efd;
        }

        .request-form {
            display: none;
        }

        .request-form.active {
            display: block;
        }
    </style>
</head>

<body>
<?php include dirname(__DIR__) . '/partials/header.php'; ?>


    <div class="container mt-4">
        <h2 class="mb-4 text-center">Yêu cầu của nhân viên</h2>

        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $successMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $errorMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Tạo yêu cầu mới</h5>
                    </div>
                    <div class="card-body">
                        <ul class="nav nav-tabs mb-3" id="requestTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="absence-tab" data-bs-toggle="tab"
                                    data-form="absence-form" type="button">Xin nghỉ phép</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="leave-tab" data-bs-toggle="tab" data-form="leave-form"
                                    type="button">Xin nghỉ việc</button>
                            </li>
                            <?php if ($employee['department_id'] =='4'): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="shift-tab" data-bs-toggle="tab" data-form="shift-form"
                                    type="button">Đổi ca làm việc</button>
                            </li>
                            <?php endif; ?>
                        </ul>

                        <form id="absence-form" class="request-form active" method="POST" action="">
                            <input type="hidden" name="request_type" value="absence">

                            <div class="mb-3">
                                <label for="absence-start-date" class="form-label">Ngày cần nghỉ</label>
                                <input type="date" class="form-control" id="absence-start-date" name="start_date"
                                    required min="<?php echo date('Y-m-d'); ?>">
                            </div>





                            <div class="mb-3">
                                <label for="absence-reason" class="form-label">Lý do xin nghỉ</label>
                                <textarea class="form-control" id="absence-reason" name="reason" rows="3"
                                    required></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Gửi yêu cầu</button>
                        </form>

                        <form id="leave-form" class="request-form" method="POST" action="">
                            <input type="hidden" name="request_type" value="leave">

                            <div class="mb-3">
                                <label for="leave-date" class="form-label">Ngày muốn nghỉ việc</label>
                                <input type="date" class="form-control" id="leave-date" name="start_date" required
                                    min="<?php echo date('Y-m-d'); ?>">
                            </div>

                            <input type="hidden" name="end_date" value="<?php echo date('Y-m-d'); ?>">
                            <input type="hidden" name="is_authorized" value="1">

                            <div class="mb-3">
                                <label for="leave-reason" class="form-label">Lý do xin nghỉ việc</label>
                                <textarea class="form-control" id="leave-reason" name="reason" rows="5"
                                    required></textarea>
                            </div>

                            <button type="submit" class="btn btn-danger w-100">Gửi yêu cầu nghỉ việc</button>
                        </form>

                        <!-- Thay đổi form "shift-form" -->
                        <form id="shift-form" class="request-form" method="POST" action="">
                            <input type="hidden" name="request_type" value="shift_change">
                            <input type="hidden" name="is_authorized" value="1">

                            <div class="mb-3">
                                <label for="shift-date" class="form-label">Chọn ngày đổi ca và lý do</label>
                                <input type="date" class="form-control" id="shift-date" name="start_date" required
                                    min="<?php echo date('Y-m-d'); ?>">
                            </div>

                            <input type="hidden" name="end_date" value="<?php echo date('Y-m-d'); ?>">

                            <div class="mb-3">
                                <label class="form-label">Loại lịch làm việc</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="schedule_type" id="shift-type"
                                        value="shift" checked>
                                    <label class="form-check-label" for="shift-type">
                                        Làm theo ca
                                    </label>
                                </div>
                            
                            </div>

                            <div id="shift-select-container" class="mb-3">
                                <label for="shift-select" class="form-label">Ca muốn đổi sang</label>
                                <select class="form-select" id="shift-select" name="shift_id">
                                    <option value="" selected disabled>Chọn ca làm việc</option>
                                    <?php foreach ($shifts as $shift): ?>
                                        <option value="<?php echo $shift['shift_id']; ?>">
                                            <?php echo $shift['shift_name']; ?> (<?php echo $shift['start_time']; ?> -
                                            <?php echo $shift['end_time']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div id="fixed-time-container" class="mb-3 d-none">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="fixed-start-time" class="form-label">Giờ bắt đầu</label>
                                        <input type="time" class="form-control" id="fixed-start-time" name="start_time">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="fixed-end-time" class="form-label">Giờ kết thúc</label>
                                        <input type="time" class="form-control" id="fixed-end-time" name="end_time">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="shift-reason" class="form-label">Lý do muốn đổi ca</label>
                                <textarea class="form-control" id="shift-reason" name="reason" rows="3" placeholder="Nhập cụ thể ngày và ca hiện tại cần đổi sang ngày và ca mới"
                                    required></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Gửi yêu cầu đổi ca</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Lịch sử yêu cầu</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($requests) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Loại yêu cầu</th>
                                            <th>Ngày bắt đầu</th>

                                            <th>Lý do</th>
                                            <th>Trạng thái</th>
                                            <th>Ngày tạo</th>
                                            <th>Lý do từ chối</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($requests as $request): ?>
                                            <tr>
                                                <td>
                                                    <?php echo $request['request_type_text']; ?>
                                                    <?php if ($request['request_type'] == 'shift_change' && $request['shift_name']): ?>
                                                        <br><small class="text-muted">Ca:
                                                            <?php echo $request['shift_name']; ?></small>
                                                    <?php endif; ?>

                                                    <?php if ($request['start_time'] && $request['end_time']): ?>
                                                        <br><small class="text-muted">Ca:
                                                            (<?php echo date('H:i', strtotime($request['start_time'])); ?> -
                                                            <?php echo date('H:i', strtotime($request['end_time'])); ?>)</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('d/m/Y', strtotime($request['start_date'])); ?></td>

                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                                        title="<?php echo htmlspecialchars($request['reason']); ?>">
                                                        Xem lý do
                                                    </button>
                                                </td>
                                                <td>
                                                    <?php if ($request['status'] == 'pending'): ?>
                                                        <span class="badge bg-warning"><?php echo $request['status_text']; ?></span>
                                                    <?php elseif ($request['status'] == 'approved'): ?>
                                                        <span class="badge bg-success"><?php echo $request['status_text']; ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger"><?php echo $request['status_text']; ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($request['created_at'])); ?></td>
                                            
                                            <td> <?php echo $request['reason_rejected']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                Bạn chưa có yêu cầu nào. Hãy tạo yêu cầu mới từ form bên trái.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const tabButtons = document.querySelectorAll('.nav-link');

            tabButtons.forEach(button => {
                button.addEventListener('click', function () {
                    tabButtons.forEach(tab => tab.classList.remove('active'));
                    document.querySelectorAll('.request-form').forEach(form => {
                        form.classList.remove('active');
                    });

                    this.classList.add('active');

                    const formId = this.getAttribute('data-form');
                    document.getElementById(formId).classList.add('active');
                });
            });

            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            const startDateInput = document.getElementById('leave-start-date');
            const endDateInput = document.getElementById('leave-end-date');



            const shiftTypeRadio = document.getElementById('shift-type');
            const fixedTypeRadio = document.getElementById('fixed-type');
            const shiftSelectContainer = document.getElementById('shift-select-container');
            const fixedTimeContainer = document.getElementById('fixed-time-container');
            const shiftSelect = document.getElementById('shift-select');
            const fixedStartTime = document.getElementById('fixed-start-time');
            const fixedEndTime = document.getElementById('fixed-end-time');

            shiftTypeRadio.addEventListener('change', function () {
                if (this.checked) {
                    shiftSelectContainer.classList.remove('d-none');
                    fixedTimeContainer.classList.add('d-none');
                    shiftSelect.required = true;
                    fixedStartTime.required = false;
                    fixedEndTime.required = false;
                }
            });

            fixedTypeRadio.addEventListener('change', function () {
                if (this.checked) {
                    shiftSelectContainer.classList.add('d-none');
                    fixedTimeContainer.classList.remove('d-none');
                    shiftSelect.required = false;
                    fixedStartTime.required = true;
                    fixedEndTime.required = true;
                }
            });
        });
    </script>
</body>

</html>