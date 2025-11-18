<?php

/**
 * Many variables benchmark
 * Tests variable rendering performance with many variables
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

$tsuku = new Tsuku();

// Generate template with 1000 variables
$template = '';
for ($i = 0; $i < 1000; $i++) {
    $template .= '{var' . $i . '} ';
}

// Generate data
$data = [];
for ($i = 0; $i < 1000; $i++) {
    $data['var' . $i] = 'value' . $i;
}

$iterations = 100;

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $tsuku->process($template, $data);
}
$end = microtime(true);

$total = ($end - $start) * 1000;
$perIteration = $total / $iterations;

echo "Many Variables Benchmark\n";
echo "========================\n";
echo "Template: 1000 simple variables\n";
echo "Iterations: " . number_format($iterations) . "\n";
echo "Total time: " . number_format($total, 2) . " ms\n";
echo "Per iteration: " . number_format($perIteration, 4) . " ms\n";
echo "Throughput: " . number_format($iterations / ($total / 1000)) . " renders/sec\n";
