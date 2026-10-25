<?php

/**
 * Run all benchmarks and display summary
 *
 * Every benchmark runs in its own process, started with the PHP binary that runs
 * this script. `php -n benchmarks/run-all.php` runs all of them with -n as well
 * (no php.ini, so no Xdebug and no OPcache).
 */

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                    TSUKU BENCHMARKS                        ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

$withoutIni = php_ini_loaded_file() === false && php_ini_scanned_files() === false;
$php = escapeshellarg(PHP_BINARY) . ($withoutIni ? ' -n' : '');

$cpu = 'unknown';
if (is_readable('/proc/cpuinfo')) {
    if (preg_match('/^model name\s*:\s*(.+)$/m', (string) file_get_contents('/proc/cpuinfo'), $matches) === 1) {
        $cpu = $matches[1];
    }
}

echo "Environment:\n";
echo "  PHP Version: " . PHP_VERSION . ' (' . PHP_BINARY . ($withoutIni ? ' -n' : '') . ")\n";
echo "  OS: " . PHP_OS . "\n";
echo "  CPU: {$cpu}\n";
echo "  Xdebug: " . (extension_loaded('xdebug') ? 'loaded' : 'not loaded') . "\n";
$opcache = extension_loaded('Zend OPcache') && filter_var(ini_get('opcache.enable_cli'), FILTER_VALIDATE_BOOLEAN);
echo "  OPcache (CLI): " . ($opcache ? 'enabled' : 'disabled') . "\n";
if (extension_loaded('xdebug')) {
    echo "\n  Warning: Xdebug slows PHP down a lot. Run: php -n benchmarks/run-all.php\n";
}
echo "\n";

// Run each benchmark
$benchmarks = [
    'simple.php' => 'Simple Template',
    'complex.php' => 'Complex Template',
    'variables.php' => 'Many Variables',
    'csv-export.php' => 'CSV Export (Real-World)',
];

$failed = [];
foreach ($benchmarks as $file => $name) {
    echo str_repeat('─', 60) . "\n";
    passthru($php . ' ' . escapeshellarg(__DIR__ . '/' . $file), $exitCode);
    if ($exitCode !== 0) {
        $failed[] = $name;
    }
    echo "\n";
}

echo str_repeat('─', 60) . "\n";
echo "\n";
echo "Summary:\n";
echo $failed === []
    ? "  ✓ All benchmarks completed successfully\n"
    : '  ✗ Failed: ' . implode(', ', $failed) . "\n";
echo "\n";

exit($failed === [] ? 0 : 1);
