import { readEffectConfigById } from "./effects-config.js";
// Snow: https://codepen.io/otsukatomoya/pen/gbDxF/

var w = window.innerWidth,
    h = window.innerHeight,
    canvas = document.getElementById("snow"),
    ctx = canvas.getContext("2d"),
    rate = 50,
    amountOfSnow = 500,
    size = 2,
    speed = 5,
    snowColor = "rgba(230, 230, 230,1)",
    snowflake = new Array(),
    time,
    count;

canvas.setAttribute("width", w);
canvas.setAttribute("height", h);

// Read JSON config overrides
const defaults = {
    amount: 500,
    size: 2,
    speed: 5,
    color: "rgba(230, 230, 230,1)",
};
const cfg = readEffectConfigById("snow", defaults);
// Apply to variables defined above
amountOfSnow = Number(cfg.amount ?? defaults.amount);
size = Number(cfg.size ?? defaults.size);
speed = Number(cfg.speed ?? defaults.speed);
snowColor = String(cfg.color ?? defaults.color);

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
}

function snow() {
    ctx.clearRect(0, 0, w, h);
    for (var i = 0; i < amountOfSnow; i++) {
        var li = snowflake[i];

        ctx.beginPath();
        ctx.arc(li.x, li.y, li.size, 0, Math.PI * 2, false);
        ctx.fillStyle = snowColor;
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
init();
window.requestAnimationFrame(snow);
// snow();
