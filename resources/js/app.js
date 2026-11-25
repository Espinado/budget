import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

document.addEventListener('input', (event) => {
    const field = event.target;

    if (!(field instanceof HTMLInputElement) || !field.hasAttribute('data-money') || field.dataset.moneyNormalizing === '1') {
        return;
    }

    const current = field.value;

    if (!current.includes('.') && !current.startsWith(',')) {
        return;
    }

    const next = current.replaceAll('.', ',').replace(/^,/, '0,');

    if (next === current) {
        return;
    }

    const start = field.selectionStart ?? next.length;
    const end = field.selectionEnd ?? next.length;
    const shift = next.length - current.length;

    field.dataset.moneyNormalizing = '1';
    field.value = next;
    field.setSelectionRange(start + shift, end + shift);
    field.dispatchEvent(new InputEvent('input', { bubbles: true }));
    delete field.dataset.moneyNormalizing;
}, true);

const accordionIdleMs = 60_000;
let accordionIdleTimer = 0;

function closeOpenDetails() {
    document.querySelectorAll('details[open]').forEach((node) => {
        node.open = false;
    });
}

function closeLivewireAccordions() {
    if (!window.Livewire) {
        return;
    }

    Livewire.all().forEach((component) => {
        if (component.name === 'budget.dashboard' || component.name === 'budget.settings' || component.name === 'budget.statistics') {
            component.$wire.closeAccordions();
        }
    });
}

function settleAccordions() {
    const expanded = document.querySelector('[aria-expanded="true"]');
    const repeating = document.querySelector('details.repeating-row[open]');

    closeOpenDetails();

    if (expanded || repeating) {
        closeLivewireAccordions();
    }
}

function armAccordionIdle() {
    window.clearTimeout(accordionIdleTimer);
    accordionIdleTimer = window.setTimeout(settleAccordions, accordionIdleMs);
}

['pointerdown', 'keydown', 'input', 'scroll', 'touchstart'].forEach((eventName) => {
    document.addEventListener(eventName, armAccordionIdle, { capture: true, passive: true });
});

document.addEventListener('toggle', (event) => {
    const details = event.target;

    if (!(details instanceof HTMLDetailsElement) || !details.open) {
        return;
    }

    const multiOpenGroup = details.closest('[data-multi-open]');

    document.querySelectorAll('details[open]').forEach((node) => {
        if (node === details) {
            return;
        }

        if (multiOpenGroup && multiOpenGroup.contains(node)) {
            return;
        }

        node.open = false;
    });
}, true);

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element) || event.target.closest('details')) {
        return;
    }

    const keepServerOpen = document.querySelector('[aria-expanded="true"], details.repeating-row[open]');
    const link = event.target.closest('a[href]');

    closeOpenDetails();

    if (!link || !keepServerOpen) {
        return;
    }

    const url = new URL(link.href, window.location.href);

    if (url.origin === window.location.origin && url.pathname !== window.location.pathname) {
        closeLivewireAccordions();
    }
}, true);

window.addEventListener('pagehide', closeOpenDetails);
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        settleAccordions();
    }
});

armAccordionIdle();

function askWithSweetAlert(message) {
    const text = String(message || 'Подтвердите действие.');
    const destructive = /удал|убрать/i.test(text);

    return Swal.fire({
        title: destructive ? 'Удалить?' : 'Подтвердите',
        text,
        icon: 'warning',
        showCancelButton: true,
        focusCancel: true,
        heightAuto: false,
        buttonsStyling: true,
        confirmButtonText: destructive ? 'Удалить' : 'Да',
        cancelButtonText: 'Отмена',
        confirmButtonColor: destructive ? '#e11d48' : '#0f6e6b',
        cancelButtonColor: '#78716c',
        reverseButtons: true,
    });
}

function bindSweetConfirm(el) {
    if (!(el instanceof HTMLElement) || !el.hasAttribute('wire:confirm')) {
        return;
    }

    const message = el.getAttribute('wire:confirm').replaceAll('\\n', '\n');

    el.__livewire_confirm = (action, instead) => {
        askWithSweetAlert(message).then((result) => {
            if (result.isConfirmed) {
                action();

                return;
            }

            instead();
        });
    };
}

function bindSweetConfirms(root) {
    const scope = root instanceof HTMLElement ? root : document;

    if (scope instanceof HTMLElement) {
        bindSweetConfirm(scope);
    }

    scope.querySelectorAll('[wire\\:confirm]').forEach((el) => bindSweetConfirm(el));
}

window.alert = (message) => {
    Swal.fire({
        text: String(message ?? ''),
        confirmButtonText: 'Хорошо',
        confirmButtonColor: '#0f6e6b',
        heightAuto: false,
    });
};

function installSweetConfirms() {
    bindSweetConfirms(document);

    if (!window.Livewire || window.__familyMoneySweetConfirm) {
        return;
    }

    window.__familyMoneySweetConfirm = true;

    const rebind = (root) => queueMicrotask(() => bindSweetConfirms(root || document));

    Livewire.hook('morphed', () => rebind(document));
    Livewire.hook('morph.added', ({ el }) => rebind(el));
    Livewire.hook('directive.init', ({ el, directive }) => {
        if (directive?.value === 'confirm') {
            rebind(el);
        }
    });
}

if (window.Livewire) {
    installSweetConfirms();
} else {
    document.addEventListener('livewire:initialized', installSweetConfirms);
}

let budgetDayTimer = 0;
let budgetDaySkew = 0;
let budgetDayStamp = '';
let budgetDayMissedMidnight = '';
let budgetDayMisses = 0;
let budgetDayWake = false;
let budgetDayRefreshAt = 0;

function budgetDayRoot() {
    return document.querySelector('[data-budget-day][data-budget-midnight][data-budget-now]');
}

function budgetDayEditing() {
    const active = document.activeElement;

    return Boolean(document.querySelector('dialog[open], .swal2-container'))
        || (active instanceof HTMLElement && Boolean(active.closest('input, textarea, select')));
}

function syncBudgetDayClock(root) {
    if (root.dataset.budgetNow === budgetDayStamp) {
        return;
    }

    budgetDayStamp = root.dataset.budgetNow;
    budgetDaySkew = Number(budgetDayStamp) * 1000 - Date.now();
}

function refreshBudgetDashboard() {
    const root = budgetDayRoot();

    if (!root) {
        return;
    }

    if (!window.Livewire) {
        window.location.reload();

        return;
    }

    if (budgetDayEditing()) {
        window.clearTimeout(budgetDayTimer);
        budgetDayTimer = window.setTimeout(refreshBudgetDashboard, 60_000);

        return;
    }

    const components = Livewire.all().filter((component) => component.name === 'budget.dashboard');

    if (components.length === 0) {
        return;
    }

    if (Date.now() - budgetDayRefreshAt < 60_000) {
        window.clearTimeout(budgetDayTimer);
        budgetDayTimer = window.setTimeout(armBudgetDay, 60_000);

        return;
    }

    budgetDayRefreshAt = Date.now();
    components.forEach((component) => component.$wire.$refresh());
}

function armBudgetDay() {
    window.clearTimeout(budgetDayTimer);

    const root = budgetDayRoot();

    if (!root) {
        return;
    }

    syncBudgetDayClock(root);

    const midnight = Number(root.dataset.budgetMidnight) * 1000;
    const wait = midnight - (Date.now() + budgetDaySkew) + 1500;

    if (!Number.isFinite(wait)) {
        return;
    }

    if (wait <= 0) {
        if (budgetDayMissedMidnight !== root.dataset.budgetMidnight) {
            budgetDayMissedMidnight = root.dataset.budgetMidnight;
            budgetDayMisses = 0;
        }

        if (budgetDayMisses >= 3 && !budgetDayWake) {
            return;
        }

        budgetDayWake = false;
        budgetDayMisses += 1;
        budgetDayTimer = window.setTimeout(refreshBudgetDashboard, budgetDayMisses === 1 ? 0 : 60_000);

        return;
    }

    budgetDayMisses = 0;
    budgetDayMissedMidnight = '';
    budgetDayTimer = window.setTimeout(refreshBudgetDashboard, Math.min(wait, 2_147_483_647));
}

function installBudgetDayRefresh() {
    if (!window.Livewire || window.__familyMoneyBudgetDay) {
        return;
    }

    window.__familyMoneyBudgetDay = true;
    armBudgetDay();
    Livewire.hook('morphed', () => armBudgetDay());
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            return;
        }

        budgetDayWake = true;
        armBudgetDay();
    });
    window.addEventListener('pageshow', () => armBudgetDay());
}

if (window.Livewire) {
    installBudgetDayRefresh();
} else {
    document.addEventListener('livewire:initialized', installBudgetDayRefresh);
}

const receiptViews = new WeakMap();
const receiptPointerStage = new Map();
const receiptPointerPoint = new Map();

function receiptView(stage) {
    let view = receiptViews.get(stage);

    if (!view) {
        view = { scale: 1, x: 0, y: 0, pinch: null, pan: null };
        receiptViews.set(stage, view);
    }

    return view;
}

function paintReceipt(stage) {
    const image = stage.querySelector('img');
    const view = receiptView(stage);

    if (!(image instanceof HTMLImageElement)) {
        return;
    }

    image.style.transform = `translate(${view.x}px, ${view.y}px) scale(${view.scale})`;
}

function clampReceipt(stage) {
    const view = receiptView(stage);
    const image = stage.querySelector('img');

    view.scale = Math.min(4, Math.max(1, view.scale));

    if (!(image instanceof HTMLImageElement) || view.scale === 1) {
        view.scale = 1;
        view.x = 0;
        view.y = 0;

        return;
    }

    const maxX = Math.max(0, (image.offsetWidth * view.scale - stage.clientWidth) / 2);
    const maxY = Math.max(0, (image.offsetHeight * view.scale - stage.clientHeight) / 2);

    view.x = Math.min(maxX, Math.max(-maxX, view.x));
    view.y = Math.min(maxY, Math.max(-maxY, view.y));
}

function pointsOnReceipt(stage) {
    const points = [];

    receiptPointerStage.forEach((owner, id) => {
        if (owner !== stage) {
            return;
        }

        const point = receiptPointerPoint.get(id);

        if (point) {
            points.push(point);
        }
    });

    return points;
}

function moveReceipt(stage) {
    const view = receiptView(stage);
    const points = pointsOnReceipt(stage);

    if (points.length >= 2) {
        const distance = Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y);

        if (!view.pinch) {
            view.pinch = { dist: Math.max(distance, 1), scale: view.scale };
        } else if (view.pinch.dist > 0) {
            view.scale = view.pinch.scale * (distance / view.pinch.dist);
        }

        view.pan = null;
    } else {
        view.pinch = null;

        if (points.length === 1 && view.scale > 1) {
            if (!view.pan) {
                view.pan = { x: view.x, y: view.y, px: points[0].x, py: points[0].y };
            }

            view.x = view.pan.x + (points[0].x - view.pan.px);
            view.y = view.pan.y + (points[0].y - view.pan.py);
        } else {
            view.pan = null;
        }
    }

    clampReceipt(stage);
    paintReceipt(stage);
}

function releaseReceiptPointer(pointerId) {
    const stage = receiptPointerStage.get(pointerId);

    receiptPointerStage.delete(pointerId);
    receiptPointerPoint.delete(pointerId);

    if (!(stage instanceof HTMLElement)) {
        return;
    }

    const view = receiptView(stage);

    view.pinch = null;
    view.pan = null;
    stage.classList.remove('is-panning');
    clampReceipt(stage);
    paintReceipt(stage);
}

function resetReceipt(dialog) {
    const stage = dialog.querySelector('.receipt-stage');

    if (!(stage instanceof HTMLElement)) {
        return;
    }

    const view = receiptView(stage);

    view.scale = 1;
    view.x = 0;
    view.y = 0;
    view.pinch = null;
    view.pan = null;
    stage.classList.remove('is-panning');
    paintReceipt(stage);
}

document.addEventListener('pointerdown', (event) => {
    const stage = event.target instanceof Element ? event.target.closest('.receipt-stage') : null;

    if (!(stage instanceof HTMLElement) || !stage.closest('dialog[open]')) {
        return;
    }

    if (typeof stage.setPointerCapture === 'function') {
        try {
            stage.setPointerCapture(event.pointerId);
        } catch (error) {
            // The pointer can end before the browser accepts capture.
        }
    }

    receiptPointerStage.set(event.pointerId, stage);
    receiptPointerPoint.set(event.pointerId, { x: event.clientX, y: event.clientY });
    stage.classList.add('is-panning');
    moveReceipt(stage);
    event.preventDefault();
}, { capture: true, passive: false });

document.addEventListener('pointermove', (event) => {
    const stage = receiptPointerStage.get(event.pointerId);

    if (!(stage instanceof HTMLElement)) {
        return;
    }

    receiptPointerPoint.set(event.pointerId, { x: event.clientX, y: event.clientY });
    moveReceipt(stage);
    event.preventDefault();
}, { capture: true, passive: false });

document.addEventListener('pointerup', (event) => releaseReceiptPointer(event.pointerId), true);
document.addEventListener('pointercancel', (event) => releaseReceiptPointer(event.pointerId), true);

document.addEventListener('click', (event) => {
    const button = event.target instanceof Element ? event.target.closest('[data-receipt-zoom]') : null;

    if (!(button instanceof HTMLButtonElement)) {
        return;
    }

    const stage = button.closest('.receipt-dialog')?.querySelector('.receipt-stage');

    if (!(stage instanceof HTMLElement)) {
        return;
    }

    event.preventDefault();
    const view = receiptView(stage);

    view.scale *= button.dataset.receiptZoom === 'in' ? 1.5 : 1 / 1.5;
    view.pinch = null;
    view.pan = null;
    clampReceipt(stage);
    paintReceipt(stage);
});

document.addEventListener('wheel', (event) => {
    const stage = event.target instanceof Element ? event.target.closest('.receipt-stage') : null;

    if (!(stage instanceof HTMLElement) || !stage.closest('dialog[open]')) {
        return;
    }

    event.preventDefault();
    const view = receiptView(stage);

    view.scale *= event.deltaY < 0 ? 1.15 : 1 / 1.15;
    clampReceipt(stage);
    paintReceipt(stage);
}, { passive: false });

document.addEventListener('close', (event) => {
    if (event.target instanceof HTMLDialogElement && event.target.classList.contains('receipt-dialog')) {
        resetReceipt(event.target);
    }
}, true);
