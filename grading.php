<?php
require_once __DIR__ . '/includes/auth.php';
requireRole('teacher');

$db = getDB();
$pageTitle = 'Chấm điểm AI';

// Save grade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grade'])) {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $sub_id = (int)$_POST['submission_id'];
    $score  = (float)$_POST['score'];
    $feedback = trim($_POST['feedback'] ?? '');
    $c = (float)($_POST['completeness'] ?? 0);
    $cr = (float)($_POST['correctness'] ?? 0);
    $cov = (float)($_POST['coverage'] ?? 0);
    $fmt = (float)($_POST['format_score'] ?? 0);

    $stmt = $db->prepare('INSERT INTO grades (submission_id,score,feedback,completeness,correctness,coverage,format_score,graded_by) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE score=VALUES(score),feedback=VALUES(feedback),completeness=VALUES(completeness),correctness=VALUES(correctness),coverage=VALUES(coverage),format_score=VALUES(format_score),graded_by=VALUES(graded_by),graded_at=NOW()');
    $stmt->execute([$sub_id,$score,$feedback,$c,$cr,$cov,$fmt,$_SESSION['user_id']]);
    $db->prepare('UPDATE submissions SET status="graded" WHERE id=?')->execute([$sub_id]);

    flash('Đã lưu điểm thành công!');
    header('Location: /assignhub/grading.php?assignment_id=' . ($_POST['assignment_id'] ?? ''));
    exit;
}

// Load assignments
// Fix: dùng prepared statement thay vì nối chuỗi $_SESSION trực tiếp vào query
$stmtAssigns = $db->prepare('SELECT a.*, COUNT(s.id) as sub_count FROM assignments a LEFT JOIN submissions s ON s.assignment_id=a.id WHERE a.teacher_id=? GROUP BY a.id ORDER BY a.created_at DESC');
$stmtAssigns->execute([$_SESSION['user_id']]);
$assigns = $stmtAssigns->fetchAll();

$aid = (int)($_GET['assignment_id'] ?? ($assigns[0]['id'] ?? 0));
$sid = (int)($_GET['sub_id'] ?? 0);

// Load submissions for selected assignment
$submissions = [];
if ($aid) {
    $stmt = $db->prepare('SELECT s.*, u.name as student_name, u.student_id as student_no, g.score, g.feedback, g.completeness, g.correctness, g.coverage, g.format_score FROM submissions s JOIN users u ON u.id=s.student_id LEFT JOIN grades g ON g.submission_id=s.id WHERE s.assignment_id=? ORDER BY s.submitted_at DESC');
    $stmt->execute([$aid]);
    $submissions = $stmt->fetchAll();
}

// Selected submission
$current = null;
if ($sid) {
    foreach ($submissions as $s) {
        if ($s['id'] == $sid) { $current = $s; break; }
    }
}
if (!$current && !empty($submissions)) $current = $submissions[0];

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div class="page-title">Chấm điểm AI</div>
  <div class="gap-row">
    <select class="form-control" style="width:auto" onchange="location='?assignment_id='+this.value">
      <?php foreach ($assigns as $a): ?>
      <option value="<?= $a['id'] ?>" <?= $a['id']==$aid?'selected':'' ?>><?= htmlspecialchars($a['title']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<div style="display:grid;grid-template-columns:280px 1fr;gap:20px;align-items:start">

<!-- Submission list -->
<div class="list-card" style="height:fit-content">
  <?php foreach ($submissions as $s): ?>
  <a href="?assignment_id=<?= $aid ?>&sub_id=<?= $s['id'] ?>" style="display:block">
    <div class="list-row" style="<?= $current && $s['id']==$current['id']?'background:var(--accent-bg)':'' ?>">
      <div class="row-left">
        <div class="dot" style="width:8px;height:8px;border-radius:50%;background:var(--<?= $s['status']==='graded'?'success':'warning' ?>);flex-shrink:0"></div>
        <div class="row-info">
          <div class="row-title"><?= htmlspecialchars($s['student_name']) ?></div>
          <div class="row-desc"><?= htmlspecialchars($s['student_no']) ?></div>
        </div>
      </div>
      <?php if ($s['score'] !== null): ?>
      <span style="font-weight:600;color:var(--accent)"><?= $s['score'] ?></span>
      <?php else: ?>
      <span class="badge badge-amber" style="font-size:11px">Chờ</span>
      <?php endif; ?>
    </div>
  </a>
  <?php endforeach; ?>
  <?php if (empty($submissions)): ?>
  <div style="padding:20px;text-align:center;color:var(--text3);font-size:14px">Chưa có bài nộp.</div>
  <?php endif; ?>
</div>

<!-- Grading panel -->
<?php if ($current): ?>
<div>
  <div class="card mb-4">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
      <div>
        <div style="font-size:16px;font-weight:500"><?= htmlspecialchars($current['student_name']) ?></div>
        <div style="font-size:13px;color:var(--text2)"><?= htmlspecialchars($current['student_no']) ?> · Nộp <?= date('d/m/Y H:i', strtotime($current['submitted_at'])) ?></div>
      </div>
      <span class="badge badge-<?= $current['status']==='graded'?'green':'amber' ?>"><?= $current['status']==='graded'?'Đã chấm':'Chờ chấm' ?></span>
    </div>

    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
      <i class="ti ti-file" style="color:var(--accent);font-size:20px"></i>
      <span style="font-size:14px"><?= htmlspecialchars($current['file_name']) ?></span>
      <a href="/assignhub/uploads/<?= urlencode($current['file_path']) ?>" class="btn btn-ghost btn-sm" download>
        <i class="ti ti-download"></i> Tải về
      </a>
    </div>

    <!-- AI Grade button -->
    <button class="btn btn-primary" onclick="runAIGrade(<?= $current['id'] ?>)">
      <i class="ti ti-robot"></i> Chấm bằng AI
    </button>
    <div id="ai-loading-<?= $current['id'] ?>" style="display:none;margin-top:10px;font-size:13px;color:var(--text2)">
      <i class="ti ti-loader-2" style="vertical-align:-2px"></i> AI đang phân tích...
    </div>
  </div>

  <!-- Score form -->
  <div class="card">
    <div style="font-size:15px;font-weight:500;margin-bottom:16px">Điểm số & Nhận xét</div>
    <form method="POST">
      <input type="hidden" name="submission_id" value="<?= $current['id'] ?>">
      <input type="hidden" name="assignment_id" value="<?= $aid ?>">
      <input type="hidden" name="save_grade" value="1">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">

      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px">
        <div class="form-group" style="margin:0">
          <label class="form-label" style="font-size:12px">Completeness /3</label>
          <input type="number" id="f_c" name="completeness" class="form-control" min="0" max="3" step="0.5" value="<?= $current['completeness'] ?? '' ?>" oninput="updateTotal()">
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label" style="font-size:12px">Correctness /3</label>
          <input type="number" id="f_cr" name="correctness" class="form-control" min="0" max="3" step="0.5" value="<?= $current['correctness'] ?? '' ?>" oninput="updateTotal()">
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label" style="font-size:12px">Coverage /3</label>
          <input type="number" id="f_cov" name="coverage" class="form-control" min="0" max="3" step="0.5" value="<?= $current['coverage'] ?? '' ?>" oninput="updateTotal()">
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label" style="font-size:12px">Format /1</label>
          <input type="number" id="f_fmt" name="format_score" class="form-control" min="0" max="1" step="0.5" value="<?= $current['format_score'] ?? '' ?>" oninput="updateTotal()">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Tổng điểm / 10</label>
        <input type="number" id="f_score" name="score" class="form-control" min="0" max="10" step="0.5" value="<?= $current['score'] ?? '' ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Nhận xét</label>
        <textarea name="feedback" class="form-control" rows="4" placeholder="Nhận xét chi tiết về bài làm..."><?= htmlspecialchars($current['feedback'] ?? '') ?></textarea>
      </div>

      <button type="submit" class="btn btn-primary"><i class="ti ti-check"></i> Lưu điểm</button>
    </form>
  </div>
</div>
<?php else: ?>
<div style="color:var(--text3);font-size:14px;padding:40px;text-align:center">
  Chọn bài nộp từ danh sách bên trái.
</div>
<?php endif; ?>
</div>

<script>
function updateTotal() {
  const c = parseFloat(document.getElementById('f_c').value) || 0;
  const cr = parseFloat(document.getElementById('f_cr').value) || 0;
  const cov = parseFloat(document.getElementById('f_cov').value) || 0;
  const fmt = parseFloat(document.getElementById('f_fmt').value) || 0;
  const total = Math.min(10, c + cr + cov + fmt);
  document.getElementById('f_score').value = total.toFixed(1);
}

async function runAIGrade(subId) {
  const loadEl = document.getElementById('ai-loading-' + subId);
  loadEl.style.display = 'block';

  try {
    const res = await fetch('/assignhub/api_grade.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ submission_id: subId })
    });

    if (!res.ok) throw new Error('Server lỗi: ' + res.status);

    const result = await res.json();

    if (result.error) throw new Error(result.error);

    document.getElementById('f_c').value = result.completeness;
    document.getElementById('f_cr').value = result.correctness;
    document.getElementById('f_cov').value = result.coverage;
    document.getElementById('f_fmt').value = result.format_score;
    document.getElementById('f_score').value = result.score;
    document.querySelector('textarea[name=feedback]').value = result.feedback;
    updateTotal();
    // If API saved the grade, refresh page to reflect saved state
    if (result.saved) {
      showToast('AI chấm xong và đã lưu vào hệ thống', 'success');
      setTimeout(function(){ location.reload(); }, 900);
    } else {
      showToast('AI chấm xong (chưa lưu)', 'success');
    }
  } catch(e) {
    alert('Lỗi AI: ' + e.message);
  } finally {
    loadEl.style.display = 'none';
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>