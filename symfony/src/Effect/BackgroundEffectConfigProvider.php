<?php

declare(strict_types=1);

namespace App\Effect;

/**
 * Central provider for background effect configuration defaults and presets.
 *
 * Responsibilities:
 * - Tell which effects support external configuration
 * - Provide per-effect sensible defaults (mirrors current JS defaults)
 * - Provide named presets (currently for Flowfields)
 * - Parse/normalize JSON payloads and merge with defaults
 *
 * Storage recommendation:
 * - Persist JSON in Event.backgroundEffectConfig (TEXT)
 * - Keep JSON compact or pretty – both are accepted. This class can normalize to pretty JSON.
 */
final class BackgroundEffectConfigProvider
{
    /**
     * Effects that currently support external configuration.
     * Keys are effect IDs as used in Event.backgroundEffect.
     *
     * @var array<string, bool>
     */
    private const array SUPPORTED_EFFECTS = [
        'flowfields' => true,
        'chladni'    => true,
        'roaches'    => true,
    ];

    /**
     * Returns true if the given effect supports configuration overrides.
     */
    public function supports(string $effect): bool
    {
        return isset(self::SUPPORTED_EFFECTS[$effect]);
    }

    /**
     * Returns the default configuration for a given effect as a PHP array.
     * The defaults mirror the current values in the respective JS files.
     *
     * @return array<string, mixed>
     */
    public function getDefaultConfig(string $effect): array
    {
        return match ($effect) {
            'flowfields' => $this->flowfieldsDefaults(),
            'chladni'    => $this->chladniDefaults(),
            'roaches'    => $this->roachesDefaults(),
            default      => [],
        };
    }

    /**
     * Returns a pretty-printed JSON representation of the default configuration.
     */
    public function getDefaultConfigJson(string $effect): string
    {
        return $this->toJson($this->getDefaultConfig($effect), true);
    }

    /**
     * Returns preset configurations for a given effect as an associative array:
     *   presetName => configArray
     *
     * Only effects with curated presets will return a non-empty array.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getPresets(string $effect): array
    {
        return match ($effect) {
            'flowfields' => $this->flowfieldsPresets(),
            default      => [],
        };
    }

    /**
     * Returns the list of preset names for the effect.
     *
     * @return list<string>
     */
    public function getPresetNames(string $effect): array
    {
        return array_keys($this->getPresets($effect));
    }

    /**
     * Returns a single named preset for an effect, or null if not found.
     *
     * @return array<string, mixed>|null
     */
    public function getPreset(string $effect, string $presetName): ?array
    {
        $presets = $this->getPresets($effect);

        return $presets[$presetName] ?? null;
    }

    /**
     * Merge a user-provided config (array) on top of the effect defaults.
     * Unknown keys are preserved; missing keys are filled in from defaults.
     *
     * @param array<string, mixed>|null $user
     * @return array<string, mixed>
     */
    public function mergeWithDefaults(string $effect, ?array $user): array
    {
        $defaults = $this->getDefaultConfig($effect);
        if ($user === null) {
            return $defaults;
        }

        return $this->arrayMergeDeep($defaults, $user);
    }

    /**
     * Parse JSON into an array. Returns null for empty input and throws on invalid JSON if $throwOnError is true.
     *
     * @return array<string, mixed>|null
     */
    public function parseJson(?string $json, bool $throwOnError = false): ?array
    {
        if ($json === null) {
            return null;
        }
        $trimmed = trim($json);
        if ($trimmed === '') {
            return null;
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($trimmed, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            if ($throwOnError) {
                throw $e;
            }
            return null;
        }

        if (!is_array($decoded)) {
            if ($throwOnError) {
                throw new \JsonException('JSON must decode to an object');
            }
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * Encode config array into JSON string. If $pretty is true, output is pretty printed and sorted by keys.
     *
     * @param array<string, mixed> $config
     */
    public function toJson(array $config, bool $pretty = false): string
    {
        $flags = \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION;

        $normalized = $pretty
            ? $this->ksortDeep($config)
            : $config;

        if ($pretty) {
            $flags |= \JSON_PRETTY_PRINT;
        }

        return json_encode($normalized, $flags | \JSON_THROW_ON_ERROR);
    }

    /**
     * Normalize a raw JSON string:
     * - parse -> merge with defaults (optional) -> pretty print
     *
     * If the input cannot be parsed, returns the defaults for the effect as JSON.
     */
    public function normalizeJson(?string $json, string $effect, bool $mergeWithDefaults = true): string
    {
        $parsed = $this->parseJson($json) ?? [];
        $normalized = $mergeWithDefaults ? $this->mergeWithDefaults($effect, $parsed) : $parsed;

        return $this->toJson($normalized, true);
    }

    /**
     * Build a minimal map for admin UI masks:
     * - keys are effect strings
     * - values are booleans (true => show config field)
     *
     * @return array<string, bool>
     */
    public function maskForChoiceField(): array
    {
        return self::SUPPORTED_EFFECTS;
    }

    // ---- Effect Defaults ----

    /**
     * Defaults based on assets/js/flowfields.js at the time of introducing this provider.
     *
     * @return array<string, mixed>
     */
    private function flowfieldsDefaults(): array
    {
        return [
            'particleCount'        => 1500,
            'particleBaseSpeed'    => 1.0,
            'particleSpeedVariation' => 0.5,
            'particleSize'         => 1.0,
            'particleColor'        => ['r' => 153, 'g' => 28, 'b' => 42],
            'fadeAmount'           => 0.03,
            'flowFieldIntensity'   => 0.5,
            'noiseScale'           => 0.003,
            'noiseSpeed'           => 0.0005,
            'particleLifespan'     => 100,
            'cursorInfluence'      => 150,
            'cursorRepel'          => false,
            'colorMode'            => 'complement', // 'fixed' | 'age' | 'position' | 'flow' | 'complement' | 'analogous'
            'enableTrails'         => true,
            'trailLength'          => 0.98,
            'trailWidth'           => 1.5,
            'hueShiftRange'        => 60,
            'showControls'         => true,
        ];
    }

    /**
     * A small set of curated Flowfields presets.
     * "Default" mirrors the defaults; "Bunka" is tuned slightly differently.
     *
     * @return array<string, array<string, mixed>>
     */
    private function flowfieldsPresets(): array
    {
        $defaults = $this->flowfieldsDefaults();

        $bunka = $this->arrayMergeDeep($defaults, [
            // Legacy bunka.js tuning
            'particleCount'          => 1100,
            'particleBaseSpeed'      => 0.3,
            'particleSpeedVariation' => 1.5,
            'particleSize'           => 1.0,
            'particleColor'          => ['r' => 153, 'g' => 28, 'b' => 42],
            'fadeAmount'             => 0.001,
            'flowFieldIntensity'     => 0.8,
            'noiseScale'             => 0.003,
            'noiseSpeed'             => 0.0001,
            'particleLifespan'       => 800,
            'cursorInfluence'        => 250,
            'cursorRepel'            => false,
            'colorMode'              => 'complement',
            'enableTrails'           => true,
            'trailLength'            => 0.98,
            'trailWidth'             => 3.0,
            'hueShiftRange'          => 120,
        ]);

        return [
            'Default' => $defaults,
            'Bunka'   => $bunka,
        ];
    }

    /**
     * Defaults for Chladni. Current JS renders time-based parameters; these fields offer
     * reasonable knobs to either keep time mode or freeze to static parameters.
     *
     * @return array<string, mixed>
     */
    private function chladniDefaults(): array
    {
        return [
            // Mode: "time" changes params with time of day; "static" renders fixed params below.
            'mode'            => 'time', // 'time' | 'static'
            // Static parameters (used when mode === 'static')
            'a'               => 1.0,
            'b'               => 1.0,
            'n'               => 3.0,
            'm'               => 3.0,
            // Render / update behavior
            'updateIntervalMs'=> 100,   // render cadence; higher reduces CPU
            'timeScale'       => 1.0,   // multiplier for time progression in time-mode
            'resolutionScale' => 1.0,   // 0.5 .. 1.0 to trade detail for performance
            // Color & blending
            'alpha'           => 1.0,   // 0..1 additional opacity multiplier
            'tint'            => '#ffffff', // optional post-tint (currently informational for future JS)
        ];
    }

    /**
     * Defaults for Cockroaches effect, matching data- attribute driven params in JS.
     *
     * @return array<string, mixed>
     */
    private function roachesDefaults(): array
    {
        return [
            'count'      => 6,           // 1..20
            'baseSpeed'  => 55,          // px/s
            'avoidMouse' => true,
            'edgeMargin' => 40,          // px
            'bodyColor'  => '#3b2f2f',   // hex color
        ];
    }

    // ---- Utilities ----

    /**
     * Deep merge $override on top of $base. Arrays are merged recursively; scalar values override.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private function arrayMergeDeep(array $base, array $override): array
    {
        $result = $base;

        foreach ($override as $key => $value) {
            if (is_array($value) && array_is_list($value) === false) {
                $result[$key] = isset($result[$key]) && is_array($result[$key]) && array_is_list($result[$key]) === false
                    ? $this->arrayMergeDeep($result[$key], $value)
                    : $value;
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Recursively ksorts associative arrays (objects), preserving lists as-is.
     *
     * @param array<string, mixed> $arr
     * @return array<string, mixed>
     */
    private function ksortDeep(array $arr): array
    {
        $isList = array_is_list($arr);
        if ($isList) {
            // For lists, normalize children but don't change order
            foreach ($arr as $i => $v) {
                if (is_array($v)) {
                    $arr[$i] = $this->ksortDeep($v);
                }
            }
            return $arr;
        }

        // For objects, sort by key and normalize children
        $keys = array_keys($arr);
        sort($keys, \SORT_STRING);

        $sorted = [];
        foreach ($keys as $k) {
            $v = $arr[$k];
            $sorted[$k] = is_array($v) ? $this->ksortDeep($v) : $v;
        }

        return $sorted;
    }
}
