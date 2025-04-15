<?php
include dirname(__DIR__) . '/partials/header.php'; 

?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-warning text-white">
                    <h4 class="mb-0">Quên mật khẩu</h4>
                </div>
                <div class="card-body">
                    <p class="text-muted">Nhập tên đăng nhập hoặc email để khôi phục mật khẩu.</p>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="recovery" class="form-label">Tên đăng nhập hoặc Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="text" class="form-control" id="recovery" name="recovery" required>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-warning">Gửi yêu cầu</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center">
                    <a href="login.php" class="text-decoration-none">Quay lại đăng nhập</a>
                </div>
            </div>
        </div>
    </div>
</div>


<?php
include('../partials/footer.php');
?>