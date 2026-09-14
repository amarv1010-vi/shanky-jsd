/* ============================================================
   JSD Construction — cursor-fx.js
   Signature desktop cursor effect:
   1. A warm champagne-gold "light" follows the cursor, as if it
      is illuminating the dark fabric of the site (mix-blend screen).
   2. Tiny golden glitter particles trail the cursor and fade out.
   Disabled automatically on touch devices and for users who
   prefer reduced motion. Zero dependencies.
   ============================================================ */

(function () {
  "use strict";

  var fine = window.matchMedia("(pointer: fine)").matches;
  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  if (!fine || reduced) return;

  /* ---------- 1. The light veil ---------- */
  var veil = document.createElement("div");
  veil.id = "cursor-veil";
  document.body.appendChild(veil);

  var mx = -1000, my = -1000, vx = -1000, vy = -1000;
  var veilOn = false;

  function paintVeil() {
    // eased follow for a soft, luxurious lag
    vx += (mx - vx) * 0.16;
    vy += (my - vy) * 0.16;
    veil.style.background =
      "radial-gradient(340px circle at " + vx + "px " + vy + "px, " +
      "rgba(224,177,113,0.115), rgba(207,161,104,0.05) 38%, transparent 70%)";
  }

  /* ---------- 2. Glitter particles ---------- */
  var canvas = document.createElement("canvas");
  canvas.id = "cursor-sparkles";
  document.body.appendChild(canvas);
  var ctx = canvas.getContext("2d");

  function resize() {
    canvas.width = window.innerWidth * devicePixelRatio;
    canvas.height = window.innerHeight * devicePixelRatio;
    ctx.setTransform(devicePixelRatio, 0, 0, devicePixelRatio, 0, 0);
  }
  resize();
  window.addEventListener("resize", resize);

  var GOLD = ["#E0B171", "#CFA168", "#C1955D", "#F5DDB8", "#9F7745"];
  var particles = [];
  var MAX = 90;

  function spawn(x, y, speedBoost) {
    if (particles.length >= MAX) return;
    var count = 1 + Math.round(Math.random() + speedBoost);
    for (var i = 0; i < count && particles.length < MAX; i++) {
      var a = Math.random() * Math.PI * 2;
      var s = 0.2 + Math.random() * (0.7 + speedBoost);
      particles.push({
        x: x + (Math.random() - 0.5) * 10,
        y: y + (Math.random() - 0.5) * 10,
        dx: Math.cos(a) * s,
        dy: Math.sin(a) * s - 0.25,           // slight upward drift, like embers
        life: 1,
        decay: 0.012 + Math.random() * 0.02,
        r: 0.6 + Math.random() * 1.8,
        star: Math.random() < 0.18,           // some particles are 4-point stars
        c: GOLD[(Math.random() * GOLD.length) | 0],
        tw: Math.random() * Math.PI * 2       // twinkle phase
      });
    }
  }

  function drawStar(p, r) {
    ctx.beginPath();
    ctx.moveTo(p.x, p.y - r * 2.4);
    ctx.quadraticCurveTo(p.x, p.y, p.x + r * 2.4, p.y);
    ctx.quadraticCurveTo(p.x, p.y, p.x, p.y + r * 2.4);
    ctx.quadraticCurveTo(p.x, p.y, p.x - r * 2.4, p.y);
    ctx.quadraticCurveTo(p.x, p.y, p.x, p.y - r * 2.4);
    ctx.fill();
  }

  var lastX = null, lastY = null, lastMove = 0;

  document.addEventListener("mousemove", function (e) {
    mx = e.clientX; my = e.clientY;
    if (!veilOn) { veil.style.opacity = "1"; veilOn = true; vx = mx; vy = my; }
    var now = performance.now();
    var dist = lastX === null ? 0 : Math.hypot(mx - lastX, my - lastY);
    // faster movement scatters more glitter
    spawn(mx, my, Math.min(dist / 40, 1.2));
    lastX = mx; lastY = my; lastMove = now;
  }, { passive: true });

  document.addEventListener("mouseleave", function () {
    veil.style.opacity = "0";
    veilOn = false;
  });

  // extra burst when hovering interactive elements — the "fabric" reacts
  document.addEventListener("mouseover", function (e) {
    if (e.target.closest("a, button, .btn, .service-card, .project-card")) {
      spawn(mx, my, 1.4);
    }
  }, { passive: true });

  function frame() {
    paintVeil();
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.globalCompositeOperation = "lighter";
    for (var i = particles.length - 1; i >= 0; i--) {
      var p = particles[i];
      p.x += p.dx; p.y += p.dy;
      p.dy += 0.008;                          // faint gravity
      p.life -= p.decay;
      p.tw += 0.25;
      if (p.life <= 0) { particles.splice(i, 1); continue; }
      var twinkle = 0.65 + 0.35 * Math.sin(p.tw);
      ctx.globalAlpha = Math.max(p.life, 0) * twinkle;
      ctx.fillStyle = p.c;
      ctx.shadowColor = p.c;
      ctx.shadowBlur = 8;
      if (p.star) {
        drawStar(p, p.r * p.life);
      } else {
        ctx.beginPath();
        ctx.arc(p.x, p.y, Math.max(p.r * p.life, 0.1), 0, Math.PI * 2);
        ctx.fill();
      }
    }
    ctx.globalAlpha = 1;
    ctx.shadowBlur = 0;
    ctx.globalCompositeOperation = "source-over";
    requestAnimationFrame(frame);
  }
  requestAnimationFrame(frame);
})();
