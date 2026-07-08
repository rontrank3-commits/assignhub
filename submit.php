<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
if (isTeacher()) { header('Location: /dashboard.php'); exit; }

$db = getDB();
$uid = $_SESSION['user_id'];
$pageTitle = 'Nộp bài';
$isAjax = !empty($_POST['ajax']) || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

function respond($success, $msg, $assignId = 0) {
    global $isAjax;
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'message' => $msg]);
        exit;
    }
    flash($msg, $success ? 'success' : 'error');
    header('Location: /submit.php' . ($assignId ? "?id=$assignId" : ''));
    exit;
}

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assign_id = (int)($_POST['assignment_id'] ?? 0);

    // Check assignment exists and not expired
    $stmt = $db->prepare('SELECT * FROM assignments WHERE id=? AND class=?');
    $stmt->execute([$assign_id, $_SESSION['class']]);
    $assign = $stmt->fetch();

    if (!$assign) {
        respond(false, 'Bài tập không tồn tại.', $assign_id);
    } elseif (strtotime($assign['deadline']) < time()) {
        respond(false, 'Bài tập đã hết hạn nộp.', $assign_id);
    } elseif (empty($_FILES['file']['name'])) {
        respond(false, 'Vui lòng chọn file.', $assign_id);
    } else {
        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = explode(',', $assign['file_types']);

        if (!in_array($ext, $allowed)) {
            respond(false, 'Loại file không được phép. Chỉ chấp nhận: ' . $assign['file_types'], $assign_id);
        } elseif ($file['size'] > MAX_FILE_SIZE) {
            respond(false, 'File quá lớn. Tối đa 20MB.', $assign_id);
        } else {
            // Check existing submission
            $check = $db->prepare('SELECT id FROM submissions WHERE assignment_id=? AND student_id=?');
            $check->execute([$assign_id, $uid]);
            $existing = $check->fetch();

            $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            $filepath = UPLOAD_DIR . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
              // If CSV, validate headers to ensure it's a valid TC file
              $invalidMsg = null;
              if ($ext === 'csv') {
                $fp = @fopen($filepath, 'r');
                if ($fp) {
                  $headers = fgetcsv($fp);
                  fclose($fp);
                  if ($headers === false) {
                    $invalidMsg = 'File CSV trống hoặc không đọc được.';
                  } else {
                    $norm = array_map(function($h){ return strtolower(trim($h)); }, $headers);
                    $required = ['tc_id','id','tc','description','desc','input','inputs','expected','expected_output','expectedoutput'];
                    // check that at least one id, one desc, one input and one expected column exist
                    $hasId = (bool)array_intersect($norm, ['tc_id','id','tc']);
                    $hasDesc = (bool)array_intersect($norm, ['description','desc']);
                    $hasInput = (bool)array_intersect($norm, ['input','inputs']);
                    $hasExpected = (bool)array_intersect($norm, ['expected','expected_output','expectedoutput']);
                    if (!($hasId && $hasDesc && $hasInput && $hasExpected)) {
                      $missing = [];
                      if (!$hasId) $missing[] = 'TC id (tc_id / id)';
                      if (!$hasDesc) $missing[] = 'Description (description / desc)';
                      if (!$hasInput) $missing[] = 'Input (input)';
                      if (!$hasExpected) $missing[] = 'Expected (expected / expected_output)';
                      $invalidMsg = 'CSV thiếu cột bắt buộc: ' . implode(', ', $missing) . '. Vui lòng dùng template TC chuẩn.';
                    }
                  }
                } else {
                  $invalidMsg = 'Không thể mở file CSV để kiểm tra.';
                }
              }

              if ($invalidMsg) {
                // remove uploaded invalid file
                @unlink($filepath);
                respond(false, $invalidMsg, $assign_id);
              }

              if ($existing) {
                @unlink($filepath);
                respond(false, 'Bạn đã nộp bài rồi. Không thể nộp lại.', $assign_id);
              }

              $stmt = $db->prepare('INSERT INTO submissions (assignment_id,student_id,file_path,file_name) VALUES (?,?,?,?)');
              $stmt->execute([$assign_id, $uid, $filename, $file['name']]);
              respond(true, 'Nộp bài thành công!', $assign_id);
            } else {
                respond(false, 'Lỗi upload file.', $assign_id);
            }
        }
    }
}

// Load assignments for this student's class
$stmt = $db->prepare('SELECT a.*, (SELECT id FROM submissions WHERE assignment_id=a.id AND student_id=?) as sub_id, (SELECT file_name FROM submissions WHERE assignment_id=a.id AND student_id=?) as sub_file, (SELECT status FROM submissions WHERE assignment_id=a.id AND student_id=?) as sub_status FROM assignments a WHERE a.class=? ORDER BY a.deadline ASC');
$stmt->execute([$uid, $uid, $uid, $_SESSION['class']]);
$assignments = $stmt->fetchAll();

// Selected assignment
$selectedId = (int)($_GET['id'] ?? 0);
$selected = null;
foreach ($assignments as $a) {
    if ($a['id'] == $selectedId) { $selected = $a; break; }
}

// My submissions
$stmt = $db->prepare('SELECT s.*, a.title, g.score, g.feedback FROM submissions s JOIN assignments a ON a.id=s.assignment_id LEFT JOIN grades g ON g.submission_id=s.id WHERE s.student_id=? ORDER BY s.submitted_at DESC');
$stmt->execute([$uid]);
$mySubmissions = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div class="page-title">Nộp bài</div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

<!-- Upload form -->
<div class="card">
  <div style="font-size:16px;font-weight:500;margin-bottom:16px">Upload bài tập</div>
  <form method="POST" enctype="multipart/form-data" id="uploadForm">
    <div class="form-group">
      <label class="form-label">Chọn bài tập</label>
      <select name="assignment_id" class="form-control" required onchange="this.form.submit()" id="assignSelect">
        <option value="">-- Chọn bài tập --</option>
        <?php foreach ($assignments as $a): ?>
          <?php $expired = strtotime($a['deadline']) < time(); ?>
          <option value="<?= $a['id'] ?>" <?= $a['id']==$selectedId?'selected':'' ?> <?= $expired?'disabled':'' ?>>
            <?= htmlspecialchars($a['title']) ?> <?= $expired?'(Hết hạn)':'' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <?php if ($selected): ?>
    <div style="background:var(--accent-bg);border-radius:8px;padding:10px 14px;font-size:13px;color:var(--accent-text);margin-bottom:14px">
      <strong>Hạn nộp:</strong> <?= date('d/m/Y H:i', strtotime($selected['deadline'])) ?><br>
      <strong>File cho phép:</strong> <?= htmlspecialchars($selected['file_types']) ?>
      <?php if ($selected['sub_file']): ?>
      <br><strong>Bài đã nộp:</strong> <?= htmlspecialchars($selected['sub_file']) ?>
      <?php endif; ?>
    </div>

    <input type="hidden" name="assignment_id" value="<?= $selected['id'] ?>">
    <input type="hidden" name="ajax" value="1">
    <div class="form-group">
      <label class="form-label">File bài nộp</label>
      <div class="upload-zone" id="uploadZone">
        <i class="ti ti-cloud-upload" id="uploadZoneIcon"></i>
        <strong id="uploadZoneText">Kéo thả hoặc click để chọn file</strong><br>
        <span style="font-size:13px;color:var(--text3)" id="uploadZoneHint">Tối đa 20MB · <?= htmlspecialchars(strtoupper($selected['file_types'])) ?></span>
      </div>
      <input type="file" id="file" name="file" style="display:none" accept=".<?= str_replace(',', ',.', $selected['file_types']) ?>">
      <div class="upload-progress-wrap" id="uploadProgressWrap" style="display:none">
        <div class="upload-progress-bar"><div class="upload-progress-fill" id="uploadProgressFill"></div></div>
        <div class="upload-progress-pct" id="uploadProgressPct">0%</div>
      </div>
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;" id="submitBtn">
      <i class="ti ti-upload"></i> Nộp bài
    </button>
    <?php else: ?>
    <div style="color:var(--text3);font-size:14px;text-align:center;padding:20px 0">Chọn bài tập để tiếp tục</div>
    <?php endif; ?>
  </form>
</div>

<!-- My submissions -->
<div>
  <div style="font-size:16px;font-weight:500;margin-bottom:12px">Lịch sử nộp bài</div>
  <div class="list-card">
    <?php foreach ($mySubmissions as $s): ?>
    <div class="list-row">
      <div class="row-left">
        <div class="row-icon" style="background:var(--<?= $s['status']==='graded'?'success':'warning' ?>-bg)">
          <i class="ti ti-file-check" style="color:var(--<?= $s['status']==='graded'?'success':'warning' ?>)"></i>
        </div>
        <div class="row-info">
          <div class="row-title"><?= htmlspecialchars($s['title']) ?></div>
          <div class="row-desc"><?= htmlspecialchars($s['file_name']) ?> · <?= date('d/m/Y H:i', strtotime($s['submitted_at'])) ?></div>
        </div>
      </div>
      <div class="row-right">
        <?php if ($s['score'] !== null): ?>
          <span class="score-big" style="font-size:18px"><?= $s['score'] ?></span>
        <?php endif; ?>
        <span class="badge badge-<?= $s['status']==='graded'?'green':'amber' ?>">
          <?= $s['status']==='graded'?'Đã chấm':'Chờ chấm' ?>
        </span>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($mySubmissions)): ?>
    <div style="padding:24px;text-align:center;color:var(--text3)">Chưa có bài nào được nộp.</div>
    <?php endif; ?>
  </div>
</div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
(function(){
  var zone = document.getElementById('uploadZone');
  var fileInput = document.getElementById('file');
  var form = document.getElementById('uploadForm');
  if (!zone || !fileInput || !form) return;

  var zoneIcon = document.getElementById('uploadZoneIcon');
  var zoneText = document.getElementById('uploadZoneText');
  var zoneHint = document.getElementById('uploadZoneHint');
  var progressWrap = document.getElementById('uploadProgressWrap');
  var progressFill = document.getElementById('uploadProgressFill');
  var progressPct = document.getElementById('uploadProgressPct');
  var submitBtn = document.getElementById('submitBtn');
  var defaultHint = zoneHint.textContent;

  var ICONS = {
    pdf: 'ti-file-type-pdf', doc: 'ti-file-type-doc', docx: 'ti-file-type-doc',
    xls: 'ti-file-type-xls', xlsx: 'ti-file-type-xls',
    zip: 'ti-file-zip', rar: 'ti-file-zip',
    ppt: 'ti-file-type-ppt', pptx: 'ti-file-type-ppt',
    jpg: 'ti-photo', jpeg: 'ti-photo', png: 'ti-photo'
  };

  function fmtSize(bytes) {
    if (bytes < 1024*1024) return Math.round(bytes/1024) + ' KB';
    return (bytes/1024/1024).toFixed(1) + ' MB';
  }

  function showFile(file) {
    var ext = file.name.split('.').pop().toLowerCase();
    zoneIcon.className = 'ti ' + (ICONS[ext] || 'ti-file');
    zoneText.textContent = file.name;
    zoneHint.textContent = fmtSize(file.size);
    zone.classList.add('upload-zone-filled');
  }

  function resetZone() {
    zoneIcon.className = 'ti ti-cloud-upload';
    zoneText.textContent = 'Kéo thả hoặc click để chọn file';
    zoneHint.textContent = defaultHint;
    zone.classList.remove('upload-zone-filled');
  }

  zone.addEventListener('click', function(){ fileInput.click(); });

  fileInput.addEventListener('change', function(){
    if (fileInput.files[0]) showFile(fileInput.files[0]);
  });

  ['dragenter','dragover'].forEach(function(evt){
    zone.addEventListener(evt, function(e){
      e.preventDefault(); e.stopPropagation();
      zone.classList.add('upload-zone-drag');
    });
  });
  ['dragleave','drop'].forEach(function(evt){
    zone.addEventListener(evt, function(e){
      e.preventDefault(); e.stopPropagation();
      zone.classList.remove('upload-zone-drag');
    });
  });
  zone.addEventListener('drop', function(e){
    var files = e.dataTransfer.files;
    if (files && files[0]) {
      fileInput.files = files;
      showFile(files[0]);
    }
  });

  form.addEventListener('submit', function(e){
    e.preventDefault();
    if (!fileInput.files[0]) {
      showToast('Vui lòng chọn file.', 'error');
      return;
    }

    var fd = new FormData(form);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/submit.php', true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="ti ti-loader-2 spin"></i> Đang nộp...';
    progressWrap.style.display = 'flex';
    progressFill.style.width = '0%';
    progressPct.textContent = '0%';

    xhr.upload.addEventListener('progress', function(e){
      if (e.lengthComputable) {
        var pct = Math.round((e.loaded / e.total) * 100);
        progressFill.style.width = pct + '%';
        progressPct.textContent = pct + '%';
      }
    });

    xhr.onload = function(){
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="ti ti-upload"></i> Nộp bài';
      var res;
      try { res = JSON.parse(xhr.responseText); }
      catch(err) { res = { success: false, message: 'Có lỗi xảy ra. Vui lòng thử lại.' }; }

      if (res.success) {
        progressFill.style.width = '100%';
        progressPct.textContent = '100%';
        showToast(res.message || 'Nộp bài thành công!', 'success');
        setTimeout(function(){ window.location.reload(); }, 900);
      } else {
        progressWrap.style.display = 'none';
        showToast(res.message || 'Có lỗi xảy ra.', 'error');
      }
    };

    xhr.onerror = function(){
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="ti ti-upload"></i> Nộp bài';
      progressWrap.style.display = 'none';
      showToast('Không thể kết nối tới máy chủ.', 'error');
    };

    xhr.send(fd);
  });

  function showToast(msg, type){
    var el = document.createElement('div');
    el.className = 'toast toast-' + (type === 'error' ? 'error' : 'success');
    el.innerHTML = '<i class="ti ti-' + (type === 'error' ? 'alert-circle' : 'circle-check') + '"></i><span>' + msg + '</span>';
    document.body.appendChild(el);
    requestAnimationFrame(function(){ el.classList.add('toast-show'); });
    setTimeout(function(){
      el.classList.remove('toast-show');
      setTimeout(function(){ el.remove(); }, 300);
    }, 3200);
  }
})();
</script>
