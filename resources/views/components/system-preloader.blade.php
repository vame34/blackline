@props([
    'autostart' => true,
])

<div id="bl-system-preloader" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black transition-opacity duration-300" @if(! $autostart) style="display:none;" @endif>
    <div class="bl-preloader-grid pointer-events-none fixed inset-0"></div>
    <div class="bl-preloader-scanlines pointer-events-none fixed inset-0 opacity-20"></div>
    <div class="bl-preloader-vignette pointer-events-none fixed inset-0"></div>

    <main class="relative z-10 flex w-full max-w-2xl flex-col items-center justify-center px-4">
        <div class="relative flex w-full max-w-lg flex-col items-center p-12">
            <div class="bl-preloader-bracket bl-preloader-bracket-tl shadow-[0_0_5px_#00ff88]"></div>
            <div class="bl-preloader-bracket bl-preloader-bracket-tr shadow-[0_0_5px_#00ff88]"></div>
            <div class="bl-preloader-bracket bl-preloader-bracket-bl shadow-[0_0_5px_#00ff88]"></div>
            <div class="bl-preloader-bracket bl-preloader-bracket-br shadow-[0_0_5px_#00ff88]"></div>

            <div class="mb-12 flex flex-col items-center bl-preloader-logo">
                <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" class="mb-4">
                    <rect x="12" y="20" width="8" height="24" fill="#00ff88" class="opacity-80"></rect>
                    <line x1="16" y1="12" x2="16" y2="52" stroke="#00ff88" stroke-width="2"></line>
                    <rect x="28" y="14" width="8" height="36" stroke="#00ff88" stroke-width="2" fill="none"></rect>
                    <line x1="32" y1="8" x2="32" y2="56" stroke="#00ff88" stroke-width="2"></line>
                    <rect x="44" y="28" width="8" height="16" fill="#00ff88"></rect>
                    <line x1="48" y1="24" x2="48" y2="48" stroke="#00ff88" stroke-width="2"></line>
                </svg>
                <h1 class="mb-2 font-mono text-5xl font-bold italic tracking-tighter text-white">BlackLine</h1>
                <span class="font-mono text-[10px] uppercase tracking-[0.5em] text-gray-500">Terminal System</span>
            </div>

            <div class="flex w-full flex-col gap-2">
                <div class="mb-1 flex items-end justify-between">
                    <span class="text-xs uppercase tracking-widest text-[#00ff88]/70">System Load</span>
                    <span id="bl-preloader-percentage" class="font-mono text-4xl font-bold leading-none text-[#00ff88] [text-shadow:0_0_10px_#00ff88]">0%</span>
                </div>

                <div class="relative h-1 w-full overflow-hidden border border-white/10 bg-[#111111]">
                    <div id="bl-preloader-progress" class="relative h-full w-0 bg-[#00ff88] transition-[width] duration-100 [box-shadow:0_0_15px_#00ff88]">
                        <div class="absolute bottom-0 right-0 top-0 w-4 blur-[2px] bg-white/50"></div>
                    </div>
                </div>

                <div class="mt-2 flex h-6 items-start justify-between">
                    <div class="flex items-center gap-2 font-mono text-xs text-gray-400">
                        <span class="text-[#00ff88]">➜</span>
                        <span id="bl-preloader-status">INITIALIZING...</span>
                        <span class="bl-preloader-cursor text-[#00ff88]">_</span>
                    </div>
                    <div class="font-mono text-[10px] tracking-wider text-gray-600">
                        MEM: <span id="bl-preloader-mem">128</span>MB
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<style>
    .bl-preloader-grid {
        background-image:
            linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
        background-size: 40px 40px;
    }
    .bl-preloader-scanlines {
        background: linear-gradient(
            to bottom,
            rgba(255,255,255,0),
            rgba(255,255,255,0) 50%,
            rgba(0,0,0,0.2) 50%,
            rgba(0,0,0,0.2)
        );
        background-size: 100% 4px;
        animation: bl-preloader-scan 15s linear infinite;
    }
    .bl-preloader-vignette {
        background: radial-gradient(circle, transparent 50%, rgba(0,0,0,0.8) 100%);
    }
    .bl-preloader-logo {
        animation: bl-preloader-pulse 3s ease-in-out infinite;
    }
    .bl-preloader-cursor {
        animation: bl-preloader-cursor 1s step-end infinite;
    }
    .bl-preloader-bracket {
        position: absolute;
        width: 20px;
        height: 20px;
        border-color: #00ff88;
        border-style: solid;
    }
    .bl-preloader-bracket-tl { top: -10px; left: -10px; border-width: 2px 0 0 2px; }
    .bl-preloader-bracket-tr { top: -10px; right: -10px; border-width: 2px 2px 0 0; }
    .bl-preloader-bracket-bl { bottom: -10px; left: -10px; border-width: 0 0 2px 2px; }
    .bl-preloader-bracket-br { bottom: -10px; right: -10px; border-width: 0 2px 2px 0; }

    @keyframes bl-preloader-scan {
        0% { background-position: 0 0; }
        100% { background-position: 0 100%; }
    }
    @keyframes bl-preloader-pulse {
        0%, 100% { filter: drop-shadow(0 0 5px #00ff88); opacity: 1; }
        50% { filter: drop-shadow(0 0 15px #00ff88); opacity: 0.8; }
    }
    @keyframes bl-preloader-cursor {
        0%, 100% { opacity: 1; }
        50% { opacity: 0; }
    }
</style>

<script>
    (function () {
        const root = document.getElementById('bl-system-preloader');
        if (!root) return;

        const progressBar = document.getElementById('bl-preloader-progress');
        const percentage = document.getElementById('bl-preloader-percentage');
        const status = document.getElementById('bl-preloader-status');
        const mem = document.getElementById('bl-preloader-mem');

        let progress = 0;
        let timer = null;
        let memTimer = null;
        let watchdog = null;

        const setProgress = (value) => {
            progress = Math.max(0, Math.min(100, Math.round(value)));
            progressBar.style.width = progress + '%';
            percentage.textContent = progress + '%';
            mem.textContent = String(Math.floor(128 + progress * 8 + Math.random() * 30));
        };

        const setStatus = (text) => {
            status.textContent = text;
        };

        const show = () => {
            root.style.display = 'flex';
            root.classList.remove('opacity-0', 'pointer-events-none');
        };

        const hide = () => {
            root.classList.add('opacity-0', 'pointer-events-none');
            setTimeout(() => {
                root.style.display = 'none';
            }, 280);
        };

        const stopTimers = () => {
            if (timer) clearInterval(timer);
            if (memTimer) clearInterval(memTimer);
            if (watchdog) clearTimeout(watchdog);
            timer = null;
            memTimer = null;
            watchdog = null;
        };

        const complete = (statusText = 'READY') => {
            setStatus(statusText);
            setProgress(100);
            stopTimers();
            setTimeout(hide, 180);
        };

        const runSequence = (messages, cap) => {
            stopTimers();
            let messageIdx = 0;
            setStatus(messages[0] || 'INITIALIZING...');
            setProgress(0);
            show();

            timer = setInterval(() => {
                if (progress < cap) {
                    setProgress(progress + Math.random() * 7 + 1);
                }
                if (messageIdx < messages.length - 1 && Math.random() > 0.65) {
                    messageIdx += 1;
                    setStatus(messages[messageIdx]);
                }
            }, 180);

            // Hard fallback: never let preloader hang forever.
            watchdog = setTimeout(() => {
                complete('READY');
            }, 12000);
        };

        window.BlacklinePreloader = {
            show,
            hide,
            setProgress,
            setStatus,
            runSequence,
            startLogin() {
                runSequence([
                    'ИНИЦИАЛИЗАЦИЯ СЕССИИ...',
                    'ЗАГРУЗКА КЛЮЧЕЙ...',
                    'ПОДКЛЮЧЕНИЕ К ПОТОКУ...',
                    'ВХОД В ТЕРМИНАЛ...'
                ], 92);
            },
            complete
        };

        const onNavigated = () => {
            if (root.style.display !== 'none') {
                complete('READY');
            }
        };

        // For wire:navigate transitions.
        document.addEventListener('livewire:navigated', onNavigated);

        const autostart = {{ $autostart ? 'true' : 'false' }};
        if (autostart) {
            runSequence([
                'INITIALIZING...',
                'LOADING CORE MODULES...',
                'ESTABLISHING SECURE HANDSHAKE...',
                'DECRYPTING USER KEYS...',
                'READY TO LAUNCH...'
            ], 88);

            if (document.readyState === 'complete') {
                setTimeout(() => complete('READY'), 120);
            } else {
                window.addEventListener('load', () => complete('READY'), { once: true });
            }
        }
    })();
</script>
