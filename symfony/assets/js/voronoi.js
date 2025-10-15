// Animated Voronoi Diagram - Soap Bubble Effect
import { readEffectConfig } from './effects-config.js';

const canvas = document.getElementById("voronoi");
const ctx = canvas.getContext("2d", { alpha: true });

// Set canvas to full window size
function resizeCanvas() {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
}

resizeCanvas();

// Configuration with defaults
const defaults = {
    seedCount: 15,              // Number of Voronoi seeds
    seedSpeed: 0.3,             // Base movement speed
    lineColor: "#ffffff",       // Cell boundary color
    lineWidth: 1.5,             // Boundary line width
    cellColors: [],             // Array of hex colors for cells (empty = transparent)
    cursorInfluence: 120,       // Radius of cursor effect
    cursorRepel: true,          // Repel from cursor
    noiseScale: 0.002,          // Perlin noise smoothness
    showSeedPoints: false,      // Show seed points
    seedColor: "transparent",   // Color of seed points (default transparent)

    // CURVED LINES MODE (Weighted Voronoi - global seed weights)
    // NOTE: Cannot be used with pushedPlane effect
    useCurvedLines: false,      // Use curved boundaries (weighted Voronoi)
    seedWeights: [],            // Array of weights for each seed (empty = random)
    minWeight: 10,              // Minimum bubble weight/radius
    maxWeight: 50,              // Maximum bubble weight/radius
    weightVariation: true,      // Use varied weights for organic look

    // PUSHED PLANE MODE (Invisible balls deforming the plane)
    // NOTE: Cannot be used with useCurvedLines effect
    usePushedPlane: false,      // Use invisible balls pushing plane from behind
    ballCount: 4,               // Number of invisible balls (1-8)
    ballRadius: 0,              // Radius of each invisible ball (0 = auto-calculate from screen size)
    ballSpeed: 0.5,             // Movement speed of balls
    ballPushStrength: 0.3,      // How much balls push the plane (0.0-1.0)
    ballNoiseScale: 0.001,      // Perlin noise smoothness for ball movement
};

const config = readEffectConfig('voronoi', defaults);

// Validate mutually exclusive modes
if (config.useCurvedLines && config.usePushedPlane) {
    console.warn('Voronoi: useCurvedLines and usePushedPlane cannot be used together. Disabling usePushedPlane.');
    config.usePushedPlane = false;
}

// Simple Perlin-like noise generator
class SimpleNoise {
    constructor() {
        this.grad3 = [
            [1,1,0],[-1,1,0],[1,-1,0],[-1,-1,0],
            [1,0,1],[-1,0,1],[1,0,-1],[-1,0,-1],
            [0,1,1],[0,-1,1],[0,1,-1],[0,-1,-1]
        ];
        this.p = [];
        for(let i=0; i<256; i++) {
            this.p[i] = Math.floor(Math.random()*256);
        }
        // Extend permutation
        this.perm = [];
        for(let i=0; i<512; i++) {
            this.perm[i] = this.p[i & 255];
        }
    }

    dot(g, x, y) {
        return g[0]*x + g[1]*y;
    }

    noise(x, y) {
        // Find unit grid cell
        let X = Math.floor(x);
        let Y = Math.floor(y);

        // Get relative xy coordinates of point within cell
        x = x - X;
        y = y - Y;

        // Wrap to 0-255
        X = X & 255;
        Y = Y & 255;

        // Calculate noise contributions from each corner
        let n00 = this.dot(this.grad3[this.perm[X+this.perm[Y]] % 12], x, y);
        let n01 = this.dot(this.grad3[this.perm[X+this.perm[Y+1]] % 12], x, y-1);
        let n10 = this.dot(this.grad3[this.perm[X+1+this.perm[Y]] % 12], x-1, y);
        let n11 = this.dot(this.grad3[this.perm[X+1+this.perm[Y+1]] % 12], x-1, y-1);

        // Compute fade curves
        let u = this.fade(x);
        let v = this.fade(y);

        // Interpolate
        let nx0 = this.lerp(n00, n10, u);
        let nx1 = this.lerp(n01, n11, u);
        return this.lerp(nx0, nx1, v);
    }

    fade(t) {
        return t * t * t * (t * (t * 6 - 15) + 10);
    }

    lerp(a, b, t) {
        return a + t * (b - a);
    }
}

const noise = new SimpleNoise();

// Mouse tracking
let mouse = {
    x: undefined,
    y: undefined,
    active: false,
};

window.addEventListener("mousemove", (event) => {
    mouse.x = event.x;
    mouse.y = event.y;
    mouse.active = true;
});

window.addEventListener("mouseout", () => {
    mouse.active = false;
});

// Seed points for Voronoi cells
let seeds = [];
let time = 0;

// Invisible balls for pushed plane effect
let balls = [];

// Initialize seeds
function initSeeds() {
    seeds = [];
    for (let i = 0; i < config.seedCount; i++) {
        // Determine weight for this seed
        let weight;
        if (config.seedWeights && config.seedWeights[i] !== undefined) {
            // Use provided weight
            weight = config.seedWeights[i];
        } else if (config.weightVariation) {
            // Random weight between min and max
            weight = config.minWeight + Math.random() * (config.maxWeight - config.minWeight);
        } else {
            // Uniform weight (average of min and max)
            weight = (config.minWeight + config.maxWeight) / 2;
        }

        seeds.push({
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height,
            vx: 0,
            vy: 0,
            weight: weight,
            // Noise offsets for smooth organic motion
            noiseOffsetX: Math.random() * 1000,
            noiseOffsetY: Math.random() * 1000,
        });
    }
}

// Calculate ball radius based on screen size
function calculateBallRadius() {
    if (config.ballRadius > 0) {
        return config.ballRadius;
    }
    // Auto-calculate: use smaller dimension divided by 3
    const minDimension = Math.min(canvas.width, canvas.height);
    const radius = minDimension / 3;
    return radius;
}

// Initialize invisible balls for pushed plane effect
function initBalls() {
    balls = [];
    if (!config.usePushedPlane) {
        return;
    }

    const count = Math.max(1, Math.min(8, config.ballCount)); // Clamp 1-8
    for (let i = 0; i < count; i++) {
        balls.push({
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height,
            vx: 0,
            vy: 0,
            // Noise offsets for smooth organic motion
            noiseOffsetX: Math.random() * 1000,
            noiseOffsetY: Math.random() * 1000,
        });
    }
}

// Calculate distance between two points
function distance(x1, y1, x2, y2) {
    const dx = x2 - x1;
    const dy = y2 - y1;
    return Math.sqrt(dx * dx + dy * dy);
}

// Calculate weighted distance (for soap bubble effect)
function weightedDistance(x, y, seed) {
    const dx = x - seed.x;
    const dy = y - seed.y;
    const euclideanDist = Math.sqrt(dx * dx + dy * dy);
    return euclideanDist - (seed.weight || 0);
}

// Update seed positions with smooth organic motion
function updateSeeds() {
    time += 0.01;

    for (let i = 0; i < seeds.length; i++) {
        const seed = seeds[i];

        // Use Perlin noise for smooth organic movement
        const noiseX = noise.noise((seed.noiseOffsetX + time) * config.noiseScale, 0);
        const noiseY = noise.noise(0, (seed.noiseOffsetY + time) * config.noiseScale);

        // Target velocity from noise
        let targetVx = noiseX * config.seedSpeed;
        let targetVy = noiseY * config.seedSpeed;

        // Mouse influence (subtle repulsion)
        if (mouse.active && config.cursorRepel) {
            const dist = distance(seed.x, seed.y, mouse.x, mouse.y);
            if (dist < config.cursorInfluence) {
                const influence = (1 - dist / config.cursorInfluence) * 2;
                const dx = seed.x - mouse.x;
                const dy = seed.y - mouse.y;
                const angle = Math.atan2(dy, dx);
                targetVx += Math.cos(angle) * influence * config.seedSpeed;
                targetVy += Math.sin(angle) * influence * config.seedSpeed;
            }
        }

        // Smooth acceleration (ease towards target velocity)
        seed.vx += (targetVx - seed.vx) * 0.1;
        seed.vy += (targetVy - seed.vy) * 0.1;

        // Update position
        seed.x += seed.vx;
        seed.y += seed.vy;

        // Wrap around edges
        if (seed.x < 0) seed.x = canvas.width;
        if (seed.x > canvas.width) seed.x = 0;
        if (seed.y < 0) seed.y = canvas.height;
        if (seed.y > canvas.height) seed.y = 0;
    }
}

// Update invisible ball positions
function updateBalls() {
    if (!config.usePushedPlane || balls.length === 0) return;

    for (let i = 0; i < balls.length; i++) {
        const ball = balls[i];

        // Use Perlin noise for smooth organic movement
        const noiseX = noise.noise((ball.noiseOffsetX + time) * config.ballNoiseScale, 0);
        const noiseY = noise.noise(0, (ball.noiseOffsetY + time) * config.ballNoiseScale);

        // Target velocity from noise
        let targetVx = noiseX * config.ballSpeed;
        let targetVy = noiseY * config.ballSpeed;

        // Mouse influence (cursor repel)
        if (mouse.active && config.cursorRepel) {
            const dist = distance(ball.x, ball.y, mouse.x, mouse.y);
            if (dist < config.cursorInfluence) {
                const influence = (1 - dist / config.cursorInfluence) * 2;
                const dx = ball.x - mouse.x;
                const dy = ball.y - mouse.y;
                const angle = Math.atan2(dy, dx);
                targetVx += Math.cos(angle) * influence * config.ballSpeed;
                targetVy += Math.sin(angle) * influence * config.ballSpeed;
            }
        }

        // Smooth acceleration
        ball.vx += (targetVx - ball.vx) * 0.1;
        ball.vy += (targetVy - ball.vy) * 0.1;

        // Update position
        ball.x += ball.vx;
        ball.y += ball.vy;

        // Wrap around edges
        if (ball.x < 0) ball.x = canvas.width;
        if (ball.x > canvas.width) ball.x = 0;
        if (ball.y < 0) ball.y = canvas.height;
        if (ball.y > canvas.height) ball.y = 0;
    }
}

// Calculate plane displacement at point (x, y) caused by invisible balls
function getPlaneDisplacement(x, y) {
    if (!config.usePushedPlane || balls.length === 0) return 0;

    const ballRadius = calculateBallRadius();
    let totalDisplacement = 0;

    for (let i = 0; i < balls.length; i++) {
        const ball = balls[i];
        const dist = distance(x, y, ball.x, ball.y);

        // If within ball's radius, calculate displacement (bulge)
        if (dist < ballRadius) {
            // Smooth falloff: closer to center = more displacement
            const normalizedDist = dist / ballRadius;
            // Use cosine for smooth bulge shape
            const displacement = Math.cos(normalizedDist * Math.PI / 2) * ballRadius * config.ballPushStrength;
            totalDisplacement += displacement;
        }
    }

    return totalDisplacement;
}

// Find closest seed for a given point
function closestSeed(x, y) {
    let minDist = Infinity;
    let closest = 0;

    for (let i = 0; i < seeds.length; i++) {
        let dist;

        if (config.useCurvedLines) {
            // Weighted Voronoi mode
            dist = weightedDistance(x, y, seeds[i]);
        } else if (config.usePushedPlane) {
            // Pushed plane mode: displacement modifies effective distance
            const euclideanDist = distance(x, y, seeds[i].x, seeds[i].y);
            const pointDisplacement = getPlaneDisplacement(x, y);
            const seedDisplacement = getPlaneDisplacement(seeds[i].x, seeds[i].y);
            // When both point and seed are pushed up, effective distance changes
            dist = euclideanDist - (pointDisplacement - seedDisplacement);
        } else {
            // Standard Voronoi
            dist = distance(x, y, seeds[i].x, seeds[i].y);
        }

        if (dist < minDist) {
            minDist = dist;
            closest = i;
        }
    }

    return closest;
}

// Draw Voronoi diagram
function draw() {
    // Clear canvas
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    const hasCellColors = config.cellColors && config.cellColors.length > 0;

    // Use a lower resolution for calculations (performance optimization)
    const resolution = isMobile ? 4 : 3;
    const w = Math.ceil(canvas.width / resolution);
    const h = Math.ceil(canvas.height / resolution);

    // Create an array to store which seed owns each pixel
    const cells = new Array(w * h);

    // Calculate cell ownership
    for (let y = 0; y < h; y++) {
        for (let x = 0; x < w; x++) {
            const px = x * resolution;
            const py = y * resolution;
            cells[y * w + x] = closestSeed(px, py);
        }
    }

    // If we have cell colors, fill the cells first
    if (hasCellColors) {
        // Group pixels by seed
        const cellPixels = {};
        for (let i = 0; i < seeds.length; i++) {
            cellPixels[i] = [];
        }

        for (let y = 0; y < h; y++) {
            for (let x = 0; x < w; x++) {
                const seedIndex = cells[y * w + x];
                cellPixels[seedIndex].push({ x: x * resolution, y: y * resolution });
            }
        }

        // Draw filled cells
        for (let i = 0; i < seeds.length; i++) {
            const color = config.cellColors[i % config.cellColors.length];
            ctx.fillStyle = color;

            // Draw each pixel of the cell
            for (const pixel of cellPixels[i]) {
                ctx.fillRect(pixel.x, pixel.y, resolution, resolution);
            }
        }
    }

    // Draw cell boundaries
    ctx.strokeStyle = config.lineColor;
    ctx.lineWidth = config.lineWidth;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    // Find boundaries (pixels where neighbors have different seeds)
    ctx.beginPath();
    for (let y = 0; y < h - 1; y++) {
        for (let x = 0; x < w - 1; x++) {
            const current = cells[y * w + x];
            const right = cells[y * w + (x + 1)];
            const bottom = cells[(y + 1) * w + x];

            const px = x * resolution;
            const py = y * resolution;

            // Draw boundary if neighbor is different
            if (current !== right) {
                ctx.moveTo(px + resolution, py);
                ctx.lineTo(px + resolution, py + resolution);
            }
            if (current !== bottom) {
                ctx.moveTo(px, py + resolution);
                ctx.lineTo(px + resolution, py + resolution);
            }
        }
    }
    ctx.stroke();

    // Optional: Draw seed points (for debugging or visual effect)
    if (config.showSeedPoints && config.seedColor !== "transparent") {
        ctx.fillStyle = config.seedColor;
        for (const seed of seeds) {
            ctx.beginPath();
            ctx.arc(seed.x, seed.y, 3, 0, Math.PI * 2);
            ctx.fill();
        }
    }
}

// Animation loop
function animate() {
    updateSeeds();
    updateBalls();
    draw();
    requestAnimationFrame(animate);
}

// Mobile detection and optimization
const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
if (isMobile) {
    config.seedCount = Math.floor(config.seedCount * 0.6);
    config.lineWidth *= 0.8;
}

// Handle window resize
window.addEventListener("resize", () => {
    resizeCanvas();
    initSeeds();
    initBalls();
});

// Start animation
initSeeds();
initBalls();
animate();
