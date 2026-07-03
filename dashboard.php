<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db = getDB();
$uid = $_SESSION['user_id'];
$isTeacher = isTeacher();

// Stats
if ($isTeacher) {
    $totalAssign = $db->query('SELECT COUNT(*) FROM assignments')->fetchColumn();
    $totalSubs   = $db->query('SELECT COUNT(*) FROM submissions')->fetchColumn();
    $graded      = $db->query("SELECT COUNT(*) FROM submissions WHERE status='graded'")->fetchColumn();
    $pending     = $totalSubs - $graded;
    $pendingRate = $totalSubs ? round($pending / $totalSubs * 100) : 0;
    $avgSubmitPerAssign = $totalAssign ? round($totalSubs / $totalAssign, 1) : 0;
} else {
    $class = $_SESSION['class'];
    $totalAssign = $db->prepare('SELECT COUNT(*) FROM assignments WHERE class=?');
    $totalAssign->execute([$class]); $totalAssign = $totalAssign->fetchColumn();
    $totalSubs   = $db->prepare('SELECT COUNT(*) FROM submissions WHERE student_id=?');
    $totalSubs->execute([$uid]); $totalSubs = $totalSubs->fetchColumn();
    $graded = $db->prepare("SELECT COUNT(*) FROM submissions WHERE student_id=? AND status='graded'");
    $graded->execute([$uid]); $graded = $graded->fetchColumn();
    $pending = $totalSubs - $graded;
    $avgScoreStmt = $db->prepare('SELECT ROUND(AVG(g.score),1) FROM grades g JOIN submissions s ON s.id=g.submission_id WHERE s.student_id=?');
    $avgScoreStmt->execute([$uid]);
    $avgScore = $avgScoreStmt->fetchColumn() ?: 0;
}

// Recent assignments
$assignQuery = $isTeacher
    ? $db->query('SELECT a.*, COUNT(s.id) as sub_count, (SELECT COUNT(*) FROM users WHERE class=a.class AND role="student") as total_students FROM assignments a LEFT JOIN submissions s ON s.assignment_id=a.id GROUP BY a.id ORDER BY a.created_at DESC LIMIT 5')
    : $db->prepare('SELECT a.*, (SELECT COUNT(*) FROM submissions WHERE assignment_id=a.id AND student_id=?) as submitted FROM assignments a WHERE a.class=? ORDER BY a.deadline ASC LIMIT 5');

if (!$isTeacher) $assignQuery->execute([$uid, $_SESSION['class']]);
$assignments = $assignQuery->fetchAll();

// ── Teacher-only widgets: submission trend, recent activity, notifications ──
$trendLabels = []; $trendData = [];
$recentActivity = [];
$notifications = [];

if ($isTeacher) {
    // Submission trend - last 7 days
    $stmt = $db->prepare("
        SELECT DATE(s.submitted_at) as d, COUNT(*) as c
        FROM submissions s
        JOIN assignments a ON a.id = s.assignment_id
        WHERE a.teacher_id = ? AND s.submitted_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(s.submitted_at)
    ");
    $stmt->execute([$uid]);
    $trendRaw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i day"));
        $trendLabels[] = $dayNames[date('w', strtotime($date))];
        $trendData[] = (int)($trendRaw[$date] ?? 0);
    }

    // Recent activity - last 8 events (submissions + grades), newest first
    $stmt = $db->prepare("
        (SELECT 'submit' as type, s.submitted_at as ts, u.name as actor, a.title as subject
         FROM submissions s
         JOIN assignments a ON a.id = s.assignment_id
         JOIN users u ON u.id = s.student_id
         WHERE a.teacher_id = ?)
        UNION ALL
        (SELECT 'grade' as type, g.graded_at as ts, u.name as actor, a.title as subject
         FROM grades g
         JOIN submissions s ON s.id = g.submission_id
         JOIN assignments a ON a.id = s.assignment_id
         JOIN users u ON u.id = s.student_id
         WHERE a.teacher_id = ?)
        ORDER BY ts DESC LIMIT 8
    ");
    $stmt->execute([$uid, $uid]);
    $recentActivity = $stmt->fetchAll();

    // Notifications: overdue assignments still awaiting grading + new ungraded submissions
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM submissions s
        JOIN assignments a ON a.id = s.assignment_id
        WHERE a.teacher_id = ? AND s.status = 'pending' AND a.deadline < NOW()
    ");
    $stmt->execute([$uid]);
    $overdueCount = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT COUNT(*) FROM submissions s
        JOIN assignments a ON a.id = s.assignment_id
        WHERE a.teacher_id = ? AND s.status = 'pending'
        AND s.submitted_at >= DATE_SUB(NOW(), INTERVAL 2 DAY)
    ");
    $stmt->execute([$uid]);
    $newSubsCount = (int)$stmt->fetchColumn();

    if ($overdueCount > 0) {
        $notifications[] = ['icon' => 'alert-triangle', 'color' => 'red', 'text' => "$overdueCount bài quá hạn chấm"];
    }
    if ($newSubsCount > 0) {
        $notifications[] = ['icon' => 'file-upload', 'color' => 'blue', 'text' => "$newSubsCount bài nộp mới"];
    }
}

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<?php
  $hour = (int)date('G');
  if ($hour < 12) { $greet = 'Good Morning'; }
  elseif ($hour < 18) { $greet = 'Good Afternoon'; }
  else { $greet = 'Good Evening'; }
?>
<div class="page-header">
  <div>
    <div class="greet-line"><?= $greet ?> <?= $hour < 12 ? '☀️' : ($hour < 18 ? '🌤️' : '👋') ?></div>
    <div class="page-title">Xin chào, <?= htmlspecialchars($_SESSION['name']) ?></div>
    <?php if ($isTeacher): ?>
    <div class="greet-summary">
      Hôm nay có
      <strong><?= $pending ?> bài cần chấm</strong>,
      <strong><?= $overdueCount ?> bài quá hạn</strong>
      <?php if (!empty($recentActivity)): ?> · AI đã chấm <strong><?= $graded ?> bài</strong><?php endif; ?>
    </div>
    <div class="greet-summary" style="margin-top:8px;font-size:14px">
      Tỷ lệ chờ chấm: <strong><?= $pendingRate ?>%</strong> · Trung bình <strong><?= $avgSubmitPerAssign ?></strong> nộp/bài.
    </div>
    <?php else: ?>
    <div style="font-size:13px;color:var(--text2);margin-top:2px"><?= date('l, d/m/Y') ?></div>
    <?php endif; ?>
  </div>
  <div class="gap-row">
    <?php if ($isTeacher): ?>
    <div class="notif-bell" id="notifBell" tabindex="0">
      <i class="ti ti-bell"></i>
      <?php if (!empty($notifications)): ?>
      <span class="notif-dot"></span>
      <?php endif; ?>
      <div class="notif-dropdown">
        <div class="notif-dropdown-title">Thông báo</div>
        <?php if (empty($notifications)): ?>
          <div class="notif-empty">Không có thông báo mới 🎉</div>
        <?php else: foreach ($notifications as $n): ?>
          <div class="notif-item">
            <i class="ti ti-<?= $n['icon'] ?>" style="color:var(--<?= $n['color']==='red'?'danger':'accent' ?>)"></i>
            <span><?= htmlspecialchars($n['text']) ?></span>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <a href="/assignhub/assignments.php?action=create" class="btn btn-primary">
      <i class="ti ti-plus"></i> Tạo bài tập
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- Stats -->
<div class="grid-4 mb-6">
  <div class="stat">
    <div class="stat-icon" style="background:var(--accent-bg);color:var(--accent)"><i class="ti ti-books"></i></div>
    <div class="stat-num"><?= $totalAssign ?></div>
    <div class="stat-lbl"><?= $isTeacher ? 'Bài tập đã tạo' : 'Bài tập' ?></div>
  </div>
  <div class="stat">
    <div class="stat-icon" style="background:var(--accent-bg);color:var(--accent)"><i class="ti ti-upload"></i></div>
    <div class="stat-num" style="color:var(--accent)"><?= $totalSubs ?></div>
    <div class="stat-lbl"><?= $isTeacher ? 'Tổng bài nộp' : 'Đã nộp' ?></div>
  </div>
  <?php if ($isTeacher): ?>
  <div class="stat">
    <div class="stat-icon" style="background:var(--success-bg);color:var(--success)"><i class="ti ti-check"></i></div>
    <div class="stat-num" style="color:var(--success)"><?= $graded ?></div>
    <div class="stat-lbl">Đã chấm</div>
  </div>
  <div class="stat">
    <div class="stat-icon" style="background:var(--warning-bg);color:var(--warning)"><i class="ti ti-clock-hour-4"></i></div>
    <div class="stat-num" style="color:var(--warning)"><?= $pending ?></div>
    <div class="stat-lbl">Chờ chấm</div>
  </div>
  <?php else: ?>
  <div class="stat">
    <div class="stat-icon" style="background:var(--success-bg);color:var(--success)"><i class="ti ti-star"></i></div>
    <div class="stat-num" style="color:var(--success)"><?= $avgScore ?></div>
    <div class="stat-lbl">Điểm TB</div>
  </div>
  <div class="stat">
    <div class="stat-icon" style="background:var(--warning-bg);color:var(--warning)"><i class="ti ti-clock-hour-4"></i></div>
    <div class="stat-num" style="color:var(--warning)"><?= $pending ?></div>
    <div class="stat-lbl">Chờ chấm</div>
  </div>
  <?php endif; ?>
</div>

<?php if ($isTeacher): ?>
<div class="dash-cols">
<div class="dash-main">
<?php endif; ?>

<?php if (! $isTeacher): ?>
<div class="card mb-5">
  <div class="card-title">Kết quả gần đây</div>
  <?php
    $recentStmt = $db->prepare(
      'SELECT a.title, g.score, g.feedback, s.status, g.graded_at
       FROM submissions s
       JOIN assignments a ON a.id = s.assignment_id
       LEFT JOIN grades g ON g.submission_id = s.id
       WHERE s.student_id = ?
       ORDER BY g.graded_at DESC, s.submitted_at DESC
       LIMIT 3'
    );
    $recentStmt->execute([$uid]);
    $recentGrades = $recentStmt->fetchAll();
  ?>
  <?php if (empty($recentGrades)): ?>
    <div style="padding:20px;color:var(--text3)">Chưa có điểm mới.</div>
  <?php else: ?>
    <div style="display:grid;gap:12px;">
      <?php foreach ($recentGrades as $rg): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 0;border-bottom:1px solid var(--border)">
        <div>
          <div style="font-weight:600;"><?= htmlspecialchars($rg['title']) ?></div>
          <div style="font-size:13px;color:var(--text3)"><?= $rg['status'] === 'graded' ? 'Đã chấm' : 'Chờ chấm' ?><?= $rg['graded_at'] ? ' · ' . date('d/m/Y', strtotime($rg['graded_at'])) : '' ?></div>
        </div>
        <div style="text-align:right">
          <div style="font-weight:700;color:var(--success)"><?= $rg['score'] !== null ? $rg['score'] : '-' ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <div style="margin-top:16px;text-align:right"><a href="/assignhub/grades.php" class="btn btn-ghost btn-sm">Xem tất cả điểm</a></div>
</div>
<?php endif; ?>

<?php if ($isTeacher): ?>
<div class="card mb-5">
  <div class="card-title">Hành động nhanh</div>
  <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-top:14px">
    <a href="/assignhub/assignments.php?action=create" class="btn btn-primary btn-sm" style="justify-content:center"><i class="ti ti-plus"></i> Tạo bài tập</a>
    <a href="/assignhub/grading.php" class="btn btn-ghost btn-sm" style="justify-content:center"><i class="ti ti-robot"></i> Chấm điểm AI</a>
    <a href="/assignhub/assignments.php" class="btn btn-ghost btn-sm" style="justify-content:center"><i class="ti ti-list-details"></i> Quản lý bài tập</a>
    <a href="/assignhub/assignments.php" class="btn btn-ghost btn-sm" style="justify-content:center"><i class="ti ti-chart-bar"></i> Xem thống kê</a>
  </div>
</div>
<?php endif; ?>

<!-- Assignments -->
<div class="page-header mb-4">
  <div class="page-title" style="font-size:17px">Bài tập gần đây</div>
  <a href="/assignhub/assignments.php" class="btn btn-ghost btn-sm">Xem tất cả</a>
</div>

<div class="grid-3">
<?php foreach ($assignments as $a): ?>
  <?php
    $deadline = strtotime($a['deadline']);
    $now = time();
    $diff = $deadline - $now;
    if ($diff < 0) { $status = 'closed'; $badge = 'red'; $label = 'Đã đóng'; }
    elseif ($diff < 86400*3) { $status = 'urgent'; $badge = 'amber'; $label = 'Sắp hết hạn'; }
    else { $status = 'open'; $badge = 'green'; $label = 'Còn hạn'; }
    $progress = $isTeacher && $a['total_students'] > 0 ? round($a['sub_count']/$a['total_students']*100) : 0;
  ?>
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
      <div>
        <div class="card-title"><?= htmlspecialchars($a['title']) ?></div>
        <div class="card-sub"><?= htmlspecialchars($a['class']) ?></div>
      </div>
      <span class="badge badge-<?= $badge ?>"><?= $label ?></span>
    </div>
    <div style="font-size:12px;color:var(--text3)">
      Hạn: <strong style="color:var(--<?= $badge==='amber'?'warning':($badge==='red'?'text3':'text') ?>)"><?= date('d/m/Y H:i', $deadline) ?></strong>
    </div>
    <?php if ($isTeacher): ?>
    <div class="progress-bar"><div class="progress-fill" style="width:<?= $progress ?>%"></div></div>
    <div class="meta-row">
      <span class="meta-label"><?= $a['sub_count'] ?>/<?= $a['total_students'] ?> bài nộp · <strong><?= $progress ?>%</strong></span>
      <span class="badge badge-blue"><?= htmlspecialchars($a['file_types']) ?></span>
    </div>
    <?php else: ?>
    <div class="meta-row">
      <span class="meta-label"><?= $a['submitted'] ? '✓ Đã nộp' : '– Chưa nộp' ?></span>
      <?php if (!$a['submitted'] && $status !== 'closed'): ?>
      <a href="/assignhub/submit.php?id=<?= $a['id'] ?>" class="btn btn-primary btn-sm">Nộp bài</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
<?php if (empty($assignments)): ?>
  <div class="empty-state" style="grid-column:1/-1">
    <div class="empty-state-icon">📂</div>
    <div class="empty-state-text">Chưa có bài tập nào.</div>
  </div>
<?php endif; ?>
</div>

<?php if ($isTeacher): ?>
</div><!-- /dash-main -->

<div class="dash-side">

  <!-- Submission Trend Chart -->
  <div class="card mb-4">
    <div class="card-section-title">
      <span><i class="ti ti-chart-line"></i> Submission Trend</span>
      <span class="card-section-tag">7 ngày</span>
    </div>
    <?php
      $maxVal = max(1, max($trendData));
      $chartW = 280; $chartH = 110; $padX = 14; $padY = 10;
      $stepX = ($chartW - $padX*2) / (count($trendData)-1);
      $points = [];
      foreach ($trendData as $i => $v) {
        $x = $padX + $i * $stepX;
        $y = $chartH - $padY - ($v / $maxVal) * ($chartH - $padY*2);
        $points[] = [$x, $y];
      }
      $polyline = implode(' ', array_map(fn($p) => round($p[0],1).','.round($p[1],1), $points));
      $areaPath = 'M' . $padX . ',' . $chartH . ' ' . implode(' ', array_map(fn($p) => 'L'.round($p[0],1).','.round($p[1],1), $points)) . ' L' . ($chartW-$padX) . ',' . $chartH . ' Z';
    ?>
    <svg viewBox="0 0 <?= $chartW ?> <?= $chartH+20 ?>" class="trend-chart">
      <path d="<?= $areaPath ?>" fill="var(--accent)" opacity="0.08"></path>
      <polyline points="<?= $polyline ?>" fill="none" stroke="var(--accent)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></polyline>
      <?php foreach ($points as $i => $p): ?>
      <circle cx="<?= $p[0] ?>" cy="<?= $p[1] ?>" r="3" fill="var(--accent)"></circle>
      <text x="<?= $p[0] ?>" y="<?= $chartH+14 ?>" text-anchor="middle" class="trend-chart-label"><?= $trendLabels[$i] ?></text>
      <?php endforeach; ?>
    </svg>
    <div class="card-section-foot">Tổng <?= array_sum($trendData) ?> bài nộp trong 7 ngày qua</div>
  </div>

  <!-- Recent Activity -->
  <div class="card">
    <div class="card-section-title"><span><i class="ti ti-activity"></i> Recent Activity</span></div>
    <div class="activity-feed">
      <?php if (empty($recentActivity)): ?>
        <div class="empty-state" style="padding:20px 0">
          <div class="empty-state-icon" style="font-size:28px">📭</div>
          <div class="empty-state-text" style="font-size:13px">Chưa có hoạt động nào.</div>
        </div>
      <?php else: foreach ($recentActivity as $act): ?>
        <div class="activity-item">
          <div class="activity-dot <?= $act['type']==='grade' ? 'activity-dot-green' : 'activity-dot-blue' ?>">
            <i class="ti ti-<?= $act['type']==='grade' ? 'check' : 'upload' ?>"></i>
          </div>
          <div class="activity-body">
            <div class="activity-text">
              <strong><?= htmlspecialchars($act['actor']) ?></strong>
              <?= $act['type']==='grade' ? 'đã được AI chấm' : 'đã nộp' ?>
              <span class="activity-subject"><?= htmlspecialchars($act['subject']) ?></span>
            </div>
            <div class="activity-time"><?= timeAgoVi($act['ts']) ?></div>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

</div><!-- /dash-side -->
</div><!-- /dash-cols -->
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
