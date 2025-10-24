/**
 * Admin-side effect config helper
 * - Adds a "Reset to defaults" button next to the backgroundEffectConfig textarea
 * - Auto-fills defaults when switching to a configurable effect and config is empty
 * - Clears config when switching to a non-configurable effect
 *
 * This file is loaded as an importmap entrypoint (admin_effects) via EventAdmin::configureAssets().
 *
 * Assumptions:
 * - The Event admin form fields end with IDs:
 *     - backgroundEffect          (select)
 *     - backgroundEffectConfig    (textarea)
 *   For example: app_event_backgroundEffect, app_event_backgroundEffectConfig
 */

(function () {
    "use strict";

    // ---------- Utilities ----------
    /**
     * @param {unknown} v
     * @returns {v is Record<string, unknown>}
     */
    function isPlainObject(v) {
        return Object.prototype.toString.call(v) === "[object Object]";
    }

    function prettyJson(obj) {
        try {
            return JSON.stringify(obj, null, 2);
        } catch {
            return "{}";
        }
    }

    /**
     * Format voronoi config with helpful comments
     */
    function formatVoronoiWithComments(obj) {
        return `{
  // Number of Voronoi seeds (1-100, mobile uses 60% of this value)
  "seedCount": ${obj.seedCount},
  // Movement speed of seeds (0.001-0.01 recommended)
  "seedSpeed": ${obj.seedSpeed},
  // Array of hex colors for cells (empty = transparent, e.g. ["#ff0000", "#00ff00"])
  "cellColors": [],
  // Array of weights for each seed (empty = auto-varied based on min/max)
  "seedWeights": [],
  // Line width for cell boundaries (0.5-3.0 recommended)
  "lineWidth": ${obj.lineWidth},
  // Line color for cell boundaries (hex color)
  "lineColor": "${obj.lineColor}",
  // Use varied weights for organic bubble look
  "weightVariation": ${obj.weightVariation},
  // Minimum bubble weight/radius (pixels)
  "minWeight": ${obj.minWeight},
  // Maximum bubble weight/radius (pixels)
  "maxWeight": ${obj.maxWeight}
}`;
    }

    /**
     * Try to find the event admin fields using suffix-based selectors
     */
    function findEffectSelect() {
        return /** @type {HTMLSelectElement|null} */ (
            document.querySelector('select[id$="_backgroundEffect"]')
        );
    }
    function findConfigTextarea() {
        return /** @type {HTMLTextAreaElement|null} */ (
            document.querySelector('textarea[id$="_backgroundEffectConfig"]')
        );
    }

    // ---------- Defaults (mirror of server-side provider) ----------
    function flowfieldsDefaults() {
        return {
            particleCount: 1500,
            particleBaseSpeed: 1.0,
            particleSpeedVariation: 0.5,
            particleSize: 1.0,
            particleColor: { r: 153, g: 28, b: 42 },
            fadeAmount: 0.03,
            flowFieldIntensity: 0.5,
            noiseScale: 0.003,
            noiseSpeed: 0.0005,
            particleLifespan: 100,
            cursorInfluence: 150,
            cursorRepel: false,
            colorMode: "complement", // 'fixed' | 'age' | 'position' | 'flow' | 'complement' | 'analogous'
            enableTrails: true,
            trailLength: 0.98,
            trailWidth: 1.5,
            hueShiftRange: 60,
            showControls: true,
        };
    }
    function chladniDefaults() {
        return {
            mode: "time", // 'time' | 'static'
            a: 1.0,
            b: 1.0,
            n: 3.0,
            m: 3.0,
            updateIntervalMs: 100,
            timeScale: 1.0,
            resolutionScale: 1.0,
            alpha: 1.0,
            tint: "#ffffff",
        };
    }
    function roachesDefaults() {
        return {
            count: 6,
            baseSpeed: 55,
            avoidMouse: true,
            edgeMargin: 40,
            bodyColor: "#3b2f2f",
        };
    }
    function gridDefaults() {
        return {
            gridSize: 40,
            colorCycle: 0.5,
            spring: { p1: 0.0005, p2: 0.01, n: 0.98, nVel: 0.02 },
            interactive: true,
        };
    }
    function linesDefaults() {
        return {
            amplitude: 50,
            frequency: 0.005,
            phase: 0,
            lineWidth: 1,
            color: "black",
            speed: 0.1,
        };
    }
    function rainDefaults() {
        return {
            raindropsCount: 150,
            speedMin: 1,
            speedMax: 8,
            depthSort: true,
        };
    }
    function snowDefaults() {
        return {
            amount: 500,
            size: 2,
            speed: 5,
            color: "rgba(230, 230, 230, 1)",
        };
    }
    function starsDefaults() {
        return {
            starCount: 60,
            meteoriteCount: 3,
            starSpeedMin: 0.1,
            starSpeedMax: 1.1,
            meteoriteSpeedMin: 2.0,
            meteoriteSpeedMax: 5.0,
        };
    }
    function tvDefaults() {
        return {
            scaleFactor: 2.5,
            fps: 60,
            sampleCount: 10,
        };
    }
    function vhsDefaults() {
        return {
            scaleFactor: 2.5,
            fps: 50,
            sampleCount: 10,
            scanDurationSec: 15,
        };
    }
    function voronoiDefaults() {
        return {
            seedCount: 15,
            seedSpeed: 0.002,
            cellColors: [],
            seedWeights: [],
            lineWidth: 1.5,
            lineColor: "#ffffff",
            weightVariation: true,
            minWeight: 30,
            maxWeight: 150,
        };
    }

    /** @type {Record<string, () => Record<string, unknown>>} */
    const DEFAULTS = {
        flowfields: flowfieldsDefaults,
        chladni: chladniDefaults,
        roaches: roachesDefaults,
        grid: gridDefaults,
        lines: linesDefaults,
        rain: rainDefaults,
        snow: snowDefaults,
        stars: starsDefaults,
        tv: tvDefaults,
        vhs: vhsDefaults,
        voronoi: voronoiDefaults,
    };

    /**
     * @param {string|null|undefined} effect
     * @returns {boolean}
     */
    function supports(effect) {
        if (!effect) return false;
        return Object.prototype.hasOwnProperty.call(DEFAULTS, effect);
    }

    /**
     * @param {string|null|undefined} effect
     * @returns {Record<string, unknown> | null}
     */
    function getDefaults(effect) {
        if (!effect || !supports(effect)) return null;
        try {
            return DEFAULTS[effect]();
        } catch {
            return null;
        }
    }

    // ---------- UI helpers ----------
    /**
     * Inject a Reset button next to the textarea
     * @param {HTMLTextAreaElement} textarea
     * @param {() => void} onClick
     */
    function addResetButton(textarea, onClick) {
        const wrapper = document.createElement("div");
        wrapper.style.marginTop = "0.3rem";
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "btn btn-sm btn-outline-secondary";
        btn.textContent = "Reset to defaults";
        btn.addEventListener("click", onClick);
        wrapper.appendChild(btn);
        textarea.parentElement && textarea.parentElement.appendChild(wrapper);
    }

    /**
     * Flash a small success hint near the textarea
     * @param {HTMLElement} anchor
     * @param {string} text
     */
    function flash(anchor, text) {
        try {
            const el = document.createElement("div");
            el.textContent = text;
            el.style.fontSize = "0.85rem";
            el.style.color = "#198754";
            el.style.marginTop = "0.25rem";
            anchor.parentElement && anchor.parentElement.appendChild(el);
            setTimeout(() => el.remove(), 2000);
        } catch {
            // ignore
        }
    }

    // ---------- Main wiring ----------
    function main() {
        const effectSelect = findEffectSelect();
        const configTextarea = findConfigTextarea();
        if (!effectSelect || !configTextarea) return;

        // Add "Reset to defaults" button
        addResetButton(configTextarea, () => {
            const effect = effectSelect.value || null;
            if (!supports(effect)) {
                // For non-configurable effects, clear the field
                configTextarea.value = "";
                flash(configTextarea, "Config cleared (effect has no config).");
                return;
            }
            const d = getDefaults(effect);
            if (d) {
                // Use special formatter for voronoi to include comments
                configTextarea.value =
                    effect === "voronoi"
                        ? formatVoronoiWithComments(d)
                        : prettyJson(d);
                flash(configTextarea, "Defaults applied.");
            }
        });

        // When effect changes:
        // - If new effect is configurable and config is empty, auto-fill defaults
        // - If not configurable, clear the config field
        effectSelect.addEventListener("change", () => {
            const newEffect = effectSelect.value || null;
            if (!supports(newEffect)) {
                // hide/clear config for non-configurable effects
                if (configTextarea.value.trim() !== "") {
                    // keep user edits? Current server-side will drop config on save; we clear to reflect that.
                    configTextarea.value = "";
                }
                return;
            }

            // Only auto-fill if empty to avoid clobbering user edits
            if (configTextarea.value.trim() === "") {
                const d = getDefaults(newEffect);
                if (d) {
                    // Use special formatter for voronoi to include comments
                    configTextarea.value =
                        newEffect === "voronoi"
                            ? formatVoronoiWithComments(d)
                            : prettyJson(d);
                    flash(
                        configTextarea,
                        "Defaults loaded for selected effect.",
                    );
                }
            }
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", main);
    } else {
        main();
    }
})();
