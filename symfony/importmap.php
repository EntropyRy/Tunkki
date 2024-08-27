<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    'e30v' => [
        'path' => './assets/e30v.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    'bootstrap' => [
        'version' => '5.3.3',
    ],
    '@popperjs/core' => [
        'version' => '2.11.8',
    ],
    'bootstrap/dist/css/bootstrap.min.css' => [
        'version' => '5.3.3',
        'type' => 'css',
    ],
    'html5-qrcode' => [
        'version' => '2.3.8',
    ],
    'masonry-layout' => [
        'version' => '4.2.2',
    ],
    'outlayer' => [
        'version' => '2.1.1',
    ],
    'get-size' => [
        'version' => '2.0.3',
    ],
    'ev-emitter' => [
        'version' => '1.1.1',
    ],
    'fizzy-ui-utils' => [
        'version' => '2.0.7',
    ],
    'desandro-matches-selector' => [
        'version' => '2.0.2',
    ],
    'sortablejs' => [
        'version' => '1.15.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    'intl-messageformat' => [
        'version' => '10.5.14',
    ],
    'tslib' => [
        'version' => '2.6.2',
    ],
    '@formatjs/icu-messageformat-parser' => [
        'version' => '2.7.8',
    ],
    '@formatjs/fast-memoize' => [
        'version' => '2.2.0',
    ],
    '@formatjs/icu-skeleton-parser' => [
        'version' => '1.8.2',
    ],
    '@hotwired/turbo' => [
        'version' => '7.3.0',
    ],
    '@fontsource-variable/space-grotesk/index.css' => [
        'version' => '5.0.19',
        'type' => 'css',
    ],
    'moment' => [
        'version' => '2.30.1',
    ],
    'signature_pad' => [
        'version' => '5.0.3',
    ],
];
