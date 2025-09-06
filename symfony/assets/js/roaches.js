/**
 * Cockroaches wandering canvas effect
 *
 * Integration notes (follow how flowfields.js is integrated):
 * 1) Add an entrypoint to importmap:
 *    'roaches' => ['path' => './assets/js/roaches.js', 'entrypoint' => true]
 * 2) Allow selecting it in EventAdmin::$backgroundEffect choices:
 *    'Cockroaches' => 'roaches'
 * 3) The base layout already renders: <canvas id="{{ event.backgroundEffect }}"></canvas>
 *    so this module looks for a canvas with id="roaches".
 *
 * Optional data attributes on the canvas to tweak behavior:
 *  - data-count: integer number of roaches (default: 6; clamped to 1..20)
 *  - data-speed: average speed in px/s (default: 55)
 *  - data-avoid-mouse: "0" or "1" (default: 1)
 *  - data-edge-margin: distance from edges to start turning (default: 40)
 *  - data-color: body color (default: "#3b2f2f")
 *
 * This module is self-contained and starts automatically when the canvas exists.
 * It also pauses when the document is hidden to save CPU.
 */

(() => {
    "use strict";

    const canvas = document.getElementById("roaches");
    if (!canvas) return; // nothing to do on pages without this effect

    const ctx = canvas.getContext("2d", { alpha: true });

    // HiDPI handling
    let dpr = Math.max(1, Math.min(window.devicePixelRatio || 1, 2));
    let viewW = 0;
    let viewH = 0;

    function resizeCanvas() {
        // Use the viewport as the canvas size (like flowfields)
        viewW = Math.floor(window.innerWidth);
        viewH = Math.floor(window.innerHeight);
        dpr = Math.max(1, Math.min(window.devicePixelRatio || 1, 2));

        canvas.width = Math.max(1, Math.floor(viewW * dpr));
        canvas.height = Math.max(1, Math.floor(viewH * dpr));
        canvas.style.width = viewW + "px";
        canvas.style.height = viewH + "px";

        // Reset transform and scale logical units to CSS pixels
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }

    // If this effect sits "in front", we still don't want it to block clicks by default
    if (!canvas.style.pointerEvents) {
        canvas.style.pointerEvents = "none";
    }

    // Configuration (can be tweaked via data- attributes)
    const cfg = {
        count: clamp(intAttr(canvas.dataset.count, 6), 1, 20),
        baseSpeed: numAttr(canvas.dataset.speed, 55), // px/s, average; each roach gets +/- variance
        avoidMouse: boolAttr(canvas.dataset.avoidMouse, true),
        edgeMargin: clamp(numAttr(canvas.dataset.edgeMargin, 40), 10, 200),
        bodyColor: canvas.dataset.color || "#3b2f2f",
    };

    // Auto-tune count for low-powered devices if author hasn't set data-count
    if (!("count" in canvas.dataset)) {
        const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
        const cores = navigator.hardwareConcurrency || 4;
        if (isMobile || cores <= 4) cfg.count = 4;
        else cfg.count = 6;
    }

    // Mouse tracking for avoidance
    const mouse = {
        x: 0,
        y: 0,
        active: false,
    };
    function onMouseMove(e) {
        const rect = canvas.getBoundingClientRect();
        mouse.x = e.clientX - rect.left;
        mouse.y = e.clientY - rect.top;
        mouse.active = true;
    }
    function onMouseLeave() {
        mouse.active = false;
    }

    // Utility functions
    function clamp(v, min, max) {
        return Math.max(min, Math.min(max, v));
    }
    function randRange(min, max) {
        return min + Math.random() * (max - min);
    }
    function lerp(a, b, t) {
        return a + (b - a) * t;
    }
    function intAttr(v, def) {
        const n = Number.parseInt(v ?? "", 10);
        return Number.isFinite(n) ? n : def;
    }
    function numAttr(v, def) {
        const n = Number.parseFloat(v ?? "");
        return Number.isFinite(n) ? n : def;
    }
    function boolAttr(v, def) {
        if (v == null) return def;
        const s = String(v).trim().toLowerCase();
        return !(s === "0" || s === "false" || s === "no" || s === "");
    }
    function angleNormalize(a) {
        // normalize to [-pi, pi]
        while (a > Math.PI) a -= Math.PI * 2;
        while (a < -Math.PI) a += Math.PI * 2;
        return a;
    }
    function angleLerp(a, b, t) {
        // shortest-path interpolation
        let diff = angleNormalize(b - a);
        return angleNormalize(a + diff * t);
    }

    // Quadratic bezier interpolation
    function bezierPoint(t, p0, p1, p2) {
        const u = 1 - t;
        const tt = t * t;
        const uu = u * u;
        return {
            x: uu * p0.x + 2 * u * t * p1.x + tt * p2.x,
            y: uu * p0.y + 2 * u * t * p1.y + tt * p2.y,
        };
    }

    // Easing for smoother starts/stops
    function easeInOutCubic(t) {
        return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
    }

    // Choose a random target, sometimes offscreen
    function chooseTarget(x, y, w, h, margin, out) {
        // 50% chance to pick a point outside
        const goOut = Math.random() < 0.5;
        if (goOut) {
            // pick a side
            const side = Math.floor(Math.random() * 4);
            switch (side) {
                case 0: // left
                    return { x: -out, y: randRange(-out, h + out) };
                case 1: // right
                    return { x: w + out, y: randRange(-out, h + out) };
                case 2: // top
                    return { x: randRange(-out, w + out), y: -out };
                case 3: // bottom
                default:
                    return { x: randRange(-out, w + out), y: h + out };
            }
        } else {
            // inside with margins to avoid immediately turning
            return {
                x: randRange(-margin, w + margin),
                y: randRange(-margin, h + margin),
            };
        }
    }

    class Roach {
        constructor(x, y, params) {
            this.x = x;
            this.y = y;
            this.size = params.size; // overall scale
            this.angle = randRange(-Math.PI, Math.PI);
            this.turnRate = randRange(1.5, 2.7); // rad/s max turn speed
            this.jitter = randRange(0.8, 1.8); // rad/s random wander strength
            this.speed = clamp(
                params.baseSpeed * randRange(0.75, 1.25),
                20,
                120,
            ); // px/s
            this.color = params.color;

            this.strokeColor = shadeColor(this.color, -20);
            this.antennaColor = shadeColor(this.color, +20);

            // leg phase for gait animation
            this.phase = Math.random() * Math.PI * 2;

            // Movement state machine
            this.mode = "idle"; // "idle" | "move"
            this.stateTime = 0; // seconds remaining in current state
            this.pathT = 0; // 0..1 progress along current path
            this.pathDur = 0; // seconds to complete movement
            this.p0 = { x: this.x, y: this.y }; // bezier start
            this.p1 = { x: this.x, y: this.y }; // bezier control
            this.p2 = { x: this.x, y: this.y }; // bezier end (target)
        }

        update(dt, env) {
            // State timer
            this.stateTime -= dt;

            // Mouse avoidance only influences target selection during planning, not continuous steering here
            if (this.mode === "idle") {
                if (this.stateTime <= 0) {
                    // Plan a new movement
                    this.planMove(env);
                } else {
                    // small idle twitches
                    const twitch = (Math.random() - 0.5) * 0.2 * dt;
                    this.angle = angleNormalize(this.angle + twitch);
                }
            } else if (this.mode === "move") {
                // Progress along quadratic bezier from p0 -> p2 with p1 as control
                this.pathT = clamp(this.pathT + dt / this.pathDur, 0, 1);
                const t = easeInOutCubic(this.pathT);
                const p = bezierPoint(t, this.p0, this.p1, this.p2);

                // Update heading to tangent (curve direction)
                const eps = 0.0001;
                const tAhead = clamp(t + eps, 0, 1);
                const pAhead = bezierPoint(tAhead, this.p0, this.p1, this.p2);
                const tangentAngle = Math.atan2(pAhead.y - p.y, pAhead.x - p.x);
                const maxTurn = this.turnRate * dt;
                const diff = angleNormalize(tangentAngle - this.angle);
                const limited = clamp(diff, -maxTurn, maxTurn);
                this.angle = angleNormalize(this.angle + limited);

                // Move body towards current curve point by stepping in the facing direction
                const targetDir = Math.atan2(p.y - this.y, p.x - this.x);
                const diff2 = angleNormalize(targetDir - this.angle);
                const turnAssist = clamp(diff2, -maxTurn, maxTurn);
                this.angle = angleNormalize(this.angle + turnAssist * 0.25);

                const step = this.speed * dt;
                this.x += Math.cos(this.angle) * step;
                this.y += Math.sin(this.angle) * step;

                if (this.pathT >= 1) {
                    // Reached destination; enter idle
                    this.planIdle();
                }

                // Advance animation phase based on distance traveled
                this.phase += (step / (10 + this.size)) * 0.9;
            }
        }

        planIdle() {
            this.mode = "idle";
            this.stateTime = randRange(1.0, 4.0);
            this.pathT = 0;
            this.pathDur = 0;
            this.p0.x = this.x;
            this.p0.y = this.y;
            this.p1.x = this.x;
            this.p1.y = this.y;
            this.p2.x = this.x;
            this.p2.y = this.y;
        }

        planMove(env) {
            // Start point
            this.p0 = { x: this.x, y: this.y };

            // Choose a destination possibly outside the viewport
            const margin = 120;
            const out = 220; // how far outside permitted
            const target = chooseTarget(
                this.x,
                this.y,
                env.w,
                env.h,
                margin,
                out,
            );

            // If mouse avoidance is on, bias target away from mouse if close
            let tx = target.x;
            let ty = target.y;
            if (cfg.avoidMouse && mouse.active) {
                const dxm = tx - mouse.x;
                const dym = ty - mouse.y;
                const dist2 = dxm * dxm + dym * dym;
                const radius = 160;
                const r2 = radius * radius;
                if (dist2 < r2) {
                    const away = Math.atan2(
                        target.y - mouse.y,
                        target.x - mouse.x,
                    );
                    const dist = Math.sqrt(dist2) || 1;
                    const push = (1 - dist / radius) * 140;
                    tx += Math.cos(away) * push;
                    ty += Math.sin(away) * push;
                }
            }

            this.p2 = { x: tx, y: ty };

            // Control point ahead of the body in current forward direction to start straight then curve
            const launch = randRange(60, 140);
            const ctrlX = this.x + Math.cos(this.angle) * launch;
            const ctrlY = this.y + Math.sin(this.angle) * launch;

            // Slight lateral variance for natural curve
            const lateral = randRange(-0.7, 0.7);
            const latX = -Math.sin(this.angle) * (launch * 0.35) * lateral;
            const latY = +Math.cos(this.angle) * (launch * 0.35) * lateral;

            this.p1 = { x: ctrlX + latX, y: ctrlY + latY };

            // Duration based on travel distance and individual speed, 2–5s range
            const dx = this.p2.x - this.p0.x;
            const dy = this.p2.y - this.p0.y;
            const dist = Math.hypot(dx, dy);
            const nominal = clamp(dist / this.speed, 1.2, 6.0);
            this.pathDur = clamp(
                randRange(2.0, 5.0) * 0.5 + nominal * 0.5,
                2.0,
                6.0,
            );
            this.pathT = 0;

            this.mode = "move";
            this.stateTime = this.pathDur;
        }

        draw(ctx) {
            const s = this.size;

            ctx.save();
            ctx.translate(this.x, this.y);
            ctx.rotate(this.angle);

            // Slight body tilt wiggle
            const wiggle = Math.sin(this.phase * 2) * 0.05;
            ctx.rotate(wiggle);

            // Body segments
            const bodyW = 10 * (s / 8); // half-width
            const bodyH = 6 * (s / 8); // half-height

            // Abdomen (rear)
            ctx.fillStyle = this.color;
            ctx.strokeStyle = this.strokeColor;
            ctx.lineWidth = 1.2;
            roundedEllipse(ctx, -s * 0.2, 0, bodyW * 1.1, bodyH * 1.05);
            ctx.fill();
            ctx.stroke();

            // Thorax (middle)
            roundedEllipse(ctx, 0, 0, bodyW, bodyH * 0.95);
            ctx.fill();
            ctx.stroke();

            // Head (front)
            roundedEllipse(ctx, s * 0.45, 0, bodyW * 0.6, bodyH * 0.7);
            ctx.fill();
            ctx.stroke();

            // Antennae
            ctx.strokeStyle = this.antennaColor;
            ctx.lineWidth = 1;
            const antBaseX = s * 0.8;
            const antA = -0.5 + Math.sin(this.phase * 1.7) * 0.15;
            const antB = 0.5 + Math.cos(this.phase * 1.5) * 0.15;
            drawAntenna(ctx, antBaseX, 0, antA, s * 0.9);
            drawAntenna(ctx, antBaseX, 0, antB, s * 0.9);

            // Legs - three pairs: hind, middle, front with tripod gait
            ctx.strokeStyle = this.strokeColor;
            ctx.lineWidth = 1.3;

            // Base attachment points along body axis
            const hindX = -s * 0.45;
            const midX = -s * 0.05;
            const frontX = s * 0.28;

            const legOut = bodyH * 1.25; // lateral distance from body
            const hindLen = s * 1.0;
            const midLen = s * 0.95;
            const frontLen = s * 0.85;

            // Tripod gait phases:
            // Group A: left front, right middle, left hind
            // Group B: right front, left middle, right hind
            const speedGait = 10; // leg speed factor
            const aPhase = Math.sin(this.phase * speedGait) * 0.5 + 0.5; // 0..1
            const bPhase =
                Math.sin(this.phase * speedGait + Math.PI) * 0.5 + 0.5;

            // Helper to compute swing angle offset (- to +)
            const swing = (p) => (p - 0.5) * 0.5; // +/- 0.25 rad

            // Hind legs (rear pair) - face backward
            const hindBaseAng = 0.7; // magnitude from straight back
            // Left hind (Group A) - angle around -(PI - hindBaseAng)
            drawLeg(
                ctx,
                hindX,
                -legOut,
                -(Math.PI - hindBaseAng) + swing(aPhase),
                hindLen,
                true,
            );
            // Right hind (Group B) - angle around +(PI - hindBaseAng)
            drawLeg(
                ctx,
                hindX,
                legOut,
                Math.PI - hindBaseAng - swing(bPhase),
                hindLen,
                false,
            );

            // Middle legs (center pair) - mostly backward
            const midBaseAng = 1.1; // magnitude from straight back (less than hind)
            // Left middle (Group B)
            drawLeg(
                ctx,
                midX,
                -legOut,
                -(Math.PI - midBaseAng) + swing(bPhase),
                midLen,
                true,
            );
            // Right middle (Group A)
            drawLeg(
                ctx,
                midX,
                legOut,
                Math.PI - midBaseAng - swing(aPhase),
                midLen,
                false,
            );

            // Front legs (front pair) - slight forward
            const frontBaseAng = 0.2;
            // Left front (Group A)
            drawLeg(
                ctx,
                frontX,
                -legOut,
                -frontBaseAng + swing(aPhase),
                frontLen,
                true,
            );
            // Right front (Group B)
            drawLeg(
                ctx,
                frontX,
                legOut,
                frontBaseAng - swing(bPhase),
                frontLen,
                false,
            );

            ctx.restore();
        }
    }

    function drawLeg(ctx, baseX, baseY, ang, length, flip, bendDir) {
        ctx.beginPath();
        ctx.moveTo(baseX, baseY);
        // knee joint
        const kneeX = baseX + Math.cos(ang) * (length * 0.55);
        const kneeY = baseY + Math.sin(ang) * (length * 0.55);
        ctx.lineTo(kneeX, kneeY);
        // foot segment: outward depends on side, bendDir controls forward/backward bend
        // Default bend: if not provided, infer from angle (forward angles bend forward, backward angles bend backward)
        if (bendDir == null) {
            const a = angleNormalize(ang);
            bendDir = Math.cos(a) > 0 ? 1 : -1;
        }
        const outward = flip ? 0.9 : -0.9;
        const bend = bendDir * 0.4;
        const footAng = ang + outward + bend;
        const footX = kneeX + Math.cos(footAng) * (length * 0.45);
        const footY = kneeY + Math.sin(footAng) * (length * 0.45);
        ctx.lineTo(footX, footY);
        ctx.stroke();
    }

    function drawAntenna(ctx, x, y, relAngle, length) {
        ctx.beginPath();
        ctx.moveTo(x, y);
        const midX = x + Math.cos(relAngle) * (length * 0.6);
        const midY = y + Math.sin(relAngle) * (length * 0.6);
        const tipX = x + Math.cos(relAngle) * length;
        const tipY = y + Math.sin(relAngle) * length;

        // subtle curve using quadratic Bezier
        const ctrlX = x + Math.cos(relAngle - 0.3) * (length * 0.4);
        const ctrlY = y + Math.sin(relAngle - 0.3) * (length * 0.4);

        ctx.quadraticCurveTo(ctrlX, ctrlY, midX, midY);
        ctx.quadraticCurveTo(
            x + Math.cos(relAngle + 0.2) * (length * 0.9),
            y + Math.sin(relAngle + 0.2) * (length * 0.9),
            tipX,
            tipY,
        );
        ctx.stroke();
    }

    function roundedEllipse(ctx, cx, cy, rx, ry) {
        // Slightly rounded ellipse to suggest segments
        ctx.beginPath();
        ctx.ellipse(cx, cy, rx, ry, 0, 0, Math.PI * 2);
    }

    function shadeColor(hex, lum = 0) {
        // Simple hex shade; lum in [-100..100]
        let c = hex.replace("#", "");
        if (c.length === 3) c = c[0] + c[0] + c[1] + c[1] + c[2] + c[2];
        const amt = (lum / 100) * 255;
        const num = parseInt(c, 16);
        const r = clamp(((num >> 16) & 0xff) + amt, 0, 255) | 0;
        const g = clamp(((num >> 8) & 0xff) + amt, 0, 255) | 0;
        const b = clamp((num & 0xff) + amt, 0, 255) | 0;
        return (
            "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1)
        );
    }

    // World setup
    resizeCanvas();

    // Initialize roaches
    const roaches = [];
    function initRoaches() {
        roaches.length = 0;

        // Distribute roaches randomly, avoiding edges
        const margin = Math.max(cfg.edgeMargin + 20, 40);
        const env = { w: viewW, h: viewH };
        for (let i = 0; i < cfg.count; i++) {
            const x = randRange(margin, env.w - margin);
            const y = randRange(margin, env.h - margin);
            const size = randRange(7, 12); // overall scale baseline ~8
            roaches.push(
                new Roach(x, y, {
                    size,
                    baseSpeed: cfg.baseSpeed,
                    color: cfg.bodyColor,
                }),
            );
        }
    }

    initRoaches();

    // Animation loop
    let lastTs = performance.now();
    let rafId = null;
    let running = true;

    function frame(ts) {
        if (!running) return;
        rafId = requestAnimationFrame(frame);

        const dt = clamp((ts - lastTs) / 1000, 0, 0.04);
        lastTs = ts;

        // Clear
        ctx.clearRect(0, 0, viewW, viewH);

        const env = {
            w: viewW,
            h: viewH,
            edgeMargin: cfg.edgeMargin,
        };

        // Update + draw
        for (let i = 0; i < roaches.length; i++) {
            const r = roaches[i];
            r.update(dt, env);
            r.draw(ctx);
        }
    }

    function start() {
        if (rafId == null) {
            lastTs = performance.now();
            running = true;
            rafId = requestAnimationFrame(frame);
        }
    }
    function stop() {
        running = false;
        if (rafId != null) {
            cancelAnimationFrame(rafId);
            rafId = null;
        }
    }

    // Visibility handling to save CPU
    function onVisibilityChange() {
        if (document.hidden) stop();
        else start();
    }

    // Events
    window.addEventListener("resize", () => {
        const prevW = viewW;
        const prevH = viewH;
        resizeCanvas();

        // If size changed a lot, keep roaches inside bounds
        if (Math.abs(viewW - prevW) > 10 || Math.abs(viewH - prevH) > 10) {
            for (const r of roaches) {
                r.x = clamp(r.x, 1, viewW - 1);
                r.y = clamp(r.y, 1, viewH - 1);
            }
        }
    });
    window.addEventListener("mousemove", onMouseMove, { passive: true });
    window.addEventListener("mouseleave", onMouseLeave);
    document.addEventListener("visibilitychange", onVisibilityChange);
    // Turbo lifecycle: pause before snapshot; resume after render/frame load
    document.addEventListener("turbo:before-cache", () => stop());
    document.addEventListener("turbo:render", () => start());
    document.addEventListener("turbo:load", () => start());
    document.addEventListener("turbo:frame-load", () => start());

    // Expose tiny debug API
    // window.roachesApp?. will allow tweaking from console if needed
    const api = {
        start,
        stop,
        setCount(n) {
            cfg.count = clamp(n | 0, 1, 20);
            initRoaches();
        },
        setAvoidMouse(v) {
            cfg.avoidMouse = !!v;
        },
        setSpeed(pxPerSec) {
            cfg.baseSpeed = clamp(+pxPerSec || 0, 10, 200);
            initRoaches();
        },
        setColor(hex) {
            cfg.bodyColor = String(hex || "#3b2f2f");
            initRoaches();
        },
    };
    try {
        // Avoid clobbering if something else sets it
        if (!window.roachesApp) {
            Object.defineProperty(window, "roachesApp", {
                value: api,
                configurable: true,
                enumerable: false,
                writable: false,
            });
        }
    } catch {
        // ignore
    }

    // Kick off
    start();

    // Clean up if canvas is ever removed (unlikely with data-turbo-permanent, but safe)
    const observer = new MutationObserver(() => {
        if (!document.body.contains(canvas)) {
            stop();
            observer.disconnect();
            window.removeEventListener("mousemove", onMouseMove);
            window.removeEventListener("mouseleave", onMouseLeave);
            document.removeEventListener(
                "visibilitychange",
                onVisibilityChange,
            );
            window.removeEventListener("resize", resizeCanvas);
        }
    });
    observer.observe(document.documentElement || document.body, {
        childList: true,
        subtree: true,
    });
})();
