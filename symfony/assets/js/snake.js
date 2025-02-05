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
        background: rgba(255, 255, 255, 0.2);
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
      ? "rgba(255, 255, 255, 0.4)"
      : "rgba(255, 255, 255, 0.2)";

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

  ctx.save();

  ctx.fillStyle = "rgba(255, 255, 255, 0.2)";
  ctx.strokeStyle = "rgba(255, 255, 255, 0.5)";
  ctx.lineWidth = 2;

  [controlButtons.left, controlButtons.right].forEach((button) => {
    ctx.beginPath();
    ctx.roundRect(button.x, button.y, button.width, button.height, 10);
    ctx.fill();
    ctx.stroke();

    ctx.fillStyle = "rgba(255, 255, 255, 0.8)";
    ctx.font = `${button.height * 0.5}px Arial`;
    ctx.textAlign = "center";
    ctx.textBaseline = "middle";
    ctx.fillText(
      button.text,
      button.x + button.width / 2,
      button.y + button.height / 2,
    );
  });

  ctx.restore();
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

    if (segment.isHorizontal) {
      const yOffset = (gridSize - snakeWidth) / 2;
      const bodyTop = segment.y + yOffset;
      const bodyBottom = segment.y + yOffset + snakeWidth;
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
      const xOffset = (gridSize - snakeWidth) / 2;
      const bodyLeft = segment.x + xOffset;
      const bodyRight = segment.x + xOffset + snakeWidth;
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
  isBlueFood = score >= 100 && Math.random() < 0.2;

  food.x = Math.floor(Math.random() * (canvas.width / gridSize)) * gridSize;
  food.y =
    Math.floor(Math.random() * ((canvas.height - 100) / gridSize)) * gridSize;

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
  gameOver = false;
  isBlueFood = false;
  generateFood();
}

function gameLoop() {
  if (!gameRunning) return;

  if (gameOver) {
    updateHighScore(score);

    ctx.fillStyle = "rgba(0, 0, 0, 0.5)";
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = "white";
    ctx.font = "48px Arial";
    ctx.textAlign = "center";
    ctx.fillText("Game Over!", canvas.width / 2, canvas.height / 2 - 40);
    ctx.font = "24px Arial";
    ctx.fillText(`Score: ${score}`, canvas.width / 2, canvas.height / 2 + 10);
    ctx.fillText(
      `High Score: ${highScore}`,
      canvas.width / 2,
      canvas.height / 2 + 40,
    );
    if (score === highScore && score > 0) {
      ctx.fillText("New High Score!", canvas.width / 2, canvas.height / 2 + 70);
      ctx.font = "16px Arial";
      ctx.fillText(
        `Achieved on: ${lastScoreDate}`,
        canvas.width / 2,
        canvas.height / 2 + 95,
      );
    }
    ctx.fillText(
      isMobile ? "Tap to Restart" : "Press Space to Restart",
      canvas.width / 2,
      canvas.height / 2 + 130,
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
      snakeWidth = Math.min(gridSize * 4, snakeWidth * 2);
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
    if (index === 0) {
      ctx.fillRect(segment.x, segment.y, gridSize - 2, gridSize - 2);
    } else {
      if (segment.isHorizontal) {
        const yOffset = (gridSize - snakeWidth) / 2;
        ctx.fillRect(segment.x, segment.y + yOffset, gridSize - 2, snakeWidth);
      } else {
        const xOffset = (gridSize - snakeWidth) / 2;
        ctx.fillRect(segment.x + xOffset, segment.y, snakeWidth, gridSize - 2);
      }
    }
  });

  drawScores();

  if (isMobile) {
    drawControlButtons();
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
