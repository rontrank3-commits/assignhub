<?php


require_once __DIR__ . '/includes/db.php';

$SECRET_KEY = 'dCek1_HUZNSin7V_BdlpCJY7NxRjc31y';

if (!isset($_GET['key']) || $_GET['key'] !== $SECRET_KEY) {
    http_response_code(403);
    die('Không có quyền truy cập.');
}

header('Content-Type: text/plain; charset=utf-8');

$statements = [
"CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('teacher','student') NOT NULL DEFAULT 'student',
    class VARCHAR(50),
    student_id VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",

"CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    deadline DATETIME NOT NULL,
    class VARCHAR(50) NOT NULL,
    file_types VARCHAR(100) DEFAULT 'csv,xlsx,pdf',
    teacher_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id)
)",

"CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending','graded') DEFAULT 'pending',
    FOREIGN KEY (assignment_id) REFERENCES assignments(id),
    FOREIGN KEY (student_id) REFERENCES users(id)
)",

"CREATE TABLE IF NOT EXISTS grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL UNIQUE,
    score DECIMAL(4,1) NOT NULL,
    feedback TEXT,
    completeness DECIMAL(3,1) DEFAULT 0,
    correctness DECIMAL(3,1) DEFAULT 0,
    coverage DECIMAL(3,1) DEFAULT 0,
    format_score DECIMAL(3,1) DEFAULT 0,
    graded_by INT,
    graded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES submissions(id),
    FOREIGN KEY (graded_by) REFERENCES users(id)
)",

"INSERT INTO users (name, email, password, role, class, student_id) VALUES
('Phan Gia Phước', 'teacher@tdc.edu.vn', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', NULL, NULL),
('Phan Duy Linh', 'linh@tdc.edu.vn', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'CD24TT3', '24211TT2418'),
('Nguyễn Văn A', 'a@tdc.edu.vn', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'CD24TT3', '24211TT2401'),
('Trần Thị B', 'b@tdc.edu.vn', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'CD24TT3', '24211TT2402')",

"INSERT INTO assignments (title, description, deadline, class, file_types, teacher_id) VALUES
('Thiết kế Test Cases – EP/BVA', 'Thiết kế test cases sử dụng kỹ thuật Equivalence Partitioning và Boundary Value Analysis cho hệ thống đăng nhập.', '2026-06-28 23:59:00', 'CD24TT3', 'csv,xlsx', 1),
('Kiểm thử hệ thống GreenMart', 'Viết test cases kiểm thử chức năng giỏ hàng và thanh toán của GreenMart.', '2026-07-05 23:59:00', 'CD24TT3', 'csv,xlsx,pdf', 1),
('Báo cáo JMeter – Performance', 'Thực hiện kiểm thử hiệu năng với JMeter, nộp file báo cáo kết quả.', '2026-06-20 23:59:00', 'CD24TT3', 'pdf', 1)",
];

try {
    $db = getDB();
    echo "Kết nối database production thành công.\n\n";
} catch (Exception $e) {
    die("Lỗi kết nối database: " . $e->getMessage());
}

foreach ($statements as $i => $sql) {
    try {
        $db->exec($sql);
        echo "[OK] Câu lệnh #" . ($i + 1) . " chạy thành công.\n";
    } catch (PDOException $e) {
        // Bỏ qua lỗi "duplicate entry" nếu chạy lại script (đã insert trước đó)
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            echo "[SKIP] Câu lệnh #" . ($i + 1) . " - dữ liệu đã tồn tại, bỏ qua.\n";
        } else {
            echo "[LỖI] Câu lệnh #" . ($i + 1) . ": " . $e->getMessage() . "\n";
        }
    }
}

echo "\n=== HOÀN TẤT ===\n";
echo "Tài khoản demo (mật khẩu cho tất cả: password):\n";
echo "- Giáo viên: teacher@tdc.edu.vn\n";
echo "- Sinh viên: linh@tdc.edu.vn\n\n";
echo "QUAN TRỌNG: Hãy xoá file _migrate.php khỏi repo ngay sau khi xác nhận thành công!\n";
