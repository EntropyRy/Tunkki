const canvas = document.getElementById("snake");
const ctx = canvas.getContext("2d");
const gridSize = 20;

const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
let gameRunning = false;
let animationFrame = null;

let highScore = parseInt(localStorage.getItem("snakeHighScore")) || 0;
let lastScoreDate = localStorage.getItem("snakeLastScoreDate") || "";

let snakeWidth = gridSize - 2;
let isBlueFood = false;
let blueEatenCount = 0;

let titleColorOffset = 0;
let lastTitleColorUpdate = 0;
const titleColorSpeed = 0.05; // Speed of color change
const titleColors = [
  "#FF0000", // Red
  "#FF7F00", // Orange
  "#FFFF00", // Yellow
  "#00FF00", // Green
  "#0000FF", // Blue
  "#4B0082", // Indigo
  "#9400D3", // Violet
];

function getSegmentWidth(index, totalLength) {
  if (index === 0 || index === totalLength - 1) return gridSize - 2; // Head and tail always normal width

  // Calculate relative position (0 to 1) from each end, use the smaller value
  const fromStart = index / (totalLength - 1);
  const fromEnd = 1 - fromStart;
  const relativePos = Math.min(fromStart, fromEnd);

  // Calculate the peak width based on blue foods eaten
  const maxWidth = (blueEatenCount + 1) * (gridSize - 2);

  // Use a sine wave to create smooth transition, peaking in the middle
  const width =
    gridSize -
    2 +
    (maxWidth - (gridSize - 2)) * Math.sin(relativePos * Math.PI);

  return width;
}

function updateHighScore(newScore) {
  if (newScore > highScore) {
    highScore = newScore;
    lastScoreDate = new Date().toISOString().slice(0, 19).replace("T", " ");
    localStorage.setItem("snakeHighScore", highScore);
    localStorage.setItem("snakeLastScoreDate", lastScoreDate);
  }
}

function drawScores() {
  ctx.fillStyle = "rgba(255, 255, 255, 0.8)";
  ctx.font = "20px Arial";
  ctx.textAlign = "left";
  ctx.fillText(`Score: ${score}`, 10, 30);
  ctx.fillText(`High Score: ${highScore}`, 10, 60);
}

if (isMobile) {
  const toggleButton = document.createElement("button");
  toggleButton.id = "toggleControls";
  toggleButton.innerText = "🎮 Show Game";
  toggleButton.style.cssText = `
        position: fixed;
        top: 10px;
        right: 10px;
        padding: 10px;
        background: rgba(0, 0, 0, 0.6);
        border: 2px solid rgba(255, 255, 255, 0.5);
        color: white;
        border-radius: 8px;
        font-size: 16px;
        z-index: 1000;
        cursor: pointer;
        backdrop-filter: blur(5px);
    `;

  document.body.appendChild(toggleButton);

  toggleButton.addEventListener("click", () => {
    gameRunning = !gameRunning;
    canvas.style.display = gameRunning ? "block" : "none";
    toggleButton.innerText = gameRunning ? "🎮 Hide Game" : "🎮 Show Game";
    toggleButton.style.background = gameRunning
      ? "rgba(0, 0, 0, 0.6)"
      : "rgba(0, 0, 0, 0.6)";

    if (gameRunning) {
      canvas.style.pointerEvents = "auto";
      resetGame();
      gameLoop();
    } else {
      canvas.style.pointerEvents = "none";
      if (animationFrame) {
        cancelAnimationFrame(animationFrame);
        animationFrame = null;
      }
    }
  });

  canvas.style.display = "none";
}

const controlButtons = {
  left: { x: 0, y: 0, width: 0, height: 0, text: "←" },
  right: { x: 0, y: 0, width: 0, height: 0, text: "→" },
};

function resizeCanvas() {
  canvas.width = window.innerWidth;
  canvas.height = window.innerHeight;

  if (isMobile) {
    const buttonWidth = canvas.width * 0.2;
    const buttonHeight = buttonWidth * 0.6;
    const buttonY = canvas.height - buttonHeight - 20;

    controlButtons.left.width = buttonWidth;
    controlButtons.left.height = buttonHeight;
    controlButtons.left.x = 20;
    controlButtons.left.y = buttonY;

    controlButtons.right.width = buttonWidth;
    controlButtons.right.height = buttonHeight;
    controlButtons.right.x = canvas.width - buttonWidth - 20;
    controlButtons.right.y = buttonY;
  }
}

window.addEventListener("resize", resizeCanvas);
resizeCanvas();

let snake = [
  {
    x: Math.floor(canvas.width / 2 / gridSize) * gridSize,
    y: Math.floor(canvas.height / 2 / gridSize) * gridSize,
    isHorizontal: true,
  },
];
let food = { x: 0, y: 0 };
let dx = gridSize;
let dy = 0;
let score = 0;
let gameOver = false;

function drawControlButtons() {
  if (!isMobile) return;

  // Draw each button separately with fresh context settings
  [controlButtons.left, controlButtons.right].forEach((button) => {
    ctx.save();

    // Background
    ctx.fillStyle = "rgba(0, 0, 0, 0.6)";
    ctx.strokeStyle = "rgba(255, 255, 255, 0.5)";
    ctx.lineWidth = 2;

    ctx.beginPath();
    ctx.roundRect(button.x, button.y, button.width, button.height, 10);
    ctx.fill();
    ctx.stroke();

    // Arrow
    ctx.fillStyle = "rgba(255, 255, 255, 1)";
    ctx.font = `${button.height * 0.5}px Arial`;
    ctx.textAlign = "center";
    ctx.textBaseline = "middle";
    ctx.fillText(
      button.text,
      button.x + button.width / 2,
      button.y + button.height / 2,
    );

    ctx.restore();
  });
}

function turnLeft() {
  if (dx > 0) {
    dx = 0;
    dy = -gridSize;
  } else if (dx < 0) {
    dx = 0;
    dy = gridSize;
  } else if (dy > 0) {
    dx = gridSize;
    dy = 0;
  } else if (dy < 0) {
    dx = -gridSize;
    dy = 0;
  }
}

function turnRight() {
  if (dx > 0) {
    dx = 0;
    dy = gridSize;
  } else if (dx < 0) {
    dx = 0;
    dy = -gridSize;
  } else if (dy > 0) {
    dx = -gridSize;
    dy = 0;
  } else if (dy < 0) {
    dx = gridSize;
    dy = 0;
  }
}

function handleTouch(e) {
  if (!isMobile || !gameRunning) return;

  e.preventDefault();
  const touch = e.touches[0];
  const rect = canvas.getBoundingClientRect();
  const touchX = (touch.clientX - rect.left) * (canvas.width / rect.width);
  const touchY = (touch.clientY - rect.top) * (canvas.height / rect.height);

  if (gameOver) {
    resetGame();
    return;
  }

  if (
    touchY >= controlButtons.left.y &&
    touchY <= controlButtons.left.y + controlButtons.left.height
  ) {
    if (
      touchX >= controlButtons.left.x &&
      touchX <= controlButtons.left.x + controlButtons.left.width
    ) {
      turnLeft();
    } else if (
      touchX >= controlButtons.right.x &&
      touchX <= controlButtons.right.x + controlButtons.right.width
    ) {
      turnRight();
    }
  }
}

function checkCollision(head) {
  if (
    head.x < 0 ||
    head.x >= canvas.width ||
    head.y < 0 ||
    head.y >= canvas.height
  ) {
    return true;
  }

  for (let i = 1; i < snake.length; i++) {
    const segment = snake[i];
    const segmentWidth = getSegmentWidth(i, snake.length);

    if (segment.isHorizontal) {
      const yOffset = (gridSize - segmentWidth) / 2;
      const bodyTop = segment.y + yOffset;
      const bodyBottom = segment.y + yOffset + segmentWidth;
      const headTop = head.y;
      const headBottom = head.y + (gridSize - 2);

      if (
        head.x === segment.x &&
        ((headTop >= bodyTop && headTop <= bodyBottom) ||
          (headBottom >= bodyTop && headBottom <= bodyBottom))
      ) {
        return true;
      }
    } else {
      const xOffset = (gridSize - segmentWidth) / 2;
      const bodyLeft = segment.x + xOffset;
      const bodyRight = segment.x + xOffset + segmentWidth;
      const headLeft = head.x;
      const headRight = head.x + (gridSize - 2);

      if (
        head.y === segment.y &&
        ((headLeft >= bodyLeft && headLeft <= bodyRight) ||
          (headRight >= bodyLeft && headRight <= bodyRight))
      ) {
        return true;
      }
    }
  }

  return false;
}

function generateFood() {
  // Only spawn blue food if the snake is long enough
  const minLengthNeeded = (blueEatenCount + 2) * 2; // Need enough length for smooth transition
  isBlueFood =
    score >= 100 && snake.length >= minLengthNeeded && Math.random() < 0.2;

  // Calculate current maximum width for margin
  const maxWidth = (blueEatenCount + 1) * (gridSize - 2);
  const margin = Math.ceil(maxWidth / gridSize) + 1;

  food.x =
    Math.floor(
      Math.random() * (canvas.width / gridSize - 2 * margin) + margin,
    ) * gridSize;
  food.y =
    Math.floor(
      Math.random() * ((canvas.height - 100) / gridSize - 2 * margin) + margin,
    ) * gridSize;

  for (let segment of snake) {
    if (segment.x === food.x && segment.y === food.y) {
      generateFood();
      break;
    }
  }
}

if (isMobile) {
  canvas.addEventListener("touchstart", handleTouch, { passive: false });
  canvas.addEventListener("touchmove", (e) => e.preventDefault(), {
    passive: false,
  });
  canvas.addEventListener("touchend", (e) => e.preventDefault(), {
    passive: false,
  });
}

document.addEventListener("keydown", (e) => {
  if (!isMobile && gameRunning) {
    switch (e.key) {
      case "ArrowLeft":
        if (dx === 0) {
          dx = -gridSize;
          dy = 0;
        }
        break;
      case "ArrowRight":
        if (dx === 0) {
          dx = gridSize;
          dy = 0;
        }
        break;
      case "ArrowUp":
        if (dy === 0) {
          dy = -gridSize;
          dx = 0;
        }
        break;
      case "ArrowDown":
        if (dy === 0) {
          dy = gridSize;
          dx = 0;
        }
        break;
    }
  }
});

function resetGame() {
  snake = [
    {
      x: Math.floor(canvas.width / 2 / gridSize) * gridSize,
      y: Math.floor(canvas.height / 2 / gridSize) * gridSize,
      isHorizontal: true,
    },
  ];
  dx = gridSize;
  dy = 0;
  score = 0;
  snakeWidth = gridSize - 2;
  blueEatenCount = 0;
  gameOver = false;
  isBlueFood = false;
  generateFood();
}

function drawGameTitle(x, y, showVersion = true) {
  const title = "HYPERSAUSAGE";
  const currentTime = Date.now();

  // Update color offset
  if (currentTime - lastTitleColorUpdate > 16) {
    // ~60fps
    titleColorOffset += titleColorSpeed;
    if (titleColorOffset >= titleColors.length) {
      titleColorOffset = 0;
    }
    lastTitleColorUpdate = currentTime;
  }

  ctx.save();
  ctx.textAlign = "center";
  ctx.textBaseline = "middle";
  ctx.font = 'bold 48px Impact, "Arial Black", sans-serif';

  // Draw each letter with its own color
  let totalWidth = 0;
  const letters = title.split("");

  // First pass to measure total width
  letters.forEach((letter) => {
    totalWidth += ctx.measureText(letter).width;
  });

  // Draw letters centered
  let currentX = x - totalWidth / 2;
  letters.forEach((letter, i) => {
    const colorIndex = Math.floor((i + titleColorOffset) % titleColors.length);
    ctx.fillStyle = titleColors[colorIndex];
    ctx.fillText(letter, currentX + ctx.measureText(letter).width / 2, y);
    currentX += ctx.measureText(letter).width;
  });

  if (showVersion) {
    ctx.font = "bold 16px Arial";
    ctx.fillStyle = "white";
    ctx.fillText("v1.0", x, y + 30);
  }

  ctx.restore();
}

function gameLoop() {
  if (!gameRunning) return;

  if (gameOver) {
    updateHighScore(score);

    // Calculate final stats
    const snakeLength = snake.length;
    const maxWidth = Math.max(
      ...snake.map((_, i) => getSegmentWidth(i, snake.length)),
    );
    const maxWidthBlocks = Math.round(maxWidth / (gridSize - 2));
    const areaBlocks = Math.floor(
      (canvas.width / gridSize) * (canvas.height / gridSize),
    );

    ctx.fillStyle = "rgba(0, 0, 0, 0.5)";
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = "white";

    // Title and version
    drawGameTitle(canvas.width / 2, canvas.height / 2 - 80);

    // Game over and stats
    ctx.fillStyle = "white";
    ctx.font = "32px Arial";
    ctx.textAlign = "center";
    ctx.fillText("Game Over!", canvas.width / 2, canvas.height / 2 - 20);

    ctx.font = "24px Arial";
    ctx.fillText(`Score: ${score}`, canvas.width / 2, canvas.height / 2 + 20);
    ctx.fillText(
      `High Score: ${highScore}`,
      canvas.width / 2,
      canvas.height / 2 + 50,
    );
    ctx.fillText(
      `Sausage: ${snakeLength} blocks long, ${maxWidthBlocks} blocks wide`,
      canvas.width / 2,
      canvas.height / 2 + 80,
    );
    ctx.fillText(
      `Play Area: ${areaBlocks} blocks${isMobile ? " (Mobile)" : ""}`,
      canvas.width / 2,
      canvas.height / 2 + 110,
    );

    if (score === highScore && score > 0) {
      ctx.font = "16px Arial";
      ctx.fillText(
        `Achieved on: ${lastScoreDate}`,
        canvas.width / 2,
        canvas.height / 2 + 140,
      );
    }

    ctx.fillText(
      isMobile ? "Tap to Restart" : "Press Space to Restart",
      canvas.width / 2,
      canvas.height / 2 + 170,
    );

    animationFrame = requestAnimationFrame(gameLoop);
    return;
  }

  animationFrame = requestAnimationFrame(gameLoop);

  if (!window.gameTime) {
    window.gameTime = Date.now();
    return;
  }

  const currentTime = Date.now();
  if (currentTime - window.gameTime < 100) return;
  window.gameTime = currentTime;

  ctx.fillStyle = "rgba(0, 0, 0, 0.1)";
  ctx.fillRect(0, 0, canvas.width, canvas.height);

  const head = {
    x: snake[0].x + dx,
    y: snake[0].y + dy,
    isHorizontal: Math.abs(dx) > 0,
  };

  if (checkCollision(head)) {
    gameOver = true;
    return;
  }

  snake.unshift(head);

  if (head.x === food.x && head.y === food.y) {
    if (isBlueFood) {
      blueEatenCount++;
      score += score;
    } else {
      score += 10;
    }
    generateFood();
  } else {
    snake.pop();
  }

  ctx.fillStyle = isBlueFood
    ? "rgba(0, 100, 255, 0.8)"
    : "rgba(255, 0, 0, 0.8)";
  ctx.fillRect(food.x, food.y, gridSize - 2, gridSize - 2);

  ctx.fillStyle = "rgba(0, 255, 0, 0.8)";
  snake.forEach((segment, index) => {
    drawSnakeSegment(segment, snake[index + 1], index, snake.length);
  });

  drawScores();

  if (isMobile) {
    drawControlButtons();
  }
}

function drawSnakeSegment(segment, nextSegment, index, totalLength) {
  const currentWidth = getSegmentWidth(index, snake.length);

  // Check if this is a turning point AND at least one blue food has been eaten
  if (
    nextSegment &&
    segment.isHorizontal !== nextSegment.isHorizontal &&
    blueEatenCount > 0
  ) {
    const radius = currentWidth / 2;

    // Draw three circles: before, at, and after the turn
    if (segment.isHorizontal) {
      // Before turn
      ctx.beginPath();
      const beforeX = segment.x;
      const beforeY = segment.y + (gridSize - currentWidth) / 2 + radius;
      ctx.arc(beforeX, beforeY, radius, 0, Math.PI * 2);
      ctx.fill();

      // At turn
      ctx.beginPath();
      const atX = segment.x + (gridSize - 2);
      const atY = segment.y + (gridSize - currentWidth) / 2 + radius;
      ctx.arc(atX, atY, radius, 0, Math.PI * 2);
      ctx.fill();

      // After turn (vertical)
      ctx.beginPath();
      const afterX =
        segment.x + (gridSize - 2) + (gridSize - currentWidth) / 2 + radius;
      const afterY = segment.y + (gridSize - 2);
      ctx.arc(afterX, afterY, radius, 0, Math.PI * 2);
      ctx.fill();
    } else {
      // Before turn
      ctx.beginPath();
      const beforeX = segment.x + (gridSize - currentWidth) / 2 + radius;
      const beforeY = segment.y;
      ctx.arc(beforeX, beforeY, radius, 0, Math.PI * 2);
      ctx.fill();

      // At turn
      ctx.beginPath();
      const atX = segment.x + (gridSize - currentWidth) / 2 + radius;
      const atY = segment.y + (gridSize - 2);
      ctx.arc(atX, atY, radius, 0, Math.PI * 2);
      ctx.fill();

      // After turn (horizontal)
      ctx.beginPath();
      const afterX = segment.x + (gridSize - 2);
      const afterY =
        segment.y + (gridSize - 2) + (gridSize - currentWidth) / 2 + radius;
      ctx.arc(afterX, afterY, radius, 0, Math.PI * 2);
      ctx.fill();
    }
  }
  // Draw regular blocks for straight segments or when no blue food eaten yet
  else if (
    !nextSegment ||
    (nextSegment && segment.isHorizontal === nextSegment.isHorizontal) ||
    blueEatenCount === 0
  ) {
    if (segment.isHorizontal) {
      const yOffset = (gridSize - currentWidth) / 2;
      ctx.fillRect(segment.x, segment.y + yOffset, gridSize - 2, currentWidth);
    } else {
      const xOffset = (gridSize - currentWidth) / 2;
      ctx.fillRect(segment.x + xOffset, segment.y, currentWidth, gridSize - 2);
    }
  }
}

if (!isMobile) {
  gameRunning = true;
  resetGame();
  gameLoop();
} else {
  canvas.addEventListener("touchstart", restartGame);
}

document.addEventListener("keydown", (e) => {
  if (e.code === "Space" && !isMobile) {
    restartGame();
  }
});

function restartGame() {
  if (gameOver) {
    resetGame();
    gameLoop();
  }
}

generateFood();
