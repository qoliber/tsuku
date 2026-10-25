<?php

/**
 * Tsuku - Transform data into any format
 *
 * Records the intended 1.4.0 output of every example whose output differs from
 * the v1.3.0 capture (tests/Fixtures/v1.4.0/examples). Run it only after reviewing
 * each difference: anything written here is accepted by ExampleCorpusTest, and
 * every difference has to be listed in CHANGELOG.md.
 *
 *   php tests/Fixtures/capture-v140.php
 *
 * @package   Qoliber\Tsuku
 * @author    Jakub Winkler <jwinkler@qoliber.com>
 * @copyright 2025 qoliber
 * @license   MIT
 */

declare(strict_types=1);

use Qoliber\Tsuku\Tests\Characterization\ExampleRunner;

require __DIR__ . '/../../vendor/autoload.php';

$baseline = __DIR__ . '/v1.3.0/examples/';
$target = __DIR__ . '/v1.4.0/examples/';
if (!is_dir($target)) {
    mkdir($target, 0777, true);
}

foreach (glob(__DIR__ . '/../../examples/*.php') ?: [] as $example) {
    $name = basename($example, '.php');
    $result = ExampleRunner::run((string) realpath($example));
    $same = is_file($baseline . $name . '.txt')
        && file_get_contents($baseline . $name . '.txt') === $result['stdout']
        && trim((string) file_get_contents($baseline . $name . '.status')) === $result['status'];

    if ($same) {
        @unlink($target . $name . '.txt');
        @unlink($target . $name . '.status');
        echo "{$name}: unchanged\n";
        continue;
    }

    file_put_contents($target . $name . '.txt', $result['stdout']);
    file_put_contents($target . $name . '.status', $result['status'] . "\n");
    echo "{$name}: CHANGED ({$result['status']})\n";
}
