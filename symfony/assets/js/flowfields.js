// Advanced Flow Field Animation with transparent background
const canvas = document.getElementById("flowfields");
const ctx = canvas.getContext("2d", { alpha: true }); // Enable transparency

// Set canvas to full window size
function resizeCanvas() {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
}

// Call once to initialize
resizeCanvas();

// Update canvas size when window is resized
window.addEventListener("resize", () => {
    resizeCanvas();
    initParticles(); // Reinitialize particles when canvas size changes
});

// Configuration (adjustable)
const defaults = {
    particleCount: 1500, // Number of particles
    particleBaseSpeed: 1, // Base speed of particles
    particleSpeedVariation: 0.5, // Speed variation
    particleSize: 1, // Size of particles
    particleColor: {
        // Particle color (RGB)
        r: 153,
        g: 28,
        b: 42,
    },
    fadeAmount: 0.03, // Fade intensity on each frame (lower = longer trails)
    flowFieldIntensity: 0.5, // Flow field force intensity
    noiseScale: 0.003, // Scale of the noise (smaller = smoother)
    noiseSpeed: 0.0005, // Speed of flow field change
    particleLifespan: 100, // Frames a particle lives for
    cursorInfluence: 150, // Radius of cursor influence
    cursorRepel: false, // Whether cursor repels particles
    colorMode: "complement", // 'fixed', 'age', 'position', 'flow', 'complement'
    enableTrails: true, // Enable particle trails
    trailLength: 0.98, // Trail length (0-1, higher = longer trails)
    trailWidth: 1.5, // Width of trail lines
    hueShiftRange: 60, // Range of hue shift for 'analogous' color mode
};
function isPlainObject(v) {
    return Object.prototype.toString.call(v) === "[object Object]";
}
function mergeDeep(a, b) {
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
function parseJsonSafe(s) {
    if (!s) return null;
    const t = String(s).trim();
    if (!t) return null;
    try {
        const obj = JSON.parse(t);
        return isPlainObject(obj) ? obj : null;
    } catch {
        return null;
    }
}
function readDataConfig(el) {
    const raw = el ? el.getAttribute("data-config") : null;
    return parseJsonSafe(raw) || {};
}
const config = mergeDeep(defaults, readDataConfig(canvas));

// Particles array
let particles = [];

// Flow field time
let flowFieldTime = 0;

// Mouse position for interaction
let mouse = {
    x: undefined,
    y: undefined,
    active: false,
};

// Track mouse position
window.addEventListener("mousemove", (event) => {
    mouse.x = event.x;
    mouse.y = event.y;
    mouse.active = true;
});

window.addEventListener("mouseout", () => {
    mouse.active = false;
});

// Improved Noise function (Simplex-like)
class ImprovedNoise {
    constructor() {
        this.p = new Array(512);
        this.permutation = [
            151, 160, 137, 91, 90, 15, 131, 13, 201, 95, 96, 53, 194, 233, 7,
            225, 140, 36, 103, 30, 69, 142, 8, 99, 37, 240, 21, 10, 23, 190, 6,
            148, 247, 120, 234, 75, 0, 26, 197, 62, 94, 252, 219, 203, 117, 35,
            11, 32, 57, 177, 33, 88, 237, 149, 56, 87, 174, 20, 125, 136, 171,
            168, 68, 175, 74, 165, 71, 134, 139, 48, 27, 166, 77, 146, 158, 231,
            83, 111, 229, 122, 60, 211, 133, 230, 220, 105, 92, 41, 55, 46, 245,
            40, 244, 102, 143, 54, 65, 25, 63, 161, 1, 216, 80, 73, 209, 76,
            132, 187, 208, 89, 18, 169, 200, 196, 135, 130, 116, 188, 159, 86,
            164, 100, 109, 198, 173, 186, 3, 64, 52, 217, 226, 250, 124, 123, 5,
            202, 38, 147, 118, 126, 255, 82, 85, 212, 207, 206, 59, 227, 47, 16,
            58, 17, 182, 189, 28, 42, 223, 183, 170, 213, 119, 248, 152, 2, 44,
            154, 163, 70, 221, 153, 101, 155, 167, 43, 172, 9, 129, 22, 39, 253,
            19, 98, 108, 110, 79, 113, 224, 232, 178, 185, 112, 104, 218, 246,
            97, 228, 251, 34, 242, 193, 238, 210, 144, 12, 191, 179, 162, 241,
            81, 51, 145, 235, 249, 14, 239, 107, 49, 192, 214, 31, 181, 199,
            106, 157, 184, 84, 204, 176, 115, 121, 50, 45, 127, 4, 150, 254,
            138, 236, 205, 93, 222, 114, 67, 29, 24, 72, 243, 141, 128, 195, 78,
            66, 215, 61, 156, 180,
        ];

        // Extend the permutation to avoid overflow
        for (let i = 0; i < 256; i++) {
            this.p[i] = this.p[i + 256] = this.permutation[i];
        }
    }

    noise(x, y, z) {
        // Find unit cube that contains point
        const X = Math.floor(x) & 255;
        const Y = Math.floor(y) & 255;
        const Z = Math.floor(z) & 255;

        // Find relative x, y, z of point in cube
        x -= Math.floor(x);
        y -= Math.floor(y);
        z -= Math.floor(z);

        // Compute fade curves for each of x, y, z
        const u = this.fade(x);
        const v = this.fade(y);
        const w = this.fade(z);

        // Hash coordinates of the 8 cube corners
        const A = this.p[X] + Y;
        const AA = this.p[A] + Z;
        const AB = this.p[A + 1] + Z;
        const B = this.p[X + 1] + Y;
        const BA = this.p[B] + Z;
        const BB = this.p[B + 1] + Z;

        // Add blended results from 8 corners of cube
        return this.lerp(
            w,
            this.lerp(
                v,
                this.lerp(
                    u,
                    this.grad(this.p[AA], x, y, z),
                    this.grad(this.p[BA], x - 1, y, z),
                ),
                this.lerp(
                    u,
                    this.grad(this.p[AB], x, y - 1, z),
                    this.grad(this.p[BB], x - 1, y - 1, z),
                ),
            ),
            this.lerp(
                v,
                this.lerp(
                    u,
                    this.grad(this.p[AA + 1], x, y, z - 1),
                    this.grad(this.p[BA + 1], x - 1, y, z - 1),
                ),
                this.lerp(
                    u,
                    this.grad(this.p[AB + 1], x, y - 1, z - 1),
                    this.grad(this.p[BB + 1], x - 1, y - 1, z - 1),
                ),
            ),
        );
    }

    fade(t) {
        return t * t * t * (t * (t * 6 - 15) + 10);
    }

    lerp(t, a, b) {
        return a + t * (b - a);
    }

    grad(hash, x, y, z) {
        // Convert low 4 bits of hash code into 12 gradient directions
        const h = hash & 15;
        const u = h < 8 ? x : y;
        const v = h < 4 ? y : h === 12 || h === 14 ? x : z;
        return ((h & 1) === 0 ? u : -u) + ((h & 2) === 0 ? v : -v);
    }
}

// Create perlin noise instance
const perlin = new ImprovedNoise();

// Create particles
function initParticles() {
    particles = [];
    for (let i = 0; i < config.particleCount; i++) {
        const particle = {
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height,
            vx: 0,
            vy: 0,
            age: Math.floor(Math.random() * config.particleLifespan),
            speed:
                config.particleBaseSpeed +
                Math.random() * config.particleSpeedVariation,
            size: config.particleSize * (0.5 + Math.random()),
        };

        // Pre-calculate initial velocity to avoid straight lines at start
        const initialAngle =
            perlin.noise(
                particle.x * config.noiseScale,
                particle.y * config.noiseScale,
                flowFieldTime,
            ) *
            Math.PI *
            2;

        // Move particle a tiny bit initially so when we draw the first line,
        // we already have a directional vector matching the flow field
        particle.vx =
            Math.cos(initialAngle) * particle.speed * config.flowFieldIntensity;
        particle.vy =
            Math.sin(initialAngle) * particle.speed * config.flowFieldIntensity;

        // Set previous position to be the same as current to avoid initial straight lines
        particle.prevX = particle.x;
        particle.prevY = particle.y;

        particles.push(particle);
    }
}
// Get color based on position and flow direction
function getParticleColor(x, y, angle, age) {
    switch (config.colorMode) {
        case "fixed":
            return `rgba(${config.particleColor.r}, ${config.particleColor.g}, ${config.particleColor.b}, ${1 - age / config.particleLifespan})`;
        case "age":
            const ageRatio = age / config.particleLifespan;
            // Transition from blue to purple to red as particles age
            const r = Math.floor(255 * ageRatio);
            const g = Math.floor(70 * (1 - ageRatio));
            const b = Math.floor(255 * (1 - ageRatio));
            return `rgba(${r}, ${g}, ${b}, ${1 - ageRatio * 0.7})`;
        case "complement":
            // Calculate complementary color of the fixed color (opposite on color wheel)
            const complementR = 255 - config.particleColor.r;
            const complementG = 255 - config.particleColor.g;
            const complementB = 255 - config.particleColor.b;

            // Use age to transition between fixed color and its complement
            const compRatio = age / config.particleLifespan;
            const blendedR = Math.floor(
                config.particleColor.r * (1 - compRatio) +
                    complementR * compRatio,
            );
            const blendedG = Math.floor(
                config.particleColor.g * (1 - compRatio) +
                    complementG * compRatio,
            );
            const blendedB = Math.floor(
                config.particleColor.b * (1 - compRatio) +
                    complementB * compRatio,
            );

            return `rgba(${blendedR}, ${blendedG}, ${blendedB}, ${1 - compRatio * 0.7})`;
        case "analogous":
            // Convert RGB to HSL to work with the color wheel
            const hsl = rgbToHsl(
                config.particleColor.r,
                config.particleColor.g,
                config.particleColor.b,
            );

            // Calculate analogous color based on particle age
            // We'll use hueShiftRange to determine the range
            const hueShiftRange = config.hueShiftRange || 60; // Default to 60 if not set
            const agePercent = age / config.particleLifespan;
            const hueShift = (agePercent - 0.5) * hueShiftRange; // Range between -hueShiftRange/2 and +hueShiftRange/2

            let newHue = hsl.h + hueShift;
            // Make sure hue stays within 0-360 range
            newHue = newHue % 360;
            if (newHue < 0) newHue += 360;

            // Convert back to RGB
            const rgb = hslToRgb(newHue, hsl.s, hsl.l);

            return `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${1 - agePercent * 0.7})`;
        case "position":
            // Color based on canvas position
            const xRatio = x / canvas.width;
            const yRatio = y / canvas.height;
            return `rgba(${Math.floor(255 * xRatio)}, ${Math.floor(255 * (1 - yRatio))}, ${Math.floor(255 * (xRatio * yRatio))}, ${1 - age / config.particleLifespan})`;
        case "flow":
            // Color based on flow direction
            const hue = (angle / (Math.PI * 2)) * 360;
            return `hsla(${hue}, 100%, 70%, ${1 - age / config.particleLifespan})`;
        default:
            return `rgba(255, 255, 255, ${1 - age / config.particleLifespan})`;
    }
}

// Helper function to convert RGB to HSL
function rgbToHsl(r, g, b) {
    r /= 255;
    g /= 255;
    b /= 255;

    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    let h,
        s,
        l = (max + min) / 2;

    if (max === min) {
        h = s = 0; // achromatic
    } else {
        const d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);

        switch (max) {
            case r:
                h = (g - b) / d + (g < b ? 6 : 0);
                break;
            case g:
                h = (b - r) / d + 2;
                break;
            case b:
                h = (r - g) / d + 4;
                break;
        }

        h /= 6;
    }

    return { h: h * 360, s: s, l: l };
}

// Helper function to convert HSL to RGB
function hslToRgb(h, s, l) {
    let r, g, b;

    h /= 360; // Convert to 0-1 range

    if (s === 0) {
        r = g = b = l; // achromatic
    } else {
        const hue2rgb = (p, q, t) => {
            if (t < 0) t += 1;
            if (t > 1) t -= 1;
            if (t < 1 / 6) return p + (q - p) * 6 * t;
            if (t < 1 / 2) return q;
            if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
            return p;
        };

        const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
        const p = 2 * l - q;

        r = hue2rgb(p, q, h + 1 / 3);
        g = hue2rgb(p, q, h);
        b = hue2rgb(p, q, h - 1 / 3);
    }

    return {
        r: Math.round(r * 255),
        g: Math.round(g * 255),
        b: Math.round(b * 255),
    };
}

// Calculate the distance between two points
function distance(x1, y1, x2, y2) {
    const dx = x2 - x1;
    const dy = y2 - y1;
    return Math.sqrt(dx * dx + dy * dy);
}

// Create an off-screen buffer for trails
let trailCanvas;
let trailCtx;

// Initialize trail canvas
function initTrailCanvas() {
    trailCanvas = document.createElement("canvas");
    trailCanvas.width = canvas.width;
    trailCanvas.height = canvas.height;
    trailCtx = trailCanvas.getContext("2d", { alpha: true });
}

// Initialize trail canvas
initTrailCanvas();

// Draw a single frame of the flow field animation
function drawFlowField() {
    // Fade existing canvas content for trails
    if (config.enableTrails) {
        // Apply fading effect to trail canvas
        trailCtx.globalCompositeOperation = "destination-in";
        trailCtx.fillStyle = `rgba(0, 0, 0, ${config.trailLength})`; // Adjust trail length via alpha
        trailCtx.fillRect(0, 0, canvas.width, canvas.height);
        trailCtx.globalCompositeOperation = "source-over";
    } else {
        // Clear the trail canvas completely
        trailCtx.clearRect(0, 0, canvas.width, canvas.height);
    }

    // Clear the main canvas
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // Update flow field time
    flowFieldTime += config.noiseSpeed;

    // Update and draw each particle
    // Update and draw each particle
    for (let i = 0; i < particles.length; i++) {
        const p = particles[i];

        // Store previous position before updating
        const prevX = p.x;
        const prevY = p.y;

        // Update particle age
        p.age++;
        if (p.age >= config.particleLifespan) {
            // Reset particle
            p.x = Math.random() * canvas.width;
            p.y = Math.random() * canvas.height;
            p.age = 0;

            // Calculate new flow direction immediately for the new position
            const newAngle =
                perlin.noise(
                    p.x * config.noiseScale,
                    p.y * config.noiseScale,
                    flowFieldTime,
                ) *
                Math.PI *
                2;

            p.vx = Math.cos(newAngle) * p.speed * config.flowFieldIntensity;
            p.vy = Math.sin(newAngle) * p.speed * config.flowFieldIntensity;

            // Set previous position to current to prevent drawing a line from old position
            p.prevX = p.x;
            p.prevY = p.y;
            continue;
        }

        // Calculate flow angle at particle position
        let angle =
            perlin.noise(
                p.x * config.noiseScale,
                p.y * config.noiseScale,
                flowFieldTime,
            ) *
            Math.PI *
            2;

        // Apply mouse influence if active
        if (mouse.active) {
            const dist = distance(p.x, p.y, mouse.x, mouse.y);
            if (dist < config.cursorInfluence) {
                const influence = (1 - dist / config.cursorInfluence) * 2;

                // Direction from mouse to particle
                let dx = p.x - mouse.x;
                let dy = p.y - mouse.y;
                const mouseAngle = Math.atan2(dy, dx);

                if (config.cursorRepel) {
                    // Blend between field angle and repulsion angle
                    angle = angle * (1 - influence) + mouseAngle * influence;
                } else {
                    // Create vortex effect around cursor
                    const vortexAngle = mouseAngle + Math.PI / 2;
                    angle = angle * (1 - influence) + vortexAngle * influence;
                }
            }
        }

        // Update velocity based on flow field
        const speed = p.speed * config.flowFieldIntensity;
        p.vx = Math.cos(angle) * speed;
        p.vy = Math.sin(angle) * speed;

        // Update position
        p.x += p.vx;
        p.y += p.vy;

        // Handle edge wrapping
        if (p.x < 0) p.x = canvas.width;
        if (p.x > canvas.width) p.x = 0;
        if (p.y < 0) p.y = canvas.height;
        if (p.y > canvas.height) p.y = 0;

        // Get particle color
        const color = getParticleColor(p.x, p.y, angle, p.age);

        // Draw line from previous position to current (trail effect)
        if (
            config.enableTrails &&
            p.prevX !== undefined &&
            p.prevY !== undefined
        ) {
            // Make sure we're not drawing across the entire screen (avoid straight lines)
            const dx = Math.abs(p.x - p.prevX);
            const dy = Math.abs(p.y - p.prevY);

            // Only draw line if the distance isn't too large (prevents lines across screen when wrapping)
            if (dx < canvas.width / 2 && dy < canvas.height / 2) {
                trailCtx.beginPath();
                trailCtx.strokeStyle = color;
                trailCtx.lineWidth = config.trailWidth;
                trailCtx.lineCap = "round";
                trailCtx.moveTo(p.prevX, p.prevY);
                trailCtx.lineTo(p.x, p.y);
                trailCtx.stroke();
            } else {
                // If the distance is too large, just draw a dot
                trailCtx.beginPath();
                trailCtx.fillStyle = color;
                trailCtx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
                trailCtx.fill();
            }
        } else {
            // Draw particle as dot
            trailCtx.beginPath();
            trailCtx.fillStyle = color;
            trailCtx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
            trailCtx.fill();
        }

        // Update previous position for next frame
        p.prevX = p.x;
        p.prevY = p.y;
    }

    // Copy the trail canvas to the main canvas
    ctx.drawImage(trailCanvas, 0, 0);

    // Request next frame
    requestAnimationFrame(drawFlowField);
}

// Handle window resize
window.addEventListener("resize", () => {
    resizeCanvas();
    initTrailCanvas();
    initParticles();
});

// Start animation
initParticles();
drawFlowField();

function optimizeParticleCount() {
    const startTime = performance.now();
    // Run a frame
    drawFlowField();
    const frameTime = performance.now() - startTime;

    // Adjust particle count for target 60fps (16ms per frame)
    if (frameTime > 16 && config.particleCount > 1000) {
        config.particleCount *= 0.8;
        initParticles();
    } else if (frameTime < 10 && config.particleCount < 10000) {
        config.particleCount *= 1.2;
        initParticles();
    }
}
// Check if device is likely to be low-powered
const isLowPowered =
    /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
        navigator.userAgent,
    );
if (isLowPowered) {
    setTimeout(optimizeParticleCount, 1000);
    config.particleCount = Math.floor(config.particleCount / 2);
    config.fadeAmount = config.fadeAmount * 1.5; // faster fade
}

// Optional: Add controls
// -----------------------
// Uncomment the following code to add controls for adjusting parameters
// and color modes interactively.
// -----------------------
//
// Note: The controls are optional and can be removed if not needed.
//
// -----------------------
//
/**
 * Creates and adds a control panel for the flow field animation
 * @param {Object} config - The configuration object to bind controls to
 * @param {Function} initParticles - Function to reinitialize particles when needed
 * @param {Function} initTrailCanvas - Function to reinitialize trail canvas when needed
 * @return {HTMLElement} - The created control panel element
 */
function createControlPanel(config, initParticles, initTrailCanvas) {
    // Make sure hueShiftRange exists in config
    if (config.hueShiftRange === undefined) {
        config.hueShiftRange = 60; // Default value - will create a range of -30 to +30
    }

    // Create base panel
    const controls = createPanelContainer();

    // Add title
    addPanelTitle(controls);

    // Add color mode selector
    addColorModeSelector(controls, config);

    // Define all control items
    const controlItems = getControlItems(config);
    const checkboxItems = getCheckboxItems(config);

    // Add controls by category
    addControlCategory(
        controls,
        "Particle Properties",
        controlItems.slice(0, 4),
        checkboxItems,
        createSliderControl,
        createCheckboxControl,
        config,
        initParticles,
        initTrailCanvas,
    );

    addControlCategory(
        controls,
        "Flow Field",
        controlItems.slice(4, 7),
        [],
        createSliderControl,
        createCheckboxControl,
        config,
        initParticles,
        initTrailCanvas,
    );

    addControlCategory(
        controls,
        "Particle Behavior",
        controlItems.slice(7, 9),
        checkboxItems,
        createSliderControl,
        createCheckboxControl,
        config,
        initParticles,
        initTrailCanvas,
    );

    addControlCategory(
        controls,
        "Trail Effects",
        controlItems.slice(9, 12),
        [],
        createSliderControl,
        createCheckboxControl,
        config,
        initParticles,
        initTrailCanvas,
    );

    addControlCategory(
        controls,
        "Color Settings",
        controlItems.slice(12),
        [],
        createSliderControl,
        createCheckboxControl,
        config,
        initParticles,
        initTrailCanvas,
    );

    // Add color preview
    addColorPreview(controls, config);

    // Add toggle button
    addToggleButton(controls);

    // Add the control panel to the document body
    document.body.appendChild(controls);

    // Return the created control panel
    return controls;
}

/**
 * Creates the basic container for the control panel
 */
function createPanelContainer() {
    const controls = document.createElement("div");
    controls.style.position = "fixed";
    controls.style.top = "10px";
    controls.style.right = "10px";
    controls.style.backgroundColor = "rgba(0,0,0,0.7)";
    controls.style.padding = "15px";
    controls.style.borderRadius = "8px";
    controls.style.color = "white";
    controls.style.fontFamily = "Arial, sans-serif";
    controls.style.zIndex = "1000";
    controls.style.maxHeight = "90vh";
    controls.style.overflowY = "auto";
    controls.style.boxShadow = "0 4px 8px rgba(0,0,0,0.3)";
    return controls;
}

/**
 * Adds the title to the control panel
 */
function addPanelTitle(controls) {
    const title = document.createElement("h3");
    title.textContent = "Flow Field Controls";
    title.style.margin = "0 0 10px 0";
    title.style.textAlign = "center";
    controls.appendChild(title);
}

/**
 * Adds the color mode selector dropdown
 */
function addColorModeSelector(controls, config) {
    const colorModeDiv = document.createElement("div");
    colorModeDiv.style.marginBottom = "10px";

    const colorModeLabel = document.createElement("label");
    colorModeLabel.textContent = "Color Mode: ";
    colorModeLabel.style.display = "inline-block";
    colorModeLabel.style.width = "120px";
    colorModeDiv.appendChild(colorModeLabel);

    const colorModeSelect = document.createElement("select");
    colorModeSelect.style.width = "150px";
    colorModeSelect.style.padding = "3px";
    colorModeSelect.style.backgroundColor = "rgba(60,60,60,0.7)";
    colorModeSelect.style.color = "white";
    colorModeSelect.style.border = "1px solid #555";
    colorModeSelect.style.borderRadius = "3px";

    const colorModes = [
        "fixed",
        "age",
        "position",
        "flow",
        "complement",
        "analogous",
    ];

    colorModes.forEach((mode) => {
        const option = document.createElement("option");
        option.value = mode;
        option.textContent = mode[0].toUpperCase() + mode.slice(1);
        if (mode === config.colorMode) option.selected = true;
        colorModeSelect.appendChild(option);
    });

    colorModeSelect.addEventListener("change", (e) => {
        config.colorMode = e.target.value;

        // Show or hide hue shift control based on color mode
        const hueShiftControls =
            document.querySelectorAll(".hue-shift-control");
        hueShiftControls.forEach((control) => {
            control.style.display =
                config.colorMode === "analogous" ? "block" : "none";
        });
    });

    colorModeDiv.appendChild(colorModeSelect);
    controls.appendChild(colorModeDiv);
}

/**
 * Returns the array of slider control configurations
 */
function getControlItems(config) {
    return [
        {
            label: "Particles",
            type: "range",
            min: "1000",
            max: "10000",
            value: config.particleCount,
            prop: "particleCount",
            reInit: true,
            step: "100",
        },
        {
            label: "Speed",
            type: "range",
            min: "0.1",
            max: "3",
            step: "0.1",
            value: config.particleBaseSpeed,
            reInit: true,
            prop: "particleBaseSpeed",
        },
        {
            label: "Speed Variation",
            type: "range",
            min: "0",
            max: "2",
            step: "0.1",
            value: config.particleSpeedVariation,
            reInit: true,
            prop: "particleSpeedVariation",
        },
        {
            label: "Size",
            type: "range",
            min: "0.5",
            max: "3",
            step: "0.1",
            value: config.particleSize,
            reInit: true,
            prop: "particleSize",
        },
        {
            label: "Flow Intensity",
            type: "range",
            min: "0.1",
            max: "2",
            step: "0.1",
            value: config.flowFieldIntensity,
            prop: "flowFieldIntensity",
        },
        {
            label: "Noise Scale",
            type: "range",
            min: "0.001",
            max: "0.01",
            step: "0.001",
            value: config.noiseScale,
            prop: "noiseScale",
        },
        {
            label: "Noise Speed",
            type: "range",
            min: "0.0001",
            max: "0.001",
            step: "0.0001",
            value: config.noiseSpeed,
            prop: "noiseSpeed",
        },
        {
            label: "Lifespan",
            type: "range",
            min: "10",
            max: "500",
            step: "10",
            value: config.particleLifespan,
            prop: "particleLifespan",
            reInit: true,
        },
        {
            label: "Cursor Influence",
            type: "range",
            min: "0",
            max: "300",
            step: "10",
            value: config.cursorInfluence,
            prop: "cursorInfluence",
        },
        {
            label: "Trail Length",
            type: "range",
            min: "0.5",
            max: "0.99",
            step: "0.01",
            value: config.trailLength,
            prop: "trailLength",
        },
        {
            label: "Trail Width",
            type: "range",
            min: "0.5",
            max: "5",
            step: "0.1",
            value: config.trailWidth,
            prop: "trailWidth",
        },
        {
            label: "Fade Amount",
            type: "range",
            min: "0.001",
            max: "0.1",
            step: "0.001",
            value: config.fadeAmount,
            prop: "fadeAmount",
        },
        {
            label: "Color R",
            type: "range",
            min: "0",
            max: "255",
            step: "1",
            value: config.particleColor.r,
            prop: "particleColor.r",
            nestedProp: true,
        },
        {
            label: "Color G",
            type: "range",
            min: "0",
            max: "255",
            step: "1",
            value: config.particleColor.g,
            prop: "particleColor.g",
            nestedProp: true,
        },
        {
            label: "Color B",
            type: "range",
            min: "0",
            max: "255",
            step: "1",
            value: config.particleColor.b,
            prop: "particleColor.b",
            nestedProp: true,
        },
        {
            label: "Hue Shift Range",
            type: "range",
            min: "10",
            max: "180",
            step: "5",
            value: config.hueShiftRange,
            prop: "hueShiftRange",
            className: "hue-shift-control",
        },
    ];
}

/**
 * Returns the array of checkbox control configurations
 */
function getCheckboxItems(config) {
    return [
        {
            label: "Cursor Repel",
            type: "checkbox",
            value: config.cursorRepel,
            prop: "cursorRepel",
        },
        {
            label: "Enable Trails",
            type: "checkbox",
            value: config.enableTrails,
            prop: "enableTrails",
            reInit: true,
        },
    ];
}

/**
 * Creates a slider control with value display
 */
function createSliderControl(item, config, initParticles, initTrailCanvas) {
    const controlDiv = document.createElement("div");
    controlDiv.style.marginBottom = "8px";

    // Add class if specified
    if (item.className) {
        controlDiv.className = item.className;

        // Initially hide hue shift control if not in analogous mode
        if (
            item.className === "hue-shift-control" &&
            config.colorMode !== "analogous"
        ) {
            controlDiv.style.display = "none";
        }
    }

    // Create label row with current value display
    const labelRow = document.createElement("div");
    labelRow.style.display = "flex";
    labelRow.style.justifyContent = "space-between";
    labelRow.style.marginBottom = "2px";

    const label = document.createElement("label");
    label.textContent = item.label;
    label.style.flex = "1";
    labelRow.appendChild(label);

    // Value display - shows current value
    const valueDisplay = document.createElement("span");
    valueDisplay.textContent = formatDisplayValue(item.value, item.step);
    valueDisplay.style.minWidth = "50px";
    valueDisplay.style.textAlign = "right";
    valueDisplay.style.fontFamily = "monospace";
    valueDisplay.style.backgroundColor = "rgba(30,30,30,0.5)";
    valueDisplay.style.padding = "0 4px";
    valueDisplay.style.borderRadius = "2px";
    labelRow.appendChild(valueDisplay);

    controlDiv.appendChild(labelRow);

    // Create the slider
    const input = document.createElement("input");
    input.type = "range";
    input.min = item.min;
    input.max = item.max;
    input.step = item.step || "1";
    input.value = item.value;
    input.style.width = "100%";
    input.style.margin = "2px 0";

    // Add event listener
    input.addEventListener("input", (e) => {
        const newValue = parseFloat(e.target.value);
        valueDisplay.textContent = formatDisplayValue(newValue, item.step);

        // Update config
        if (item.nestedProp) {
            const [parent, child] = item.prop.split(".");
            config[parent][child] = newValue;
        } else {
            config[item.prop] = newValue;
        }
    });

    // Handle reinitialization separately on change end (when user releases slider)
    if (item.reInit) {
        input.addEventListener("change", () => {
            // For properties that require reinitialization
            if (item.prop === "particleCount") {
                // Clear any pending timeouts to prevent multiple reinitializations
                if (window.reinitTimeout) {
                    clearTimeout(window.reinitTimeout);
                }

                // Use a small timeout to prevent too many reinitializations while sliding
                window.reinitTimeout = setTimeout(() => {
                    initParticles();
                    initTrailCanvas && initTrailCanvas(); // Also reinitialize the trail canvas if function exists
                }, 50);
            } else {
                // Other properties that require reinitialization
                initParticles();
            }
        });
    }

    controlDiv.appendChild(input);
    return controlDiv;
}

/**
 * Format numeric values with appropriate decimal places
 */
function formatDisplayValue(value, step) {
    const stepSize = parseFloat(step || "1");
    const decimals =
        stepSize >= 1
            ? 0
            : stepSize >= 0.1
              ? 1
              : stepSize >= 0.01
                ? 2
                : stepSize >= 0.001
                  ? 3
                  : 4;

    return value.toFixed(decimals);
}

/**
 * Creates a checkbox control
 */
function createCheckboxControl(item, config, initParticles, initTrailCanvas) {
    const controlDiv = document.createElement("div");
    controlDiv.style.marginBottom = "8px";

    // Add class if specified
    if (item.className) {
        controlDiv.className = item.className;
    }

    const input = document.createElement("input");
    input.type = "checkbox";
    input.id = `checkbox-${item.prop}`;
    input.checked = item.value;
    input.style.marginRight = "8px";

    input.addEventListener("change", (e) => {
        config[item.prop] = e.target.checked;

        // Reinitialize if needed
        if (item.reInit) {
            if (item.prop === "enableTrails" && initTrailCanvas) {
                // If trail enabling/disabling, reinitialize trail canvas
                initTrailCanvas();
            }
            initParticles();
        }
    });

    const label = document.createElement("label");
    label.htmlFor = `checkbox-${item.prop}`;
    label.textContent = item.label;

    controlDiv.appendChild(input);
    controlDiv.appendChild(label);
    return controlDiv;
}

/**
 * Adds a category of controls with a header
 */
function addControlCategory(
    controls,
    title,
    sliderItems,
    checkboxItems,
    sliderCreator,
    checkboxCreator,
    config,
    initParticles,
    initTrailCanvas,
) {
    // Add separator with category title
    const separator = document.createElement("div");
    separator.style.borderTop = "1px solid #555";
    separator.style.margin = "15px 0 10px";
    separator.style.paddingTop = "5px";
    separator.style.fontWeight = "bold";
    separator.textContent = title;
    controls.appendChild(separator);

    // Add slider controls for this category
    sliderItems.forEach((item) => {
        controls.appendChild(
            sliderCreator(item, config, initParticles, initTrailCanvas),
        );
    });

    // Add checkbox controls if any exist for this category
    if (
        checkboxItems &&
        checkboxItems.length > 0 &&
        title === "Particle Behavior"
    ) {
        checkboxItems.forEach((item) => {
            controls.appendChild(
                checkboxCreator(item, config, initParticles, initTrailCanvas),
            );
        });
    }
}

/**
 * Adds a color preview box to see the current color
 */
function addColorPreview(controls, config) {
    const colorPreview = document.createElement("div");
    colorPreview.style.width = "100%";
    colorPreview.style.height = "20px";
    colorPreview.style.marginTop = "5px";
    colorPreview.style.backgroundColor = `rgb(${config.particleColor.r}, ${config.particleColor.g}, ${config.particleColor.b})`;
    colorPreview.style.border = "1px solid #555";
    colorPreview.style.borderRadius = "3px";

    // Store reference to the element for updates
    colorPreview.id = "color-preview";

    controls.appendChild(colorPreview);

    // Add event listeners to update the preview when RGB values change
    const rgbProps = ["particleColor.r", "particleColor.g", "particleColor.b"];
    rgbProps.forEach((prop) => {
        const sliders = controls.querySelectorAll('input[type="range"]');
        for (let i = 0; i < sliders.length; i++) {
            const slider = sliders[i];
            const parentDiv = slider.parentNode;
            const label = parentDiv.querySelector("label");

            if (
                label &&
                (label.textContent === "Color R" ||
                    label.textContent === "Color G" ||
                    label.textContent === "Color B")
            ) {
                slider.addEventListener(
                    "input",
                    updateColorPreview.bind(null, colorPreview, config),
                );
            }
        }
    });
}

/**
 * Updates the color preview box to reflect current RGB values
 */
function updateColorPreview(previewElement, config) {
    previewElement.style.backgroundColor = `rgb(${config.particleColor.r}, ${config.particleColor.g}, ${config.particleColor.b})`;
}

/**
 * Adds a toggle button to show/hide the controls
 */
function addToggleButton(controls) {
    const toggleButton = document.createElement("button");
    toggleButton.textContent = "Hide Controls";
    toggleButton.style.marginTop = "15px";
    toggleButton.style.padding = "5px 10px";
    toggleButton.style.width = "100%";
    toggleButton.style.backgroundColor = "#555";
    toggleButton.style.border = "none";
    toggleButton.style.borderRadius = "4px";
    toggleButton.style.color = "white";
    toggleButton.style.cursor = "pointer";

    let controlsVisible = true;
    toggleButton.addEventListener("click", () => {
        // Get all elements except the toggle button
        const elements = Array.from(controls.children).filter(
            (el) => el !== toggleButton,
        );

        if (controlsVisible) {
            elements.forEach((el) => (el.style.display = "none"));
            toggleButton.textContent = "Show Controls";
        } else {
            elements.forEach((el) => {
                // Special case for hue shift control - only show if in analogous mode
                if (
                    el.classList &&
                    el.classList.contains("hue-shift-control")
                ) {
                    const config = window.flowConfig || {}; // Get config from global if available
                    el.style.display =
                        config.colorMode === "analogous" ? "block" : "none";
                } else {
                    el.style.display = "";
                }
            });
            toggleButton.textContent = "Hide Controls";
        }
        controlsVisible = !controlsVisible;
    });

    controls.appendChild(toggleButton);
}
const controlPanel = createControlPanel(config, initParticles, initTrailCanvas);
