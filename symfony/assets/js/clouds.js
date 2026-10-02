import { readEffectConfig } from "./effects-config.js";

// Soft, slowly drifting cloud color field.
let activeCanvas = null;
let activeConfig = null;
let stopActive = () => {};
let resumeActive = () => {};

function start() {
    const canvas = document.getElementById("clouds");
    const rawConfig = canvas?.getAttribute("data-config");
    if (canvas === activeCanvas && rawConfig === activeConfig) {
        resumeActive();
        return;
    }
    stopActive();
    stopActive = () => {};
    resumeActive = () => {};
    activeCanvas = canvas;
    activeConfig = rawConfig;
    if (!canvas) return;
    const defaults = {
        colorA: "#4f7dd6",
        colorB: "#86aef2",
        colorC: "#eaf2ff",
        speed: 0.15,
        scale: 2.8,
        resolutionScale: 0.7,
    };
    const config = readEffectConfig(canvas, defaults);
    const clamp = (value, fallback, min, max) => {
        const number = Number(value);
        return Number.isFinite(number)
            ? Math.max(min, Math.min(max, number))
            : fallback;
    };
    const validHex = (value, fallback) =>
        typeof value === "string" && /^#[0-9a-f]{6}$/i.test(value) ? value : fallback;
    const hexColors = [
        validHex(config.colorA, defaults.colorA),
        validHex(config.colorB, defaults.colorB),
        validHex(config.colorC, defaults.colorC),
    ];
    const colors = hexColors.map((value) =>
        [1, 3, 5].map((index) => parseInt(value.slice(index, index + 2), 16) / 255),
    );
    const speed = clamp(config.speed, defaults.speed, 0, 1);
    const scale = clamp(config.scale, defaults.scale, 0.5, 8);
    const resolutionScale = clamp(config.resolutionScale, defaults.resolutionScale, 0.25, 1);
    // Use the shorter side so the phone limit also applies in landscape.
    const isPhone = () =>
        window.matchMedia("(pointer: coarse)").matches &&
        Math.min(window.innerWidth, window.innerHeight) <= 600;
    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
    canvas.style.background = `linear-gradient(135deg, ${hexColors[0]}, ${hexColors[2]})`;
    const gl = canvas.getContext("webgl", { alpha: false, antialias: false });

    if (gl) {
        const vertexSource = `
            attribute vec2 a_position;
            void main() {
                gl_Position = vec4(a_position, 0.0, 1.0);
            }
        `;
        const fragmentSource = `
            precision highp float;
            uniform vec2 u_resolution;
            uniform float u_time;
            uniform float u_scale;
            uniform vec3 u_color_a;
            uniform vec3 u_color_b;
            uniform vec3 u_color_c;

            vec2 hash(vec2 p) {
                p = vec2(dot(p, vec2(127.1, 311.7)), dot(p, vec2(269.5, 183.3)));
                return -1.0 + 2.0 * fract(sin(p) * 43758.5453);
            }

            float noise(vec2 p) {
                vec2 cell = floor(p);
                vec2 local = fract(p);
                vec2 blend = local * local * (3.0 - 2.0 * local);
                return mix(
                    mix(dot(hash(cell), local), dot(hash(cell + vec2(1.0, 0.0)), local - vec2(1.0, 0.0)), blend.x),
                    mix(dot(hash(cell + vec2(0.0, 1.0)), local - vec2(0.0, 1.0)), dot(hash(cell + vec2(1.0)), local - vec2(1.0)), blend.x),
                    blend.y
                );
            }

            float clouds(vec2 p) {
                float sum = 0.0;
                float weight = 0.5;
                for (int i = 0; i < 4; i++) {
                    sum += weight * noise(p);
                    p = mat2(0.8, 0.6, -0.6, 0.8) * p * 2.0;
                    weight *= 0.5;
                }
                return sum;
            }

            void main() {
                vec2 p = gl_FragCoord.xy / u_resolution.y * u_scale;
                float time = u_time;
                vec2 warp = vec2(
                    clouds(p + vec2(time * 0.35, time * 0.18)),
                    clouds(p + vec2(5.2 - time * 0.21, 1.3 + time * 0.29))
                );
                float field = clouds(p + warp * 2.4 + vec2(time * 0.14, -time * 0.12));
                float brightness = smoothstep(-0.35, 0.42, field);
                vec3 result = mix(u_color_a, u_color_b, smoothstep(0.0, 0.65, brightness));
                result = mix(result, u_color_c, smoothstep(0.38, 1.0, brightness));
                gl_FragColor = vec4(result, 1.0);
            }
        `;

        const compile = (type, source) => {
            const shader = gl.createShader(type);
            if (!shader) return null;
            gl.shaderSource(shader, source);
            gl.compileShader(shader);
            if (gl.getShaderParameter(shader, gl.COMPILE_STATUS)) return shader;
            gl.deleteShader(shader);
            return null;
        };

        const vertex = compile(gl.VERTEX_SHADER, vertexSource);
        const fragment = compile(gl.FRAGMENT_SHADER, fragmentSource);

        if (vertex && fragment) {
            const program = gl.createProgram();
            gl.attachShader(program, vertex);
            gl.attachShader(program, fragment);
            gl.linkProgram(program);
            gl.deleteShader(vertex);
            gl.deleteShader(fragment);

            if (gl.getProgramParameter(program, gl.LINK_STATUS)) {
                gl.useProgram(program);
                const buffer = gl.createBuffer();
                gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
                gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
                const position = gl.getAttribLocation(program, "a_position");
                gl.enableVertexAttribArray(position);
                gl.vertexAttribPointer(position, 2, gl.FLOAT, false, 0, 0);

                const uniforms = {
                    resolution: gl.getUniformLocation(program, "u_resolution"),
                    time: gl.getUniformLocation(program, "u_time"),
                    scale: gl.getUniformLocation(program, "u_scale"),
                    colors: ["u_color_a", "u_color_b", "u_color_c"].map((name) => gl.getUniformLocation(program, name)),
                };
                gl.uniform1f(uniforms.scale, scale);
                colors.forEach((rgb, index) => gl.uniform3fv(uniforms.colors[index], rgb));

                let frame = 0;
                let elapsed = 0;
                let lastFrame = 0;
                let lastDraw = 0;
                let phone = isPhone();
                let stopped = false;
                const resize = () => {
                    phone = isPhone();
                    const scaleForDevice = phone ? Math.min(resolutionScale, 0.5) : resolutionScale;
                    const ratio = Math.min(window.devicePixelRatio || 1, 2) * scaleForDevice;
                    canvas.width = Math.max(1, Math.round(window.innerWidth * ratio));
                    canvas.height = Math.max(1, Math.round(window.innerHeight * ratio));
                    gl.viewport(0, 0, canvas.width, canvas.height);
                    gl.uniform2f(uniforms.resolution, canvas.width, canvas.height);
                    render();
                    lastDraw = performance.now();
                };
                const render = () => {
                    gl.uniform1f(uniforms.time, elapsed * speed);
                    gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
                };
                const tick = (now) => {
                    if (stopped || reducedMotion.matches || document.hidden) return;
                    if (lastFrame) elapsed += Math.min((now - lastFrame) / 1000, 0.1);
                    lastFrame = now;
                    if (!phone || now - lastDraw >= 1000 / 30 - 1) {
                        render();
                        lastDraw = now;
                    }
                    frame = requestAnimationFrame(tick);
                };
                const resume = () => {
                    cancelAnimationFrame(frame);
                    lastFrame = 0;
                    render();
                    lastDraw = performance.now();
                    if (!stopped && !reducedMotion.matches && !document.hidden) {
                        frame = requestAnimationFrame(tick);
                    }
                };
                const stop = () => {
                    stopped = true;
                    cancelAnimationFrame(frame);
                    window.removeEventListener("resize", resize);
                    document.removeEventListener("visibilitychange", resume);
                    reducedMotion.removeEventListener("change", resume);
                    document.removeEventListener("turbo:before-cache", pause);
                    gl.deleteBuffer(buffer);
                    gl.deleteProgram(program);
                };
                const pause = () => {
                    cancelAnimationFrame(frame);
                    lastFrame = 0;
                };

                window.addEventListener("resize", resize);
                document.addEventListener("visibilitychange", resume);
                reducedMotion.addEventListener("change", resume);
                document.addEventListener("turbo:before-cache", pause);
                stopActive = stop;
                resumeActive = resume;
                resize();
                resume();
            } else {
                gl.deleteProgram(program);
                gl.clearColor(...colors[0], 1);
                gl.clear(gl.COLOR_BUFFER_BIT);
            }
        } else {
            if (vertex) gl.deleteShader(vertex);
            if (fragment) gl.deleteShader(fragment);
            gl.clearColor(...colors[0], 1);
            gl.clear(gl.COLOR_BUFFER_BIT);
        }
    }
}

document.addEventListener("turbo:load", start);
document.addEventListener("turbo:before-render", (event) => {
    const current = document.getElementById("clouds");
    const incoming = event.detail.newBody?.querySelector("canvas#clouds");
    if (!current || !incoming) return;
    current.setAttribute("data-config", incoming.getAttribute("data-config") || "");
    current.setAttribute("style", incoming.getAttribute("style") || "");
});
start();
