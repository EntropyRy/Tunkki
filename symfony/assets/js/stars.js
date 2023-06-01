const canvas = document.getElementById("stars");
const context = canvas.getContext("2d");

// Set the canvas dimensions to match the window size
canvas.width = window.innerWidth;
canvas.height = window.innerHeight;

// Star class
class Star {
  constructor(x, y, size, speed) {
    this.x = x;
    this.y = y;
    this.size = size;
    this.speed = speed;
  }

  update() {
    this.x -= this.speed;
    if (this.x < 0) {
      this.x = canvas.width;
      this.y = Math.random() * canvas.height;
    }
  }

  draw() {
    context.beginPath();
    context.arc(this.x, this.y, this.size, 0, Math.PI * 2);
    context.closePath();
    context.fillStyle = "white";
    context.fill();
  }
}

// Meteorite class
class Meteorite {
  constructor(x, y, size, speed) {
    this.x = x;
    this.y = y;
    this.size = size;
    this.speed = speed;
    this.opacity = 1;
  }

  update() {
    this.x -= this.speed;
    if (this.x < 0) {
      this.x = canvas.width;
      this.y = Math.random() * canvas.height;
      this.opacity = 1;
    }
    this.opacity -= 0.01;
  }

  draw() {
    context.beginPath();
    context.arc(this.x, this.y, this.size, 0, Math.PI * 2);
    context.closePath();
    context.fillStyle = `rgba(255, 255, 255, ${this.opacity})`;
    context.fill();
  }
}

// Generate stars
const stars = [];
for (let i = 0; i < 200; i++) {
  const x = Math.random() * canvas.width;
  const y = Math.random() * canvas.height;
  const size = Math.random() * 3;
  const speed = 0.1 + Math.random() * 2;
  stars.push(new Star(x, y, size, speed));
}

// Generate meteorites
const meteorites = [];
for (let i = 0; i < 5; i++) {
  const x = Math.random() * canvas.width;
  const y = Math.random() * canvas.height;
  const size = Math.random() * 5 + 2;
  const speed = 2 + Math.random() * 3;
  meteorites.push(new Meteorite(x, y, size, speed));
}

// Animation loop
function animate() {
  requestAnimationFrame(animate);
  context.clearRect(0, 0, canvas.width, canvas.height);

  // Draw stars
  for (let i = 0; i < stars.length; i++) {
    stars[i].update();
    stars[i].draw();
  }

  // Draw meteorites
  for (let i = 0; i < meteorites.length; i++) {
    meteorites[i].update();
    meteorites[i].draw();
  }
  // Generate occasional meteorites
  if (Math.random() < 0.01) {
    const x = canvas.width;
    const y = Math.random() * canvas.height;
    const size = Math.random() * 5 + 2;
    const speed = 2 + Math.random() * 3;
    meteorites.push(new Meteorite(x, y, size, speed));
  }

  // Remove faded meteorites
  meteorites.forEach((meteorite, index) => {
    if (meteorite.opacity <= 0) {
      meteorites.splice(index, 1);
    }
  });
}
// Start the animation loop
animate();

// Adjust canvas dimensions on window resize
window.addEventListener("resize", () => {
  canvas.width = window.innerWidth;
  canvas.height = window.innerHeight;
});
