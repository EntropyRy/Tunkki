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
    "app" => [
        "path" => "./assets/app.js",
        "entrypoint" => true,
    ],
    "admin_effects" => [
        "path" => "./assets/js/admin_effects.js",
        "entrypoint" => true,
    ],
    "rain" => [
        "path" => "./assets/js/rain.js",
        "entrypoint" => true,
    ],
    "snow" => [
        "path" => "./assets/js/snow.js",
        "entrypoint" => true,
    ],
    "chladni" => [
        "path" => "./assets/js/chladni.js",
        "entrypoint" => true,
    ],
    "snow_mouse_dodge" => [
        "path" => "./assets/js/snow_mouse_dodge.js",
        "entrypoint" => true,
    ],
    "grid" => [
        "path" => "./assets/js/grid.js",
        "entrypoint" => true,
    ],
    "stars" => [
        "path" => "./assets/js/stars.js",
        "entrypoint" => true,
    ],
    "tv" => [
        "path" => "./assets/js/tv.js",
        "entrypoint" => true,
    ],
    "lines" => [
        "path" => "./assets/js/lines.js",
        "entrypoint" => true,
    ],
    "vhs" => [
        "path" => "./assets/js/vhs.js",
        "entrypoint" => true,
    ],
    "snake" => [
        "path" => "./assets/js/snake.js",
        "entrypoint" => true,
    ],
    "flowfields" => [
        "path" => "./assets/js/flowfields.js",
        "entrypoint" => true,
    ],
    "roaches" => [
        "path" => "./assets/js/roaches.js",
        "entrypoint" => true,
    ],
    "@symfony/stimulus-bundle" => [
        "path" => "./vendor/symfony/stimulus-bundle/assets/dist/loader.js",
    ],
    "@symfony/ux-live-component" => [
        "path" =>
            "./vendor/symfony/ux-live-component/assets/dist/live_controller.js",
    ],
    "@symfony/ux-leaflet-map" => [
        "path" =>
            "./vendor/symfony/ux-leaflet-map/assets/dist/map_controller.js",
    ],
    "@hotwired/stimulus" => [
        "version" => "3.2.2",
    ],
    "bootstrap" => [
        "version" => "5.3.8",
    ],
    "@popperjs/core" => [
        "version" => "2.11.8",
    ],
    "html5-qrcode" => [
        "version" => "2.3.8",
    ],
    "@hotwired/turbo" => [
        "version" => "8.0.13",
    ],
    "@fontsource-variable/space-grotesk/index.css" => [
        "version" => "5.2.9",
        "type" => "css",
    ],
    "signature_pad" => [
        "version" => "5.1.0",
    ],
    "moment/min/moment-with-locales.min.js" => [
        "version" => "2.30.1",
    ],
    "es-module-shims" => [
        "version" => "2.6.2",
    ],
    "bootstrap/dist/css/bootstrap.min.css" => [
        "version" => "5.3.8",
        "type" => "css",
    ],
    "sortablejs" => [
        "version" => "1.15.6",
    ],
    "howler" => [
        "version" => "2.2.4",
    ],
    "leaflet" => [
        "version" => "1.9.4",
    ],
    "leaflet/dist/leaflet.min.css" => [
        "version" => "1.9.4",
        "type" => "css",
    ],
];
