/**
 * Tiny helper for parsing and merging effect configs from data-config
 *
 * Usage (in effect scripts):
 *   import { readEffectConfig } from './effects-config.js';
 *
 *   const defaults = { particleCount: 1500, colorMode: 'complement' };
 *   // If the canvas has id="flowfields"
 *   const config = readEffectConfig('flowfields', defaults);
 *   // or if you already have the canvas element:
 *   // const el = document.getElementById('flowfields');
 *   // const config = readEffectConfig(el, defaults);
 *
 * Behavior:
 * - Reads data-config JSON from the canvas element
 * - Silently ignores invalid/empty JSON
 * - Deep-merges overrides onto provided defaults (arrays are replaced, not merged)
 * - Always returns a plain object (at least the defaults)
 */

/**
 * Returns true if value is a plain object (and not an array, function, etc.)
 * @param {unknown} v
 * @returns {v is Record<string, unknown>}
 */
function isPlainObject(v) {
  return Object.prototype.toString.call(v) === '[object Object]';
}

/**
 * Deep merge of b over a. Only merges plain-objects; arrays and scalars replace.
 * Neither input is mutated; returns a new object reference.
 * @param {Record<string, unknown>} a
 * @param {Record<string, unknown>} b
 * @returns {Record<string, unknown>}
 */
export function mergeDeep(a, b) {
  const out = { ...a };
  for (const [k, v] of Object.entries(b || {})) {
    const av = out[k];
    if (isPlainObject(av) && isPlainObject(v)) {
      out[k] = mergeDeep(av, v);
    } else {
      out[k] = v;
    }
  }
  return out;
}

/**
 * Parse JSON safely. Returns null on empty/invalid input.
 * @param {string | null | undefined} s
 * @returns {Record<string, unknown> | null}
 */
export function parseJsonSafe(s) {
  if (!s) return null;
  const trimmed = String(s).trim();
  if (!trimmed) return null;
  try {
    const obj = JSON.parse(trimmed);
    return isPlainObject(obj) ? obj : null;
  } catch {
    return null;
  }
}

/**
 * Get the canvas element for a given input (element or id string).
 * @param {HTMLElement|string|null|undefined} elOrId
 * @returns {HTMLElement|null}
 */
function resolveElement(elOrId) {
  if (!elOrId) return null;
  if (typeof elOrId === 'string') return document.getElementById(elOrId);
  if (elOrId instanceof HTMLElement) return elOrId;
  return null;
}

/**
 * Read JSON from element's data-config attribute and parse it.
 * Returns null for missing/invalid content.
 * @param {HTMLElement|null} el
 * @returns {Record<string, unknown> | null}
 */
export function readDataConfig(el) {
  if (!el) return null;
  const raw = el.getAttribute('data-config');
  return parseJsonSafe(raw);
}

/**
 * Read and merge effect config for the given canvas.
 * - If elOrId is a string, it's treated as element id (e.g., effect name)
 * - If elOrId is an HTMLElement, it's used directly
 *
 * @template T extends Record<string, unknown>
 * @param {HTMLElement|string|null|undefined} elOrId
 * @param {T} defaults
 * @returns {T} merged config (defaults overlaid by JSON overrides)
 */
export function readEffectConfig(elOrId, defaults) {
  /** @type {T} */
  const base = (isPlainObject(defaults) ? defaults : {}) as any;
  const el = resolveElement(elOrId);
  const overrides = readDataConfig(el) || {};
  // Ensure we don't mutate provided defaults
  return mergeDeep(base, overrides) as T;
}

/**
 * Convenience: read config by effect id with a fallback lookup by canvas id.
 * Same as readEffectConfig(effectId, defaults).
 * @template T extends Record<string, unknown>
 * @param {string} effectId
 * @param {T} defaults
 * @returns {T}
 */
export function readEffectConfigById(effectId, defaults) {
  return readEffectConfig(effectId, defaults);
}
