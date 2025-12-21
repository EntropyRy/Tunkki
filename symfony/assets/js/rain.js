import { readEffectConfigById } from "./effects-config.js";
const canvas = document.getElementById("rain");
const ctx = canvas.getContext("2d");

const raindrops = [];
const defaults = {
    raindropsCount: 150,
    speedMin: 1,
    speedMax: 8,
    depthSort: true,
};
const cfg = readEffectConfigById("rain", defaults);
const RAIN_DROPS_COUNT = Number(cfg.raindropsCount ?? defaults.raindropsCount);
const SPEED_MIN = Number(cfg.speedMin ?? defaults.speedMin);
const SPEED_MAX = Number(cfg.speedMax ?? defaults.speedMax);
const DEPTH_SORT = cfg.depthSort !== false;
const MAX_DROP_WIDTH_PX = 4;
const TARGET_FPS = 60;
const FRAME_INTERVAL = 1000 / TARGET_FPS;
const DPR_CAP = 1.5;
const BASE_AREA = 1280 * 720;
const SORT_EVERY = 8;
const SPEED_SCALE = 1.8;

let canvasWidth = 0;
let canvasHeight = 0;
let deviceScale = 1;
let lastFrameTime = 0;
let frameCounter = 0;

function randomBetween(a, b) {
    return a + Math.random() * (b - a);
}

function createDrop() {
    return {
        x: Math.random() * canvasWidth,
        y: Math.random() * canvasHeight,
        length: randomBetween(14, 24),
        speed: randomBetween(SPEED_MIN, SPEED_MAX),
        width: randomBetween(1.5, 3.5),
        z: Math.random(), // for optional depth sorting
    };
}

function resizeCanvas() {
    const rect = canvas.getBoundingClientRect();
    const nextWidth = Math.max(1, Math.floor(rect.width));
    const nextHeight = Math.max(1, Math.floor(rect.height));
    if (nextWidth === canvasWidth && nextHeight === canvasHeight) {
        return;
    }

    deviceScale = Math.min(window.devicePixelRatio || 1, DPR_CAP);
    canvas.width = Math.round(nextWidth * deviceScale);
    canvas.height = Math.round(nextHeight * deviceScale);
    ctx.setTransform(deviceScale, 0, 0, deviceScale, 0, 0);
    canvasWidth = nextWidth;
    canvasHeight = nextHeight;
    syncRaindrops();
}

function syncRaindrops() {
    const targetCount = Math.max(
        30,
        Math.min(
            RAIN_DROPS_COUNT,
            Math.round((RAIN_DROPS_COUNT * canvasWidth * canvasHeight) / BASE_AREA),
        ),
    );

    while (raindrops.length < targetCount) {
        raindrops.push(createDrop());
    }
    if (raindrops.length > targetCount) {
        raindrops.length = targetCount;
    }

    for (const drop of raindrops) {
        drop.x = Math.min(drop.x, canvasWidth);
        drop.y = Math.min(drop.y, canvasHeight);
    }
}

function drawRain() {
    requestAnimationFrame(drawRain);
    const now = performance.now();
    if (now - lastFrameTime < FRAME_INTERVAL) {
        return;
    }
    const deltaScale = lastFrameTime === 0 ? 1 : (now - lastFrameTime) / FRAME_INTERVAL;
    lastFrameTime = now;

    ctx.save();
    ctx.globalCompositeOperation = "destination-out";
    ctx.fillStyle = "rgba(0,0,0,0.22)";
    ctx.fillRect(0, 0, canvasWidth, canvasHeight);
    ctx.restore();

    // Optional: sort by z for painter's order
    frameCounter += 1;
    if (DEPTH_SORT && frameCounter % SORT_EVERY === 0) {
        raindrops.sort((a, b) => a.z - b.z);
    }

    for (let drop of raindrops) {
        const width = Math.min(drop.width, MAX_DROP_WIDTH_PX);
        ctx.beginPath();
        ctx.strokeStyle = "rgba(200,220,255,0.55)";
        ctx.lineWidth = width;
        ctx.moveTo(drop.x, drop.y);
        ctx.lineTo(drop.x, drop.y + drop.length);
        ctx.stroke();

        ctx.fillStyle = "rgba(255,255,255,0.25)";
        ctx.fillRect(drop.x - width * 0.3, drop.y + 1, width * 0.6, width * 0.6);

        // Move drop
        drop.y += drop.speed * deltaScale * SPEED_SCALE;
        if (drop.y > canvasHeight) {
            // Reset drop to top
            drop.x = Math.random() * canvasWidth;
            drop.y = -drop.length;
            drop.length = randomBetween(10, 24);
            drop.speed = randomBetween(SPEED_MIN, SPEED_MAX);
            drop.width = randomBetween(1, 3);
            drop.z = Math.random();
        }
    }
}

// Start animation
resizeCanvas();
let resizeRaf = 0;
window.addEventListener("resize", () => {
    cancelAnimationFrame(resizeRaf);
    resizeRaf = requestAnimationFrame(resizeCanvas);
});
drawRain();
