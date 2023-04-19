window.requestAnimFrame = (function(callback) {
  return window.requestAnimationFrame || window.webkitRequestAnimationFrame || window.mozRequestAnimationFrame || window.oRequestAnimationFrame || window.msRequestAnimationFrame ||
    function(callback) {
      window.setTimeout(callback, 1000 / 60);
    };
})();

var gnum = 40; //num grids / frame
var _x = window.innerWidth + 200; //x width (canvas width)
var _y = window.innerHeight + 200; //y height (canvas height)
var w = _x / gnum; //grid sq width
var h = _y / gnum; //grid sq height
var $; //context
var parts; //particles 
var frm = 0; //value from
var P1 = 0.0005; //point one
var P2 = 0.01; //point two
var n = 0.98; //n value for later
var n_vel = 0.02; //velocity
var ŭ = 0; //color update
var msX = 0; //mouse x
var msY = 0; //mouse y
var msdn = false; //mouse down flag

var Part = function() {
  this.x = 0; //x pos
  this.y = 0; //y pos
  this.vx = 0; //velocity x
  this.vy = 0; //velocity y
  this.ind_x = 0; //index x
  this.ind_y = 0; //index y
};

Part.prototype.frame = function() {

  if (this.ind_x == 0 || this.ind_x == gnum - 1 || this.ind_y == 0 || this.ind_y == gnum - 1) {
    return;
  }

  var ax = 0; //angle x
  var ay = 0; //angle y
  //off_dx, off_dy = offset distance x, y
  var off_dx = this.ind_x * w - this.x;
  var off_dy = this.ind_y * h - this.y;
  ax = P1 * off_dx;
  ay = P1 * off_dy;

  ax -= P2 * (this.x - parts[this.ind_x - 1][this.ind_y].x);
  ay -= P2 * (this.y - parts[this.ind_x - 1][this.ind_y].y);

  ax -= P2 * (this.x - parts[this.ind_x + 1][this.ind_y].x);
  ay -= P2 * (this.y - parts[this.ind_x + 1][this.ind_y].y);

  ax -= P2 * (this.x - parts[this.ind_x][this.ind_y - 1].x);
  ay -= P2 * (this.y - parts[this.ind_x][this.ind_y - 1].y);

  ax -= P2 * (this.x - parts[this.ind_x][this.ind_y + 1].x);
  ay -= P2 * (this.y - parts[this.ind_x][this.ind_y + 1].y);

  this.vx += (ax - this.vx * n_vel);
  this.vy += (ay - this.vy * n_vel);

  this.x += this.vx * n;
  this.y += this.vy * n;
  if (msdn) {
    var dx = this.x - msX;
    var dy = this.y - msY;
    var ɋ = Math.sqrt(dx * dx + dy * dy);
    if (ɋ < 50) {
      ɋ = ɋ < 10 ? 10 : ɋ;
      this.x -= dx / ɋ * 5;
      this.y -= dy / ɋ * 5;
    }
  }
};

function go() {
    parts = []; //particle array
    for (var i = 0; i < gnum; i++) {
      parts.push([]);
      for (var j = 0; j < gnum; j++) {
        var p = new Part();
        p.ind_x = i;
        p.ind_y = j;
        p.x = i * w;
        p.y = j * h;
        parts[i][j] = p;
      }
    }
  }
  //move particles function
function mv_part() {

    for (var i = 0; i < gnum; i++) {
      for (var j = 0; j < gnum; j++) {
        var p = parts[i][j];
        p.frame();
      }
    }
  }
  //draw grid function
function draw() {
    $.strokeStyle = "hsla(" + (ŭ % 360) + ",100%,50%,1)";
    $.beginPath();
    ŭ -= .5;
    for (var i = 0; i < gnum - 1; i += 1) {
      for (var j = 0; j < gnum - 1; j += 1) {
        var p1 = parts[i][j];
        var p2 = parts[i][j + 1];
        var p3 = parts[i + 1][j + 1];
        var p4 = parts[i + 1][j];
        draw_each(p1, p2, p3, p4);
      }
    }
    $.stroke();

  }
  //draw each in array
function draw_each(p1, p2, p3, p4) {

    $.moveTo(p1.x, p1.y);
    $.lineTo(p2.x, p2.y);
    $.moveTo(p1.x, p1.y);
    $.lineTo(p4.x, p4.y);
    
    if (p1.ind_x == gnum - 2) {
      $.moveTo(p3.x, p3.y);
      $.lineTo(p4.x, p4.y);
    }
    if (p1.ind_y == gnum - 2) {
      $.moveTo(p3.x, p3.y);
      $.lineTo(p2.x, p2.y);
    }
  }
  //call functions to run
function calls() {
    $.fillStyle = "hsla(0, 5%, 5%, 0)";
    $.fillRect(0, 0, _x, _y);

    mv_part();
    draw();
    frm++;
  }
  //create wave effect 
function wave(x, y) {

  var wv = Math.sin(x / wv * xw);
  var wave = Math.sin(0.2 * w * frm + y * yw) * w;

  return wave;
}

var c = document.getElementById('grid');
var $ = c.getContext('2d');
$.fillStyle = "hsla(0, 5%, 5%, 0)";
$.fillRect(0, 0, _x, _y);

function resize() {
  if (c.width < window.innerWidth) {
    c.width = window.innerWidth;
  }

  if (c.height < window.innerHeight) {
    c.height = window.innerHeight;
  }
}
window.requestAnimFrame(go);

document.addEventListener('mousemove', MSMV, false);
document.addEventListener('mousedown', MSDN, false);
document.addEventListener('touchstart', MSDN, false);
document.addEventListener('mouseup', MSUP, false);
document.addEventListener('touchend', MSUP, false);

function MSDN(e) {
  msdn = true;
}

function MSUP(e) {
  msdn = false;
}

function MSMV(e) {
  var rect = e.target.getBoundingClientRect();
  msX = e.clientX - rect.left;
  msY = e.clientY - rect.top;
}
window.onload = function() {
  run();

  function run() {
    window.requestAnimFrame(calls);
    window.requestAnimFrame(run, 33);
  }
  resize();
};
