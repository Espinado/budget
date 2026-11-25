<div wire:ignore class="rounded-2xl bg-stone-50 p-3">
    <h3 class="text-lg font-medium">{{ __('Кредитный калькулятор') }}</h3>
    <p class="mt-1 text-base text-stone-600">{{ __('Сумма, срок и ставка. EURIBOR прибавляется к фиксированной, поле можно оставить пустым. Цифры меняются сразу при вводе.') }}</p>
    <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
        <label class="block">
            <span class="text-base text-stone-600">{{ __('Сумма долга') }}</span>
            <input data-calc="principal" inputmode="decimal" autocomplete="off" placeholder="0,00" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide">
        </label>
        <label class="block">
            <span class="text-base text-stone-600">{{ __('Срок, месяцев') }}</span>
            <input data-calc="months" inputmode="numeric" autocomplete="off" placeholder="12" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide">
        </label>
        <label class="block">
            <span class="text-base text-stone-600">{{ __('Надбавка банка или ставка, % годовых') }}</span>
            <input data-calc="fixed" inputmode="decimal" autocomplete="off" placeholder="0" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide">
        </label>
        <label class="block">
            <span class="text-base text-stone-600">{{ __('EURIBOR, % годовых, из договора') }}</span>
            <input data-calc="euribor" inputmode="decimal" autocomplete="off" placeholder="{{ __('необязательно') }}" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide">
        </label>
    </div>
    <dl class="mt-4 space-y-1.5 text-lg" aria-live="polite">
        <div data-row="single" class="flex items-start justify-between gap-3">
            <dt>{{ __('Месячный платёж') }}</dt>
            <dd data-out="payment" class="shrink-0 font-semibold tabular-nums">—</dd>
        </div>
        <div data-row="last" class="flex items-start justify-between gap-3" style="display: none">
            <dt>{{ __('Последний платёж') }}</dt>
            <dd data-out="last" class="shrink-0 font-semibold tabular-nums">—</dd>
        </div>
        <div class="flex items-start justify-between gap-3">
            <dt>{{ __('Всего к выплате') }}</dt>
            <dd data-out="total" class="shrink-0 font-semibold tabular-nums">—</dd>
        </div>
        <div class="flex items-start justify-between gap-3">
            <dt>{{ __('Переплата') }}</dt>
            <dd data-out="over" class="shrink-0 font-semibold tabular-nums">—</dd>
        </div>
    </dl>
    <p data-out="note" class="mt-2 text-base text-stone-600"></p>
<script>
(function () {
    var root = document.currentScript.parentElement;
    if (!root) return;

    function field(name) {
        return root.querySelector('[data-calc="' + name + '"]');
    }

    function parseCents(raw) {
        var value = String(raw || '').trim().replace(/[\s\u00A0]/g, '').replace(/\./g, ',');
        if (value === '') return null;
        if (value.charAt(0) === ',') value = '0' + value;
        value = value.replace(',', '.');
        if (!/^\d+(\.\d{1,2})?$/.test(value)) return null;
        var parts = value.split('.');
        var fraction = ((parts[1] || '') + '00').slice(0, 2);
        return (Number(parts[0]) * 100) + Number(fraction);
    }

    function parseMilli(raw) {
        var value = String(raw || '').trim().replace(/[\s\u00A0]/g, '').replace(/\./g, ',');
        if (value === '') return null;
        if (value.charAt(0) === ',') value = '0' + value;
        value = value.replace(',', '.');
        if (!/^\d+(\.\d{1,3})?$/.test(value)) return null;
        var parts = value.split('.');
        var fraction = ((parts[1] || '') + '000').slice(0, 3);
        return (Number(parts[0]) * 1000) + Number(fraction);
    }

    function parseMonths(raw) {
        var value = String(raw || '').trim();
        if (!/^\d+$/.test(value)) return null;
        var months = Number(value);
        if (months < 1 || months > 600) return null;
        return months;
    }

    function annuity(balance, months, annualMilli) {
        if (balance <= 0 || months <= 0) return 0;
        if (annualMilli <= 0) return Math.max(1, Math.round(balance / months));
        var monthly = annualMilli / 1200000;
        return Math.max(1, Math.round(balance * monthly / (1 - Math.pow(1 + monthly, -months))));
    }

    function interest(balance, annualMilli) {
        if (balance <= 0 || annualMilli <= 0) return 0;
        return Math.round(balance * annualMilli / 1200000);
    }

    function formatCents(cents) {
        var sign = cents < 0 ? '−' : '';
        var absolute = Math.abs(cents);
        var whole = Math.floor(absolute / 100);
        var fraction = String(absolute % 100).padStart(2, '0');
        return sign + String(whole).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ',' + fraction + ' €';
    }

    function formatPercent(milli) {
        var whole = Math.floor(milli / 1000);
        var fraction = String(milli % 1000).padStart(3, '0').replace(/0+$/, '');
        if (fraction.length === 1) fraction += '0';
        return whole + (fraction ? ',' + fraction : '') + '%';
    }

    function show(row, visible) {
        row.style.display = visible ? '' : 'none';
    }

    function quote() {
        var principal = parseCents(field('principal').value);
        var months = parseMonths(field('months').value);
        var fixedRaw = field('fixed').value;
        var euriborRaw = field('euribor').value;
        var fixed = parseMilli(fixedRaw);
        var euribor = parseMilli(euriborRaw);
        var payment = root.querySelector('[data-out="payment"]');
        var last = root.querySelector('[data-out="last"]');
        var total = root.querySelector('[data-out="total"]');
        var over = root.querySelector('[data-out="over"]');
        var note = root.querySelector('[data-out="note"]');

        function blank() {
            payment.textContent = '—';
            last.textContent = '—';
            total.textContent = '—';
            over.textContent = '—';
            note.textContent = '';
            show(root.querySelector('[data-row="last"]'), false);
        }

        if (principal === null || months === null || (fixedRaw.trim() !== '' && fixed === null) || (euriborRaw.trim() !== '' && euribor === null)) {
            blank();
            return;
        }

        fixed = fixed || 0;
        euribor = euribor || 0;
        var annual = fixed + euribor;
        var regular = annuity(principal, months, annual);
        var balance = principal;
        var sum = 0;
        var lastDue = 0;

        for (var month = 1; month <= months && balance > 0; month++) {
            var monthInterest = interest(balance, annual);
            var closes = month === months || regular >= balance + monthInterest;
            var due;
            if (closes) {
                due = balance + monthInterest;
                balance = 0;
            } else if (regular <= monthInterest) {
                due = monthInterest;
            } else {
                due = regular;
                balance -= regular - monthInterest;
            }
            sum += due;
            lastDue = due;
        }

        show(root.querySelector('[data-row="last"]'), lastDue !== regular && lastDue !== 0);
        payment.textContent = formatCents(regular);
        last.textContent = formatCents(lastDue);
        total.textContent = formatCents(sum);
        over.textContent = formatCents(sum - principal);
        note.textContent = euribor > 0
            ? @json(__('Ставка')) + ' ' + formatPercent(fixed) + ' + EURIBOR ' + formatPercent(euribor) + ' = ' + formatPercent(annual) + ' ' + @json(__('годовых.'))
            : @json(__('Ставка')) + ' ' + formatPercent(annual) + ' ' + @json(__('годовых.'));
    }

    root.querySelectorAll('[data-calc]').forEach(function (input) {
        input.addEventListener('input', quote);
    });
})();
</script>
</div>
