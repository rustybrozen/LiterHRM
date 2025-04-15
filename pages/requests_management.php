<?php
require_once dirname(__DIR__) . '/config/db.php';

$currentUser = getCurrentUser();
$isHROrAdmin = ($currentUser['role_name'] == 'Admin' || $currentUser['role_name'] == 'Quản Lý Nhân Sự');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $isHROrAdmin) {
    if (isset($_POST['action'])) {
      
        if ($_POST['action'] == 'approve' || $_POST['action'] == 'reject') {
            $requestId = $_POST['request_id'];
            $status = ($_POST['action'] == 'approve') ? 'approved' : 'rejected';
            $reasonRejected = null;
            
          
            if ($status == 'rejected' && isset($_POST['reason_rejected'])) {
                $reasonRejected = $_POST['reason_rejected'];
            }
            
            try {
                $pdo->beginTransaction();
                
               
                $sql = "UPDATE employee_requests 
                        SET status = ?, reviewed_by = ?, review_date = NOW()";
                
                $params = [$status, $currentUser['user_id']];
                
                if ($reasonRejected !== null) {
                    $sql .= ", reason_rejected = ?";
                    $params[] = $reasonRejected;
                }
                
                $sql .= " WHERE request_id = ?";
                $params[] = $requestId;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
             
                if ($status == 'approved') {
              
                    $stmt = $pdo->prepare("
                        SELECT * FROM employee_requests WHERE request_id = ?
                    ");
                    $stmt->execute([$requestId]);
                    $request = $stmt->fetch();
                    


                    if ($request['request_type'] == 'absence') {
                        $absenceField = $request['is_absence_authorized'] ? 'authorized_absences' : 'unauthorized_absences';
                        $daysDiff = (strtotime($request['end_date']) - strtotime($request['start_date'])) / (60 * 60 * 24) + 1;
                        
                        // Cập nhật employee_monthly_performance
                        $stmt = $pdo->prepare("
                            SELECT * FROM employee_monthly_performance 
                            WHERE employee_id = ? AND MONTH(?) = month AND YEAR(?) = year
                        ");
                        $stmt->execute([$request['employee_id'], $request['start_date'], $request['start_date']]);
                        $performance = $stmt->fetch();
                        
                        if ($performance) {
                            $stmt = $pdo->prepare("
                                UPDATE employee_monthly_performance 
                                SET $absenceField = $absenceField + ? 
                                WHERE performance_id = ?
                            ");
                            $stmt->execute([$daysDiff, $performance['performance_id']]);
                        }
                        
                        // Tạo vòng lặp để cập nhật tất cả các ngày từ start_date đến end_date
                        $currentDate = new DateTime($request['start_date']);
                        $endDate = new DateTime($request['end_date']);
                        
                        while ($currentDate <= $endDate) {
                            $dateString = $currentDate->format('Y-m-d');
                            
                            // Cập nhật tất cả các ca làm việc trong ngày này
                            $stmt = $pdo->prepare("
                                UPDATE employee_work_schedules 
                                SET check_in = NULL, 
                                    check_out = NULL, 
                                    is_authorized_absence = ?, 
                                    is_worked = 1
                                WHERE employee_id = ?
                                AND effective_date = ?
                            ");
                            $stmt->execute([
                                ($request['is_absence_authorized'] ? 1 : 0), 
                                $request['employee_id'], 
                                $dateString
                            ]);
                            
                            // Tăng ngày lên 1 để duyệt đến ngày tiếp theo
                            $currentDate->modify('+1 day');
                        }
                    }
                    
                    
                    
                    
                    else if ($request['request_type'] == 'shift_change') {
                        // if ($request['shift_id']) {
                         
                        //     $stmt = $pdo->prepare("
                        //         SELECT COUNT(*) FROM employee_work_schedules 
                        //         WHERE employee_id = ? AND effective_date = ? AND shift_id =?
                        //     ");
                        //     $stmt->execute([$request['employee_id'], date('Y-m-d'),$request['shift_id']]);
                        //     $employeeShiftCount = $stmt->fetchColumn();
                        
                        //     if ($employeeShiftCount > 0) {
                        //         die("Nhân viên này đã đăng ký ca trùng lặp trong ngày hôm nay.");
                        //     }
                        
                          
                        //     $stmt = $pdo->prepare("
                        //         SELECT COUNT(*) FROM employee_work_schedules 
                        //         WHERE effective_date = ? AND shift_id = ?
                        //     ");
                        //     $stmt->execute([date('Y-m-d'), $request['shift_id']]);
                        //     $totalShiftCount = $stmt->fetchColumn();
                        
                        //     if ($totalShiftCount >= 4) {
                        //         die("Nhân viên không thể yêu cầu do ca này trong ngày hôm nay đã đầy người!");
                        //     }
                        
                        //     $stmt = $pdo->prepare("
                        //     INSERT INTO employee_work_schedules 
                        //     (employee_id, schedule_type_id, shift_id, effective_date) 
                        //     VALUES (?, 2, ?, ?)
                        // ");
                        // $stmt->execute([$request['employee_id'], $request['shift_id'], $request['start_date']]);
                        // }
                        //  else if ($request['start_time'] && $request['end_time']) {
                        //     $stmt = $pdo->prepare("
                        //         UPDATE employee_work_schedules 
                        //         SET start_time = ?, end_time = ?, shift_id = NULL, schedule_type_id = 1, updated_at = NOW() 
                        //         WHERE employee_id = ? AND effective_date = ?
                        //     ");
                        //     $stmt->execute([$request['start_time'], $request['end_time'], $request['employee_id'], $request['start_date']]);
                            
                        //     if ($stmt->rowCount() == 0) {
                        //         $stmt = $pdo->prepare("
                        //             INSERT INTO employee_work_schedules 
                        //             (employee_id, schedule_type_id, start_time, end_time, effective_date) 
                        //             VALUES (?, 1, ?, ?, ?)
                        //         ");
                        //         $stmt->execute([$request['employee_id'], $request['start_time'], $request['end_time'], $request['start_date']]);
                        //     }
                        // }
                    }
                }
                
                $pdo->commit();
                $successMessage = "Đã " . ($status == 'approved' ? 'phê duyệt' : 'từ chối') . " yêu cầu thành công!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $errorMessage = "Lỗi: " . $e->getMessage();
            }
        }
        
       
        if ($_POST['action'] == 'bulk_approve' || $_POST['action'] == 'bulk_reject') {
            if (isset($_POST['selected_requests']) && is_array($_POST['selected_requests'])) {
                $status = ($_POST['action'] == 'bulk_approve') ? 'approved' : 'rejected';
                $selectedRequests = $_POST['selected_requests'];
                $reasonRejected = null;
                
              
                if ($status == 'rejected' && isset($_POST['reason_rejected'])) {
                    $reasonRejected = $_POST['reason_rejected'];
                }
                
                try {
                    $pdo->beginTransaction();
                    
                    foreach ($selectedRequests as $requestId) {
              
                        $sql = "UPDATE employee_requests 
                                SET status = ?, reviewed_by = ?, review_date = NOW()";
                        
                        $params = [$status, $currentUser['user_id']];
                        
                        if ($reasonRejected !== null) {
                            $sql .= ", reason_rejected = ?";
                            $params[] = $reasonRejected;
                        }
                        
                        $sql .= " WHERE request_id = ?";
                        $params[] = $requestId;
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                      
                        if ($status == 'approved') {
                          
                            $stmt = $pdo->prepare("
                                SELECT * FROM employee_requests WHERE request_id = ?
                            ");
                            $stmt->execute([$requestId]);
                            $request = $stmt->fetch();
                            






                            if ($request['request_type'] == 'absence') {

                                $absenceField = $request['is_absence_authorized'] ? 'authorized_absences' : 'unauthorized_absences';
                                $daysDiff = (strtotime($request['end_date']) - strtotime($request['start_date'])) / (60 * 60 * 24) + 1;

                                $stmt = $pdo->prepare("
                                    SELECT * FROM employee_monthly_performance 
                                    WHERE employee_id = ? AND MONTH(?) = month AND YEAR(?) = year
                                ");
                                $stmt->execute([$request['employee_id'], $request['start_date'], $request['start_date']]);
                                $performance = $stmt->fetch();

                                if ($performance) {

                                    $stmt = $pdo->prepare("
                                        UPDATE employee_monthly_performance 
                                        SET $absenceField = $absenceField + ? 
                                        WHERE performance_id = ?
                                    ");
                                    $stmt->execute([$daysDiff, $performance['performance_id']]);
                                }

                            } 
                            
                            
                            
                            
                            
                            
                            
                            else if ($request['request_type'] == 'shift_change') {
                                if ($request['shift_id']) {
                                    $stmt = $pdo->prepare("
                                        UPDATE employee_work_schedules 
                                        SET shift_id = ?, schedule_type_id = 2, start_time = NULL, end_time = NULL, updated_at = NOW() 
                                        WHERE employee_id = ? AND effective_date = ?
                                    ");
                                    $stmt->execute([$request['shift_id'], $request['employee_id'], $request['start_date']]);
                                    
                                    if ($stmt->rowCount() == 0) {
                                        $stmt = $pdo->prepare("
                                            INSERT INTO employee_work_schedules 
                                            (employee_id, schedule_type_id, shift_id, effective_date) 
                                            VALUES (?, 2, ?, ?)
                                        ");
                                        $stmt->execute([$request['employee_id'], $request['shift_id'], $request['start_date']]);
                                    }
                                } else if ($request['start_time'] && $request['end_time']) {
                                    $stmt = $pdo->prepare("
                                        UPDATE employee_work_schedules 
                                        SET start_time = ?, end_time = ?, shift_id = NULL, schedule_type_id = 1, updated_at = NOW() 
                                        WHERE employee_id = ? AND effective_date = ?
                                    ");
                                    $stmt->execute([$request['start_time'], $request['end_time'], $request['employee_id'], $request['start_date']]);
                                    
                                    if ($stmt->rowCount() == 0) {
                                        $stmt = $pdo->prepare("
                                            INSERT INTO employee_work_schedules 
                                            (employee_id, schedule_type_id, start_time, end_time, effective_date) 
                                            VALUES (?, 1, ?, ?, ?)
                                        ");
                                        $stmt->execute([$request['employee_id'], $request['start_time'], $request['end_time'], $request['start_date']]);
                                    }
                                }
                            }
                        }
                    }
                    
                    $pdo->commit();
                    $successMessage = "Đã " . ($status == 'approved' ? 'phê duyệt' : 'từ chối') . " các yêu cầu đã chọn thành công!";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $errorMessage = "Lỗi: " . $e->getMessage();
                }
            }
        }
    }
}

$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$departmentFilter = isset($_GET['department_id']) ? $_GET['department_id'] : '';

$departments = [];
if ($currentUser['role_name'] == 'Admin') {
    $stmt = $pdo->prepare("SELECT department_id, department_name FROM departments where is_active = 1 ORDER BY department_name");

}
else{
    $stmt = $pdo->prepare("SELECT department_id, department_name FROM departments where is_active = 1 and department_id !=3 ORDER BY department_name");

}
$stmt->execute();
$departments = $stmt->fetchAll();

$query = "
    SELECT er.*, e.first_name, e.last_name, d.department_name, s.shift_name,
    u.username as reviewer_name, 
    CASE er.request_type 
        WHEN 'absence' THEN CONCAT('Vắng mặt (', IF(er.is_absence_authorized = 1, 'Có phép', 'Không phép'), ')')
        WHEN 'leave' THEN 'Nghỉ phép'
        WHEN 'shift_change' THEN 'Đăng ký ca'
        ELSE 'Khác'
    END as request_type_text
    FROM employee_requests er
    JOIN employees e ON er.employee_id = e.employee_id
    JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN shifts s ON er.shift_id = s.shift_id
    LEFT JOIN users u ON er.reviewed_by = u.user_id
    WHERE 1=1
    and e.is_locked = 0
";

$params = [];

if (!empty($statusFilter)) {
    $query .= " AND er.status = ?";
    $params[] = $statusFilter;
}

if (!empty($departmentFilter)) {
    $query .= " AND e.department_id = ?";
    $params[] = $departmentFilter;
}

if (!$isHROrAdmin) {
    $employeeInfo = $pdo->prepare("SELECT employee_id FROM employees WHERE user_id = ?");
    $employeeInfo->execute([$currentUser['user_id']]);
    $employee = $employeeInfo->fetch();
    
    if ($employee) {
        $query .= " AND er.employee_id = ?";
        $params[] = $employee['employee_id'];
    }
}

$query .= " ORDER BY er.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý yêu cầu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
<?php include dirname(__DIR__) . '/partials/header.php'; ?>

    
    <div class="container-fluid mt-4 mb-5">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Quản lý yêu cầu</h5>
                        <?php if (isset($successMessage)): ?>
                            <div class="alert alert-success alert-dismissible fade show py-2 mb-0" role="alert">
                                <?= $successMessage ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($errorMessage)): ?>
                            <div class="alert alert-danger alert-dismissible fade show py-2 mb-0" role="alert">
                                <?= $errorMessage ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row mb-4">
                            <div class="col-md-3 mb-2">
                                <label for="status" class="form-label">Trạng thái</label>
                                <select name="status" id="status" class="form-select">
                                    <option value="" <?= $statusFilter == '' ? 'selected' : '' ?>>Tất cả</option>
                                    <option value="pending" <?= $statusFilter == 'pending' ? 'selected' : '' ?>>Đang chờ</option>
                                    <option value="approved" <?= $statusFilter == 'approved' ? 'selected' : '' ?>>Đã duyệt</option>
                                    <option value="rejected" <?= $statusFilter == 'rejected' ? 'selected' : '' ?>>Đã từ chối</option>
                                </select>
                            </div>
                            <?php if ($isHROrAdmin): ?>
                            <div class="col-md-3 mb-2">
                                <label for="department_id" class="form-label">Phòng ban</label>
                                <select name="department_id" id="department_id" class="form-select">
                                    <option value="">Tất cả phòng ban</option>
                                    <?php foreach ($departments as $department): ?>
                                        <option value="<?= $department['department_id'] ?>" <?= $departmentFilter == $department['department_id'] ? 'selected' : '' ?>>
                                            <?= $department['department_name'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            <div class="col-md-2 mb-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Lọc</button>
                            </div>
                        </form>
                        
                        <?php if (count($requests) > 0): ?>
                            <form method="POST" id="bulkActionForm">
                                <?php if ($isHROrAdmin && $statusFilter == 'pending'): ?>
                                <div class="mb-3">
                            
                                    <button type="button" id="bulkRejectBtn" class="btn btn-danger btn-sm">
    <i class="bi bi-x-lg"></i> Từ chối đã chọn
</button>


                                </div>
                                <?php endif; ?>
                                
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <?php if ($isHROrAdmin && $statusFilter == 'pending'): ?>
                                                <th width="40">
                                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                                </th>
                                                <?php endif; ?>
                                                <th>Mã</th>
                                                <th>Nhân viên</th>
                                                <th>Phòng ban</th>
                                                <th>Loại yêu cầu</th>
                                                <th>Thời gian</th>
                                                <th>Ca/Giờ</th>
                                                <th>Lý do</th>
                                                <th>Trạng thái</th>
                                        
                                                <th>Ngày tạo</th>
                                                <?php if ($isHROrAdmin && $statusFilter == 'pending'): ?>
                                                <th>Thao tác</th>
                                                <?php endif; ?>
                                                <?php if ($isHROrAdmin && $statusFilter == 'rejected'): ?>
<th>Lý do từ chối</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($requests as $request): ?>
                                                <tr>
                                                    <?php if ($isHROrAdmin && $statusFilter == 'pending'): ?>
                                                    <td>
                                                        <input type="checkbox" name="selected_requests[]" value="<?= $request['request_id'] ?>" class="form-check-input request-checkbox">
                                                    </td>
                                                    <?php endif; ?>
                                                    <td>REQ-<?= str_pad($request['request_id'], 4, '0', STR_PAD_LEFT) ?></td>
                                                    <td><?= $request['first_name'] . ' ' . $request['last_name'] ?></td>
                                                    <td><?= $request['department_name'] ?></td>
                                                    <td><?= $request['request_type_text'] ?></td>
                                                    <td>
                                                        <?= date('d/m/Y', strtotime($request['start_date'])) ?>
                                                     
                                                    </td>
                                                    <td>
                                                        <?= $request['shift_name'] ?? (isset($request['start_time'], $request['end_time']) ? date('H:i', strtotime($request['start_time'])) . ' - ' . date('H:i', strtotime($request['end_time'])) : '') ?>
                                                    </td>
                                                    <td><?= $request['reason'] ?></td>
                                                    <td>
                                                        <?php if ($request['status'] == 'pending'): ?>
                                                            <span class="badge bg-warning">Đang chờ</span>
                                                        <?php elseif ($request['status'] == 'approved'): ?>
                                                            <span class="badge bg-success">Đã duyệt</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Đã từ chối</span>
                                                        <?php endif; ?>
                                                    </td>
                                                  
                                                    
                                                    
                                                    
                                                    
                                                    <td><?= date('d/m/Y H:i', strtotime($request['created_at'])) ?></td>
                                                    
                                                    <?php if ($isHROrAdmin && $statusFilter == 'rejected'): ?>
<td><?php echo $request['reason_rejected'] ?></td>
                                                        <?php endif; ?>
                                                    <?php if ($isHROrAdmin && $request['status'] == 'pending'): ?>
                                                    <td>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="request_id" value="<?= $request['request_id'] ?>">
                                                            <button type="submit" name="action" value="approve" class="btn btn-success btn-sm" onclick="return confirm('Bạn có chắc chắn muốn phê duyệt yêu cầu này?')">
                                                                <i class="bi bi-check-lg"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-danger btn-sm open-reject-modal" data-request-id="<?= $request['request_id'] ?>">
    <i class="bi bi-x-lg"></i>
</button>
                                                        </form>
                                                    </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-info">
                                Không có yêu cầu nào phù hợp với bộ lọc.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Thêm modal này ở cuối file, trước thẻ đóng body -->
<div class="modal fade" id="rejectReasonModal" tabindex="-1" aria-labelledby="rejectReasonModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="rejectReasonModalLabel">Lý do từ chối</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="rejectForm" method="POST">
          <input type="hidden" id="reject_request_id" name="request_id">
          <input type="hidden" name="action" value="reject">
          <div class="mb-3">
            <label for="reason_rejected" class="form-label">Lý do từ chối <span class="text-danger">*</span></label>
            <textarea class="form-control" id="reason_rejected" name="reason_rejected" rows="3" required></textarea>
          </div>
          <div class="text-end">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            <button type="submit" class="btn btn-danger">Từ chối</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal cho từ chối hàng loạt -->
<div class="modal fade" id="bulkRejectModal" tabindex="-1" aria-labelledby="bulkRejectModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="bulkRejectModalLabel">Lý do từ chối hàng loạt</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="bulk_reason_rejected" class="form-label">Lý do từ chối <span class="text-danger">*</span></label>
          <textarea class="form-control" id="bulk_reason_rejected" name="reason_rejected" rows="3" required></textarea>
        </div>
        <div class="text-end">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
          <button type="button" id="confirmBulkReject" class="btn btn-danger">Từ chối</button>
        </div>
      </div>
    </div>
  </div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('selectAll');
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('.request-checkbox');
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = selectAllCheckbox.checked;
                    });
                });
            }



            const rejectButtons = document.querySelectorAll('.open-reject-modal');
    rejectButtons.forEach(button => {
        button.addEventListener('click', function() {
            const requestId = this.getAttribute('data-request-id');
            document.getElementById('reject_request_id').value = requestId;
            
            const rejectModal = new bootstrap.Modal(document.getElementById('rejectReasonModal'));
            rejectModal.show();
        });
    });
    
    // Xử lý từ chối hàng loạt
    const bulkRejectBtn = document.getElementById('bulkRejectBtn');
    if (bulkRejectBtn) {
        bulkRejectBtn.addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('.request-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('Vui lòng chọn ít nhất một yêu cầu để từ chối');
                return;
            }
            
            const bulkRejectModal = new bootstrap.Modal(document.getElementById('bulkRejectModal'));
            bulkRejectModal.show();
        });
    }
    
    // Xử lý xác nhận từ chối hàng loạt
    const confirmBulkReject = document.getElementById('confirmBulkReject');
    if (confirmBulkReject) {
        confirmBulkReject.addEventListener('click', function() {
            const reasonRejected = document.getElementById('bulk_reason_rejected').value;
            if (!reasonRejected.trim()) {
                alert('Vui lòng nhập lý do từ chối');
                return;
            }
            
            // Thêm input reason_rejected vào form
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'reason_rejected';
            input.value = reasonRejected;
            
            const bulkForm = document.getElementById('bulkActionForm');
            bulkForm.appendChild(input);
            
            // Thêm action=bulk_reject vào form
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'bulk_reject';
            bulkForm.appendChild(actionInput);
            
            // Submit form
            bulkForm.submit();
        });
    }
});
      
    </script>
</body>
</html>