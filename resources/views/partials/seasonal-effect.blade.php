@php($seasonalEffect = \App\Models\Setting::seasonalEffect())
@if ($seasonalEffect)
  <div id="seasonal-fx" data-effect="{{ $seasonalEffect }}" aria-hidden="true"></div>
  <style>
    #seasonal-fx { position: fixed; inset: 0; pointer-events: none; overflow: hidden; z-index: 30; }
    #seasonal-fx span { position: absolute; top: -10vh; line-height: 1; animation: sfx-fall linear infinite; will-change: transform; }
    #seasonal-fx[data-effect="snow"] span { color: #93c5fd; }
    #seasonal-fx[data-effect="rain"] span { width: 1.5px; height: 20px; background: rgba(59, 130, 246, .8); }
    @keyframes sfx-fall { to { transform: translate(var(--dx), 115vh) rotate(var(--rot)); } }
    @media (prefers-reduced-motion: reduce) { #seasonal-fx { display: none; } }
  </style>
  <script>
    (() => {
      const box = document.getElementById('seasonal-fx');
      const fx = {
        snow:   { n: 45, chars: ['❄'],       min: 7,  max: 14,  dx: 60,  rot: 180 },
        leaves: { n: 18, chars: ['🍂', '🍁'], min: 9,  max: 16,  dx: 160, rot: 720 },
        rain:   { n: 60, chars: [''],        min: .7, max: 1.2, dx: 0,   rot: 0 },
      }[box.dataset.effect];
      const r = (a, b) => a + Math.random() * (b - a);
      for (let i = 0; i < fx.n; i++) {
        const s = document.createElement('span');
        const dur = r(fx.min, fx.max);
        s.textContent = fx.chars[i % fx.chars.length];
        s.style.cssText = `left:${r(0, 100)}vw;font-size:${r(10, 22)}px;opacity:${r(.35, .85)};`
          + `animation-duration:${dur}s;animation-delay:${-r(0, dur)}s;`
          + `--dx:${r(-fx.dx, fx.dx)}px;--rot:${r(-fx.rot, fx.rot)}deg`;
        box.append(s);
      }
    })();
  </script>
@endif
