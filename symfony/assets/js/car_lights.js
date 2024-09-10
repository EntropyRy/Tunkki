// Create a new instance of CarLightsBehindHeavySnow
const carLightsBehindHeavySnow = new CarLightsBehindHeavySnow();

// Create the animation
carLightsBehindHeavySnow.create();

// Resize the animation when the window is resized
window.addEventListener("resize", () => carLightsBehindHeavySnow.resize());

// Create a class for the animation
function CarLightsBehindHeavySnow() {
  // Set the canvas and context
  let canvas = document.createElement("canvas");
  let context = canvas.getContext("2d");

  // Set the canvas width and height
  let width = 0;
  let height = 0;

  // Set the snowflakes
  let snowflakes = [];

  // Set the car lights
  let carLights = [];

  // Set the mist
  let mist = [];

  // Set the animation frame
  let animationFrame = null;

  // Set the animation speed
  let speed = 0.5;

  // Set the snowflake size
  let snowflakeSize = 2;

  // Set the car lights size
  let carLightsSize = 2;

  // Set the mist size
  let mistSize = 2;

  // Set the mist opacity
  let mistOpacity = 0.05;

  // Set the mist speed
  let mistSpeed = 0.5;

  // Set the mist color
  let mistColor = "rgba(255, 255, 255, 0.05)";

  // Set the car lights color
  let carLightsColor = "rgba(255, 255, 255, 0.5)";

  // Set the snowflake color
  let snowflakeColor = "rgba(255, 255, 255, 0.5)";

  // Set the snowflake count
  let snowflakeCount = 100;

  // Set the car lights count
  let carLightsCount = 10;

  // Set the mist count
  let mistCount = 10;

  // Set the mist direction
  let mistDirection = "right";

  // Set the mist direction speed
  let mistDirection;
  let mistDirectionSpeed = 0.5;

  // Set the mist direction change
  //
  let mistDirectionChange = 0;

  // Set the mist direction change speed
  let mistDirectionChangeSpeed = 0.01;

  // Create the animation
  this.create = () => {
    // Get the element with the id of #carlights
    let element = document.getElementById("carlights");

    // Set the canvas width and height
    width = element.offsetWidth;
    height = element.offsetHeight;

    // Set the canvas width and height
    canvas.width = width;
    canvas.height = height;

    // Set the canvas style
    canvas.style.width = "100%";
    canvas.style.height = "100%";

    // Set the canvas position
    canvas.style.position = "absolute";
    canvas.style.top = 0;
    canvas.style.left = 0;

    // Append the canvas to the element
    element.appendChild(canvas);

    // Create the snowflakes
    for (let i = 0; i < snowflakeCount; i++) {
      snowflakes.push({
        x: Math.random() * width,
        y: Math.random() * height,
        size: snowflakeSize,
        speed: Math.random() * speed,
      });
    }

    // Create the car lights
    for (let i = 0; i < carLightsCount; i++) {
      carLights.push({
        x: Math.random() * width,
        y: Math.random() * height,
        size: carLightsSize,
        speed: Math.random() * speed,
      });
    }

    // Create the mist
    for (let i = 0; i < mistCount; i++) {
      mist.push({
        x: Math.random() * width,
        y: Math.random() * height,
        size: mistSize,
        speed: Math.random() * mistSpeed,
      });
    }

    // Render the animation
    this.render();
  };

  // Resize the animation
  this.resize = () => {
    // Set the canvas width and height
    width = canvas.offsetWidth;
    height = canvas.offsetHeight;

    // Set the canvas width and height
    canvas.width = width;
    canvas.height = height;

    // Clear the canvas
    context.clearRect(0, 0, width, height);

    // Create the snowflakes
    snowflakes = [];
    for (let i = 0; i < snowflakeCount; i++) {
      snowflakes.push({
        x: Math.random() * width,
        y: Math.random() * height,
        size: snowflakeSize,
        speed: Math.random() * speed,
      });
    }

    // Create the car lights
    carLights = [];
    for (let i = 0; i < carLightsCount; i++) {
      carLights.push({
        x: Math.random() * width,
        y: Math.random() * height,
        size: carLightsSize,
        speed: Math.random() * speed,
      });
    }

    // Create the mist
    mist = [];
    for (let i = 0; i < mistCount; i++) {
      mist.push({
        x: Math.random() * width,
        y: Math.random() * height,
        size: mistSize,
        speed: Math.random() * mistSpeed,
      });
    }

    // Render the animation

    this.render();
  };

  // Render the animation
  // This function is called recursively using requestAnimationFrame
  // It will render the snowflakes, car lights, and mist
  // It will also update the snowflakes, car lights, and mist positions
  // It will also clear the canvas when it is not visible
  // This function is optimized for performance by not rendering the canvas when it is not visible

  this.render = () => {
    // Check if the canvas is visible
    if (canvas.offsetWidth === 0 && canvas.offsetHeight === 0) {
      // Clear the canvas
      context.clearRect(0, 0, width, height);
      // Stop the animation
      cancelAnimationFrame(animationFrame);
      // Return
      return;
    }

    // Clear the canvas
    context.clearRect(0, 0, width, height);

    // Render the snowflakes
    for (let i = 0; i < snowflakes.length; i++) {
      context.fillStyle = snowflakeColor;
      context.beginPath();
      context.arc(
        snowflakes[i].x,
        snowflakes[i].y,
        snowflakes[i].size,
        0,
        Math.PI * 2
      );
      context.fill();
    }

    // Render the car lights
    for (let i = 0; i < carLights.length; i++) {
      context.fillStyle = carLightsColor;
      context.beginPath();
      context.arc(
        carLights[i].x,
        carLights[i].y,
        carLights[i].size,
        0,
        Math.PI * 2
      );
      context.fill();
    }

    // Render the mist
    for (let i = 0; i < mist.length; i++) {
      context.fillStyle = mistColor;
      context.beginPath();
      context.arc(mist[i].x, mist[i].y, mist[i].size, 0, Math.PI * 2);
      context.fill();
    }

    // Update the snowflakes
    for (let i = 0; i < snowflakes.length; i++) {
      snowflakes[i].y += snowflakes[i].speed;
      if (snowflakes[i].y > height) {
        snowflakes[i].y = 0;
      }
    }

    // Update the car lights
    for (let i = 0; i < carLights.length; i++) {
      carLights[i].y += carLights[i].speed;
      if (carLights[i].y > height) {
        carLights[i].y = 0;
      }
    }

    // Update the mist
    for (let i = 0; i < mist.length; i++) {
      mist[i].y += mist[i].speed;
      if (mist[i].y > height) {
        mist[i].y = 0;
      }
    }

    // Update
    mistDirectionChange += mistDirectionChangeSpeed;
    if (mistDirectionChange > 1) {
      mistDirectionChange = 0;
      mistDirection = mistDirection === "right" ? "left" : "right";
    }

    // Update the mist direction

    if (mistDirection === "right") {
      for (let i = 0; i < mist.length; i++) {
        mist[i].x += mist[i].speed;
        if (mist[i].x > width) {
          mist[i].x = 0;
        }
      }
    }

    if (mistDirection === "left") {
      for (let i = 0; i < mist.length; i++) {
        mist[i].x -= mist[i].speed;
        if (mist[i].x < 0) {
          mist[i].x = width;
        }
      }
    }

    // Create the animation
    animationFrame = requestAnimationFrame(this.render);
  };
}
