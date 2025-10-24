import { readEffectConfig } from "./effects-config.js";

// WebGL Voronoi Diagram - Soap Bubble Effect
const canvas = document.getElementById("voronoi");
const gl = canvas.getContext("webgl");
gl.enable(gl.BLEND);
gl.blendFunc(gl.SRC_ALPHA, gl.ONE_MINUS_SRC_ALPHA);
gl.clearColor(0, 0, 0, 0);

// Configuration with defaults
const defaults = {
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
const config = readEffectConfig("voronoi", defaults);

// Mobile detection and optimization
const isMobile =
    /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
        navigator.userAgent,
    );
if (isMobile) {
    config.seedCount = Math.floor(config.seedCount * 0.6);
    config.lineWidth *= 0.8;
}

// Clamp seedCount to reasonable limits
config.seedCount = Math.max(1, Math.min(100, config.seedCount));

// Handle canvas resize
function resizeCanvas() {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    gl.viewport(0, 0, canvas.width, canvas.height);
}
resizeCanvas();
window.addEventListener("resize", () => {
    resizeCanvas();
});

// Vertex shader (fullscreen quad)
const vertexSrc = `
attribute vec2 a_position;
varying vec2 v_uv;
void main() {
    v_uv = a_position * 0.5 + 0.5;
    gl_Position = vec4(a_position, 0, 1);
}
`;

// Generate fragment shader dynamically based on seedCount
function generateFragmentShader(maxSeeds) {
    // Generate color selection if-chain (WebGL 1.0 doesn't support dynamic array indexing)
    let colorSelection = 'vec3 color = u_colors[0];';
    for (let i = 1; i < maxSeeds; i++) {
        colorSelection += `\n        if (c == ${i}) color = u_colors[${i}];`;
    }

    return `
precision highp float;
#define MAX_SEEDS ${maxSeeds}
uniform int u_seedCount;
uniform vec2 u_seeds[MAX_SEEDS];
uniform float u_weights[MAX_SEEDS];
uniform vec3 u_colors[MAX_SEEDS];
uniform vec3 u_lineColor;
uniform float u_lineThreshold;
varying vec2 v_uv;

int findClosest(vec2 p) {
    float minDist = 1e6;
    int closest = 0;
    for (int i = 0; i < MAX_SEEDS; i++) {
        if (i >= u_seedCount) break;
        float d = distance(p, u_seeds[i]) - u_weights[i];
        if (i == 0 || d < minDist) {
            minDist = d;
            closest = i;
        }
    }
    return closest;
}

void main() {
    int c = findClosest(v_uv);
    int cx = findClosest(v_uv + vec2(u_lineThreshold, 0.0));
    int cy = findClosest(v_uv + vec2(0.0, u_lineThreshold));

    // If neighbor cell is different, draw line
    if (c != cx || c != cy) {
        gl_FragColor = vec4(u_lineColor, 1.0);
    } else {
        ${colorSelection}
        gl_FragColor = vec4(color, 0.0);
    }
}
`;
}

// Compile shader utility
function compileShader(type, src) {
    const shader = gl.createShader(type);
    gl.shaderSource(shader, src);
    gl.compileShader(shader);
    if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
        throw new Error(gl.getShaderInfoLog(shader));
    }
    return shader;
}

// Create program with dynamic shader
const fragmentSrc = generateFragmentShader(config.seedCount);
const vertShader = compileShader(gl.VERTEX_SHADER, vertexSrc);
const fragShader = compileShader(gl.FRAGMENT_SHADER, fragmentSrc);
const program = gl.createProgram();
gl.attachShader(program, vertShader);
gl.attachShader(program, fragShader);
gl.linkProgram(program);
if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
    throw new Error(gl.getProgramInfoLog(program));
}
gl.useProgram(program);

// Fullscreen quad
const positionBuffer = gl.createBuffer();
gl.bindBuffer(gl.ARRAY_BUFFER, positionBuffer);
gl.bufferData(
    gl.ARRAY_BUFFER,
    new Float32Array([-1, -1, 1, -1, -1, 1, -1, 1, 1, -1, 1, 1]),
    gl.STATIC_DRAW,
);

const a_position = gl.getAttribLocation(program, "a_position");
gl.enableVertexAttribArray(a_position);
gl.vertexAttribPointer(a_position, 2, gl.FLOAT, false, 0, 0);

// Uniform locations
const u_seedCount = gl.getUniformLocation(program, "u_seedCount");
const u_seeds = gl.getUniformLocation(program, "u_seeds");
const u_weights = gl.getUniformLocation(program, "u_weights");
const u_colors = gl.getUniformLocation(program, "u_colors");
const u_lineColor = gl.getUniformLocation(program, "u_lineColor");
const u_lineThreshold = gl.getUniformLocation(program, "u_lineThreshold");

// Seed and color animation logic (preserved from your original)
let seeds = [];
let weights = [];
let colors = [];
let seedTargets = [];
let seedLerp = config.seedSpeed;

function randomColor() {
    return [Math.random(), Math.random(), Math.random()];
}
function initSeeds() {
    seeds = [];
    weights = [];
    colors = [];
    seedTargets = [];
    for (let i = 0; i < config.seedCount; i++) {
        seeds.push([Math.random(), Math.random()]);
        seedTargets.push([Math.random(), Math.random()]);
        let weight =
            config.seedWeights[i] !== undefined
                ? config.seedWeights[i]
                : config.weightVariation
                  ? config.minWeight +
                    Math.random() * (config.maxWeight - config.minWeight)
                  : (config.minWeight + config.maxWeight) / 2;
        weights.push(weight / Math.max(canvas.width, canvas.height)); // normalize for shader
        colors.push(
            config.cellColors.length > 0
                ? hexToRgb(config.cellColors[i % config.cellColors.length])
                : randomColor(),
        );
    }
}
function hexToRgb(hex) {
    // Handle various hex formats: "#RRGGBB", "RRGGBB", "#RGB", "RGB"
    if (!hex) return [1, 1, 1]; // default to white

    // Remove # if present
    hex = hex.replace(/^#/, '');

    // Expand shorthand format (e.g., "03F" -> "0033FF")
    if (hex.length === 3) {
        hex = hex.split('').map(char => char + char).join('');
    }

    // Validate hex format
    if (!/^[0-9A-Fa-f]{6}$/.test(hex)) {
        console.warn(`Invalid hex color: ${hex}, using white`);
        return [1, 1, 1];
    }

    // Convert to RGB (0-1 range)
    const r = parseInt(hex.slice(0, 2), 16) / 255;
    const g = parseInt(hex.slice(2, 4), 16) / 255;
    const b = parseInt(hex.slice(4, 6), 16) / 255;

    return [r, g, b];
}
initSeeds();

// Pre-convert line color once (doesn't change during animation)
const lineColorRgb = hexToRgb(config.lineColor);
const lineThreshold = config.lineWidth / 1000;

// Animate and draw
let lastFrameTime = 0;
const targetFPS = 30;
const frameDuration = 1000 / targetFPS;

function animate(now) {
    if (!lastFrameTime || now - lastFrameTime >= frameDuration) {
        // Animate seeds (smooth interpolation towards random targets)
        for (let i = 0; i < config.seedCount; i++) {
            if (
                Math.abs(seeds[i][0] - seedTargets[i][0]) < 0.01 &&
                Math.abs(seeds[i][1] - seedTargets[i][1]) < 0.01
            ) {
                seedTargets[i][0] = Math.random();
                seedTargets[i][1] = Math.random();
            }
            seeds[i][0] += (seedTargets[i][0] - seeds[i][0]) * seedLerp;
            seeds[i][1] += (seedTargets[i][1] - seeds[i][1]) * seedLerp;
            seeds[i][0] = Math.max(0, Math.min(1, seeds[i][0]));
            seeds[i][1] = Math.max(0, Math.min(1, seeds[i][1]));
        }

        // Pass uniforms
        gl.uniform1i(u_seedCount, config.seedCount);
        gl.uniform2fv(u_seeds, seeds.flat());
        gl.uniform1fv(u_weights, weights);
        gl.uniform3fv(u_colors, colors.flat());
        gl.uniform3fv(u_lineColor, lineColorRgb);
        gl.uniform1f(u_lineThreshold, lineThreshold);

        // Draw
        gl.clear(gl.COLOR_BUFFER_BIT);
        gl.drawArrays(gl.TRIANGLES, 0, 6);

        lastFrameTime = now;
    }
    requestAnimationFrame(animate);
}
requestAnimationFrame(animate);
