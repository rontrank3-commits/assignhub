<?php
// =====================================================================
// SCRIPT SỬA LỖI ENCODING - CHỈ DÙNG 1 LẦN
// Mục đích: database production được hosting tạo sẵn với charset mặc định
// không phải utf8mb4 (thường là latin1), khiến các ký tự tiếng Việt có
// dấu đặc biệt (ế, ử, ệ, ố...) bị lưu sai thành dấu "?" không thể phục hồi.
// Script này chuyển toàn bộ bảng sang utf8mb4 và insert lại dữ liệu mẫu
// với encoding đúng.
//
// Truy cập: https://assignhub.plt.pro.vn/_fix_encoding.php?key=<xem giá trị SECRET_KEY khai báo bên dưới>
// XOÁ FILE NÀY khỏi repo ngay sau khi chạy xong và xác nhận thành công.
// =====================================================================

require_once __DIR__ . '/includes/db.php';

$SECRET_KEY = 'dCek1_HUZNSin7V_BdlpCJY7NxRjc31y'; // gitleaks:allow - token tạm 1 lần, không phải credential thật

if (!isset($_GET['key']) || $_GET['key'] !== $SECRET_KEY) {
    http_response_code(403);
    die('Không có quyền truy cập.');
}

header('Content-Type: text/plain; charset=utf-8');

try {
    $db = getDB();
    echo "Kết nối database production thành công.\n\n";
} catch (Exception $e) {
    die("Lỗi kết nối database: " . $e->getMessage());
}

// Bước 1: chuyển toàn bộ bảng sang utf8mb4 để lưu đúng ký tự tiếng Việt
$tables = ['users', 'assignments', 'submissions', 'grades'];
foreach ($tables as $t) {
    try {
        $db->exec("ALTER TABLE `$t` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "[OK] Đã chuyển bảng '$t' sang utf8mb4.\n";
    } catch (PDOException $e) {
        echo "[LỖI] Bảng '$t': " . $e->getMessage() . "\n";
    }
}

echo "\n";

// Bước 2: xoá dữ liệu mẫu cũ (đã bị hỏng ký tự) và insert lại đúng
try {
    $db->exec("DELETE FROM grades");
    $db->exec("DELETE FROM submissions");
    $db->exec("DELETE FROM assignments");
    $db->exec("DELETE FROM users");
    // Reset auto-increment về 1 để id khớp lại với dữ liệu insert bên dưới
    $db->exec("ALTER TABLE users AUTO_INCREMENT = 1");
    $db->exec("ALTER TABLE assignments AUTO_INCREMENT = 1");
    echo "[OK] Đã xoá dữ liệu mẫu cũ bị lỗi encoding.\n";
} catch (PDOException $e) {
    echo "[LỖI] Khi xoá dữ liệu cũ: " . $e->getMessage() . "\n";
}

$hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; // = "password"

try {
    $stmt = $db->prepare(
        "INSERT INTO users (name, email, password, role, class, student_id) VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute(['Phan Gia Phước', 'teacher@tdc.edu.vn', $hash, 'teacher', null, null]);
    $stmt->execute(['Phan Duy Linh', 'linh@tdc.edu.vn', $hash, 'student', 'CD24TT3', '24211TT2418']);
    $stmt->execute(['Nguyễn Văn A', 'a@tdc.edu.vn', $hash, 'student', 'CD24TT3', '24211TT2401']);
    $stmt->execute(['Trần Thị B', 'b@tdc.edu.vn', $hash, 'student', 'CD24TT3', '24211TT2402']);
    echo "[OK] Đã insert lại 4 user với encoding đúng.\n";

    $stmt2 = $db->prepare(
        "INSERT INTO assignments (title, description, deadline, class, file_types, teacher_id) VALUES (?, ?, ?, ?, ?, 1)"
    );
    $stmt2->execute(['Thiết kế Test Cases – EP/BVA', 'Thiết kế test cases sử dụng kỹ thuật Equivalence Partitioning và Boundary Value Analysis cho hệ thống đăng nhập.', '2026-06-28 23:59:00', 'CD24TT3', 'csv,xlsx']);
    $stmt2->execute(['Kiểm thử hệ thống GreenMart', 'Viết test cases kiểm thử chức năng giỏ hàng và thanh toán của GreenMart.', '2026-07-05 23:59:00', 'CD24TT3', 'csv,xlsx,pdf']);
    $stmt2->execute(['Báo cáo JMeter – Performance', 'Thực hiện kiểm thử hiệu năng với JMeter, nộp file báo cáo kết quả.', '2026-06-20 23:59:00', 'CD24TT3', 'pdf']);
    echo "[OK] Đã insert lại 3 bài tập mẫu với encoding đúng.\n";
} catch (PDOException $e) {
    echo "[LỖI] Khi insert lại dữ liệu: " . $e->getMessage() . "\n";
}

echo "\n=== HOÀN TẤT ===\n";
echo "Tài khoản demo (mật khẩu cho tất cả: password):\n";
echo "- Giáo viên: teacher@tdc.edu.vn\n";
echo "- Sinh viên: linh@tdc.edu.vn\n\n";
echo "QUAN TRỌNG: Hãy xoá file _fix_encoding.php khỏi repo ngay sau khi xác nhận thành công!\n";