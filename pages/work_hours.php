<?php
require_once dirname(__DIR__) . '/config/db.php';
$currentUser = checkPermission('admin');

$message = '';
$messageType = '';



if (isset($_POST['update_shift'])) {
    $shift_id = $_POST['shift_id'];
    $shift_name = trim($_POST['shift_name']);
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];

    if (empty($shift_name) || empty($start_time) || empty($end_time)) {
        $message = "Vui lòng điền đầy đủ thông tin!";
        $messageType = "danger";
    } else if (strtotime($end_time) <= strtotime($start_time)) {
        $message = "Thời gian kết thúc phải sau thời gian bắt đầu!";
        $messageType = "danger";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM shifts WHERE shift_name = ? AND shift_id != ?");
            $stmt->execute([$shift_name, $shift_id]);
            if ($stmt->fetchColumn() > 0) {
                $message = "Tên ca '$shift_name' đã tồn tại!";
                $messageType = "danger";
            } else {
                $stmt = $pdo->prepare("UPDATE shifts SET shift_name = ?, start_time = ?, end_time = ? WHERE shift_id = ?");
                $stmt->execute([$shift_name, $start_time, $end_time, $shift_id]);
                $message = "Cập nhật ca làm việc thành công!";
                $messageType = "success";
            }
        } catch (PDOException $e) {
            $message = "Lỗi: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}



$stmt = $pdo->query("SELECT * FROM shifts ORDER BY start_time");
$shifts = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý giờ làm việc</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
</head>

<body>
    <?php include dirname(__DIR__) . '/partials/header.php'; ?>

    <main class="content px-3 py-2">
        <div class="container-fluid">
            <div class="mb-3">
                <h4>Quản lý giờ làm việc</h4>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>



            <div class="card border-0 mt-4">
                <div class="card-header">
                    <h5 class="card-title">Danh sách ca làm việc</h5>
                </div>
                <div class="card-body">
                    <table id="shifts-table" class="table table-striped table-hover">
                        <thead>
                            <tr>
                             
                                <th>Tên ca làm việc</th>
                                <th>Giờ bắt đầu</th>
                                <th>Giờ kết thúc</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shifts as $shift): ?>
                                <tr>
                                 
                                    <td><?php echo htmlspecialchars($shift['shift_name']); ?></td>
                                    <td><?php echo date('H:i', strtotime($shift['start_time'])); ?></td>
                                    <td><?php echo date('H:i', strtotime($shift['end_time'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary edit-shift"
                                            data-id="<?php echo $shift['shift_id']; ?>"
                                            data-name="<?php echo htmlspecialchars($shift['shift_name']); ?>"
                                            data-start="<?php echo $shift['start_time']; ?>"
                                            data-end="<?php echo $shift['end_time']; ?>">
                                            <i class="bi bi-pencil"></i> Sửa
                                        </button>
                                   
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="editShiftModal" tabindex="-1" aria-labelledby="editShiftModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editShiftModalLabel">Sửa ca làm việc</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="shift_id" id="edit_shift_id">
                        <div class="mb-3">
                            <label for="edit_shift_name" class="form-label">Tên ca làm việc</label>
                            <input type="text" class="form-control" id="edit_shift_name" name="shift_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_start_time" class="form-label">Giờ bắt đầu</label>
                            <input type="time" class="form-control" id="edit_start_time" name="start_time" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_end_time" class="form-label">Giờ kết thúc</label>
                            <input type="time" class="form-control" id="edit_end_time" name="end_time" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" name="update_shift" class="btn btn-primary">Lưu thay đổi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"
        integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script>
        $(document).ready(function () {




            $('.edit-shift').click(function () {
                var id = $(this).data('id');
                var name = $(this).data('name');
                var start = $(this).data('start');
                var end = $(this).data('end');

                $('#edit_shift_id').val(id);
                $('#edit_shift_name').val(name);
                $('#edit_start_time').val(start);
                $('#edit_end_time').val(end);

                $('#editShiftModal').modal('show');
            });


            setTimeout(function () {
                $('.alert').alert('close');
            }, 5000);
        });
    </script>
</body>

</html>