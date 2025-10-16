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

if (config.useCurvedLines && config.usePushedPlane) {
    console.warn(
        "Voronoi: useCurvedLines and usePushedPlane cannot be used together. Disabling usePushedPlane.",
    );
    config.usePushedPlane = false;
}

// Mobile detection and optimization
const isMobile =
    /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
        navigator.userAgent,
    );
if (isMobile) {
    config.seedCount = Math.floor(config.seedCount * 0.6);
    config.lineWidth *= 0.8;
}

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

// Fragment shader (Voronoi calculation, supports weights)
const fragmentSrc = `
precision highp float;
#define MAX_SEEDS 15
uniform int u_seedCount;
uniform vec2 u_seeds[MAX_SEEDS];
uniform float u_weights[MAX_SEEDS];
uniform vec3 u_colors[MAX_SEEDS];
uniform vec3 u_lineColor;
varying vec2 v_uv;

int findClosest(vec2 p) {
    float minDist = 1e6;
    int closest = 0;
    float d;
    if (u_seedCount > 0) {
        d = distance(p, u_seeds[0]) - u_weights[0];
        minDist = d;
        closest = 0;
    }
    if (u_seedCount > 1) {
        d = distance(p, u_seeds[1]) - u_weights[1];
        if (d < minDist) { minDist = d; closest = 1; }
    }
    if (u_seedCount > 2) {
        d = distance(p, u_seeds[2]) - u_weights[2];
        if (d < minDist) { minDist = d; closest = 2; }
    }
    if (u_seedCount > 3) {
        d = distance(p, u_seeds[3]) - u_weights[3];
        if (d < minDist) { minDist = d; closest = 3; }
    }
    if (u_seedCount > 4) {
        d = distance(p, u_seeds[4]) - u_weights[4];
        if (d < minDist) { minDist = d; closest = 4; }
    }
    if (u_seedCount > 5) {
        d = distance(p, u_seeds[5]) - u_weights[5];
        if (d < minDist) { minDist = d; closest = 5; }
    }
    if (u_seedCount > 6) {
        d = distance(p, u_seeds[6]) - u_weights[6];
        if (d < minDist) { minDist = d; closest = 6; }
    }
    if (u_seedCount > 7) {
        d = distance(p, u_seeds[7]) - u_weights[7];
        if (d < minDist) { minDist = d; closest = 7; }
    }
    if (u_seedCount > 8) {
        d = distance(p, u_seeds[8]) - u_weights[8];
        if (d < minDist) { minDist = d; closest = 8; }
    }
    if (u_seedCount > 9) {
        d = distance(p, u_seeds[9]) - u_weights[9];
        if (d < minDist) { minDist = d; closest = 9; }
    }
    if (u_seedCount > 10) {
        d = distance(p, u_seeds[10]) - u_weights[10];
        if (d < minDist) { minDist = d; closest = 10; }
    }
    if (u_seedCount > 11) {
        d = distance(p, u_seeds[11]) - u_weights[11];
        if (d < minDist) { minDist = d; closest = 11; }
    }
    if (u_seedCount > 12) {
        d = distance(p, u_seeds[12]) - u_weights[12];
        if (d < minDist) { minDist = d; closest = 12; }
    }
    if (u_seedCount > 13) {
        d = distance(p, u_seeds[13]) - u_weights[13];
        if (d < minDist) { minDist = d; closest = 13; }
    }
    if (u_seedCount > 14) {
        d = distance(p, u_seeds[14]) - u_weights[14];
        if (d < minDist) { minDist = d; closest = 14; }
    }
    return closest;
}

void main() {
    int c = findClosest(v_uv);
    int cx = findClosest(v_uv + vec2(0.002, 0.0));
    int cy = findClosest(v_uv + vec2(0.0, 0.002));

    // If neighbor cell is different, draw line
    if (c != cx || c != cy) {
        gl_FragColor = vec4(u_lineColor, 1.0);
    } else {
        vec3 color = u_colors[0];
        if (c == 1) color = u_colors[1];
        if (c == 2) color = u_colors[2];
        if (c == 3) color = u_colors[3];
        if (c == 4) color = u_colors[4];
        if (c == 5) color = u_colors[5];
        if (c == 6) color = u_colors[6];
        if (c == 7) color = u_colors[7];
        if (c == 8) color = u_colors[8];
        if (c == 9) color = u_colors[9];
        if (c == 10) color = u_colors[10];
        if (c == 11) color = u_colors[11];
        if (c == 12) color = u_colors[12];
        if (c == 13) color = u_colors[13];
        if (c == 14) color = u_colors[14];
        gl_FragColor = vec4(color, 0.0);
    }
}
`;

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

// Create program
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
    // "#RRGGBB" to [r,g,b] in 0..1
    let r = parseInt(hex.slice(1, 3), 16) / 255;
    let g = parseInt(hex.slice(3, 5), 16) / 255;
    let b = parseInt(hex.slice(5, 7), 16) / 255;
    return [r, g, b];
}
initSeeds();

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
        gl.uniform3fv(u_lineColor, [1.0, 1.0, 1.0]); // white lines

        // Draw
        gl.clear(gl.COLOR_BUFFER_BIT);
        gl.drawArrays(gl.TRIANGLES, 0, 6);

        lastFrameTime = now;
    }
    requestAnimationFrame(animate);
}
requestAnimationFrame(animate);
