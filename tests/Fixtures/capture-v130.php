<?php

/**
 * Tsuku - Transform data into any format
 *
 * Captures the reference outputs used by the characterization tests from an
 * UNMODIFIED v1.3.0 checkout:
 *
 *   git archive v1.3.0 | tar -x -C /tmp/tsuku-130   (composer install or any PSR-4 autoloader)
 *   php tests/Fixtures/capture-v130.php /tmp/tsuku-130
 *
 * Writes tests/Fixtures/v1.3.0/conditions.json and tests/Fixtures/v1.3.0/examples/*.
 * Never run it against a modified tree: the fixtures are the 1.3.0 baseline.
 *
 * @package   Qoliber\Tsuku
 * @author    Jakub Winkler <jwinkler@qoliber.com>
 * @copyright 2025 qoliber
 * @license   MIT
 */

declare(strict_types=1);

use Qoliber\Tsuku\Tests\Characterization\ConditionMatrix;
use Qoliber\Tsuku\Tests\Characterization\ExampleRunner;
use Qoliber\Tsuku\Tsuku;

$reference = $argv[1] ?? '';
if ($reference === '' || !is_dir($reference . '/src')) {
    fwrite(STDERR, "usage: php tests/Fixtures/capture-v130.php <path-to-unmodified-v1.3.0-checkout>\n");
    exit(1);
}

spl_autoload_register(static function (string $class) use ($reference): void {
    $map = [
        'Qoliber\\Tsuku\\Tests\\' => __DIR__ . '/../',
        'Qoliber\\Tsuku\\' => rtrim($reference, '/') . '/src/',
    ];
    foreach ($map as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $file = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});

$results = [];
foreach (ConditionMatrix::cases() as $id => [$template, $valueKey]) {
    $data = ConditionMatrix::baseData();
    if ($valueKey !== null) {
        $data['v'] = ConditionMatrix::values()[$valueKey];
    }
    try {
        $results[$id] = (new Tsuku())->process($template, $data);
    } catch (\Throwable $e) {
        $results[$id] = '!' . get_class($e);
    }
}

$fixtureDir = __DIR__ . '/v1.3.0';
if (!is_dir($fixtureDir . '/examples')) {
    mkdir($fixtureDir . '/examples', 0777, true);
}
file_put_contents(
    $fixtureDir . '/conditions.json',
    json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
);
echo count($results) . " condition cases captured\n";

foreach (glob(rtrim($reference, '/') . '/examples/*.php') ?: [] as $example) {
    $result = ExampleRunner::run($example);
    $name = basename($example, '.php');
    file_put_contents($fixtureDir . '/examples/' . $name . '.txt', $result['stdout']);
    file_put_contents($fixtureDir . '/examples/' . $name . '.status', $result['status'] . "\n");
    echo "{$name}: {$result['status']}\n";
}
