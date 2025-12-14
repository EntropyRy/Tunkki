<?php

declare(strict_types=1);

/**
 * coverage_to_shields.php.
 *
 * Convert a PHPUnit Clover coverage.xml into a shields.io JSON badge.
 *
 * Usage:
 *   php coverage_to_shields.php [--in=coverage.xml] [--out=coverage.json]
 *
 * Defaults:
 *   --in=coverage.xml           (relative to current working directory)
 *   --out=coverage.json         (writes to current working directory)
 *
 * Exit codes:
 *   0  success
 *   2  invalid arguments or file not found
 *   3  parse error
 */

// -------- Argument parsing ---------------------------------------------------
$inPath = 'coverage.xml';
$outPath = 'coverage.json';

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--in=')) {
        $inPath = substr($arg, 5);
    } elseif (str_starts_with($arg, '--out=')) {
        $outPath = substr($arg, 6);
    }
}

// -------- Validate input -----------------------------------------------------
if (!is_file($inPath)) {
    fwrite(
        \STDERR,
        "[coverage_to_shields] Coverage file not found: {$inPath}\n",
    );
    exit(2);
}

// -------- Parse Clover XML ---------------------------------------------------
libxml_use_internal_errors(true);
$xml = simplexml_load_file($inPath);
if (!$xml) {
    fwrite(
        \STDERR,
        "[coverage_to_shields] Failed to parse Clover XML: {$inPath}\n",
    );
    foreach (libxml_get_errors() as $err) {
        fwrite(\STDERR, '  libxml: '.trim($err->message)."\n");
    }
    libxml_clear_errors();
    exit(3);
}

// Clover structure:
// <coverage>
//   <project timestamp="...">
//     <metrics statements="X" coveredstatements="Y" elements="E" coveredelements="CE" ... />
//   </project>
// </coverage>
$project = $xml->project ?? null;
$metrics = $project ? $project->metrics ?? null : null;

if (!$metrics) {
    fwrite(
        \STDERR,
        "[coverage_to_shields] No <project><metrics> found in Clover XML.\n",
    );
    exit(3);
}

$statements = (int) ($metrics['statements'] ?? 0);
$coveredStatements = (int) ($metrics['coveredstatements'] ?? 0);

// Fallback to elements if statements are missing in generator
if (0 === $statements && isset($metrics['elements'])) {
    $statements = (int) $metrics['elements'];
    $coveredStatements = (int) ($metrics['coveredelements'] ?? 0);
}

$percentage = 0.0;
if ($statements > 0) {
    $percentage = ($coveredStatements / $statements) * 100.0;
}
$rounded = (float) round($percentage, 2);

// -------- Color ramp ---------------------------------------------------------
$color = (function (float $p): string {
    if ($p >= 90) {
        return 'brightgreen';
    }
    if ($p >= 75) {
        return 'green';
    }
    if ($p >= 60) {
        return 'yellow';
    }
    if ($p >= 45) {
        return 'orange';
    }

    return 'red';
})($rounded);

// -------- Output JSON --------------------------------------------------------
$payload = [
    'schemaVersion' => 1,
    'label' => 'coverage',
    'message' => "{$rounded}%",
    'color' => $color,
];

$dir = dirname($outPath);
if ('' !== $dir && '.' !== $dir && !is_dir($dir)) {
    // Attempt to create directory tree if it doesn't exist
    if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
        fwrite(
            \STDERR,
            "[coverage_to_shields] Failed to create directory: {$dir}\n",
        );
        exit(3);
    }
}

$json = json_encode($payload, \JSON_UNESCAPED_SLASHES);
if (false === $json) {
    fwrite(\STDERR, "[coverage_to_shields] Failed to encode JSON payload.\n");
    exit(3);
}

if (false === @file_put_contents($outPath, $json.\PHP_EOL)) {
    fwrite(
        \STDERR,
        "[coverage_to_shields] Failed to write output file: {$outPath}\n",
    );
    exit(3);
}

fwrite(
    \STDOUT,
    "[coverage_to_shields] Wrote {$outPath} with {$rounded}% coverage ({$coveredStatements}/{$statements}).\n",
);
exit(0);
