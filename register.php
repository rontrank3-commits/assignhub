<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';
$success = false;
$old = ['name' => '', 'email' => '', 'class' => '', 'student_id' => ''];

$db = getDB();

// Existing classes, for the datalist suggestion (avoids typos that don't match any assignment)
try {
    $existingClasses = $db->query("SELECT DISTINCT class FROM users WHERE class IS NOT NULL AND class != '' ORDER BY class")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $existingClasses = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['name']       = trim($_POST['name'] ?? '');
    $old['email']       = trim($_POST['email'] ?? '');
    $old['class']       = trim($_POST['class'] ?? '');
    $old['student_id']  = trim($_POST['student_id'] ?? '');
    $password           = $_POST['password'] ?? '';
    $passwordConfirm    = $_POST['password_confirm'] ?? '';

    if (!$old['name'] || !$old['email'] || !$old['class'] || !$old['student_id'] || !$password) {
        $error = 'Vui lòng nhập đầy đủ thông tin.';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ.';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Mật khẩu nhập lại không khớp.';
    } else {
        $check = $db->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$old['email']]);
        if ($check->fetch()) {
            $error = 'Email này đã được đăng ký.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO users (name, email, password, role, class, student_id) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$old['name'], $old['email'], $hash, 'student', $old['class'], $old['student_id']]);
            $success = true;
        }
    }
}

$pageTitle = 'Đăng ký';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PLT Solutions – Đăng ký</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --plt:   #3d3d9e;
    --plt2:  #5252c4;
    --white: #ffffff;
    --gray1: #f5f5f8;
    --gray2: #e8e8f0;
    --gray3: #9898b8;
    --text:  #1a1a2e;
    --text2: #555570;
    --success: #16a34a;
    --danger:#dc2626;
    --radius: 12px;
  }

  body {
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--gray1);
    padding: 24px;
  }

  .register-card {
    width: 100%;
    max-width: 440px;
    background: var(--white);
    border-radius: 16px;
    padding: 40px;
    box-shadow: 0 10px 40px rgba(0,0,0,.06);
    animation: fadeUp .5s ease both;
  }
  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  .register-logo { text-align: center; margin-bottom: 22px; }
  .register-logo img { height: 40px; width: auto; }

  .register-card h1 {
    font-size: 22px;
    font-weight: 700;
    color: var(--text);
    text-align: center;
    margin-bottom: 4px;
    letter-spacing: -.3px;
  }
  .register-card .subtitle {
    font-size: 13.5px;
    color: var(--text2);
    text-align: center;
    margin-bottom: 28px;
  }

  .form-row { display: flex; gap: 12px; }
  .form-row .form-group { flex: 1; }
  .form-group { margin-bottom: 16px; }

  .form-label {
    display: block;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 6px;
  }

  .input-wrap { position: relative; }
  .input-wrap i {
    position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
    font-size: 16px; color: var(--gray3); pointer-events: none; transition: color .2s;
  }
  .input-wrap input {
    width: 100%;
    padding: 10px 14px 10px 38px;
    border: 1.5px solid var(--gray2);
    border-radius: var(--radius);
    font-size: 13.5px;
    color: var(--text);
    background: var(--white);
    outline: none;
    transition: border-color .2s, box-shadow .2s;
    font-family: inherit;
  }
  .input-wrap input:focus { border-color: var(--plt); box-shadow: 0 0 0 3px rgba(61,61,158,.12); }
  .input-wrap input:focus + i, .input-wrap:focus-within i { color: var(--plt); }

  .btn-register {
    width: 100%;
    padding: 12px;
    background: var(--plt);
    color: var(--white);
    border: none;
    border-radius: var(--radius);
    font-size: 14.5px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    transition: background .2s, transform .1s, box-shadow .2s;
    box-shadow: 0 2px 12px rgba(61,61,158,.3);
    margin-top: 6px;
  }
  .btn-register:hover { background: var(--plt2); box-shadow: 0 4px 20px rgba(61,61,158,.4); }
  .btn-register:active { transform: scale(.98); }

  .alert-error {
    display: flex; align-items: center; gap: 8px;
    background: #fef2f2; border: 1px solid #fecaca; border-radius: var(--radius);
    padding: 11px 14px; font-size: 13px; color: var(--danger); margin-bottom: 18px;
  }
  .alert-success {
    text-align: center;
    padding: 12px 0 4px;
  }
  .alert-success i { font-size: 44px; color: var(--success); margin-bottom: 12px; display: block; }
  .alert-success h2 { font-size: 18px; color: var(--text); margin-bottom: 6px; }
  .alert-success p { font-size: 13.5px; color: var(--text2); margin-bottom: 24px; }

  .back-link {
    display: block;
    text-align: center;
    margin-top: 20px;
    font-size: 13px;
    color: var(--text2);
  }
  .back-link a { color: var(--plt); font-weight: 600; text-decoration: none; }
  .back-link a:hover { text-decoration: underline; }

  .form-hint { font-size: 11.5px; color: var(--gray3); margin-top: 5px; }
</style>
</head>
<body>

<div class="register-card">
  <div class="register-logo">
    <img src="/assets/img/logo-dark.png" alt="PLT Solutions">
  </div>

  <?php if ($success): ?>
    <div class="alert-success">
      <i class="ti ti-circle-check"></i>
      <h2>Đăng ký thành công!</h2>
      <p>Tài khoản của bạn đã được tạo. Hãy đăng nhập để bắt đầu sử dụng AssignHub.</p>
      <a href="/login.php" class="btn-register" style="text-decoration:none">
        <i class="ti ti-login"></i> Đến trang đăng nhập
      </a>
    </div>
  <?php else: ?>

  <h1>Tạo tài khoản sinh viên</h1>
  <p class="subtitle">Đăng ký để nộp bài và theo dõi tiến độ học tập</p>

  <?php if ($error): ?>
  <div class="alert-error">
    <i class="ti ti-alert-circle"></i>
    <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label class="form-label" for="name">Họ và tên</label>
      <div class="input-wrap">
        <input type="text" id="name" name="name" placeholder="Nguyễn Văn A" required
               value="<?= htmlspecialchars($old['name']) ?>">
        <i class="ti ti-user"></i>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="email">Email</label>
      <div class="input-wrap">
        <input type="email" id="email" name="email" placeholder="name@tdc.edu.vn" required autocomplete="email"
               value="<?= htmlspecialchars($old['email']) ?>">
        <i class="ti ti-mail"></i>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label" for="class">Lớp</label>
        <div class="input-wrap">
          <input type="text" id="class" name="class" placeholder="CD24TT3" required list="classList"
                 value="<?= htmlspecialchars($old['class']) ?>">
          <i class="ti ti-school"></i>
        </div>
        <datalist id="classList">
          <?php foreach ($existingClasses as $c): ?>
          <option value="<?= htmlspecialchars($c) ?>">
          <?php endforeach; ?>
        </datalist>
      </div>
      <div class="form-group">
        <label class="form-label" for="student_id">Mã số SV</label>
        <div class="input-wrap">
          <input type="text" id="student_id" name="student_id" placeholder="24211TT2418" required
                 value="<?= htmlspecialchars($old['student_id']) ?>">
          <i class="ti ti-id"></i>
        </div>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="password">Mật khẩu</label>
      <div class="input-wrap">
        <input type="password" id="password" name="password" placeholder="Ít nhất 6 ký tự" required autocomplete="new-password">
        <i class="ti ti-lock"></i>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="password_confirm">Nhập lại mật khẩu</label>
      <div class="input-wrap">
        <input type="password" id="password_confirm" name="password_confirm" placeholder="Nhập lại mật khẩu" required autocomplete="new-password">
        <i class="ti ti-lock-check"></i>
      </div>
    </div>

    <button type="submit" class="btn-register">
      <i class="ti ti-user-plus"></i> Đăng ký
    </button>
  </form>

  <div class="back-link">
    Đã có tài khoản? <a href="/login.php">Đăng nhập</a>
  </div>

  <div class="back-link" style="margin-top:8px;">
    <a href="/index.php">← Về trang chủ</a>
  </div>

  <?php endif; ?>
</div>

</body>
</html>
