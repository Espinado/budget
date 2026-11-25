<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        @if (request()->routeIs('home'))
            <script>
                if (window.matchMedia('(display-mode: standalone)').matches
                    || window.matchMedia('(display-mode: fullscreen)').matches
                    || window.matchMedia('(display-mode: minimal-ui)').matches
                    || window.matchMedia('(display-mode: window-controls-overlay)').matches
                    || window.navigator.standalone === true) {
                    window.location.replace(@json(route('dashboard')));
                }
            </script>
        @endif
        <meta name="theme-color" content="#0f6e6b">
        <meta name="app-version" content="{{ \App\Support\AppRelease::current() }}">
        @auth
            @php($familyHousehold = auth()->user()->households()->first())
            @if ($familyHousehold)
                <meta name="family-pulse-url" content="{{ route('family.pulse') }}">
                <meta name="family-after" content="{{ (int) (\App\Models\HouseholdActivity::query()->where('household_id', $familyHousehold->id)->max('id') ?? 0) }}">
            @endif
        @endauth
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="Family Money">
        <title>{{ $title ?? 'Family Money' }}</title>
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" href="/favicon.ico" sizes="48x48">
        <link rel="icon" href="/icons/family-money-192.png" type="image/png" sizes="192x192">
        <link rel="icon" href="/icons/family-money-512.png" type="image/png" sizes="512x512">
        <link rel="apple-touch-icon" href="/icons/family-money-180.png">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-paper text-ink antialiased" @if (request()->routeIs('home')) data-page="home" @endif>
        <div id="update-banner" hidden class="border-b border-sky-200 bg-sky-800 text-white">
            <div class="mx-auto flex max-w-6xl flex-col gap-3 px-3 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-4">
                <p class="text-base font-medium">{{ __('Вышло обновление Family Money. Установите его, чтобы видеть последнюю версию.') }}</p>
                <button type="button" id="update-button" onclick="familyMoneyUpdate(this)" class="w-full rounded-full bg-white px-4 py-3 text-lg font-semibold text-sky-800 sm:w-auto" style="touch-action: manipulation; min-height: 3.25rem;">{{ __('Обновить') }}</button>
            </div>
        </div>
        <script>
            function familyMoneyUpdate(button) {
                if (window.__familyMoneyUpdating) {
                    return;
                }

                window.__familyMoneyUpdating = true;
                button.disabled = true;
                button.textContent = @json(__('Обновляем…'));

                const reload = () => {
                    const ios = /iPad|iPhone|iPod/.test(navigator.userAgent)
                        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

                    if (!ios) {
                        window.location.reload();

                        return;
                    }

                    const url = new URL(window.location.href);
                    url.searchParams.set('_fm', String(Date.now()));
                    window.location.replace(url.href);
                };

                if (navigator.serviceWorker) {
                    navigator.serviceWorker.addEventListener('controllerchange', reload);
                    navigator.serviceWorker.getRegistration().then((registration) => {
                        const worker = registration && (registration.waiting || registration.installing);

                        if (worker) {
                            worker.postMessage('skip-waiting');
                        }
                    }).catch(() => {});
                }

                window.setTimeout(reload, 200);
            }
        </script>
        @unless (request()->routeIs('home'))
        <div id="install-banner" hidden class="border-b border-sky-200 bg-sky-50">
            <div class="mx-auto flex max-w-6xl flex-col gap-3 px-3 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-4">
                <p id="install-text" class="text-base font-medium">{{ __('Установите Family Money на этот телефон или компьютер.') }}</p>
                <div class="flex shrink-0 gap-2">
                    <button type="button" id="install-button" class="rounded-full bg-sky-800 px-4 py-2 text-base font-medium text-white">{{ __('Установить') }}</button>
                    <button type="button" id="install-later" class="rounded-full px-4 py-2 text-base font-medium text-stone-600">{{ __('Позже') }}</button>
                </div>
            </div>
            <p id="install-help" hidden class="mx-auto max-w-6xl px-3 pb-3 text-base text-stone-600 sm:px-4">{{ __('На телефоне откройте меню браузера и выберите «На экран Домой» или «Установить приложение». На компьютере — значок установки в адресной строке или пункт меню «Установить Family Money».') }}</p>
            <div id="install-ios" hidden class="mx-auto max-w-6xl space-y-1 px-3 pb-3 text-base text-stone-700 sm:px-4">
                <p>{{ __('На iPhone это делается в Safari:') }}</p>
                <p>{{ __('1. Нажмите «Поделиться» внизу экрана — квадрат со стрелкой вверх.') }}</p>
                <p>{{ __('2. Выберите «На экран Домой».') }}</p>
                <p>{{ __('3. Нажмите «Добавить».') }}</p>
            </div>
            <p id="install-ios-browser" hidden class="mx-auto max-w-6xl px-3 pb-3 text-base text-stone-700 sm:px-4">{{ __('На iPhone поставить Family Money можно только из Safari. Откройте эту страницу в Safari, нажмите «Поделиться» и выберите «На экран Домой».') }}</p>
        </div>
        @endunless
        <style>
            .fm-top { display: flex; flex-direction: column; align-items: stretch; }
            .fm-top > a { align-self: flex-start; max-width: 100%; }
            .fm-top > a span { white-space: nowrap; }
            .fm-icon-rows {
                display: flex;
                flex-direction: column;
                margin-top: 0.85rem;
                padding-top: 0.85rem;
                border-top: 1px solid rgba(28, 25, 23, 0.18);
                gap: 0;
            }
            .fm-icon-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
            .fm-icon-row + .fm-icon-row {
                margin-top: 0.85rem;
                padding-top: 0.85rem;
                border-top: 1px solid rgba(28, 25, 23, 0.18);
            }
            .fm-icon-row form { display: inline-flex; margin: 0; }
            @media (min-width: 768px) {
                .fm-top { flex-direction: row; align-items: center; justify-content: space-between; gap: 1.5rem; }
                .fm-icon-rows { margin-top: 0; padding-top: 0; border-top: 0; min-width: 17rem; }
                .fm-icon-row { justify-content: flex-end; gap: 0.25rem; }
            }
        </style>
        <header class="border-b border-stone-900/5 bg-paper/90 backdrop-blur">
            <div class="mx-auto max-w-6xl px-3 py-3 sm:px-4 sm:py-4">
                <div class="fm-top">
                <x-brand />
                @auth
                    @unless (request()->routeIs('home'))
                    <div class="fm-icon-rows">
                    <div class="fm-icon-row">
                        <a href="{{ route('objects.index') }}" title="{{ __('Объекты') }}" aria-label="{{ __('Объекты') }}" class="inline-grid place-items-center rounded-full text-tide {{ request()->routeIs('objects.*') ? 'bg-white' : 'hover:bg-white' }}" style="width: 2.75rem; height: 2.75rem">
                            <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M4 10.5 12 4l8 6.5"></path>
                                <path d="M6 9.5V20h12V9.5"></path>
                                <path d="M10 20v-6h4v6"></path>
                            </svg>
                        </a>
                        <a href="{{ route('shopping') }}" title="{{ __('Список покупок') }}" aria-label="{{ __('Список покупок') }}" class="inline-grid place-items-center rounded-full text-orange-700 {{ request()->routeIs('shopping') ? 'bg-white' : 'hover:bg-white' }}" style="width: 2.75rem; height: 2.75rem">
                            <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="9" cy="20" r="1"></circle>
                                <circle cx="17" cy="20" r="1"></circle>
                                <path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L21 8H7"></path>
                            </svg>
                        </a>
                        <a href="{{ route('vault') }}" title="{{ __('Хранение конфиденциальной информации') }}" aria-label="{{ __('Хранение конфиденциальной информации') }}" class="inline-grid place-items-center rounded-full text-emerald-700 {{ request()->routeIs('vault') ? 'bg-white' : 'hover:bg-white' }}" style="width: 2.75rem; height: 2.75rem">
                            <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.78 7.78 5.5 5.5 0 0 1 7.78-7.78zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path>
                            </svg>
                        </a>
                    </div>
                    <div class="fm-icon-row">
                        <a href="{{ route('settings', array_filter(['period' => request('period')])) }}" title="{{ __('Настройки') }}" aria-label="{{ __('Настройки') }}" class="inline-grid place-items-center rounded-full text-sky-800 {{ request()->routeIs('settings') ? 'bg-white' : 'hover:bg-white' }}" style="width: 2.75rem; height: 2.75rem">
                            <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                            </svg>
                        </a>
                        <a href="{{ route('help') }}" title="{{ __('Помощь') }}" aria-label="{{ __('Помощь') }}" class="inline-grid place-items-center rounded-full text-amber-800 {{ request()->routeIs('help') ? 'bg-white' : 'hover:bg-white' }}" style="width: 2.75rem; height: 2.75rem">
                            <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="9"></circle>
                                <path d="M9.5 9a2.5 2.5 0 1 1 4.2 1.8c-.7.5-1.2 1-1.2 1.9"></path>
                                <path d="M12 17h.01"></path>
                            </svg>
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" title="{{ __('Выйти') }}" aria-label="{{ __('Выйти') }}" class="inline-grid place-items-center rounded-full text-rose-700 hover:bg-white" style="width: 2.75rem; height: 2.75rem">
                                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M9 6H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h3"></path>
                                    <path d="M13 16l4-4-4-4"></path>
                                    <path d="M17 12H9"></path>
                                </svg>
                            </button>
                        </form>
                    </div>
                    </div>
                    @endunless
                @endauth
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl overflow-x-clip px-3 py-4 sm:px-4 sm:py-8">
            @isset($slot)
                {{ $slot }}
            @endisset
            @yield('content')
        </main>
        @livewireScripts
        <script>
            (function () {
                const trap = document.createElement('input');
                trap.type = 'text';
                trap.tabIndex = -1;
                trap.autocomplete = 'off';
                trap.setAttribute('aria-hidden', 'true');
                trap.style.cssText = 'position:fixed;left:0;top:0;width:1px;height:1px;opacity:0;pointer-events:none';
                document.body.appendChild(trap);

                document.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-focus-open]');

                    if (!button || button.getAttribute('aria-expanded') === 'true') {
                        return;
                    }

                    const rect = button.getBoundingClientRect();
                    trap.style.top = rect.top + 'px';
                    trap.style.left = rect.left + 'px';
                    trap.focus({ preventScroll: true });
                }, true);

                const focusOpenedFields = (el) => {
                    if (!(el instanceof HTMLElement) || !el.hasAttribute('data-focus-fields')) {
                        return;
                    }

                    const field = el.querySelector('input:not([type="hidden"]), textarea, select');

                    if (!field) {
                        return;
                    }

                    field.focus();

                    if (typeof field.select === 'function' && !['date', 'checkbox', 'radio', 'file'].includes(field.type)) {
                        field.select();
                    }
                };

                const arm = () => {
                    if (!window.Livewire || window.__focusOpenReady) {
                        return;
                    }

                    window.__focusOpenReady = true;
                    Livewire.hook('morph.added', ({ el }) => focusOpenedFields(el));
                };

                document.addEventListener('livewire:init', arm);
                arm();
            })();
        </script>
        @auth
            @if ($familyHousehold ?? null)
            <div id="family-toasts" aria-live="polite" style="position:fixed;right:1rem;bottom:1rem;z-index:60;display:flex;flex-direction:column;gap:0.5rem;max-width:22rem;width:calc(100% - 2rem)"></div>
            <script>
                (function () {
                    const url = document.querySelector('meta[name="family-pulse-url"]').content;
                    const box = document.getElementById('family-toasts');
                    let after = Number(document.querySelector('meta[name="family-after"]').content || 0);
                    let pending = false;
                    let busyUntil = 0;
                    const markBusy = () => { busyUntil = Date.now() + 12000; };
                    document.addEventListener('focusin', (event) => {
                        if (event.target.closest('input, textarea, select')) {
                            markBusy();
                        }
                    });
                    document.addEventListener('input', markBusy);
                    const toast = (text) => {
                        const item = document.createElement('p');
                        item.textContent = text;
                        item.style.cssText = 'margin:0;padding:0.85rem 1rem;border-radius:1rem;background:#fff;color:#1c1917;box-shadow:0 8px 24px rgba(28,25,23,.12);border:1px solid rgba(15,110,107,.35);font-size:1rem;line-height:1.35';
                        box.appendChild(item);
                        window.setTimeout(() => item.remove(), 8000);
                    };
                    const refresh = () => {
                        if (window.Livewire) {
                            Livewire.dispatch('family-pulse');
                        }
                    };
                    const poll = async () => {
                        if (document.hidden) {
                            return;
                        }

                        try {
                            const response = await fetch(url + '?after=' + after, {
                                headers: {Accept: 'application/json'},
                                credentials: 'same-origin',
                            });

                            if (!response.ok) {
                                return;
                            }

                            const data = await response.json();
                            const events = data.events || [];
                            after = data.latest || after;

                            if (events.length === 0) {
                                return;
                            }

                            events.forEach((event) => toast(event.message));

                            if (Date.now() < busyUntil || document.querySelector('dialog[open], .swal2-container')) {
                                pending = true;

                                return;
                            }

                            pending = false;
                            refresh();
                        } catch (error) {}
                    };
                    window.setInterval(() => {
                        if (pending && Date.now() >= busyUntil && !document.querySelector('dialog[open], .swal2-container')) {
                            pending = false;
                            refresh();
                        }

                        poll();
                    }, 3000);
                    document.addEventListener('visibilitychange', () => {
                        if (!document.hidden) {
                            poll();
                        }
                    });
                })();
            </script>
            @endif
        @endauth
        <script src="/pwa.js" defer></script>
    </body>
</html>
