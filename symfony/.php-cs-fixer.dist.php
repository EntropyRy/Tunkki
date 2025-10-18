<?php

$finder = new PhpCsFixer\Finder()
    ->in(__DIR__)
    ->exclude('var')
    ->notPath('importmap.php')
;

return new PhpCsFixer\Config()
    ->setRiskyAllowed(true)
    ->setRules([
        "@Symfony" => true,
        "@Symfony:risky" => true,
        "single_quote" => true,
        "array_syntax" => ["syntax" => "short"],
        "declare_strict_types" => true,
        "ordered_imports" => ["sort_algorithm" => "alpha"],
        "no_unused_imports" => true,
        "phpdoc_align" => ["align" => "vertical"],
    ])
    ->setFinder($finder)
    // Enable parallel processing with auto-detected optimal settings
    ->setParallelConfig(
        PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect(),
    );
