const canvas = document.getElementById("rain");
const ctx = canvas.getContext("2d");

const raindrops = [];
const RAIN_DROPS_COUNT = 150; // Number of raindrops
const RAIN_COLOR = "rgba(174,194,224,0.6)";
const SPEED_MIN = 2;
const SPEED_MAX = 5;

function randomBetween(a, b) {
  return a + Math.random() * (b - a);
}

function createDrop() {
  return {
    x: Math.random() * canvas.width,
    y: Math.random() * canvas.height,
    length: randomBetween(5, 15),
    speed: randomBetween(SPEED_MIN, SPEED_MAX),
    width: randomBetween(1, 2),
  };
}

// Initialize raindrops
for (let i = 0; i < RAIN_DROPS_COUNT; i++) {
  raindrops.push(createDrop());
}

function drawRain() {
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  ctx.strokeStyle = RAIN_COLOR;
  ctx.lineCap = "round";

  for (let drop of raindrops) {
    ctx.beginPath();
    ctx.lineWidth = drop.width;
    ctx.moveTo(drop.x, drop.y);
    ctx.lineTo(drop.x, drop.y + drop.length);
    ctx.stroke();

    // Move drop
    drop.y += drop.speed;
    if (drop.y > canvas.height) {
      // Reset drop to top
      drop.x = Math.random() * canvas.width;
      drop.y = -drop.length;
      drop.length = randomBetween(5, 15);
      drop.speed = randomBetween(SPEED_MIN, SPEED_MAX);
      drop.width = randomBetween(1, 2);
    }
  }

  requestAnimationFrame(drawRain);
}

// Start animation
drawRain();
