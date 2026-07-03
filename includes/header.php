<?php
$flash = getFlash();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(currentLang()) ?>" dir="<?= htmlspecialchars(langDir()) ?>" data-mode="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PLT Solutions – <?= htmlspecialchars($pageTitle ?? t('Dashboard')) ?></title>
<link rel="stylesheet" href="/assignhub/assets/css/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
</head>
<body>

<nav class="topbar">
  <a class="logo" href="/assignhub/dashboard.php" style="padding:0 16px">
    <img id="topbarLogo" src="/assignhub/assets/img/logo-dark.png" alt="PLT Solutions" style="height:36px;width:auto;object-fit:contain">
  </a>
  <div class="topbar-right">
    <button class="toggle-btn" onclick="toggleMode()" id="modeBtn">
      <i class="ti ti-sun" id="modeIcon"></i>
      <span id="modeLbl">Light</span>
    </button>
    <div class="user-info">
      <span class="user-name"><?= htmlspecialchars($_SESSION['name'] ?? '') ?></span>
      <span class="badge badge-<?= isTeacher() ? 'blue' : 'green' ?>"><?= isTeacher() ? t('Giảng viên') : t('Sinh viên') ?></span>
    </div>
    <a href="/assignhub/logout.php" class="btn btn-ghost btn-sm" id="logoutBtn"><i class="ti ti-logout"></i></a>
  </div>
</nav>

<div class="modal-overlay" id="logoutModal" aria-hidden="true">
  <div class="modal-card">
    <div class="modal-title"><?= htmlspecialchars(t('Xác nhận đăng xuất')) ?></div>
    <p><?= htmlspecialchars(t('Bạn có chắc muốn rời khỏi phiên làm việc và đăng xuất khỏi hệ thống?')) ?></p>
    <div class="modal-actions">
      <button type="button" class="btn btn-ghost" id="logoutCancelBtn"><?= htmlspecialchars(t('Hủy')) ?></button>
      <button type="button" class="btn btn-danger" id="logoutConfirmBtn"><?= htmlspecialchars(t('Đăng xuất')) ?></button>
    </div>
  </div>
</div>

<div class="sidebar">
  <a href="/assignhub/dashboard.php" class="nav-item <?= $currentPage==='dashboard'?'active':'' ?>">
    <i class="ti ti-dashboard"></i> Dashboard
  </a>
  <a href="/assignhub/assignments.php" class="nav-item <?= $currentPage==='assignments'?'active':'' ?>">
    <i class="ti ti-file-text"></i> Bài tập
  </a>
  <?php if (isStudent()): ?>
  <a href="/assignhub/submit.php" class="nav-item <?= $currentPage==='submit'?'active':'' ?>">
    <i class="ti ti-upload"></i> Nộp bài
  </a>
  <a href="/assignhub/grades.php" class="nav-item <?= $currentPage==='grades'?'active':'' ?>">
    <i class="ti ti-award"></i> Điểm của tôi
  </a>
  <?php endif; ?>
  <?php if (isTeacher()): ?>
  <a href="/assignhub/grading.php" class="nav-item <?= $currentPage==='grading'?'active':'' ?>">
    <i class="ti ti-robot"></i> Chấm điểm AI
  </a>
  <?php endif; ?>
</div>

<div class="page-content">
<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?>">
  <i class="ti ti-<?= $flash['type']==='success'?'check':'alert-circle' ?>"></i>
  <?= htmlspecialchars($flash['msg']) ?>
</div>
<?php endif; ?>
