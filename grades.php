<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
if (isTeacher()) { header('Location: /assignhub/dashboard.php'); exit; }

$db = getDB();
$uid = $_SESSION['user_id'];
$pageTitle = 'Điểm của tôi';

// Latest grades and feedback
$stmt = $db->prepare(
    'SELECT a.title, a.class, s.submitted_at, g.score, g.feedback, g.graded_at, s.status
     FROM submissions s
     JOIN assignments a ON a.id = s.assignment_id
     LEFT JOIN grades g ON g.submission_id = s.id
     WHERE s.student_id = ?
     ORDER BY s.submitted_at DESC'
);
$stmt->execute([$uid]);
$grades = $stmt->fetchAll();

// Summary
$summaryStmt = $db->prepare(
    'SELECT
       COUNT(*) AS total_submissions,
       SUM(s.status = "graded") AS graded_count,
       ROUND(AVG(g.score),1) AS avg_score
     FROM submissions s
     LEFT JOIN grades g ON g.submission_id = s.id
     WHERE s.student_id = ?'
);
$summaryStmt->execute([$uid]);
$summary = $summaryStmt->fetch();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">Điểm của tôi</div>
    <div style="font-size:13px;color:var(--text2);margin-top:4px">Xem kết quả và phản hồi chi tiết cho các bài tập đã nộp.</div>
  </div>
</div>

<div class="grid-3 mb-5">
  <div class="stat">
    <div class="stat-icon" style="background:var(--accent-bg);color:var(--accent)"><i class="ti ti-upload"></i></div>
    <div class="stat-num"><?= $summary['total_submissions'] ?></div>
    <div class="stat-lbl">Bài đã nộp</div>
  </div>
  <div class="stat">
    <div class="stat-icon" style="background:var(--success-bg);color:var(--success)"><i class="ti ti-check"></i></div>
    <div class="stat-num"><?= $summary['graded_count'] ?></div>
    <div class="stat-lbl">Bài đã chấm</div>
  </div>
  <div class="stat">
    <div class="stat-icon" style="background:var(--accent-bg);color:var(--accent)"><i class="ti ti-star"></i></div>
    <div class="stat-num"><?= $summary['avg_score'] ?: 0 ?></div>
    <div class="stat-lbl">Điểm trung bình</div>
  </div>
</div>

<div class="card">
  <div class="card-title" style="margin-bottom:16px">Danh sách bài đã nộp</div>
  <?php if (empty($grades)): ?>
    <div style="padding:26px;text-align:center;color:var(--text3)">Bạn chưa nộp bài nào.</div>
  <?php else: ?>
    <div style="display:grid;gap:14px;">
      <?php foreach ($grades as $row): ?>
      <div class="card" style="padding:18px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:10px">
          <div>
            <div style="font-size:15px;font-weight:600"><?= htmlspecialchars($row['title']) ?></div>
            <div style="font-size:13px;color:var(--text3);margin-top:2px">Lớp <?= htmlspecialchars($row['class']) ?></div>
          </div>
          <span class="badge badge-<?= $row['status']==='graded' ? 'green' : 'amber' ?>"><?= $row['status']==='graded' ? 'Đã chấm' : 'Chờ chấm' ?></span>
        </div>
        <div style="display:flex;gap:18px;font-size:13px;color:var(--text2);margin-bottom:14px">
          <span>Nộp: <?= date('d/m/Y H:i', strtotime($row['submitted_at'])) ?></span>
          <?php if ($row['graded_at']): ?><span>Chấm: <?= date('d/m/Y H:i', strtotime($row['graded_at'])) ?></span><?php endif; ?>
        </div>
        <div style="display:grid;grid-template-columns:120px 1fr;gap:12px;align-items:start;margin-bottom:14px">
          <div style="background:var(--success-bg);border-radius:12px;padding:12px;min-width:0;text-align:center">
            <div style="font-size:20px;font-weight:700;color:var(--success)"><?= $row['score'] !== null ? $row['score'] : '-' ?></div>
            <div style="font-size:12px;color:var(--text3);margin-top:4px">Tổng điểm</div>
          </div>
          <div style="font-size:14px;color:var(--text2);white-space:pre-line;min-height:68px;background:var(--surface2);border-radius:12px;padding:12px;">
            <?= $row['feedback'] ? htmlspecialchars($row['feedback']) : 'Chưa có nhận xét.' ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>