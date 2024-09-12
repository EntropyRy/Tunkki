(function () {
  "use strict";

  var canvas = document.querySelector("#tv"),
    context = canvas.getContext("gl") || canvas.getContext("2d"),
    scaleFactor = 2.5, // Noise size
    samples = [],
    sampleIndex = 0,
    FPS = 60,
    SAMPLE_COUNT = 10;

  window.onresize = function () {
    canvas.width = canvas.offsetWidth / scaleFactor;
    canvas.height = canvas.width / (canvas.offsetWidth / canvas.offsetHeight);

    samples = [];
    for (var i = 0; i < SAMPLE_COUNT; i++)
      samples.push(generateRandomSample(context, canvas.width, canvas.height));
  };

  function generateRandomSample(context, w, h) {
    var intensity = [];
    var random = 0.1;
    var factor = h / 50;
    var trans = 1 - Math.random() * 0.05;

    var imageData = context.createImageData(w, h);
    for (var i = 0; i < w * h; i++) {
      var k = i * 4;
      var color = Math.floor(36 * Math.random());
      imageData.data[k] = imageData.data[k + 1] = imageData.data[k + 2] = color;
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
    window.requestAnimationFrame(render);
  }
  window.onresize();
  window.requestAnimationFrame(render);
})();
