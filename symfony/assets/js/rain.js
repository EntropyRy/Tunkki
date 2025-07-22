const canvas = document.getElementById("rain");
const ctx = canvas.getContext("2d");

const raindrops = [];
const RAIN_DROPS_COUNT = 150; // Number of raindrops
const SPEED_MIN = 1;
const SPEED_MAX = 8;

function randomBetween(a, b) {
  return a + Math.random() * (b - a);
}

function createDrop() {
  return {
    x: Math.random() * canvas.width,
    y: Math.random() * canvas.height,
    length: randomBetween(14, 24),
    speed: randomBetween(SPEED_MIN, SPEED_MAX),
    width: randomBetween(2, 3.5),
    z: Math.random(), // for optional depth sorting
  };
}

// Initialize raindrops
for (let i = 0; i < RAIN_DROPS_COUNT; i++) {
  raindrops.push(createDrop());
}

function drawRain() {
  ctx.clearRect(0, 0, canvas.width, canvas.height);

  // Optional: sort by z for painter's order
  raindrops.sort((a, b) => a.z - b.z);

  for (let drop of raindrops) {
    // Create a gradient for the glass effect
    let grad = ctx.createLinearGradient(
      drop.x,
      drop.y,
      drop.x,
      drop.y + drop.length,
    );
    grad.addColorStop(0, "rgba(255,255,255,0.7)"); // bright top
    grad.addColorStop(0.3, "rgba(180,220,255,0.35)"); // blueish center
    grad.addColorStop(0.7, "rgba(100,150,220,0.18)"); // faint blue
    grad.addColorStop(1, "rgba(255,255,255,0.05)"); // fade out

    ctx.save();
    // Optional: add subtle shadow for depth
    ctx.shadowColor = "rgba(100,150,220,0.1)";
    ctx.shadowBlur = 6;

    ctx.beginPath();
    ctx.strokeStyle = grad;
    ctx.lineWidth = drop.width;
    ctx.moveTo(drop.x, drop.y);
    ctx.lineTo(drop.x, drop.y + drop.length);
    ctx.stroke();

    // Optional: white highlight ellipse at the top for glassy reflection
    ctx.shadowBlur = 0;
    ctx.beginPath();
    ctx.ellipse(
      drop.x,
      drop.y + 2,
      drop.width * 0.7,
      drop.width * 0.3,
      0,
      0,
      2 * Math.PI,
    );
    ctx.fillStyle = "rgba(255,255,255,0.45)";
    ctx.fill();

    ctx.restore();

    // Move drop
    drop.y += drop.speed;
    if (drop.y > canvas.height) {
      // Reset drop to top
      drop.x = Math.random() * canvas.width;
      drop.y = -drop.length;
      drop.length = randomBetween(10, 24);
      drop.speed = randomBetween(SPEED_MIN, SPEED_MAX);
      drop.width = randomBetween(1, 2.5);
      drop.z = Math.random();
    }
  }

  requestAnimationFrame(drawRain);
}

// Start animation
drawRain();
