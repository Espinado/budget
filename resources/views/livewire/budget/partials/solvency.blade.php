<section class="flex shrink-0 items-center gap-1 rounded-2xl px-3 py-2 text-base font-medium leading-tight text-white {{ $solvency['passed'] ? 'bg-emerald-600' : 'bg-rose-600' }}" style="width: max-content; max-width: 100%;">
    <p class="whitespace-nowrap leading-tight">{{ __('Платежеспособность') }}: <span class="font-semibold">{{ $solvency['passed'] ? __('да') : __('нет') }}</span></p>
    <details class="solvency-tip">
        <summary aria-label="{{ __('Пояснение оценки платежеспособности') }}">i</summary>
        <p class="solvency-bubble" role="tooltip">
            @php
                $ratioTenths = $solvency['ratio_tenths'] ?? null;

                if ($ratioTenths === null) {
                    $solvencyTip = __('Пока нет месяцев с полученным доходом, поэтому долю посчитать нельзя.');
                } else {
                    $ratioTenths = (int) $ratioTenths;
                    $whole = intdiv($ratioTenths, 10);
                    $tenth = $ratioTenths % 10;
                    $percent = $tenth === 0 ? $whole.' %' : $whole.','.$tenth.' %';
                    $months = (int) ($solvency['income_months'] ?? $solvency['months'] ?? 0);
                    $solvencyTip = __('Регулярные взносы по банковским кредитам составляют :percent от среднего дохода за :months мес.', [
                        'percent' => $percent,
                        'months' => $months,
                    ]);
                    $currentIncome = (int) ($solvency['current_income'] ?? 0);
                    $average = (int) ($solvency['income'] ?? 0);

                    if ($currentIncome > 0) {
                        $solvencyTip .= ' '.__('В этом месяце получено :current, среднее :average.', [
                            'current' => money($currentIncome),
                            'average' => money($average),
                        ]);
                    } else {
                        $solvencyTip .= ' '.__('В этом месяце доход ещё не пришёл. Среднее — :average.', [
                            'average' => money($average),
                        ]);
                    }

                    $dtiTenths = $solvency['dti_tenths'] ?? null;

                    if ($dtiTenths !== null) {
                        $solvencyTip .= ' '.__('Остаток долга — :dti годовых дохода из 6.', [
                            'dti' => intdiv((int) $dtiTenths, 10).','.((int) $dtiTenths % 10),
                        ]);
                    }

                    $room = $solvency['room_cents'] ?? null;

                    if ($room !== null) {
                        $solvencyTip .= ' '.($room > 0
                            ? __('Под новый платёж остаётся :room.', ['room' => money((int) $room)])
                            : __('Под новый платёж запаса нет.'));
                    }

                    $stressedRoom = $solvency['stressed_room_cents'] ?? null;

                    if ($stressedRoom !== null) {
                        $solvencyTip .= ' '.($stressedRoom > 0
                            ? __('Если ставка с EURIBOR вырастет на 2 пункта, под новый платёж останется :room.', ['room' => money((int) $stressedRoom)])
                            : __('Если ставка с EURIBOR вырастет на 2 пункта, запаса под новый платёж не останется.'));
                    }
                }

                if (! empty($solvency['preliminary'])) {
                    $solvencyTip .= ' '.__('Предварительно').': '.__('месяцев с доходом ещё меньше шести.');
                }
            @endphp
            <span>{{ $solvencyTip }}</span>
        </p>
    </details>
</section>

<style>
    .solvency-tip {
        position: relative;
        flex: none;
    }

    .solvency-tip summary {
        display: inline-grid;
        width: 1.25rem;
        height: 1.25rem;
        place-items: center;
        border: 1px solid rgba(255, 255, 255, 0.85);
        border-radius: 999px;
        color: #fff;
        font-size: 0.85rem;
        font-weight: 600;
        line-height: 1;
        list-style: none;
        cursor: pointer;
    }

    .solvency-tip summary::-webkit-details-marker {
        display: none;
    }

    .solvency-bubble {
        display: none;
        position: absolute;
        right: 0;
        top: 100%;
        z-index: 30;
        width: 22rem;
        max-width: calc(100vw - 1.5rem);
        padding-top: 0.4rem;
        text-align: left;
        box-sizing: border-box;
    }

    .solvency-bubble span {
        display: block;
        max-width: 100%;
        padding: 0.75rem;
        border-radius: 1rem;
        background: #fff;
        color: #44403c;
        font-size: 1rem;
        font-weight: 400;
        line-height: 1.35;
        white-space: normal;
        overflow-wrap: break-word;
        box-sizing: border-box;
        box-shadow: 0 8px 24px rgba(28, 25, 23, 0.16);
    }

    .solvency-tip[open] .solvency-bubble,
    .solvency-tip:hover .solvency-bubble,
    .solvency-tip:focus-within .solvency-bubble {
        display: block;
    }

    @media (max-width: 40rem) {
        .solvency-bar {
            position: relative;
            z-index: 30;
        }

        .solvency-tip {
            position: static;
        }

        .solvency-bubble {
            left: 0;
            right: 0;
            width: auto;
            max-width: 100%;
        }
    }
</style>
