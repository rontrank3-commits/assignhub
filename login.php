<?php
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: /assignhub/dashboard.php');
    exit;
}

$error = '';

// Aggregate stats for the dashboard preview (left panel) — safe, non-sensitive counts only
try {
    $db = getDB();
    $previewAssignments = (int)$db->query('SELECT COUNT(*) FROM assignments')->fetchColumn();
    $previewGraded      = (int)$db->query("SELECT COUNT(*) FROM submissions WHERE status='graded'")->fetchColumn();
    $previewTotalSubs   = (int)$db->query('SELECT COUNT(*) FROM submissions')->fetchColumn();
    $previewAvgScore    = $db->query('SELECT AVG(score) FROM grades')->fetchColumn();
    $previewAvgScore    = $previewAvgScore !== null ? round($previewAvgScore, 1) : '—';
} catch (Exception $e) {
    $previewAssignments = 0; $previewGraded = 0; $previewTotalSubs = 0; $previewAvgScore = '—';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['name']       = $user['name'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['class']      = $user['class'];
            $_SESSION['student_id'] = $user['student_id'];

            // Remember me: extend the session cookie lifetime to 30 days
            if (!empty($_POST['remember'])) {
                $params = session_get_cookie_params();
                setcookie(session_name(), session_id(), [
                    'expires'  => time() + 60 * 60 * 24 * 30,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => $params['secure'],
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }

            header('Location: /assignhub/dashboard.php');
            exit;
        } else {
            $error = t('Email hoặc mật khẩu không đúng.');
        }
    } else {
        $error = t('Vui lòng nhập đầy đủ thông tin.');
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(currentLang()) ?>" dir="<?= htmlspecialchars(langDir()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PLT Solutions – <?= htmlspecialchars(t('Đăng nhập')) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --plt:   #3d3d9e;
    --plt2:  #5252c4;
    --plt3:  #2a2a7a;
    --white: #ffffff;
    --gray1: #f5f5f8;
    --gray2: #e8e8f0;
    --gray3: #9898b8;
    --text:  #1a1a2e;
    --text2: #555570;
    --danger:#dc2626;
    --radius: 12px;
  }

  body {
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    min-height: 100vh;
    display: flex;
    background: var(--gray1);
  }

  /* ── LEFT PANEL ── */
  .panel-left {
    width: 46%;
    background: var(--plt);
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 48px 52px;
  }

  /* animated blob background */
  .panel-left::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
      radial-gradient(ellipse 60% 50% at 80% 20%, rgba(255,255,255,.08) 0%, transparent 60%),
      radial-gradient(ellipse 50% 60% at 10% 80%, rgba(255,255,255,.06) 0%, transparent 60%);
    animation: blobShift 8s ease-in-out infinite alternate;
  }
  @keyframes blobShift {
    from { transform: scale(1) rotate(0deg); }
    to   { transform: scale(1.06) rotate(2deg); }
  }

  /* grid lines */
  .panel-left::after {
    content: '';
    position: absolute;
    inset: 0;
    background-image:
      linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px);
    background-size: 48px 48px;
  }

  .panel-brand { position: relative; z-index: 2; }
  .panel-brand img { height: 52px; width: auto; filter: brightness(0) invert(1); }

  .panel-hero { position: relative; z-index: 2; }
  .panel-hero h2 {
    font-size: 32px;
    font-weight: 700;
    color: var(--white);
    line-height: 1.25;
    letter-spacing: -.5px;
    margin-bottom: 14px;
  }
  .panel-hero p {
    font-size: 15px;
    color: rgba(255,255,255,.72);
    line-height: 1.65;
    max-width: 340px;
  }

  .panel-features { position: relative; z-index: 2; display: flex; flex-direction: column; gap: 12px; }
  .feat {
    display: flex;
    align-items: center;
    gap: 12px;
    background: rgba(255,255,255,.1);
    border: 1px solid rgba(255,255,255,.15);
    border-radius: 10px;
    padding: 12px 16px;
    backdrop-filter: blur(4px);
    transition: background .2s;
  }
  .feat:hover { background: rgba(255,255,255,.16); }
  .feat i { font-size: 20px; color: rgba(255,255,255,.9); flex-shrink: 0; }
  .feat-text { font-size: 13px; color: rgba(255,255,255,.85); font-weight: 500; }

  /* Dashboard preview cards */
  .panel-preview {
    position: relative;
    z-index: 2;
    display: flex;
    gap: 10px;
    margin-bottom: 4px;
  }
  .preview-card {
    flex: 1;
    background: rgba(255,255,255,.1);
    border: 1px solid rgba(255,255,255,.15);
    border-radius: 10px;
    padding: 12px 14px;
    backdrop-filter: blur(4px);
    transition: background .2s, transform .2s;
  }
  .preview-card:hover { background: rgba(255,255,255,.16); transform: translateY(-2px); }
  .preview-card-icon {
    width: 26px; height: 26px; border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(255,255,255,.15);
    color: var(--white); font-size: 13px;
    margin-bottom: 8px;
  }
  .preview-card-num { font-size: 18px; font-weight: 700; color: var(--white); line-height: 1; }
  .preview-card-lbl { font-size: 10.5px; color: rgba(255,255,255,.65); margin-top: 4px; }

  /* Entrance animations */
  @keyframes fadeLeft {
    from { opacity: 0; transform: translateX(-18px); }
    to   { opacity: 1; transform: translateX(0); }
  }
  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .panel-brand    { animation: fadeLeft .6s ease both; }
  .panel-hero     { animation: fadeLeft .6s ease .1s both; }
  .panel-preview  { animation: fadeUp .6s ease .2s both; }
  .panel-features .feat:nth-child(1) { animation: fadeUp .5s ease .3s both; }
  .panel-features .feat:nth-child(2) { animation: fadeUp .5s ease .38s both; }
  .panel-features .feat:nth-child(3) { animation: fadeUp .5s ease .46s both; }
  .login-card     { animation: fadeUp .6s ease .15s both; }

  /* ── RIGHT PANEL ── */
  .panel-right {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 48px 40px;
  }

  .login-card {
    width: 100%;
    max-width: 400px;
  }

  .login-card h1 {
    font-size: 24px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 6px;
    letter-spacing: -.4px;
  }
  .login-card .subtitle {
    font-size: 14px;
    color: var(--text2);
    margin-bottom: 36px;
  }

  .form-group { margin-bottom: 20px; }

  .form-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 7px;
    letter-spacing: .01em;
  }

  .input-wrap {
    position: relative;
  }
  .input-wrap i {
    position: absolute;
    left: 13px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 17px;
    color: var(--gray3);
    pointer-events: none;
    transition: color .2s;
  }
  .input-wrap input {
    width: 100%;
    padding: 11px 14px 11px 40px;
    border: 1.5px solid var(--gray2);
    border-radius: var(--radius);
    font-size: 14px;
    color: var(--text);
    background: var(--white);
    outline: none;
    transition: border-color .2s, box-shadow .2s;
    font-family: inherit;
  }
  .input-wrap input::placeholder { color: var(--gray3); }
  .input-wrap input:focus {
    border-color: var(--plt);
    box-shadow: 0 0 0 3px rgba(61,61,158,.12);
  }
  .input-wrap input:focus + i,
  .input-wrap:focus-within i { color: var(--plt); }

  /* toggle password */
  .pw-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    color: var(--gray3);
    font-size: 17px;
    padding: 2px;
    line-height: 1;
    transition: color .2s;
  }
  .pw-toggle:hover { color: var(--plt); }

  .pw-wrap input { padding-right: 42px; }

  /* remember me + forgot password */
  .form-row-between {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 22px;
    margin-top: -6px;
  }
  .checkbox-wrap {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 13px;
    color: var(--text2);
    cursor: pointer;
    user-select: none;
  }
  .checkbox-wrap input[type="checkbox"] {
    width: 15px; height: 15px;
    accent-color: var(--plt);
    cursor: pointer;
  }
  .forgot-link {
    font-size: 13px;
    color: var(--plt);
    text-decoration: none;
    font-weight: 500;
    transition: color .2s;
  }
  .forgot-link:hover { color: var(--plt2); text-decoration: underline; }

  /* error */
  .alert-error {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: var(--radius);
    padding: 11px 14px;
    font-size: 13px;
    color: var(--danger);
    margin-bottom: 20px;
    animation: shake .35s ease;
  }
  @keyframes shake {
    0%,100% { transform: translateX(0); }
    20%      { transform: translateX(-6px); }
    60%      { transform: translateX(6px); }
  }

  /* submit button */
  .btn-login {
    width: 100%;
    padding: 13px;
    background: var(--plt);
    color: var(--white);
    border: none;
    border-radius: var(--radius);
    font-size: 15px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: background .2s, transform .1s, box-shadow .2s;
    box-shadow: 0 2px 12px rgba(61,61,158,.3);
    margin-top: 8px;
  }
  .btn-login:hover {
    background: var(--plt2);
    box-shadow: 0 4px 20px rgba(61,61,158,.4);
  }
  .btn-login:active { transform: scale(.98); }


  /* demo hint */
  .demo-hint {
    margin-top: 28px;
    background: var(--gray1);
    border: 1px dashed var(--gray2);
    border-radius: var(--radius);
    padding: 14px 16px;
    font-size: 12px;
    color: var(--text2);
    line-height: 1.8;
  }
  .demo-hint strong { color: var(--plt); }
  .demo-hint .hint-title {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--gray3);
    margin-bottom: 6px;
    display: block;
  }
  .demo-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 3px 0;
    border-radius: 6px;
    transition: background .15s;
    padding: 3px 6px;
    margin: 0 -6px;
  }
  .demo-row:hover { background: var(--gray2); }
  .demo-row span { font-family: 'Courier New', monospace; font-size: 12px; }

  .register-link {
    text-align: center;
    margin-top: 18px;
    font-size: 13px;
    color: var(--text2);
  }
  .register-link a { color: var(--plt); font-weight: 600; text-decoration: none; }
  .register-link a:hover { text-decoration: underline; }

  /* responsive */
  @media (max-width: 720px) {
    .panel-left { display: none; }
    .panel-right { padding: 32px 24px; }
  }
</style>
</head>
<body>

<!-- LEFT BRAND PANEL -->
<div class="panel-left">
  <div class="panel-brand">
    <img src="/assignhub/assets/img/logo-dark.png" alt="PLT Solutions">
  </div>

  <div class="panel-hero">
    <h2><?= htmlspecialchars(t('Quản lý bài tập')) ?><br><?= htmlspecialchars(t('thông minh hơn.')) ?></h2>
    <p><?= htmlspecialchars(t('Nền tảng nộp bài & chấm điểm tích hợp AI — giúp giảng viên tiết kiệm thời gian, sinh viên nắm rõ tiến độ.')) ?></p>
  </div>

  <div class="panel-preview">
    <div class="preview-card">
      <div class="preview-card-icon"><i class="ti ti-target-arrow"></i></div>
      <div class="preview-card-num"><?= $previewAvgScore ?></div>
      <div class="preview-card-lbl"><?= htmlspecialchars(t('Điểm TB')) ?></div>
    </div>
    <div class="preview-card">
      <div class="preview-card-icon"><i class="ti ti-files"></i></div>
      <div class="preview-card-num"><?= $previewTotalSubs ?></div>
      <div class="preview-card-lbl"><?= htmlspecialchars(t('Bài nộp')) ?></div>
    </div>
    <div class="preview-card">
      <div class="preview-card-icon"><i class="ti ti-robot"></i></div>
      <div class="preview-card-num"><?= $previewGraded ?></div>
      <div class="preview-card-lbl"><?= htmlspecialchars(t('AI đã chấm')) ?></div>
    </div>
  </div>

  <div class="panel-features">
    <div class="feat">
      <i class="ti ti-robot"></i>
      <span class="feat-text"><?= htmlspecialchars(t('Chấm điểm hỗ trợ AI theo rubric')) ?></span>
    </div>
    <div class="feat">
      <i class="ti ti-upload"></i>
      <span class="feat-text"><?= htmlspecialchars(t('Nộp bài trực tuyến, theo dõi trạng thái')) ?></span>
    </div>
    <div class="feat">
      <i class="ti ti-chart-bar"></i>
      <span class="feat-text"><?= htmlspecialchars(t('Thống kê tiến độ lớp học theo thời gian thực')) ?></span>
    </div>
  </div>
</div>

<!-- RIGHT FORM PANEL -->
<div class="panel-right">
  <div class="login-card">
    <h1><?= htmlspecialchars(t('Đăng nhập')) ?></h1>
    <p class="subtitle"><?= htmlspecialchars(t('Nhập thông tin tài khoản để tiếp tục')) ?></p>
    <?php if ($error): ?>
    <div class="alert-error">
      <i class="ti ti-alert-circle"></i>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="loginForm">
      <div class="form-group">
        <label class="form-label" for="email"><?= htmlspecialchars(t('Email')) ?></label>
        <div class="input-wrap">
          <input
            type="email" id="email" name="email"
            placeholder="name@tdc.edu.vn" required autocomplete="email"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          <i class="ti ti-mail"></i>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="password"><?= htmlspecialchars(t('Mật khẩu')) ?></label>
        <div class="input-wrap pw-wrap">
          <input
            type="password" id="password" name="password"
            placeholder="••••••••" required autocomplete="current-password">
          <i class="ti ti-lock"></i>
          <button type="button" class="pw-toggle" onclick="togglePw()" id="pwToggleBtn" aria-label="Hiện/ẩn mật khẩu">
            <i class="ti ti-eye" id="pwIcon"></i>
          </button>
        </div>
      </div>

      <div class="form-row-between">
        <label class="checkbox-wrap">
          <input type="checkbox" name="remember" id="remember">
          <span><?= htmlspecialchars(t('Remember me')) ?></span>
        </label>
        <a href="#" class="forgot-link" onclick="alert('<?= htmlspecialchars(t('Vui lòng liên hệ giảng viên/quản trị viên để đặt lại mật khẩu.')) ?>'); return false;"><?= htmlspecialchars(t('Forgot password?')) ?></a>
      </div>

      <button type="submit" class="btn-login">
        <i class="ti ti-login"></i> <?= htmlspecialchars(t('Đăng nhập')) ?>
      </button>
    </form>

      <div style="margin-top:12px; text-align:center">
        <a href="/assignhub/index.php" style="color:var(--plt); font-weight:600; text-decoration:none;">← <?= htmlspecialchars(t('Về trang chủ')) ?></a>
      </div>

    <!-- Demo accounts hint -->
    <div class="demo-hint">
      <span class="hint-title"><?= htmlspecialchars(t('Tài khoản demo')) ?></span>
      <div class="demo-row" onclick="fillDemo('teacher@edu.vn','password')" title="<?= htmlspecialchars(t('Click để điền tự động')) ?>">
        <span>👨‍🏫 <?= htmlspecialchars(t('Giảng viên')) ?></span>
        <span><strong>teacher@edu.vn</strong> / password</span>
      </div>
      <div class="demo-row" onclick="fillDemo('linh@edu.vn','password')" title="<?= htmlspecialchars(t('Click để điền tự động')) ?>">
        <span>👩‍🎓 <?= htmlspecialchars(t('Sinh viên')) ?></span>
        <span><strong>linh@edu.vn</strong> / password</span>
      </div>
    </div>

    <div class="register-link">
      <?= htmlspecialchars(t('Chưa có tài khoản?')) ?> <a href="/assignhub/register.php"><?= htmlspecialchars(t('Đăng ký ngay')) ?></a>
    </div>
  </div>
</div>

<script>
function togglePw() {
  const inp = document.getElementById('password');
  const ico = document.getElementById('pwIcon');
  if (inp.type === 'password') {
    inp.type = 'text';
    ico.className = 'ti ti-eye-off';
  } else {
    inp.type = 'password';
    ico.className = 'ti ti-eye';
  }
}

// Click demo row → auto-fill + soft focus animation
function fillDemo(email, pw) {
  const eInp = document.getElementById('email');
  const pInp = document.getElementById('password');
  eInp.value = email;
  pInp.value = pw;
  // brief highlight
  [eInp, pInp].forEach(el => {
    el.style.transition = 'box-shadow .15s';
    el.style.boxShadow = '0 0 0 3px rgba(61,61,158,.22)';
    setTimeout(() => el.style.boxShadow = '', 600);
  });
}
</script>
<script src="/assignhub/assets/js/app.js"></script>
</body>
</html>
