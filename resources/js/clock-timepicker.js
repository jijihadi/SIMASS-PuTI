/**
 * Lightweight 24-Hour Material Clock Timepicker + Simple HH:MM Direct Input
 * Zero external dependencies, dark-mode ready, safe for optional fields.
 */

let activeModal = null;

function pad(n) {
    return String(n).padStart(2, '0');
}

function parseTime(str) {
    if (!str) return null;
    const match = String(str).trim().match(/^(\d{1,2}):(\d{2})/);
    if (!match) return null;
    const h = parseInt(match[1], 10);
    const m = parseInt(match[2], 10);
    if (h < 0 || h > 23 || m < 0 || m > 59) return null;
    return { h, m };
}

function buildClockModal() {
    if (activeModal) return activeModal;

    const overlay = document.createElement('div');
    overlay.id = 'simassClockPickerOverlay';
    overlay.className =
        'fixed inset-0 z-[10050] hidden items-center justify-center bg-black/45 backdrop-blur-[2px] p-4 opacity-0 transition-opacity duration-150 ease-out';

    overlay.innerHTML = `
      <div id="simassClockPickerCard"
        class="w-full max-w-[310px] rounded-2xl overflow-hidden bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-2xl transform scale-[0.97] transition-transform duration-150 ease-out select-none">
        <!-- Header: Large clickable/editable HH:MM -->
        <div class="bg-[#b6252a] text-white px-5 py-4">
          <p class="text-[10px] uppercase tracking-wider font-semibold text-white/75 mb-1">Pilih Waktu (24 Jam)</p>
          <div class="flex items-center justify-center gap-1.5">
            <input type="text" inputmode="numeric" maxlength="2" id="simassClockHourInput" aria-label="Jam"
              class="w-16 text-center text-4xl font-bold tabular-nums bg-white/15 hover:bg-white/25 focus:bg-white/30 rounded-xl py-1 outline-none transition cursor-pointer" />
            <span class="text-4xl font-bold opacity-80 -mt-1">:</span>
            <input type="text" inputmode="numeric" maxlength="2" id="simassClockMinInput" aria-label="Menit"
              class="w-16 text-center text-4xl font-bold tabular-nums bg-white/10 hover:bg-white/20 focus:bg-white/30 rounded-xl py-1 outline-none transition cursor-pointer opacity-75" />
          </div>
        </div>

        <!-- Analog 24h Dial -->
        <div class="p-5 flex items-center justify-center bg-white dark:bg-gray-900">
          <div id="simassClockDial"
            class="relative w-[236px] h-[236px] rounded-full bg-gray-100 dark:bg-gray-800/90 cursor-pointer touch-none">
            <!-- Center dot -->
            <div class="absolute left-1/2 top-1/2 w-2 h-2 -ml-1 -mt-1 rounded-full bg-[#b6252a] z-20 pointer-events-none"></div>
            <!-- Hand line -->
            <div id="simassClockHand"
              class="absolute left-1/2 top-1/2 w-0.5 bg-[#b6252a] origin-bottom z-10 pointer-events-none transition-transform duration-150 ease-out"
              style="height: 88px; margin-left: -1px; margin-top: -88px;">
              <div class="absolute -top-4 -left-[15px] w-8 h-8 rounded-full bg-[#b6252a] shadow-sm"></div>
            </div>
            <!-- Numbers container -->
            <div id="simassClockNumbers" class="absolute inset-0 z-20 pointer-events-none"></div>
          </div>
        </div>

        <!-- Footer Actions -->
        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between gap-2">
          <button type="button" id="simassClockClearBtn"
            class="px-3 py-1.5 rounded-lg text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 active:scale-[0.98] transition duration-150">
            Hapus
          </button>
          <div class="flex items-center gap-1.5 ml-auto">
            <button type="button" id="simassClockCancelBtn"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-200/70 dark:hover:bg-gray-700 active:scale-[0.98] transition duration-150">
              Batal
            </button>
            <button type="button" id="simassClockOkBtn"
              class="px-4 py-1.5 rounded-lg text-xs font-bold bg-[#b6252a] hover:bg-[#931c20] text-white shadow-xs active:scale-[0.98] transition duration-150">
              Pilih
            </button>
          </div>
        </div>
      </div>
    `;

    document.body.appendChild(overlay);

    const card = overlay.querySelector('#simassClockPickerCard');
    const hourInput = overlay.querySelector('#simassClockHourInput');
    const minInput = overlay.querySelector('#simassClockMinInput');
    const dial = overlay.querySelector('#simassClockDial');
    const hand = overlay.querySelector('#simassClockHand');
    const numbersEl = overlay.querySelector('#simassClockNumbers');
    const clearBtn = overlay.querySelector('#simassClockClearBtn');
    const cancelBtn = overlay.querySelector('#simassClockCancelBtn');
    const okBtn = overlay.querySelector('#simassClockOkBtn');

    const state = {
        targetInput: null,
        mode: 'hour', // 'hour' | 'minute'
        hour: 8,
        minute: 30,
        dragging: false,
    };

    function setMode(mode) {
        state.mode = mode;
        hourInput.classList.toggle('opacity-75', mode !== 'hour');
        hourInput.classList.toggle('bg-white/25', mode === 'hour');
        minInput.classList.toggle('opacity-75', mode !== 'minute');
        minInput.classList.toggle('bg-white/25', mode === 'minute');
        renderDial();
    }

    function renderDial() {
        hourInput.value = pad(state.hour);
        minInput.value = pad(state.minute);
        numbersEl.innerHTML = '';

        const cx = 118;
        const cy = 118;

        if (state.mode === 'hour') {
            // Outer ring: 1..12
            const outerR = 94;
            for (let i = 1; i <= 12; i++) {
                const angle = (i * 30 - 90) * (Math.PI / 180);
                const x = cx + outerR * Math.cos(angle);
                const y = cy + outerR * Math.sin(angle);
                const isSelected = state.hour === i;
                const span = document.createElement('span');
                span.textContent = String(i);
                span.className = `absolute w-8 h-8 -ml-4 -mt-4 flex items-center justify-center rounded-full text-xs font-semibold ${
                    isSelected ? 'text-white font-bold' : 'text-gray-700 dark:text-gray-200'
                }`;
                span.style.left = `${x}px`;
                span.style.top = `${y}px`;
                numbersEl.appendChild(span);
            }

            // Inner ring: 13..23 and 00
            const innerR = 62;
            for (let i = 1; i <= 12; i++) {
                const val = i === 12 ? 0 : i + 12;
                const angle = (i * 30 - 90) * (Math.PI / 180);
                const x = cx + innerR * Math.cos(angle);
                const y = cy + innerR * Math.sin(angle);
                const isSelected = state.hour === val;
                const span = document.createElement('span');
                span.textContent = pad(val);
                span.className = `absolute w-7 h-7 -ml-3.5 -mt-3.5 flex items-center justify-center rounded-full text-[11px] font-medium ${
                    isSelected ? 'text-white font-bold' : 'text-gray-500 dark:text-gray-400'
                }`;
                span.style.left = `${x}px`;
                span.style.top = `${y}px`;
                numbersEl.appendChild(span);
            }

            const isInner = state.hour === 0 || state.hour > 12;
            const handLen = isInner ? innerR : outerR;
            const deg = (state.hour % 12) * 30;
            hand.style.height = `${handLen}px`;
            hand.style.marginTop = `-${handLen}px`;
            hand.style.transform = `rotate(${deg}deg)`;
        } else {
            // Minute ring: 00, 05, ..., 55
            const r = 94;
            for (let i = 0; i < 12; i++) {
                const val = i * 5;
                const angle = (i * 30 - 90) * (Math.PI / 180);
                const x = cx + r * Math.cos(angle);
                const y = cy + r * Math.sin(angle);
                const isSelected = state.minute === val;
                const span = document.createElement('span');
                span.textContent = pad(val);
                span.className = `absolute w-8 h-8 -ml-4 -mt-4 flex items-center justify-center rounded-full text-xs font-semibold ${
                    isSelected ? 'text-white font-bold' : 'text-gray-700 dark:text-gray-200'
                }`;
                span.style.left = `${x}px`;
                span.style.top = `${y}px`;
                numbersEl.appendChild(span);
            }

            const deg = state.minute * 6;
            hand.style.height = `${r}px`;
            hand.style.marginTop = `-${r}px`;
            hand.style.transform = `rotate(${deg}deg)`;
        }
    }

    function updateFromPointer(clientX, clientY) {
        const rect = dial.getBoundingClientRect();
        const cx = rect.left + rect.width / 2;
        const cy = rect.top + rect.height / 2;
        const dx = clientX - cx;
        const dy = clientY - cy;
        const dist = Math.hypot(dx, dy);

        let deg = Math.atan2(dy, dx) * (180 / Math.PI) + 90;
        if (deg < 0) deg += 360;

        if (state.mode === 'hour') {
            let slot = Math.round(deg / 30) % 12;
            if (slot === 0) slot = 12;
            const isInner = dist < rect.width * 0.33;
            state.hour = isInner ? (slot === 12 ? 0 : slot + 12) : slot;
        } else {
            state.minute = Math.round(deg / 6) % 60;
        }

        renderDial();
    }

    dial.addEventListener('pointerdown', (e) => {
        state.dragging = true;
        dial.setPointerCapture(e.pointerId);
        updateFromPointer(e.clientX, e.clientY);
    });

    dial.addEventListener('pointermove', (e) => {
        if (!state.dragging) return;
        updateFromPointer(e.clientX, e.clientY);
    });

    dial.addEventListener('pointerup', (e) => {
        if (!state.dragging) return;
        state.dragging = false;
        updateFromPointer(e.clientX, e.clientY);
        if (state.mode === 'hour') {
            setTimeout(() => setMode('minute'), 140);
        }
    });

    // Direct typing inside header HH / MM inputs
    hourInput.addEventListener('focus', () => {
        setMode('hour');
        hourInput.select();
    });
    minInput.addEventListener('focus', () => {
        setMode('minute');
        minInput.select();
    });

    hourInput.addEventListener('input', () => {
        const digits = hourInput.value.replace(/\D/g, '').slice(0, 2);
        hourInput.value = digits;
        const num = parseInt(digits, 10);
        if (!Number.isNaN(num) && num >= 0 && num <= 23) {
            state.hour = num;
            renderDial();
            if (digits.length === 2) {
                minInput.focus();
            }
        }
    });

    minInput.addEventListener('input', () => {
        const digits = minInput.value.replace(/\D/g, '').slice(0, 2);
        minInput.value = digits;
        const num = parseInt(digits, 10);
        if (!Number.isNaN(num) && num >= 0 && num <= 59) {
            state.minute = num;
            renderDial();
        }
    });

    function commit(value) {
        if (!state.targetInput) return;
        state.targetInput.value = value;
        state.targetInput.dispatchEvent(new Event('input', { bubbles: true }));
        state.targetInput.dispatchEvent(new Event('change', { bubbles: true }));
        close();
    }

    function open(inputEl) {
        state.targetInput = inputEl;
        const parsed = parseTime(inputEl.value);
        if (parsed) {
            state.hour = parsed.h;
            state.minute = parsed.m;
        } else {
            const now = new Date();
            state.hour = now.getHours();
            state.minute = now.getMinutes();
        }

        clearBtn.style.visibility = inputEl.required ? 'hidden' : 'visible';
        setMode('hour');

        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
        requestAnimationFrame(() => {
            overlay.classList.remove('opacity-0');
            card.classList.remove('scale-[0.97]');
        });
    }

    function close() {
        overlay.classList.add('opacity-0');
        card.classList.add('scale-[0.97]');
        setTimeout(() => {
            overlay.classList.add('hidden');
            overlay.classList.remove('flex');
            state.targetInput = null;
        }, 150);
    }

    clearBtn.addEventListener('click', () => commit(''));
    cancelBtn.addEventListener('click', close);
    okBtn.addEventListener('click', () => commit(`${pad(state.hour)}:${pad(state.minute)}`));
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) close();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !overlay.classList.contains('hidden')) {
            close();
        }
    });

    activeModal = { open, close };
    return activeModal;
}

/**
 * Attach 24h Clock Dial + Simple HH:MM Direct Input to an <input type="time"> element.
 */
export function attachClockTimepicker(el) {
    if (el._clockTimepicker) return;
    el._clockTimepicker = true;

    // Convert to text input so native browser clock UI doesn't collide,
    // while keeping simple direct HH:MM typing fully functional.
    const initialVal = el.value ? el.value.substring(0, 5) : '';
    el.type = 'text';
    el.value = initialVal;
    el.inputMode = 'numeric';
    el.maxLength = 5;
    el.autocomplete = 'off';
    if (!el.placeholder) {
        el.placeholder = 'HH:MM';
    }

    // Wrap input with a subtle clock trigger button so user can EITHER type directly OR click the clock icon/input
    const wrapper = document.createElement('div');
    wrapper.className = 'relative flex items-center w-full';
    el.parentNode.insertBefore(wrapper, el);
    wrapper.appendChild(el);
    el.classList.add('pr-9');

    const triggerBtn = document.createElement('button');
    triggerBtn.type = 'button';
    triggerBtn.tabIndex = -1;
    triggerBtn.title = 'Buka pemilih jam analog';
    triggerBtn.className =
        'absolute right-2.5 p-1 rounded-lg text-gray-400 hover:text-[#b6252a] hover:bg-gray-100 dark:hover:bg-gray-700/60 transition';
    triggerBtn.innerHTML =
        '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
    wrapper.appendChild(triggerBtn);

    const modal = buildClockModal();

    triggerBtn.addEventListener('click', (e) => {
        e.preventDefault();
        modal.open(el);
    });

    // Clicking the input opens the clock dial if empty; typing directly works anytime
    el.addEventListener('click', () => {
        if (el.value.trim() === '') {
            modal.open(el);
        }
    });

    // Auto-format simple direct typing (e.g. "0830" -> "08:30")
    el.addEventListener('input', () => {
        const digits = el.value.replace(/[^\d]/g, '').slice(0, 4);
        if (digits.length >= 3) {
            el.value = `${digits.slice(0, 2)}:${digits.slice(2)}`;
        } else if (digits.length === 2 && el.value.endsWith(':')) {
            el.value = `${digits}:`;
        } else {
            el.value = digits;
        }
    });

    el.addEventListener('blur', () => {
        const val = el.value.trim();
        if (val === '') return;
        const parsed = parseTime(val);
        if (parsed) {
            el.value = `${pad(parsed.h)}:${pad(parsed.m)}`;
            el.setCustomValidity('');
        } else {
            el.setCustomValidity('Gunakan format jam 24 jam HH:MM (contoh: 08:30).');
        }
    });
}
