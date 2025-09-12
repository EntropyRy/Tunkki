import { readEffectConfigById } from "./effects-config.js";
(function () {
    "use strict";

    // Defaults align with provider-side vhsDefaults()
    const defaults = {
        scaleFactor: 2.5,
        fps: 50,
        sampleCount: 10,
        scanDurationSec: 15,
    };
    const cfg = readEffectConfigById("vhs", defaults);

    var canvas = document.querySelector("#vhs"),
        context = canvas.getContext("gl") || canvas.getContext("2d"),
        scaleFactor = Number(cfg.scaleFactor ?? defaults.scaleFactor),
        samples = [],
        sampleIndex = 0,
        scanOffsetY = 0,
        scanSize = 0,
        FPS = Number(cfg.fps ?? defaults.fps),
        scanSpeed =
            FPS * Number(cfg.scanDurationSec ?? defaults.scanDurationSec), // seconds for scan to travel
        SAMPLE_COUNT = Number(cfg.sampleCount ?? defaults.sampleCount);

    window.onresize = function () {
        canvas.width = canvas.offsetWidth / scaleFactor;
        canvas.height =
            canvas.width / (canvas.offsetWidth / canvas.offsetHeight);
        scanSize = canvas.offsetHeight / scaleFactor / 3;

        samples = [];
        for (var i = 0; i < SAMPLE_COUNT; i++)
            samples.push(
                generateRandomSample(context, canvas.width, canvas.height),
            );
    };

    function interpolate(x, x0, y0, x1, y1) {
        return y0 + (y1 - y0) * ((x - x0) / (x1 - x0));
    }

    function generateRandomSample(context, w, h) {
        var intensity = [];
        var random = 0.1;
        var factor = h / 50;
        var trans = 1 - Math.random() * 0.05;

        var intensityCurve = [];
        for (var i = 0; i < Math.floor(h / factor) + factor; i++)
            intensityCurve.push(Math.floor(Math.random() * 15));

        for (var i = 0; i < h; i++) {
            var value = interpolate(
                i / factor,
                Math.floor(i / factor),
                intensityCurve[Math.floor(i / factor)],
                Math.floor(i / factor) + 1,
                intensityCurve[Math.floor(i / factor) + 1],
            );
            intensity.push(value);
        }

        var imageData = context.createImageData(w, h);
        for (var i = 0; i < w * h; i++) {
            var k = i * 4;
            var color = Math.floor(36 * Math.random());
            // Optional: add an intensity curve to try to simulate scan lines
            color += intensity[Math.floor(i / w)];
            imageData.data[k] =
                imageData.data[k + 1] =
                imageData.data[k + 2] =
                    color;
            // Bell curve for alpha calculation
            // Using a Gaussian function to determine alpha
            var mean = 20; // Center of the bell curve (around gray)
            var stdDev = 8; // Standard deviation (controls the width of the curve)
            var alpha = Math.round(
                255 * Math.exp(-0.5 * Math.pow((color - mean) / stdDev, 2)),
            );
            imageData.data[k + 3] = alpha; // Set alpha based on the bell curve
        }
        return imageData;
    }

    function render() {
        context.putImageData(samples[Math.floor(sampleIndex)], 0, 0);

        sampleIndex += 20 / FPS; // 1/FPS == 1 second
        if (sampleIndex >= samples.length) sampleIndex = 0;

        var grd = context.createLinearGradient(
            0,
            scanOffsetY,
            0,
            scanSize + scanOffsetY,
        );

        grd.addColorStop(0, "rgba(255,255,255,0)");
        grd.addColorStop(0.1, "rgba(255,255,255,0)");
        grd.addColorStop(0.2, "rgba(255,255,255,0.2)");
        grd.addColorStop(0.3, "rgba(255,255,255,0.0)");
        grd.addColorStop(0.45, "rgba(255,255,255,0.1)");
        grd.addColorStop(0.5, "rgba(255,255,255,0.8)");
        grd.addColorStop(0.55, "rgba(255,255,255,0.45)");
        grd.addColorStop(0.6, "rgba(255,255,255,0.25)");
        //grd.addColorStop(0.8, 'rgba(255,255,255,0.15)');
        grd.addColorStop(1, "rgba(255,255,255,0)");

        context.fillStyle = grd;
        context.fillRect(0, scanOffsetY, canvas.width, scanSize + scanOffsetY);
        context.globalCompositeOperation = "lighter";

        scanOffsetY += canvas.height / scanSpeed;
        if (scanOffsetY > canvas.height) scanOffsetY = -(scanSize / 2);

        window.requestAnimationFrame(render);
    }
    window.onresize();
    window.requestAnimationFrame(render);
})();
