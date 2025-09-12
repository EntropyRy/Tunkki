import { readEffectConfigById } from "./effects-config.js";
(function () {
    const canvas = document.getElementById("lines");
    const ctx = canvas.getContext("2d");

    const defaults = {
        amplitude: 50,
        frequency: 0.005,
        phase: 0,
        lineWidth: 1,
        color: "black",
        speed: 0.1,
    };
    const cfg = readEffectConfigById("lines", defaults);

    const amplitude = Number(cfg.amplitude ?? 50); // amplitude of the wave
    const frequency = Number(cfg.frequency ?? 0.005); // frequency of the wave
    const phase = Number(cfg.phase ?? 0); // phase of the wave
    const lineWidth = Number(cfg.lineWidth ?? 1); // width of lines
    const color = String(cfg.color ?? "black"); // color of lines
    const speed = Number(cfg.speed ?? 0.1); // speed of animation

    let numLines = 0; // number of lines to draw (computed on resize)
    let lineSpacing = 0; // spacing between lines (computed on resize)

    let time = 0; // time variable for animation
    function drawLines() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        for (let i = 0; i < numLines; i++) {
            ctx.beginPath();
            ctx.strokeStyle = color;
            ctx.lineWidth = lineWidth;

            for (let x = -10; x < canvas.width; x++) {
                const y =
                    amplitude *
                        Math.sin(frequency * x + phase + time * speed * i) +
                    i * lineSpacing +
                    amplitude *
                        Math.sin(
                            frequency * x + phase + time * speed * (i + 1),
                        );
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
        numLines = Math.max(1, Math.floor(canvas.height / 15));
        lineSpacing = (canvas.height * 9) / numLines;
        drawLines();
    }

    window.addEventListener("resize", handleResize);
    handleResize(); // initialize canvas size
})();
