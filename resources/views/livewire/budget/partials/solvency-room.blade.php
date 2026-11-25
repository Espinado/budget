@if (($solvency['income'] ?? null) !== null && ($solvency['room_cents'] ?? null) !== null)
    <section id="solvency-room" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5"
        data-income="{{ (int) $solvency['income'] }}"
        data-obligations="{{ (int) $solvency['obligations'] }}"
        data-living="{{ (int) $solvency['living'] }}"
        data-debt="{{ (int) $solvency['debt_cents'] }}">
        <h2 class="text-lg font-medium">{{ __('Запас под новый кредит') }}</h2>
        <p class="mt-1 text-base text-stone-600">{{ __('Месячный платёж из калькулятора сравнивается с запасом. Договор, который новый кредит закроет, из платежей и остатка убирается.') }}</p>
        @if (($solvency['replaceable'] ?? []) !== [])
            <label class="mt-3 block text-base">
                <span class="text-stone-600">{{ __('Этот кредит закроет') }}</span>
                <select class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3" data-solvency-replace>
                    <option value="" data-payment="0" data-principal="0">{{ __('Ни один текущий договор') }}</option>
                    @foreach ($solvency['replaceable'] as $debt)
                        <option value="{{ $debt['id'] }}" data-payment="{{ $debt['payment_cents'] }}" data-principal="{{ $debt['principal_cents'] }}">{{ $debt['name'] }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <p class="mt-3 text-lg" data-solvency-verdict></p>
    </section>
    <script>
        (function () {
            var box = document.getElementById('solvency-room');
            if (!box) return;
            var verdict = box.querySelector('[data-solvency-verdict]');
            var fitsText = @json(__('Новый платёж помещается.'));
            var overText = @json(__('Новый платёж больше запаса :room.'));
            var debtText = @json(__('Вместе с оставшимся долгом это :dti годовых дохода из 6.'));
            var roomText = @json(__('Под новый платёж остаётся :room.'));
            var noneText = @json(__('Под новый платёж запаса нет.'));

            function money(cents) {
                var sign = cents < 0 ? '−' : '';
                var absolute = Math.abs(cents);
                var whole = Math.floor(absolute / 100);
                var fraction = String(absolute % 100).padStart(2, '0');
                return sign + String(whole).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ',' + fraction + ' €';
            }

            function dtiLabel(tenths) {
                var whole = Math.floor(Math.abs(tenths) / 10);
                return whole + ',' + String(Math.abs(tenths) % 10);
            }

            function parseCents(raw) {
                var value = String(raw || '').trim().replace(/[\s\u00A0]/g, '').replace(/\./g, ',');
                if (value === '') return null;
                if (value.charAt(0) === ',') value = '0' + value;
                value = value.replace(',', '.');
                if (!/^\d+(\.\d{1,2})?$/.test(value)) return null;
                var parts = value.split('.');
                return (Number(parts[0]) * 100) + Number(((parts[1] || '') + '00').slice(0, 2));
            }

            function annuity(balance, months, annualMilli) {
                if (balance <= 0 || months <= 0) return 0;
                if (annualMilli <= 0) return Math.max(1, Math.round(balance / months));
                var monthly = annualMilli / 1200000;
                return Math.max(1, Math.round(balance * monthly / (1 - Math.pow(1 + monthly, -months))));
            }

            function readLoan() {
                var principal = document.querySelector('[data-calc="principal"]');
                var months = document.querySelector('[data-calc="months"]');
                var fixed = document.querySelector('[data-calc="fixed"]');
                var euribor = document.querySelector('[data-calc="euribor"]');
                if (!principal || !months) return null;
                var amount = parseCents(principal.value);
                var term = /^\d+$/.test(String(months.value || '').trim()) ? Number(months.value) : null;
                if (amount === null || term === null || term < 1) return null;
                var fixedMilli = parseMilli(fixed ? fixed.value : '');
                var euriborMilli = parseMilli(euribor ? euribor.value : '');
                return { amount: amount, payment: annuity(amount, term, (fixedMilli || 0) + (euriborMilli || 0)) };
            }

            function parseMilli(raw) {
                var value = String(raw || '').trim().replace(/[\s\u00A0]/g, '').replace(/\./g, ',');
                if (value === '') return 0;
                if (value.charAt(0) === ',') value = '0' + value;
                value = value.replace(',', '.');
                if (!/^\d+(\.\d{1,3})?$/.test(value)) return null;
                var parts = value.split('.');
                return (Number(parts[0]) * 1000) + Number(((parts[1] || '') + '000').slice(0, 3));
            }

            function show() {
                box = document.getElementById('solvency-room');
                verdict = box ? box.querySelector('[data-solvency-verdict]') : null;
                if (!box || !verdict) return;
                var income = Number(box.dataset.income);
                var obligations = Number(box.dataset.obligations);
                var living = Number(box.dataset.living);
                var debt = Number(box.dataset.debt);
                var chosen = box.querySelector('[data-solvency-replace]');
                var option = chosen ? chosen.selectedOptions[0] : null;
                var dropPayment = option ? Number(option.dataset.payment || 0) : 0;
                var dropPrincipal = option ? Number(option.dataset.principal || 0) : 0;
                var payments = obligations - dropPayment;
                var ratioRoom = Math.floor((income * 40 - payments * 100) / 100);
                var room = Math.min(ratioRoom, income - payments - living);
                var loan = readLoan();
                var parts = [room > 0 ? roomText.replace(':room', money(room)) : noneText];

                if (loan && loan.payment > 0) {
                    var nextDebt = debt - dropPrincipal + loan.amount;
                    var tenths = Math.round(nextDebt * 10 / (income * 12));
                    parts = [loan.payment <= room ? fitsText : overText.replace(':room', money(Math.max(0, room)))];
                    parts.push(debtText.replace(':dti', dtiLabel(tenths)));
                }

                verdict.textContent = parts.join(' ');
            }

            window.solvencyRoomShow = show;
            if (!window.solvencyRoomBound) {
                window.solvencyRoomBound = true;
                document.addEventListener('input', function (event) {
                    if (event.target.closest && event.target.closest('[data-calc]') && window.solvencyRoomShow) window.solvencyRoomShow();
                });
                document.addEventListener('change', function (event) {
                    if (event.target.matches && event.target.matches('[data-solvency-replace]') && window.solvencyRoomShow) window.solvencyRoomShow();
                });
            }
            show();
        })();
    </script>
@endif
