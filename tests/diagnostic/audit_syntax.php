<?php
declare(strict_types=1);

// Command-line only: never runnable through a web server.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$dir = new RecursiveDirectoryIterator(__DIR__ . '/../../');
$it = new RecursiveIteratorIterator($dir);
$errors = 0;
$checked = 0;
$errorList = [];

foreach ($it as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getPathname();
        if (str_contains($path, 'vendor') || str_contains($path, '.git')) {
            continue;
        }
        $checked++;
        $out = [];
        $ret = 0;
        exec('php -l ' . escapeshellarg($path), $out, $ret);
        if ($ret !== 0) {
            $errorList[] = [$path, implode("\n", $out)];
            $errors++;
        }
    }
}

echo "=== PHP SYNTAX LINT AUDIT ===\n";
echo "Files checked: {$checked}\n";
echo "Syntax errors: {$errors}\n";
if ($errors > 0) {
    foreach ($errorList as [$p, $msg]) {
        echo "Error in {$p}:\n{$msg}\n\n";
    }
}
