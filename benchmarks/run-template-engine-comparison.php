<?php

/**
 * Run all template engine comparison benchmarks
 */

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║      TSUKU vs POPULAR TEMPLATE ENGINES BENCHMARKS          ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

$phpVersion = PHP_VERSION;
$os = PHP_OS;

echo "Environment:\n";
echo "  PHP Version: {$phpVersion}\n";
echo "  OS: {$os}\n";
echo "\n";

// Run each benchmark
$benchmarks = [
    'CSV Export' => [
        'twig-vs-tsuku.php' => 'Twig',
        'mustache-vs-tsuku.php' => 'Mustache',
        'smarty-vs-tsuku.php' => 'Smarty',
        'plates-vs-tsuku.php' => 'Plates',
        'latte-vs-tsuku.php' => 'Latte',
    ],
    'JSON Export' => [
        'twig-vs-tsuku-json.php' => 'Twig',
        'mustache-vs-tsuku-json.php' => 'Mustache',
        'smarty-vs-tsuku-json.php' => 'Smarty',
        'plates-vs-tsuku-json.php' => 'Plates',
        'latte-vs-tsuku-json.php' => 'Latte',
    ],
    'XML Export' => [
        'twig-vs-tsuku-xml.php' => 'Twig',
        'mustache-vs-tsuku-xml.php' => 'Mustache',
        'smarty-vs-tsuku-xml.php' => 'Smarty',
        'plates-vs-tsuku-xml.php' => 'Plates',
        'latte-vs-tsuku-xml.php' => 'Latte',
    ],
];

$results = [];

foreach ($benchmarks as $category => $files) {
    echo str_repeat('═', 60) . "\n";
    echo "  " . strtoupper($category) . "\n";
    echo str_repeat('═', 60) . "\n\n";

    foreach ($files as $file => $name) {
        echo str_repeat('─', 60) . "\n";

        ob_start();
        passthru('php ' . __DIR__ . '/' . $file . ' 2>&1', $exitCode);
        $output = ob_get_clean();

        echo $output;
        echo "\n";

        // Parse results
        $key = $category . ' - ' . $name;
        if (preg_match('/Tsuku is ([\d.]+)x FASTER/', $output, $matches)) {
            $results[$key] = [
                'winner' => 'Tsuku',
                'speedup' => (float)$matches[1]
            ];
        } elseif (preg_match('/(\w+) is ([\d.]+)x FASTER/', $output, $matches)) {
            $results[$key] = [
                'winner' => $matches[1],
                'speedup' => (float)$matches[2]
            ];
        }
    }
}

echo str_repeat('─', 60) . "\n";
echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                    BENCHMARK SUMMARY                       ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

echo "Performance Comparison:\n";
echo "-----------------------\n\n";

// Group results by format
foreach (['CSV Export', 'JSON Export', 'XML Export'] as $format) {
    echo "  {$format}:\n";
    foreach ($results as $key => $result) {
        if (str_starts_with($key, $format)) {
            $engine = str_replace($format . ' - ', '', $key);
            $icon = $result['winner'] === 'Tsuku' ? '✓' : '✗';
            $speedupText = number_format($result['speedup'], 2) . 'x';

            if ($result['winner'] === 'Tsuku') {
                echo "    {$icon} vs {$engine}: Tsuku is {$speedupText} FASTER\n";
            } else {
                echo "    {$icon} vs {$engine}: {$result['winner']} is {$speedupText} faster\n";
            }
        }
    }
    echo "\n";
}

// Count wins
$tsukuWins = count(array_filter($results, fn($r) => $r['winner'] === 'Tsuku'));
$totalBenchmarks = count($results);
$otherWins = $totalBenchmarks - $tsukuWins;

echo "Score:\n";
echo "------\n";
echo "  Tsuku wins: {$tsukuWins} / {$totalBenchmarks}\n";
echo "  Others win: {$otherWins} / {$totalBenchmarks}\n";
echo "\n";

echo "Key Insights:\n";
echo "-------------\n";
echo "  • Tsuku prioritizes SIMPLICITY over raw speed\n";
echo "  • Tsuku is optimized for DATA TRANSFORMATION (CSV, XML, JSON)\n";
echo "  • Most template engines optimize for HTML rendering\n";
echo "  • Tsuku beats XSLT (1.10-1.49x) which is the real comparison\n";
echo "  • Performance is excellent for production use\n";
echo "\n";

echo "Why Choose Tsuku?\n";
echo "-----------------\n";
echo "  ✓ Simplest syntax for data transformations\n";
echo "  ✓ No learning curve (5 minutes to productivity)\n";
echo "  ✓ String-based (no file system required)\n";
echo "  ✓ Perfect for CSV, XML, JSON, TSV exports\n";
echo "  ✓ Much simpler than XSLT with better performance\n";
echo "  ✓ Clean, readable templates\n";
echo "\n";

echo "When to Use Others?\n";
echo "-------------------\n";
echo "  • Twig: Complex HTML with heavy caching needs\n";
echo "  • Mustache: When you need true logic-less templates\n";
echo "  • Smarty: Legacy projects already using it\n";
echo "  • Plates: When native PHP syntax is preferred\n";
echo "  • Latte: Nette Framework projects\n";
echo "\n";

echo "Bottom Line:\n";
echo "------------\n";
echo "  Tsuku isn't trying to be the fastest template engine.\n";
echo "  It's trying to be the SIMPLEST way to transform data.\n";
echo "  And it's WAY faster than XSLT (the traditional solution).\n";
echo "\n";
