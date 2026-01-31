import { readEffectConfigById } from "./effects-config.js";
// Snow: https://codepen.io/otsukatomoya/pen/gbDxF/

const defaults = {
    amountOfSnow: 500,
    size: 2,
    speed: 5,
    colorLight: "rgba(50, 50, 50, 0.8)",
    colorDark: "rgba(230, 230, 230, 1)",
};

const config = readEffectConfigById("snow", defaults);

function getTheme() {
    return document.documentElement.getAttribute("data-bs-theme") || "light";
}

function getSnowColor() {
    // Legacy support: if old snowColor config exists, use it for both themes
    if (config.snowColor) {
        return config.snowColor;
    }
    return getTheme() === "dark" ? config.colorDark : config.colorLight;
}

var w = window.innerWidth,
    h = window.innerHeight,
    canvas = document.getElementById("snow"),
    ctx = canvas.getContext("2d"),
    rate = 50,
    amountOfSnow = config.amountOfSnow,
    size = config.size,
    speed = config.speed,
    snowColor = getSnowColor(),
    snowflake = new Array(),
    time,
    count;

document.addEventListener("theme:changed", function (event) {
    snowColor = getSnowColor();
});

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
