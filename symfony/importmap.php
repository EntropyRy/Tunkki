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
    'snow' => [
        'path' => './assets/js/snow.js',
        'entrypoint' => true,
    ],
    'snow_mouse_dodge' => [
        'path' => './assets/js/snow_mouse_dodge.js',
        'entrypoint' => true,
    ],
    'grid' => [
        'path' => './assets/js/grid.js',
        'entrypoint' => true,
    ],
    'stars' => [
        'path' => './assets/js/stars.js',
        'entrypoint' => true,
    ],
    'tv' => [
        'path' => './assets/js/tv.js',
        'entrypoint' => true,
    ],
    'lines' => [
        'path' => './assets/js/lines.js',
        'entrypoint' => true,
    ],
    'vhs' => [
        'path' => './assets/js/vhs.js',
        'entrypoint' => true,
    ],
    'snake' => [
        'path' => './assets/js/snake.js',
        'entrypoint' => true,
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
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
    'html5-qrcode' => [
        'version' => '2.3.8',
    ],
    '@hotwired/turbo' => [
        'version' => '8.0.13',
    ],
    '@fontsource-variable/space-grotesk/index.css' => [
        'version' => '5.2.6',
        'type' => 'css',
    ],
    'signature_pad' => [
        'version' => '5.0.6',
    ],
    '@fortawesome/fontawesome-free/css/all.css' => [
        'version' => '6.7.2',
        'type' => 'css',
    ],
    'moment/min/moment-with-locales.min.js' => [
        'version' => '2.30.1',
    ],
    'es-module-shims' => [
        'version' => '2.0.10',
    ],
    'bootstrap/dist/css/bootstrap.min.css' => [
        'version' => '5.3.3',
        'type' => 'css',
    ],
    'sortablejs' => [
        'version' => '1.15.6',
    ],
];
