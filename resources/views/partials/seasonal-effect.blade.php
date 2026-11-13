{{-- Check-in presensi menjalankan kamera + pengenalan wajah di HP: jangan dibebani animasi. --}}
@php($seasonalEffect = request()->routeIs('presence.index') ? null : \App\Models\Setting::seasonalEffect())
@if ($seasonalEffect)
  {{-- z-45: di atas sidebar (z-40), di bawah modal (z-50) --}}
  <canvas id="seasonal-fx" data-effect="{{ $seasonalEffect }}" aria-hidden="true"
    style="position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:45"></canvas>
  <script>
    (() => {
      const cv = document.getElementById('seasonal-fx');
      if (matchMedia('(prefers-reduced-motion: reduce)').matches) return cv.remove();
      const ctx = cv.getContext('2d');
      const rnd = (a, b) => a + Math.random() * (b - a);
      const isDark = () => document.documentElement.classList.contains('dark');
      let w = 0, h = 0, t = 0;

      const sprite = (sw, sh, paint) => {
        const c = document.createElement('canvas');
        c.width = sw;
        c.height = sh;
        paint(c.getContext('2d'));
        return c;
      };
      // Titik bulat lembut (bokeh) untuk salju.
      const glow = (rgb) => sprite(32, 32, (g) => {
        const gr = g.createRadialGradient(16, 16, 0, 16, 16, 16);
        gr.addColorStop(0, `rgba(${rgb},1)`);
        gr.addColorStop(0.4, `rgba(${rgb},.7)`);
        gr.addColorStop(1, `rgba(${rgb},0)`);
        g.fillStyle = gr;
        g.fillRect(0, 0, 32, 32);
      });
      // Garis hujan dengan ekor memudar.
      const streak = (rgb) => sprite(4, 64, (g) => {
        const gr = g.createLinearGradient(0, 0, 0, 64);
        gr.addColorStop(0, `rgba(${rgb},0)`);
        gr.addColorStop(1, `rgba(${rgb},1)`);
        g.fillStyle = gr;
        g.fillRect(0, 0, 4, 64);
      });

      const SLANT = 0.2; // kemiringan hujan (tertiup angin ke kiri)
      const LEAF = ['#f59e0b', '#ea580c', '#c2410c', '#b45309', '#dc2626', '#ca8a04'];

      // p.z = kedalaman 0.2 (jauh) .. 1 (dekat): ukuran, kecepatan, opacity ikut z → parallax.
      // base = jumlah partikel di layar 1440x900.
      const FX = {
        snow: {
          base: 110,
          img: { light: glow('96,165,250'), dark: glow('255,255,255') },
          init(p) {
            p.x = rnd(-20, w + 20);
            p.r = 1 + p.z * 2.6;
            p.vy = 0.3 + p.z * 1.1;
            p.amp = 0.2 + p.z * 0.5;
            p.ph = rnd(0, 7);
            p.f = rnd(0.5, 1.3);
            p.a = 0.3 + p.z * 0.6;
          },
          step(p, dt, wind) {
            p.x += (Math.sin(t * p.f + p.ph) * p.amp + wind * p.z) * dt;
            p.y += p.vy * dt;
          },
          draw(p, dark) {
            const s = p.r * 4;
            ctx.globalAlpha = p.a;
            ctx.drawImage(dark ? this.img.dark : this.img.light, p.x - s / 2, p.y - s / 2, s, s);
          },
        },
        leaves: {
          base: 30,
          init(p) {
            p.x = rnd(-20, w + 20);
            p.s = 7 + p.z * 8;
            p.vy = 0.45 + p.z * 0.8;
            p.amp = 0.6 + p.z * 1.1;
            p.ph = rnd(0, 7);
            p.f = rnd(0.4, 0.9);
            p.rot = rnd(0, 7);
            p.vr = rnd(-0.03, 0.03);
            p.flip = rnd(0, 7);
            p.vf = rnd(0.012, 0.03);
            p.c = LEAF[Math.floor(Math.random() * LEAF.length)];
            p.a = 0.6 + p.z * 0.35;
          },
          step(p, dt, wind) {
            p.x += (Math.sin(t * p.f + p.ph) * p.amp + wind * p.z * 2) * dt;
            p.y += p.vy * dt;
            p.rot += p.vr * dt;
            p.flip += p.vf * dt;
          },
          draw(p) {
            const k = Math.cos(p.flip); // daun berputar 3D: lebar menyempit saat membalik
            ctx.globalAlpha = p.a * (0.6 + 0.4 * Math.abs(k));
            ctx.save();
            ctx.translate(p.x, p.y);
            ctx.rotate(p.rot);
            ctx.scale(k, 1);
            ctx.fillStyle = p.c;
            ctx.beginPath();
            ctx.moveTo(0, -p.s);
            ctx.quadraticCurveTo(p.s * 1.25, 0, 0, p.s);
            ctx.quadraticCurveTo(-p.s * 1.25, 0, 0, -p.s);
            ctx.fill();
            ctx.strokeStyle = 'rgba(69,26,3,.35)'; // tulang daun + tangkai
            ctx.lineWidth = 0.8;
            ctx.beginPath();
            ctx.moveTo(0, -p.s * 0.75);
            ctx.lineTo(0, p.s * 1.3);
            ctx.stroke();
            ctx.restore();
          },
        },
        rain: {
          base: 150,
          img: { light: streak('59,130,246'), dark: streak('186,230,253') },
          init(p) {
            p.x = rnd(0, w + h * SLANT);
            p.vy = 10 + p.z * 10;
            p.len = 10 + p.z * 16;
            p.lw = 0.8 + p.z * 1.2;
            p.a = 0.15 + p.z * 0.35;
          },
          step(p, dt) {
            p.x -= p.vy * SLANT * dt;
            p.y += p.vy * dt;
          },
          draw(p, dark) {
            ctx.globalAlpha = p.a;
            ctx.save();
            ctx.translate(p.x, p.y);
            ctx.rotate(Math.atan(SLANT));
            ctx.drawImage(dark ? this.img.dark : this.img.light, -p.lw / 2, -p.len, p.lw, p.len);
            ctx.restore();
          },
        },
      };

      const fx = FX[cv.dataset.effect];
      if (!fx) return cv.remove();
      const parts = [];
      const spawn = (p, anywhere) => {
        p.z = rnd(0.2, 1);
        fx.init(p);
        p.y = anywhere ? rnd(-20, h) : rnd(-60, -10);
        return p;
      };

      const resize = () => {
        const dpr = Math.min(devicePixelRatio || 1, 2);
        w = innerWidth;
        h = innerHeight;
        cv.width = w * dpr;
        cv.height = h * dpr;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        // Jumlah partikel ikut luas layar; layar HP tetap kebagian minimal 35%.
        const n = Math.round(fx.base * Math.min(1.5, Math.max(0.35, (w * h) / (1440 * 900))));
        parts.length = Math.min(parts.length, n);
        while (parts.length < n) parts.push(spawn({}, true));
      };
      addEventListener('resize', resize);

      // Pindah halaman di SIMASS = muat ulang penuh. Simpan partikel saat halaman ditinggal,
      // lanjutkan di halaman berikutnya supaya efek tidak terlihat mulai ulang.
      const KEY = 'seasonal-fx';
      const restore = () => {
        try {
          const saved = JSON.parse(sessionStorage.getItem(KEY));
          if (saved?.effect === cv.dataset.effect) parts.splice(0, parts.length, ...saved.parts);
        } catch {}
      };
      addEventListener('pagehide', () => {
        try {
          sessionStorage.setItem(KEY, JSON.stringify({ effect: cv.dataset.effect, parts }));
        } catch {}
      });
      // Kembali lewat tombol Back (bfcache): pakai posisi dari halaman yang barusan ditinggal.
      addEventListener('pageshow', (e) => {
        if (e.persisted) {
          restore();
          resize();
        }
      });
      restore();
      resize();

      let last = performance.now() - 16.67;
      const frame = (now) => {
        requestAnimationFrame(frame);
        // Layar 120/144 Hz memanggil frame 2x lebih sering; lewati yang terlalu rapat (hemat CPU & baterai).
        if (now - last < 10) return;
        const dt = Math.min((now - last) / 16.67, 3); // dinormalisasi ke 60fps; cegah lompatan setelah tab ditinggal
        last = now;
        t = (performance.timeOrigin + now) / 1000; // jam dinding: ayunan & angin menyambung antarhalaman
        const wind = Math.sin(t * 0.3) * 0.5; // hembusan angin pelan bolak-balik
        const dark = isDark();
        ctx.clearRect(0, 0, w, h);
        for (const p of parts) {
          fx.step(p, dt, wind);
          if (p.y > h + 40) spawn(p);
          fx.draw(p, dark);
        }
      };
      frame(performance.now()); // gambar langsung agar partikel sudah ada saat halaman pertama tampil
    })();
  </script>
@endif
