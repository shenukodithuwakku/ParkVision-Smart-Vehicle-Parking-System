/* ParkVision — Animated parking garage canvas background */
(function () {
  const canvas = document.getElementById('authCanvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');

  let W, H, particles, beams, rafId;

  /* ── Resize ── */
  function resize() {
    W = canvas.width  = window.innerWidth;
    H = canvas.height = window.innerHeight;
  }

  /* ── Particle: bokeh light (ceiling lights, car lights, reflections) ── */
  function makeParticle() {
    const isRed   = Math.random() < .18;
    const isGreen = Math.random() < .08;
    const isCeiling = Math.random() < .25;
    let r, g, b;
    if      (isRed)    { r=220; g=40+Math.random()*30;  b=30; }
    else if (isGreen)  { r=30;  g=160+Math.random()*60; b=80; }
    else if (isCeiling){ r=160; g=210; b=255; }   // cool white ceiling
    else               { r=37;  g=99+Math.random()*60; b=210+Math.random()*45; } // blue
    return {
      x:    Math.random() * W,
      y:    Math.random() * H,
      r:    2 + Math.random() * (isCeiling ? 22 : 14),
      vx:   (Math.random() - .5) * .25,
      vy:   (Math.random() - .5) * .18,
      a:    .12 + Math.random() * .55,
      life: Math.random(),
      speed: .003 + Math.random() * .006,
      col: `${r},${g},${b}`,
    };
  }

  /* ── Light beam (like headlights reflected on wet floor) ── */
  function makeBeam() {
    return {
      x:    Math.random() * W * .6,
      y:    H * (.55 + Math.random() * .4),
      len:  80 + Math.random() * 200,
      angle: -Math.PI / 2 + (Math.random() - .5) * .4,
      a:    .04 + Math.random() * .08,
      speed: .0008 + Math.random() * .001,
      phase: Math.random() * Math.PI * 2,
    };
  }

  /* ── Grid lines (parking lot floor) ── */
  function drawGrid() {
    ctx.save();
    ctx.strokeStyle = 'rgba(37,99,235,.04)';
    ctx.lineWidth = 1;
    /* Perspective grid from vanishing point */
    const vx = W * .45, vy = H * .38;
    const lines = 16;
    for (let i = 0; i <= lines; i++) {
      const x0 = (W / lines) * i;
      ctx.beginPath();
      ctx.moveTo(x0, H);
      ctx.lineTo(vx + (x0 - W / 2) * .1, vy);
      ctx.stroke();
    }
    /* Horizontal cross lines */
    for (let t = .45; t < 1; t += .07) {
      const y = vy + (H - vy) * t;
      const xl = vx - (W / 2) * t * 1.4;
      const xr = vx + (W / 2) * t * 1.4;
      ctx.beginPath(); ctx.moveTo(xl, y); ctx.lineTo(xr, y); ctx.stroke();
    }
    /* Yellow lane line */
    const laneX = W * .36;
    const grad = ctx.createLinearGradient(laneX, H * .45, laneX, H);
    grad.addColorStop(0, 'rgba(245,158,11,.0)');
    grad.addColorStop(.4,'rgba(245,158,11,.22)');
    grad.addColorStop(1, 'rgba(245,158,11,.35)');
    ctx.strokeStyle = grad;
    ctx.lineWidth = 3;
    ctx.setLineDash([32, 18]);
    ctx.beginPath();
    ctx.moveTo(laneX, H * .48); ctx.lineTo(laneX, H);
    ctx.stroke();
    ctx.setLineDash([]);
    ctx.restore();
  }

  function init() {
    resize();
    particles = Array.from({ length: 80  }, makeParticle);
    beams     = Array.from({ length: 6   }, makeBeam);
  }

  function draw(ts) {
    /* Dark base */
    ctx.fillStyle = '#04070f';
    ctx.fillRect(0, 0, W, H);

    /* Floor-level ambient glow */
    const floorGrad = ctx.createRadialGradient(W*.35, H*.7, 0, W*.35, H*.7, W*.55);
    floorGrad.addColorStop(0,'rgba(37,60,120,.28)');
    floorGrad.addColorStop(1,'transparent');
    ctx.fillStyle = floorGrad;
    ctx.fillRect(0, 0, W, H);

    drawGrid();

    /* Light beams */
    beams.forEach(b => {
      b.phase += b.speed;
      const a = b.a * (.5 + .5 * Math.sin(b.phase));
      const bGrad = ctx.createLinearGradient(
        b.x, b.y, b.x + Math.cos(b.angle) * b.len, b.y + Math.sin(b.angle) * b.len
      );
      bGrad.addColorStop(0, `rgba(56,189,248,${a})`);
      bGrad.addColorStop(1, 'transparent');
      ctx.save();
      ctx.strokeStyle = bGrad;
      ctx.lineWidth = 18;
      ctx.lineCap = 'round';
      ctx.beginPath();
      ctx.moveTo(b.x, b.y);
      ctx.lineTo(b.x + Math.cos(b.angle)*b.len, b.y + Math.sin(b.angle)*b.len);
      ctx.stroke();
      ctx.restore();
    });

    /* Bokeh particles */
    particles.forEach(p => {
      p.x += p.vx; p.y += p.vy; p.life += p.speed;
      const pulse = .5 + .5 * Math.sin(p.life * Math.PI * 2);
      const alpha = p.a * pulse;

      /* Reflection below */
      const ry = H - (H - p.y) * .18 + H * .62;
      if (ry < H) {
        const rGrad = ctx.createRadialGradient(p.x, ry, 0, p.x, ry, p.r * .6);
        rGrad.addColorStop(0, `rgba(${p.col},${alpha * .18})`);
        rGrad.addColorStop(1, 'transparent');
        ctx.beginPath(); ctx.arc(p.x, ry, p.r * .6, 0, Math.PI * 2);
        ctx.fillStyle = rGrad; ctx.fill();
      }

      /* Main glow */
      const g = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, p.r);
      g.addColorStop(0,  `rgba(${p.col},${alpha})`);
      g.addColorStop(.45,`rgba(${p.col},${alpha*.5})`);
      g.addColorStop(1,  'transparent');
      ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
      ctx.fillStyle = g; ctx.fill();

      /* Wrap */
      if (p.x < -p.r*2)   p.x = W + p.r;
      if (p.x > W + p.r*2) p.x = -p.r;
      if (p.y < -p.r*2)   p.y = H + p.r;
      if (p.y > H + p.r*2) p.y = -p.r;
    });

    rafId = requestAnimationFrame(draw);
  }

  window.addEventListener('resize', () => { resize(); });
  init();
  rafId = requestAnimationFrame(draw);
})();
