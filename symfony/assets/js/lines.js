(function () {
  const canvas = document.getElementById("lines");
  const ctx = canvas.getContext("2d");

  const amplitude = 50; // amplitude of the wave
  const frequency = 0.005; // frequency of the wave
  const phase = 0; // phase of the wave
  const numLines = canvas.height / 15; // number of lines to draw
  const lineSpacing = (canvas.height * 9) / numLines; // spacing between lines
  const lineWidth = 1; // width of lines
  const color = "black"; // color of lines
  const speed = 0.1; // speed of animation

  let time = 0; // time variable for animation
  function drawLines() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    for (let i = 0; i < numLines; i++) {
      ctx.beginPath();
      ctx.strokeStyle = color;
      ctx.lineWidth = lineWidth;

      for (let x = -10; x < canvas.width; x++) {
        const y =
          amplitude * Math.sin(frequency * x + phase + time * speed * i) +
          i * lineSpacing +
          amplitude * Math.sin(frequency * x + phase + time * speed * (i + 1));
        if (i === 0) {
          ctx.moveTo(x, y);
        } else {
          ctx.lineTo(x, y);
        }
      }

      ctx.stroke();
    }

    time += 0.01; // increment time for animation

    requestAnimationFrame(drawLines); // request next animation frame
  }

  requestAnimationFrame(drawLines); // request first animation frame

  function handleResize() {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    drawLines();
  }

  window.addEventListener("resize", handleResize);
  handleResize(); // initialize canvas size
})();
