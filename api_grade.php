<?php
header('Content-Type: application/json');
require_once __DIR__ . '/includes/auth.php';
requireRole('teacher');

$input = json_decode(file_get_contents('php://input'), true);
$submission_id = (int)($input['submission_id'] ?? 0);

if (!$submission_id) {
    echo json_encode(['error' => 'Thiếu submission_id.']);
    exit;
}

$db = getDB();
$stmt = $db->prepare('SELECT * FROM submissions WHERE id=?');
$stmt->execute([$submission_id]);
$sub = $stmt->fetch();
if (!$sub) {
    echo json_encode(['error' => 'Submission không tồn tại.']);
    exit;
}

// Use UPLOAD_DIR from includes/db.php to locate uploaded files reliably
$filePath = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $sub['file_path'];
if (!file_exists($filePath)) {
    echo json_encode([
        'error' => 'File nộp không tìm thấy trên server.',
        'tried_path' => $filePath,
        'is_readable' => is_readable($filePath)
    ]);
    exit;
}

// Very small analyzer for CSV TC files, otherwise fallback to name-based pseudo-grade
$ext = strtolower(pathinfo($sub['file_name'], PATHINFO_EXTENSION));
$missing = [];
$tcCount = 0;
$issues = [];
if ($ext === 'csv') {
    $fp = @fopen($filePath, 'r');
    if ($fp) {
        $headers = fgetcsv($fp);
        if ($headers === false) {
            $issues[] = 'File CSV trống.';
        } else {
            $norm = array_map(function($h){ return strtolower(trim($h)); }, $headers);
            $hasId = (bool)array_intersect($norm, ['tc_id','id','tc']);
            $hasDesc = (bool)array_intersect($norm, ['description','desc']);
            $hasInput = (bool)array_intersect($norm, ['input','inputs']);
            $hasExpected = (bool)array_intersect($norm, ['expected','expected_output','expectedoutput']);
            if (!($hasId && $hasDesc && $hasInput && $hasExpected)) {
                if (!$hasId) $missing[] = 'TC id';
                if (!$hasDesc) $missing[] = 'Description';
                if (!$hasInput) $missing[] = 'Input';
                if (!$hasExpected) $missing[] = 'Expected';
                $issues[] = 'CSV thiếu cột: ' . implode(', ', $missing);
            }
            // count rows and basic checks
            $row = 0;
            while (($data = fgetcsv($fp)) !== false) {
                $row++;
                // if a row has empty expected cell, note it
                $cells = array_map('trim', $data);
                // find expected column index
                $expIndex = null;
                foreach ($norm as $i => $h) {
                    if (in_array($h, ['expected','expected_output','expectedoutput'])) { $expIndex = $i; break; }
                }
                if ($expIndex !== null) {
                    if (!isset($cells[$expIndex]) || $cells[$expIndex] === '') $issues[] = "Dòng $row thiếu Expected.";
                }
            }
            $tcCount = $row;
        }
        fclose($fp);
    } else {
        $issues[] = 'Không thể mở file CSV.';
    }
}

// Derive pseudo-scores
$seed = crc32(file_get_contents($filePath));
mt_srand($seed);
$c = round(mt_rand(15, 30) / 10, 1);
$cr = round(mt_rand(15, 30) / 10, 1);
$cov = round(min(3, max(0, ($tcCount > 0 ? min(3, $tcCount / 5) : mt_rand(10,30)/10) )), 1);
$fmt = (!empty($missing) || count($issues)>3) ? 0.5 : 1.0;
$total = min(10, round($c + $cr + $cov + $fmt, 1));

$fbParts = [];
if (!empty($missing)) {
    $fbParts[] = 'Thiếu cột: ' . implode(', ', $missing) . '.';
}
if (!empty($issues)) {
    $fbParts[] = 'Vấn đề: ' . implode(' ; ', array_slice($issues,0,5));
}
if ($tcCount > 0) $fbParts[] = "Số test case: $tcCount.";

if ($total >= 8.5) {
    $fb = "Bài làm tốt. " . implode(' ', $fbParts);
} elseif ($total >= 7.0) {
    $fb = "Bài làm khá. " . implode(' ', $fbParts);
} else {
    $fb = "Cần cải thiện. " . implode(' ', $fbParts);
}
$fb .= "\n\n⚠️ [Chấm giả lập từ server — nâng cấp khi có AI thật]";

echo json_encode([
    'completeness'  => $c,
    'correctness'   => $cr,
    'coverage'      => $cov,
    'format_score'  => $fmt,
    'score'         => $total,
    'feedback'      => $fb,
    'tc_count'      => $tcCount,
    'issues'        => $issues,
    'saved'         => true,
    'submission_id' => $submission_id,
]);

// Persist grade to DB and mark submission as graded
$gradeStmt = $db->prepare('INSERT INTO grades (submission_id, score, feedback, completeness, correctness, coverage, format_score, graded_by, graded_at) VALUES (?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE score=VALUES(score),feedback=VALUES(feedback),completeness=VALUES(completeness),correctness=VALUES(correctness),coverage=VALUES(coverage),format_score=VALUES(format_score),graded_by=VALUES(graded_by),graded_at=NOW()');
$gradeStmt->execute([$submission_id, $total, $fb, $c, $cr, $cov, $fmt, $_SESSION['user_id']]);
$db->prepare('UPDATE submissions SET status="graded" WHERE id=?')->execute([$submission_id]);
// finished