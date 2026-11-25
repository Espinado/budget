const UPDATE_EVERY_MS = 60 * 1000;
const loadedVersion = document.querySelector('meta[name="app-version"]')?.content || '';

(function stripIosReloadMark() {
    const url = new URL(window.location.href);

    if (!url.searchParams.has('_fm')) {
        return;
    }

    url.searchParams.delete('_fm');
    const next = url.pathname + url.search + url.hash;
    window.history.replaceState(window.history.state, '', next);
})();

function familyMoneyIos() {
    const agent = window.navigator.userAgent || '';

    return /iPad|iPhone|iPod/.test(agent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
}

function familyMoneyIosSafari() {
    const agent = window.navigator.userAgent || '';

    return familyMoneyIos()
        && /Safari/i.test(agent)
        && !/CriOS|FxiOS|EdgiOS|OPiOS|DuckDuckGo|YaBrowser/i.test(agent);
}

function showUpdateBanner() {
    const banner = document.getElementById('update-banner');

    if (!banner || !banner.hasAttribute('hidden')) {
        return;
    }

    banner.removeAttribute('hidden');
    banner.style.position = 'fixed';
    banner.style.top = '0';
    banner.style.left = '0';
    banner.style.right = '0';
    banner.style.zIndex = '60';
    window.requestAnimationFrame(() => {
        document.body.style.paddingTop = banner.offsetHeight + 'px';
    });
}

async function checkAppVersion() {
    if (loadedVersion === '') {
        return;
    }

    try {
        const response = await fetch('/app-version?t=' + Date.now(), {
            cache: 'no-store',
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();

        if (data.version && data.version !== loadedVersion) {
            showUpdateBanner();
        }
    } catch (error) {
        // The next check tries again.
    }
}

function familyMoneyInstalled() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.matchMedia('(display-mode: fullscreen)').matches
        || window.matchMedia('(display-mode: minimal-ui)').matches
        || window.matchMedia('(display-mode: window-controls-overlay)').matches
        || window.navigator.standalone === true;
}

let deferredInstall = null;

function showInstallOffer() {
    if (document.body?.dataset.page === 'home' || familyMoneyInstalled()) {
        return;
    }

    if (sessionStorage.getItem('family-money-install-hidden') === '1') {
        return;
    }

    document.getElementById('install-banner')?.removeAttribute('hidden');

    if (!familyMoneyIos()) {
        return;
    }

    const text = document.getElementById('install-text');

    if (text) {
        text.textContent = 'Установите Family Money на этот iPhone.';
    }

    document.getElementById('install-button')?.setAttribute('hidden', '');
    document.getElementById(familyMoneyIosSafari() ? 'install-ios' : 'install-ios-browser')?.removeAttribute('hidden');
}

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstall = event;
    showInstallOffer();
});

window.addEventListener('appinstalled', () => {
    deferredInstall = null;
    document.getElementById('install-banner')?.setAttribute('hidden', '');
});

async function runFamilyMoneyInstall() {
    if (familyMoneyIos()) {
        document.getElementById(familyMoneyIosSafari() ? 'install-ios' : 'install-ios-browser')?.removeAttribute('hidden');

        return;
    }

    if (!deferredInstall) {
        document.getElementById('install-help')?.removeAttribute('hidden');

        return;
    }

    deferredInstall.prompt();
    await deferredInstall.userChoice;
    deferredInstall = null;
}

document.getElementById('install-button')?.addEventListener('click', runFamilyMoneyInstall);
document.querySelectorAll('[data-install]').forEach((button) => {
    button.addEventListener('click', runFamilyMoneyInstall);
});

document.getElementById('install-later')?.addEventListener('click', () => {
    sessionStorage.setItem('family-money-install-hidden', '1');
    document.getElementById('install-banner')?.setAttribute('hidden', '');
});

document.getElementById('update-button')?.addEventListener('click', (event) => {
    const button = event.currentTarget;

    if (typeof familyMoneyUpdate === 'function') {
        familyMoneyUpdate(button);

        return;
    }

    button.disabled = true;
    button.textContent = 'Обновляем…';
    window.setTimeout(() => window.location.reload(), 200);
});

function watchForUpdates() {
    checkAppVersion();

    if (!('serviceWorker' in navigator)) {
        return;
    }

    navigator.serviceWorker.getRegistration().then((registration) => {
        if (!registration) {
            return;
        }

        if (registration.waiting && navigator.serviceWorker.controller) {
            showUpdateBanner();
        }

        registration.update().catch(() => {});
    }).catch(() => {});
}

watchForUpdates();
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        watchForUpdates();
    }
});
window.addEventListener('pageshow', watchForUpdates);
window.setInterval(watchForUpdates, UPDATE_EVERY_MS);

if ('serviceWorker' in navigator) {
    window.addEventListener('load', async () => {
        try {
            const registration = await navigator.serviceWorker.register('/sw.js', { updateViaCache: 'none' });

            const offerUpdate = () => {
                if (registration.waiting && navigator.serviceWorker.controller) {
                    showUpdateBanner();
                }
            };

            const watch = (worker) => {
                worker?.addEventListener('statechange', offerUpdate);
            };

            watch(registration.installing);
            registration.addEventListener('updatefound', () => watch(registration.installing));
            offerUpdate();
        } catch (error) {
            // The version check above still runs without a service worker.
        }
    });
}

showInstallOffer();
