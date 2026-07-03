<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db = getDB();
$action = $_GET['action'] ?? 'list';
$pageTitle = 'Bài tập';

// CREATE
if ($action === 'create' && isTeacher() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $deadline = $_POST['deadline'] ?? '';
    $class = trim($_POST['class'] ?? '');
    $ftypes = implode(',', array_map('trim', explode(',', $_POST['file_types'] ?? 'csv,xlsx')));

    if ($title && $deadline && $class) {
        $stmt = $db->prepare('INSERT INTO assignments (title,description,deadline,class,file_types,teacher_id) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$title, $desc, $deadline, $class, $ftypes, $_SESSION['user_id']]);
        flash('Đã tạo bài tập thành công!');
        header('Location: /assignhub/assignments.php');
        exit;
    }
}

// DELETE
if ($action === 'delete' && isTeacher() && isset($_GET['id'])) {
    $stmt = $db->prepare('DELETE FROM assignments WHERE id=? AND teacher_id=?');
    $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
    flash('Đã xóa bài tập.');
    header('Location: /assignhub/assignments.php');
    exit;
}

// LIST
$uid = $_SESSION['user_id'];
$assignmentDetail = null;
$assignmentSubs = [];
$assign_id = (int)($_GET['id'] ?? 0);
if ($action === 'view' && isTeacher()) {
    if ($assign_id) {
        $stmt = $db->prepare('SELECT * FROM assignments WHERE id=? AND teacher_id=?');
        $stmt->execute([$assign_id, $uid]);
        $assignmentDetail = $stmt->fetch();
    }
    if (!$assignmentDetail) {
        flash('Bài tập không tồn tại hoặc bạn không có quyền truy cập.');
        header('Location: /assignhub/assignments.php');
        exit;
    }
    $stmt = $db->prepare('SELECT s.*, u.name AS student_name, u.student_id AS student_no, g.score, g.feedback FROM submissions s JOIN users u ON u.id=s.student_id LEFT JOIN grades g ON g.submission_id=s.id WHERE s.assignment_id=? ORDER BY s.submitted_at DESC');
    $stmt->execute([$assign_id]);
    $assignmentSubs = $stmt->fetchAll();
}

if ($action !== 'create') {
    if (isTeacher()) {
        $stmt = $db->prepare('SELECT a.*, COUNT(s.id) as sub_count FROM assignments a LEFT JOIN submissions s ON s.assignment_id=a.id WHERE a.teacher_id=? GROUP BY a.id ORDER BY a.created_at DESC');
        $stmt->execute([$uid]);
    } else {
        $stmt = $db->prepare('SELECT a.*, (SELECT COUNT(*) FROM submissions WHERE assignment_id=a.id AND student_id=?) as submitted FROM assignments a WHERE a.class=? ORDER BY a.deadline ASC');
        $stmt->execute([$uid, $_SESSION['class']]);
    }
    $assignments = $stmt->fetchAll();
}

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($action === 'create' && isTeacher()): ?>
<div class="page-header">
  <div class="page-title">Tạo bài tập mới</div>
  <a href="/assignhub/assignments.php" class="btn btn-ghost"><i class="ti ti-arrow-left"></i> Quay lại</a>
</div>
<div class="card" style="max-width:620px">
  <form method="POST">
    <div class="form-group">
      <label class="form-label">Tên bài tập *</label>
      <input type="text" name="title" class="form-control" placeholder="VD: Thiết kế test cases EP/BVA" required>
    </div>
    <div class="form-group">
      <label class="form-label">Mô tả</label>
      <textarea name="description" class="form-control" placeholder="Hướng dẫn, yêu cầu bài tập..."></textarea>
    </div>
    <div class="form-group">
      <label class="form-label">Lớp *</label>
      <input type="text" name="class" class="form-control" placeholder="CD24TT3" required value="CD24TT3">
    </div>
    <div class="form-group">
      <label class="form-label">Hạn nộp *</label>
      <input type="datetime-local" name="deadline" class="form-control" required>
    </div>
    <div class="form-group">
      <label class="form-label">Loại file cho phép</label>
      <input type="text" name="file_types" class="form-control" value="csv,xlsx" placeholder="csv,xlsx,pdf">
      <div class="form-hint">Phân cách bằng dấu phẩy</div>
    </div>
    <div class="gap-row">
      <button type="submit" class="btn btn-primary"><i class="ti ti-plus"></i> Tạo bài tập</button>
      <a href="/assignhub/assignments.php" class="btn btn-ghost">Hủy</a>
    </div>
  </form>
</div>

<?php elseif ($action === 'view' && isTeacher()): ?>

<div class="page-header">
  <div>
    <div class="page-title">Danh sách nộp: <?= htmlspecialchars($assignmentDetail['title']) ?></div>
    <div style="font-size:13px;color:var(--text2);margin-top:4px">Lớp <?= htmlspecialchars($assignmentDetail['class']) ?> · Hạn nộp <?= date('d/m/Y H:i', strtotime($assignmentDetail['deadline'])) ?></div>
  </div>
  <div class="gap-row">
    <a href="/assignhub/assignments.php" class="btn btn-ghost"><i class="ti ti-arrow-left"></i> Quay lại</a>
    <a href="/assignhub/grading.php?assignment_id=<?= $assignmentDetail['id'] ?>" class="btn btn-primary"><i class="ti ti-robot"></i> Chấm nhanh</a>
  </div>
</div>

<div class="list-card">
  <?php if (empty($assignmentSubs)): ?>
  <div style="padding:24px;text-align:center;color:var(--text3)">Chưa có sinh viên nào nộp bài.</div>
  <?php endif; ?>
  <?php foreach ($assignmentSubs as $s): ?>
  <div class="list-row">
    <div class="row-left">
      <div class="row-icon" style="background:var(--<?= $s['status']==='graded'?'success':'warning' ?>-bg)">
        <i class="ti ti-user" style="color:var(--<?= $s['status']==='graded'?'success':'warning' ?>)"></i>
      </div>
      <div class="row-info">
        <div class="row-title"><?= htmlspecialchars($s['student_name']) ?></div>
        <div class="row-desc">
          <?= htmlspecialchars($s['student_no']) ?> · <?= date('d/m/Y H:i', strtotime($s['submitted_at'])) ?>
        </div>
      </div>
    </div>
    <div class="row-right" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
      <?php if ($s['score'] !== null): ?>
      <span class="score-big" style="font-size:16px;"><?= $s['score'] ?></span>
      <?php endif; ?>
      <span class="badge badge-<?= $s['status']==='graded'?'success':'amber' ?>"><?= $s['status']==='graded'?'Đã chấm':'Chờ chấm' ?></span>
      <a href="/assignhub/grading.php?assignment_id=<?= $assignmentDetail['id'] ?>&sub_id=<?= $s['id'] ?>" class="btn btn-ghost btn-sm">Chấm</a>
      <a href="/assignhub/uploads/<?= urlencode($s['file_path']) ?>" class="btn btn-ghost btn-sm" download>Tải file</a>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php else: ?>

<div class="page-header">
  <div class="page-title">Danh sách bài tập</div>
  <?php if (isTeacher()): ?>
  <a href="/assignhub/assignments.php?action=create" class="btn btn-primary"><i class="ti ti-plus"></i> Tạo mới</a>
  <?php endif; ?>
</div>

<div class="list-card">
<?php foreach ($assignments as $a):
  $deadline = strtotime($a['deadline']);
  $diff = $deadline - time();
  if ($diff < 0) { $badge='red'; $label='Đã đóng'; }
  elseif ($diff < 86400*3) { $badge='amber'; $label='Sắp hết hạn'; }
  else { $badge='green'; $label='Còn hạn'; }
?>
  <div class="list-row">
    <div class="row-left">
      <div class="row-icon" style="background:var(--accent-bg)">
        <i class="ti ti-file-spreadsheet" style="color:var(--accent)"></i>
      </div>
      <div class="row-info">
        <div class="row-title"><?= htmlspecialchars($a['title']) ?></div>
        <div class="row-desc">
          Hạn: <?= date('d/m/Y H:i', $deadline) ?> · <?= htmlspecialchars($a['class']) ?>
          <?php if (isTeacher()): ?> · <?= $a['sub_count'] ?> bài nộp<?php endif; ?>
          <?php if (isStudent()): ?> · <?= $a['submitted'] ? '✓ Đã nộp' : '– Chưa nộp' ?><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="row-right">
      <span class="badge badge-<?= $badge ?>"><?= $label ?></span>
      <?php if (isStudent() && !$a['submitted'] && $diff > 0): ?>
      <a href="/assignhub/submit.php?id=<?= $a['id'] ?>" class="btn btn-primary btn-sm">Nộp bài</a>
      <?php endif; ?>
      <?php if (isTeacher()): ?>
      <a href="/assignhub/assignments.php?action=view&id=<?= $a['id'] ?>" class="btn btn-ghost btn-sm">Danh sách nộp</a>
      <a href="/assignhub/assignments.php?action=delete&id=<?= $a['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Xóa bài tập này?')">
        <i class="ti ti-trash"></i>
      </a>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
<?php if (empty($assignments)): ?>
  <div style="padding:28px;text-align:center;color:var(--text3)">Chưa có bài tập nào.</div>
<?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
