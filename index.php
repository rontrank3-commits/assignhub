<?php
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: /dashboard.php');
    exit;
}

// Public, non-sensitive aggregate stats for the landing page
$db = getDB();
try {
    $statAssignments = (int)$db->query('SELECT COUNT(*) FROM assignments')->fetchColumn();
    $statSubmissions = (int)$db->query('SELECT COUNT(*) FROM submissions')->fetchColumn();
    $statGraded      = (int)$db->query("SELECT COUNT(*) FROM submissions WHERE status='graded'")->fetchColumn();
} catch (Exception $e) {
    $statAssignments = 0; $statSubmissions = 0; $statGraded = 0;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(currentLang()) ?>" dir="<?= htmlspecialchars(langDir()) ?>" data-mode="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PLT Solutions</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  html { scroll-behavior: smooth; }

  :root {
    --bg: #f8fbff;
    --surface: #ffffff;
    --surface-soft: #f2f5ff;
    --text: #0f172a;
    --text2: #475569;
    --text3: #64748b;
    --border: #e2e8f0;
    --accent: #4f46e5;
    --accent-soft: #eef2ff;
    --accent2: #6366f1;
    --radius: 20px;
    --shadow: 0 24px 80px rgba(15, 23, 42, .08);
  }

  html[data-mode="dark"] {
    --bg: #05070f;
    --surface: #111827;
    --surface-soft: #141b2d;
    --text: #f8fafc;
    --text2: #cbd5e1;
    --text3: #94a3b8;
    --border: #1f2937;
    --accent: #818cf8;
    --accent-soft: #1e293b;
    --accent2: #a5b4fc;
    --shadow: 0 24px 80px rgba(0, 0, 0, .35);
  }

  body {
    min-height: 100vh;
    font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
    font-size: 15px;
    line-height: 1.65;
    background: radial-gradient(circle at top, rgba(79, 70, 229, .12), transparent 30%),
                radial-gradient(circle at 20% 10%, rgba(99, 102, 241, .12), transparent 16%),
                var(--bg);
    color: var(--text);
    transition: background .25s, color .25s;
  }

  a { color: inherit; text-decoration: none; }
  button { font: inherit; }

  .navbar {
    position: sticky;
    top: 0;
    z-index: 99;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 20px 6%;
    background: rgba(255, 255, 255, .92);
    backdrop-filter: blur(18px);
    border-bottom: 1px solid rgba(226, 232, 240, .9);
  }
  html[data-mode="dark"] .navbar {
    background: rgba(15, 23, 42, .88);
    border-color: rgba(55, 65, 81, .9);
  }

  .navbar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .navbar-brand img { width: 42px; height: auto; }
  .navbar-brand-name { font-size: 18px; font-weight: 800; color: var(--text); }
  .navbar-brand-sub { font-size: 13px; color: var(--text3); }

  .navbar-menu { display: flex; align-items: center; gap: 28px; }
  .navbar-menu a { font-size: 14px; font-weight: 500; color: var(--text2); transition: color .2s; }
  .navbar-menu a:hover { color: var(--accent); }

  .navbar-actions { display: flex; align-items: center; gap: 12px; }
  .mode-toggle {
    width: 40px; height: 40px; border-radius: 14px;
    border: 1px solid var(--border); background: var(--surface);
    display: grid; place-items: center;
    color: var(--text2); cursor: pointer; transition: all .2s;
  }
  .mode-toggle:hover { border-color: var(--accent); color: var(--accent); }

  .btn-nav-cta,
  .btn-hero-primary,
  .btn-hero-secondary {
    display: inline-flex; align-items: center; justify-content: center;
    gap: 8px; border-radius: 999px; padding: 14px 24px;
    font-weight: 700; transition: all .2s;
  }
  .btn-nav-cta,
  .btn-hero-primary {
    background: var(--accent); color: #fff;
  }
  .btn-nav-cta:hover,
  .btn-hero-primary:hover { transform: translateY(-1px); opacity: .96; }
  .btn-hero-secondary {
    background: transparent; color: var(--text);
    border: 1.5px solid var(--border);
  }
  .btn-hero-secondary:hover { border-color: var(--accent); color: var(--accent); transform: translateY(-1px); }

  .hero {
    display: grid; grid-template-columns: 1.1fr .9fr; gap: 42px;
    align-items: center; padding: 90px 6% 64px;
  }
  .hero-eyebrow {
    display: inline-flex; padding: 10px 15px; border-radius: 999px;
    background: var(--accent-soft); color: var(--accent);
    font-size: 12px; font-weight: 700; letter-spacing: .08em;
    text-transform: uppercase; margin-bottom: 22px;
  }
  .hero h1 {
    font-size: clamp(3.5rem, 5vw, 5.2rem);
    line-height: 1.01; color: var(--text); max-width: 780px;
  }
  .hero h1 .accent { color: var(--accent); display: inline-block; }
  .hero p { max-width: 560px; margin: 28px 0 34px; color: var(--text2); font-size: 1.05rem; }
  .hero-actions { display: flex; flex-wrap: wrap; gap: 16px; }

  .hero-keypoints {
    display: grid; gap: 14px; max-width: 560px;
  }
  .hero-keypoint {
    display: grid; grid-template-columns: auto 1fr; gap: 14px;
    padding: 18px 20px; border-radius: 18px; background: var(--surface);
    border: 1px solid var(--border); box-shadow: 0 18px 40px rgba(15, 23, 42, .05);
  }
  .hero-keypoint i {
    width: 42px; height: 42px; display: grid; place-items: center;
    border-radius: 14px; background: var(--accent-soft); color: var(--accent);
    font-size: 18px;
  }
  .hero-keypoint strong { font-size: 15px; color: var(--text); }
  .hero-keypoint span { font-size: 14px; color: var(--text3); }

  .hero-right { position: relative; display: grid; place-items: center; }
  .hero-right::before {
    content: '';
    position: absolute; inset: 0;
    border-radius: 34px;
    background: radial-gradient(circle at top left, rgba(79, 70, 229, .15), transparent 28%);
    pointer-events: none;
  }
  .hero-visual {
    position: relative; width: 100%; max-width: 580px; padding: 32px;
    border-radius: 34px; overflow: hidden;
    background: rgba(255,255,255,.78);
    border: 1px solid rgba(255,255,255,.8);
    backdrop-filter: blur(18px);
    box-shadow: var(--shadow);
  }
  .hero-visual::before {
    content: '';
    position: absolute; inset: -24px; z-index: -1;
    border-radius: 42px;
    background: radial-gradient(circle at top left, rgba(79,70,229,.18), transparent 30%);
  }
  .hero-visual::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 34px;
    background: linear-gradient(180deg, rgba(255,255,255,.65), rgba(255,255,255,0));
    pointer-events: none;
  }
  .panel-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
  .badge { padding: 10px 14px; border-radius: 999px; background: rgba(79,70,229,.14); color: var(--accent); font-size: 13px; font-weight: 700; }
  .status { color: var(--text3); font-size: 13px; }
  .panel-title { font-size: 22px; font-weight: 700; line-height: 1.25; color: var(--text); margin-bottom: 10px; }
  .panel-copy { color: var(--text2); font-size: 14px; line-height: 1.8; max-width: 420px; margin-bottom: 26px; }

  .dashboard-pills { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-bottom: 20px; }
  .dashboard-pill {
    padding: 18px 16px; border-radius: 22px;
    background: rgba(255,255,255,.75); border: 1px solid rgba(255,255,255,.6);
    box-shadow: inset 0 1px 0 rgba(255,255,255,.8), 0 8px 24px rgba(15,23,42,.05);
  }
  .dashboard-pill strong { display: block; font-size: 28px; font-weight: 800; color: var(--text); }
  .dashboard-pill span { display: block; margin-top: 6px; font-size: 13px; color: var(--text3); }

  .dashboard-chart { padding: 20px 0; margin-bottom: 20px; }
  .chart-title { font-size: 14px; font-weight: 700; color: var(--text); margin-bottom: 16px; }
  .chart-bars { display: grid; gap: 14px; }
  .chart-bars div { display: grid; grid-template-columns: 1fr auto; gap: 14px; align-items: center; }
  .chart-bar {
    display: block; width: 100%; height: 11px; border-radius: 999px;
    background: rgba(79,70,229,.12); position: relative; overflow: hidden;
  }
  .chart-bar::after {
    content: ''; position: absolute; inset: 0;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--accent), var(--accent2));
  }
  .chart-bars small { color: var(--text3); font-size: 13px; }
  .bar-1::after { width: 88%; }
  .bar-2::after { width: 72%; }
  .bar-3::after { width: 95%; }

  .dashboard-mockup {
    display: grid;
    gap: 18px;
    margin-bottom: 20px;
  }
  .dashboard-panel,
  .dashboard-table {
    padding: 22px 22px 18px;
    border-radius: 26px;
    background: rgba(255,255,255,.74);
    border: 1px solid rgba(255,255,255,.6);
    box-shadow: inset 0 1px 0 rgba(255,255,255,.85), 0 16px 40px rgba(15,23,42,.06);
    backdrop-filter: blur(18px);
  }
  .panel-subtitle { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .12em; color: var(--accent); margin-bottom: 14px; }
  .mini-chart { display: grid; gap: 14px; }
  .mini-chart-row { display: grid; grid-template-columns: 1fr 1fr auto; align-items: center; gap: 14px; }
  .mini-chart-row span { font-size: 13px; color: var(--text3); }
  .mini-chart-bar {
    height: 10px; border-radius: 999px;
    background: rgba(79,70,229,.1);
    position: relative;
    overflow: hidden;
  }
  .mini-chart-bar::after {
    content: '';
    display: block;
    height: 100%;
    width: var(--progress, 0%);
    border-radius: 999px;
    background: linear-gradient(90deg, var(--accent), var(--accent2));
  }
  .mini-chart-row strong { font-size: 13px; color: var(--text); }

  .dashboard-table .table-row { display: grid; grid-template-columns: 1.3fr .8fr .9fr; gap: 14px; align-items: center; padding: 14px 0; border-bottom: 1px solid rgba(15,23,42,.05); }
  .dashboard-table .table-row:last-child { border-bottom: none; }
  .dashboard-table .header { font-size: 12px; text-transform: uppercase; letter-spacing: .14em; color: var(--text3); }
  .status-badge {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 7px 10px; border-radius: 999px;
    font-size: 12px; font-weight: 700;
  }
  .status-badge.success { background: rgba(34,197,94,.14); color: #15803d; }
  .status-badge.warning { background: rgba(250,204,21,.14); color: #92400e; }
  .status-badge.info { background: rgba(59,130,246,.14); color: #1d4ed8; }

  .dashboard-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-bottom: 20px; }
  .dashboard-card {
    padding: 18px 16px; border-radius: 22px;
    background: rgba(255,255,255,.75); border: 1px solid rgba(255,255,255,.6);
    box-shadow: inset 0 1px 0 rgba(255,255,255,.8), 0 8px 24px rgba(15,23,42,.05);
  }
  .card-label { color: var(--text3); font-size: 13px; margin-bottom: 10px; }
  .card-value { font-size: 26px; font-weight: 800; color: var(--text); }
  .card-note { margin-top: 8px; color: var(--text3); font-size: 13px; }

  .preview-card { background: rgba(255,255,255,.95); border-radius: 22px; padding: 18px 20px; border: 1px solid rgba(79,70,229,.1); }
  .preview-card h4 { font-size: 14px; font-weight: 700; color: var(--text); margin-bottom: 8px; }
  .preview-card p { font-size: 13.5px; color: var(--text2); line-height: 1.75; }

  .features { padding: 88px 6% 0; max-width: 1120px; margin: 0 auto; }
  .features-head { text-align: center; max-width: 700px; margin: 0 auto 54px; }
  .features-eyebrow { font-size: 13px; font-weight: 700; color: var(--accent); letter-spacing: .12em; text-transform: uppercase; margin-bottom: 12px; }
  .features-head h2 { font-size: clamp(2.4rem, 3.4vw, 3.6rem); font-weight: 800; color: var(--text); margin-bottom: 16px; line-height: 1.05; }
  .features-head p { font-size: 1rem; color: var(--text2); max-width: 620px; margin: 0 auto; }

  .features-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 22px; }
  .feature-card { border-radius: 24px; padding: 28px 24px; background: var(--surface); border: 1px solid var(--border); box-shadow: 0 20px 45px rgba(15, 23, 42, .06); transition: transform .2s, border-color .2s; }
  .feature-card:hover { transform: translateY(-4px); border-color: rgba(79, 70, 229, .22); }
  .feature-icon { width: 46px; height: 46px; display: grid; place-items: center; border-radius: 16px; background: var(--accent-soft); color: var(--accent); margin-bottom: 20px; font-size: 20px; }
  .feature-card h3 { font-size: 18px; font-weight: 700; margin-bottom: 12px; }
  .feature-card p { color: var(--text2); font-size: 14px; line-height: 1.8; }

  .stats-bar { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin: 64px 6% 32px; }
  .stat-box { background: var(--surface); border: 1px solid var(--border); border-radius: 24px; padding: 28px; text-align: center; box-shadow: 0 20px 45px rgba(15, 23, 42, .05); }
  .stat-box .stat-label { font-size: 13px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--text3); margin-bottom: 16px; }
  .stat-box .stat-value { font-size: 36px; font-weight: 800; color: var(--text); }
  .stat-box .stat-note { margin-top: 12px; color: var(--text2); font-size: 14px; }

  .cta { padding: 84px 6% 90px; text-align: center; background: var(--surface-soft); }
  .cta h2 { font-size: clamp(2.4rem, 3vw, 3rem); font-weight: 800; margin-bottom: 16px; }
  .cta p { max-width: 640px; margin: 0 auto 28px; color: var(--text2); font-size: 1rem; }

  .footer { padding: 24px 6% 40px; text-align: center; color: var(--text3); font-size: 13px; }

  @media (max-width: 1024px) {
    .hero { grid-template-columns: 1fr; min-height: auto; padding-top: 70px; }
    .hero-right { padding-top: 30px; }
    .features-grid { grid-template-columns: 1fr 1fr; }
    .stats-bar { grid-template-columns: 1fr 1fr; }
  }
  @media (max-width: 720px) {
    .navbar { padding: 18px 5%; }
    .navbar-menu { display: none; }
    .hero { padding: 70px 5% 50px; }
    .hero-eyebrow { margin-bottom: 18px; }
    .hero h1 { font-size: 3rem; }
    .hero p { font-size: 1rem; }
    .hero-actions { flex-direction: column; align-items: stretch; }
    .hero-keypoints { gap: 12px; }
    .features-grid, .stats-bar { grid-template-columns: 1fr; }
    .hero-visual { padding: 22px; }
  }
</style>
</head>
<body>

<nav class="navbar">
  <div class="navbar-brand">
    <img src="/assets/img/logo-dark.png" alt="PLT Solutions" id="navLogo">
    <div>
      <div class="navbar-brand-name">PLT Solutions</div>
      <div class="navbar-brand-sub"><?= htmlspecialchars(t('Cổng học tập đại học')) ?></div>
    </div>
  </div>

  <div class="navbar-menu">
    <a href="/index.php"><?= htmlspecialchars(t('Trang chủ')) ?></a>
    <a href="#features"><?= htmlspecialchars(t('Tính năng')) ?></a>
    <a href="/login.php"><?= htmlspecialchars(t('Đăng nhập')) ?></a>
  </div>

  <div class="navbar-actions">
    <button class="mode-toggle" onclick="toggleMode()" id="modeBtn" aria-label="<?= htmlspecialchars(t('Đổi giao diện sáng/tối')) ?>">
      <i class="ti ti-sun" id="modeIcon"></i>
    </button>
    <a href="/register.php" class="btn-nav-cta"><?= htmlspecialchars(t('Đăng ký')) ?></a>
  </div>
</nav>

<section class="hero">
  <div class="hero-left">
    <div class="hero-eyebrow"><?= htmlspecialchars(t('Nền tảng học tập dành cho trường đại học')) ?></div>
    <h1><?= htmlspecialchars(t('Giáo dục số')) ?>
      <span class="accent"><?= htmlspecialchars(t('chuyên nghiệp và nhanh chóng')) ?></span>
    </h1>
    <p><?= htmlspecialchars(t('PLT Solutions giúp trường đại học quản lý bài tập, chấm điểm và báo cáo tiến độ học tập dễ dàng hơn.')) ?></p>
    <div class="hero-actions">
      <a href="/register.php" class="btn-hero-primary"><i class="ti ti-user-plus"></i> <?= htmlspecialchars(t('Đăng ký miễn phí')) ?></a>
      <a href="/login.php" class="btn-hero-secondary"><i class="ti ti-arrow-right"></i> <?= htmlspecialchars(t('Đăng nhập')) ?></a>
    </div>
  </div>

  <div class="hero-right">
    <div class="hero-visual">
      <div class="panel-top">
        <div class="badge"><?= htmlspecialchars(t('Lớp học mẫu')) ?></div>
        <div class="status"><?= htmlspecialchars(t('Hoạt động')) ?></div>
      </div>
      <div class="panel-title"><?= htmlspecialchars(t('Bảng điều khiển điểm số và tiến độ học tập')) ?></div>
      <div class="panel-copy"><?= htmlspecialchars(t('Xem nhanh trạng thái nộp bài, điểm trung bình lớp và báo cáo trực quan ngay trong cùng một màn hình.')) ?></div>
      <div class="dashboard-pills">
        <div class="dashboard-pill">
          <strong><?= $statAssignments ?>+</strong>
          <span><?= htmlspecialchars(t('Bài tập')) ?></span>
        </div>
        <div class="dashboard-pill">
          <strong><?= $statSubmissions ?>+</strong>
          <span><?= htmlspecialchars(t('Đã nộp')) ?></span>
        </div>
      </div>
      <div class="dashboard-mockup">
        <div class="dashboard-panel">
          <div class="panel-subtitle"><?= htmlspecialchars(t('Tổng quan nộp bài')) ?></div>
          <div class="mini-chart">
            <div class="mini-chart-row">
              <span><?= htmlspecialchars(t('Hoàn thành')) ?></span>
              <div class="mini-chart-bar" style="--progress:88%"></div>
              <strong>88%</strong>
            </div>
            <div class="mini-chart-row">
              <span><?= htmlspecialchars(t('Trễ hạn')) ?></span>
              <div class="mini-chart-bar" style="--progress:72%"></div>
              <strong>72%</strong>
            </div>
            <div class="mini-chart-row">
              <span><?= htmlspecialchars(t('Đạt chuẩn')) ?></span>
              <div class="mini-chart-bar" style="--progress:95%"></div>
              <strong>95%</strong>
            </div>
          </div>
        </div>
        <div class="dashboard-table">
          <div class="table-row header">
            <span><?= htmlspecialchars(t('Sinh viên')) ?></span>
            <span><?= htmlspecialchars(t('Điểm')) ?></span>
            <span><?= htmlspecialchars(t('Trạng thái')) ?></span>
          </div>
          <div class="table-row">
            <span>Nguyễn An</span>
            <strong>9.2</strong>
            <span class="status-badge success"><?= htmlspecialchars(t('Hoàn thành')) ?></span>
          </div>
          <div class="table-row">
            <span>Trần Mai</span>
            <strong>8.7</strong>
            <span class="status-badge warning"><?= htmlspecialchars(t('Chờ chấm')) ?></span>
          </div>
          <div class="table-row">
            <span>Phạm Long</span>
            <strong>7.9</strong>
            <span class="status-badge info"><?= htmlspecialchars(t('Đã nộp')) ?></span>
          </div>
        </div>
      </div>
      <div class="preview-card">
        <h4><?= htmlspecialchars(t('Bảng báo cáo điểm số')) ?></h4>
        <p><?= htmlspecialchars(t('Tổng hợp nhanh số bài đã chấm, trạng thái bài nộp và điểm trung bình lớp học.')) ?></p>
      </div>
    </div>
  </div>
</section>

<section class="features" id="features">
  <div class="features-head">
    <div class="features-eyebrow"><?= htmlspecialchars(t('Tính năng nổi bật')) ?></div>
    <h2><?= htmlspecialchars(t('Một cổng thông tin học tập hoàn chỉnh')) ?></h2>
    <p><?= htmlspecialchars(t('Kết nối giảng viên, sinh viên và quản trị viên với chức năng nộp bài, chấm điểm và tổng hợp báo cáo trong một nền tảng duy nhất.')) ?></p>
  </div>
  <div class="features-grid">
    <div class="feature-card">
      <div class="feature-icon"><i class="ti ti-rocket"></i></div>
      <h3><?= htmlspecialchars(t('Khởi tạo nhanh khóa học')) ?></h3>
      <p><?= htmlspecialchars(t('Tạo đề, thời hạn và hướng dẫn chi tiết chỉ với vài cú nhấp.')) ?></p>
    </div>
    <div class="feature-card">
      <div class="feature-icon"><i class="ti ti-cloud-upload"></i></div>
      <h3><?= htmlspecialchars(t('Upload bài hiểu quả')) ?></h3>
      <p><?= htmlspecialchars(t('Sinh viên nộp bài trực tuyến, kiểm tra lại file và xem trạng thái nộp ngay lập tức.')) ?></p>
    </div>
    <div class="feature-card">
      <div class="feature-icon"><i class="ti ti-clipboard-check"></i></div>
      <h3><?= htmlspecialchars(t('Quản lý điểm thông minh')) ?></h3>
      <p><?= htmlspecialchars(t('Theo dõi điểm số theo lớp và xuất báo cáo cho mỗi học phần.')) ?></p>
    </div>
  </div>
</section>

<section class="stats-bar">
  <div class="stat-box">
    <div class="stat-label"><?= htmlspecialchars(t('Bài tập hiện hành')) ?></div>
    <div class="stat-value"><?= $statAssignments ?></div>
    <div class="stat-note"><?= htmlspecialchars(t('Số lượng bài đang mở và chờ nộp.')) ?></div>
  </div>
  <div class="stat-box">
    <div class="stat-label"><?= htmlspecialchars(t('Lượt nộp đến giờ')) ?></div>
    <div class="stat-value"><?= $statSubmissions ?></div>
    <div class="stat-note"><?= htmlspecialchars(t('Lượt nộp bài đã ghi nhận từ sinh viên.')) ?></div>
  </div>
  <div class="stat-box">
    <div class="stat-label"><?= htmlspecialchars(t('Bài đã chấm')) ?></div>
    <div class="stat-value"><?= $statGraded ?></div>
    <div class="stat-note"><?= htmlspecialchars(t('Tỷ lệ bài hoàn thành chấm điểm.')) ?></div>
  </div>
</section>

<section class="cta">
  <h2><?= htmlspecialchars(t('Nâng tầm trải nghiệm học tập ngay hôm nay')) ?></h2>
  <p><?= htmlspecialchars(t('PLT Solutions giúp bạn chuyển đổi quy trình giáo dục thành một nền tảng chuyên nghiệp, dễ quản lý.')) ?></p>
  <a href="/register.php" class="btn-hero-primary"><i class="ti ti-user-plus"></i> <?= htmlspecialchars(t('Đăng ký ngay')) ?></a>
</section>

<footer class="footer">
  &copy; <?= date('Y') ?> PLT Solutions. <?= htmlspecialchars(t('Xây dựng để phục vụ giáo dục đại học hiện đại.')) ?>
</footer>

<script src="/assets/js/app.js"></script>
</body>
</html>
