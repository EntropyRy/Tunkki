var w = window.innerWidth,
  h = window.innerHeight,
  canvas = document.getElementById("snow_mouse_dodge"),
  ctx = canvas.getContext("2d"),
  rate = 50,
  amountOfSnow = 500,
  size = 2,
  speed = 5,
  snowColor = "rgba(230, 230, 230,1)",
  snowflake = new Array(),
  time,
  count,
  mouseX = w / 2,
  mouseY = h / 2,
  dodgeDistance = 50,
  lensDistance = 100,
  lensStrength = 0.05;

canvas.setAttribute("width", w);
canvas.setAttribute("height", h);

function init() {
  time = 0;
  count = 0;
  for (var i = 0; i < amountOfSnow; i++) {
    snowflake[i] = {
      x: Math.ceil(Math.random() * w),
      y: Math.ceil(Math.random() * h),
      toX: Math.random() * 5 + 1,
      toY: Math.random() * 5 + 1,
      c: snowColor,
      size: Math.random() * size,
    };
  }
  window.addEventListener("resize", onWindowResize);
}

function onWindowResize() {
  w = window.innerWidth;
  h = window.innerHeight;
  canvas.setAttribute("width", w);
  canvas.setAttribute("height", h);
}

function snow() {
  ctx.clearRect(0, 0, w, h);
  for (var i = 0; i < amountOfSnow; i++) {
    var li = snowflake[i];

    // Calculate distance to cursor
    var dx = li.x - mouseX;
    var dy = li.y - mouseY;
    var distance = Math.sqrt(dx * dx + dy * dy);

    // Apply lens-like bending effect
    if (distance < lensDistance) {
      var distortionFactor = 1 - (distance / lensDistance) * lensStrength;
      li.x = mouseX + dx * distortionFactor;
      li.y = mouseY + dy * distortionFactor;
    }

    ctx.beginPath();
    ctx.arc(li.x, li.y, li.size, 0, Math.PI * 2, false);
    ctx.fillStyle = li.c;
    ctx.fill();
    li.x = li.x + li.toX * (time * 0.05);
    li.y = li.y + li.toY * (time * 0.05);
    if (li.x > w) {
      li.x = 0;
    }
    if (li.y > h) {
      li.y = 0;
    }
    if (li.x < 0) {
      li.x = w;
    }
    if (li.y < 0) {
      li.y = h;
    }
  }
  if (time < speed) {
    time++;
  }
  window.requestAnimationFrame(snow);
}

function updateMousePosition(event) {
  mouseX = event.clientX;
  mouseY = event.clientY;
}

init();
window.requestAnimationFrame(snow);
window.addEventListener("mousemove", updateMousePosition);
window.addEventListener("touchmove", updateMousePosition);
