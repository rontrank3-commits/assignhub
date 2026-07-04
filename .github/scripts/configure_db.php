<?php

$file = 'build/includes/db.php';
$content = file_get_contents($file);

if ($content === false) {
    fwrite(STDERR, "Không đọc được file: $file\n");
    exit(1);
}

$replacements = [
    "DB_HOST', 'localhost'" => "DB_HOST', '" . getenv('DB_HOST') . "'",
    "DB_NAME', 'AI'"        => "DB_NAME', '" . getenv('DB_NAME') . "'",
    "DB_USER', 'root'"      => "DB_USER', '" . getenv('DB_USER') . "'",
    "DB_PASS', ''"          => "DB_PASS', '" . getenv('DB_PASS') . "'",
];

$missing = [];
foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $key) {
    if (getenv($key) === false || getenv($key) === '') {
        $missing[] = $key;
    }
}
if (!empty($missing)) {
    fwrite(STDERR, "Thiếu biến môi trường: " . implode(', ', $missing) . "\n");
    exit(1);
}

foreach ($replacements as $search => $replace) {
    $content = str_replace($search, $replace, $content);
}

file_put_contents($file, $content);
echo "Đã cập nhật cấu hình DB production cho includes/db.php\n";
