<section class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
    <div class="flex items-center justify-between gap-3">
        <h2 class="text-lg font-medium">{{ __('Новый долг') }}</h2>
        <button type="button" wire:click="closeNew" class="rounded-full px-3 py-2 text-base text-stone-600">{{ __('Закрыть') }}</button>
    </div>
    <p class="mt-2 text-base text-stone-600">{{ __('Личный долг — с человеком. Кредит — банк или лизинг. Выданный личный долг сразу списывается с расчётного счёта, взятый — зачисляется на расчётный счёт без дохода.') }}</p>
    <form wire:submit="saveNew" class="mt-4 space-y-3">
        <div class="grid grid-cols-2 gap-2">
            <button type="button" wire:click="choosePersonal" class="rounded-2xl px-3 py-3 text-base font-medium {{ $newKind === 'personal' ? 'bg-emerald-600 text-white' : 'bg-stone-50 text-stone-700' }}">{{ __('Личный долг') }}</button>
            <button type="button" wire:click="chooseBank" class="rounded-2xl px-3 py-3 text-base font-medium {{ $newKind === 'bank' ? 'bg-sky-800 text-white' : 'bg-stone-50 text-stone-700' }}">{{ __('Кредит') }}</button>
        </div>

        @if ($newKind === 'personal')
            <div class="grid grid-cols-2 gap-2">
                <button type="button" wire:click="chooseLend" class="rounded-2xl px-3 py-3 text-base font-medium {{ $newDirection === 'lend' ? 'bg-emerald-600 text-white' : 'bg-stone-50 text-stone-700' }}">{{ __('Дали в долг') }}</button>
                <button type="button" wire:click="chooseBorrow" class="rounded-2xl px-3 py-3 text-base font-medium {{ $newDirection === 'borrow' ? 'bg-rose-600 text-white' : 'bg-stone-50 text-stone-700' }}">{{ __('Взяли в долг') }}</button>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <button type="button" wire:click="$set('newMode', 'lump')" class="rounded-2xl px-3 py-3 text-base font-medium {{ $newMode === 'lump' ? 'bg-sky-800 text-white' : 'bg-stone-50 text-stone-700' }}">{{ __('Полный возврат') }}</button>
                <button type="button" wire:click="$set('newMode', 'parts')" class="rounded-2xl px-3 py-3 text-base font-medium {{ $newMode === 'parts' ? 'bg-sky-800 text-white' : 'bg-stone-50 text-stone-700' }}">{{ __('По частям') }}</button>
            </div>
        @else
            <div class="grid grid-cols-2 gap-2">
                <button type="button" wire:click="$set('newOrigin', 'new')" class="rounded-2xl px-3 py-3 text-base font-medium {{ $newOrigin === 'new' ? 'bg-sky-800 text-white' : 'bg-stone-50 text-stone-700' }}">{{ __('Новый договор') }}</button>
                <button type="button" wire:click="$set('newOrigin', 'running')" class="rounded-2xl px-3 py-3 text-base font-medium {{ $newOrigin === 'running' ? 'bg-sky-800 text-white' : 'bg-stone-50 text-stone-700' }}">{{ __('Существующий договор') }}</button>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <button type="button" wire:click="$set('newProduct', 'loan')" class="rounded-2xl px-3 py-3 text-base font-medium {{ $newProduct === 'loan' ? 'bg-sky-800 text-white' : 'bg-stone-50 text-stone-700' }}">{{ __('Кредит') }}</button>
                <button type="button" wire:click="$set('newProduct', 'leasing')" class="rounded-2xl px-3 py-3 text-base font-medium {{ $newProduct === 'leasing' ? 'bg-sky-800 text-white' : 'bg-stone-50 text-stone-700' }}">{{ __('Лизинг') }}</button>
            </div>
        @endif

        <label class="block">
            <span class="text-base text-stone-600">{{ $newDirection === 'lend' ? __('Кому') : __('Кто / банк') }}</span>
            <input wire:model="newCounterparty" autocomplete="off" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide {{ $errors->has('newCounterparty') ? 'field-invalid' : '' }}">
        </label>

        @if ($newKind === 'personal' && in_array($newDirection, ['lend', 'borrow'], true))
            <label class="block">
                <span class="text-base text-stone-600">{{ $newDirection === 'lend' ? __('Счёт списания') : __('Счёт зачисления') }}</span>
                <select wire:model="newAccountId" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide {{ $errors->has('newAccountId') ? 'field-invalid' : '' }}">
                    <option value="">{{ __('Выберите расчётный счёт') }}</option>
                    @foreach ($operatingAccounts as $account)
                        <option value="{{ $account->id }}">{{ $account->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <label class="block">
                <span class="text-base text-stone-600">{{ __('Номер договора') }}</span>
                <input wire:model="newContract" autocomplete="off" placeholder="{{ __('необязательно') }}" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide">
            </label>
            <label class="block">
                <span class="text-base text-stone-600">{{ __('Референс') }}</span>
                <input wire:model="newReference" autocomplete="off" placeholder="{{ __('необязательно') }}" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide">
            </label>
        </div>
        <label class="block">
            <span class="text-base text-stone-600">{{ $newKind === 'bank' && $newOrigin === 'running' ? __('Сколько осталось основной суммы') : __('Сумма') }}</span>
            <input wire:model.live="newAmount" data-money inputmode="decimal" autocomplete="off" placeholder="0,00" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide {{ $errors->has('newAmount') ? 'field-invalid' : '' }}">
        </label>
        @if (($newKind === 'personal' && $newMode === 'parts') || ($newKind === 'bank' && $newOrigin === 'new'))
            <label class="block">
                <span class="text-base text-stone-600">{{ __('Срок, месяцев') }}</span>
                <input wire:model.live="newMonths" inputmode="numeric" autocomplete="off" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide {{ $errors->has('newMonths') ? 'field-invalid' : '' }}">
            </label>
        @endif
        <label class="block">
            <span class="text-base text-stone-600">
                @if ($newDirection === 'lend' && $newMode === 'lump')
                    {{ __('Дата полного возврата') }}
                @elseif ($newKind === 'bank' && $newOrigin === 'running')
                    {{ __('Дата следующего платежа') }}
                @elseif ($newKind === 'bank')
                    {{ __('Дата первого платежа') }}
                @elseif ($newDirection === 'borrow' && $newMode === 'lump')
                    {{ __('Дата полного возврата') }}
                @else
                    {{ __('Дата ежемесячного платежа') }}
                @endif
            </span>
            <x-date-input wire:model.live="newDate" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide {{ $errors->has('newDate') ? 'field-invalid' : '' }}" />
        </label>
        @if ($newKind === 'bank' && $newOrigin === 'running')
            <label class="block">
                <span class="text-base text-stone-600">{{ __('Дата окончания из банка') }}</span>
                <x-date-input wire:model.live="newEnd" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide {{ $errors->has('newEnd') ? 'field-invalid' : '' }}" />
            </label>
        @endif
        @if ($newKind === 'bank')
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                <button type="button" wire:click="$set('newInterest', 'none')" class="rounded-2xl px-3 py-3 text-base font-medium {{ $newInterest === 'none' ? 'bg-sky-800 text-white' : 'bg-stone-50 text-stone-700' }}">{{ __('Без процентов') }}</button>
                <button type="button" wire:click="$set('newInterest', 'fixed')" class="rounded-2xl px-3 py-3 text-base font-medium {{ $newInterest === 'fixed' ? 'bg-sky-800 text-white' : 'bg-stone-50 text-stone-700' }}">{{ __('Фиксированная') }}</button>
                <button type="button" wire:click="$set('newInterest', 'mixed')" class="rounded-2xl px-3 py-3 text-base font-medium {{ $newInterest === 'mixed' ? 'bg-sky-800 text-white' : 'bg-stone-50 text-stone-700' }}">{{ __('С EURIBOR') }}</button>
            </div>
            @if ($newInterest !== 'none')
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-base text-stone-600">{{ $newInterest === 'mixed' ? __('Надбавка банка, % годовых') : __('Ставка, % годовых') }}</span>
                        <input wire:model.live="newFixed" inputmode="decimal" autocomplete="off" placeholder="2,38" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide {{ $errors->has('newFixed') ? 'field-invalid' : '' }}">
                    </label>
                    @if ($newInterest === 'mixed')
                        <label class="block">
                            <span class="text-base text-stone-600">{{ $newOrigin === 'running' ? __('EURIBOR из выписки, %') : __('EURIBOR из предложения, %') }}</span>
                            <input wire:model.live="newEuribor" inputmode="decimal" autocomplete="off" placeholder="2,477" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide {{ $errors->has('newEuribor') ? 'field-invalid' : '' }}">
                        </label>
                    @endif
                </div>
            @endif
            @if ($newInterest === 'mixed')
                <div class="grid grid-cols-3 gap-2">
                    <button type="button" wire:click="$set('newTenor', '3')" class="rounded-2xl px-3 py-3 text-base font-medium {{ $newTenor === '3' ? 'bg-sky-800 text-white' : 'bg-stone-50 text-stone-700' }}">{{ __('3 месяца') }}</button>
                    <button type="button" wire:click="$set('newTenor', '6')" class="rounded-2xl px-3 py-3 text-base font-medium {{ $newTenor === '6' ? 'bg-sky-800 text-white' : 'bg-stone-50 text-stone-700' }}">{{ __('6 месяцев') }}</button>
                    <button type="button" wire:click="$set('newTenor', '12')" class="rounded-2xl px-3 py-3 text-base font-medium {{ $newTenor === '12' ? 'bg-sky-800 text-white' : 'bg-stone-50 text-stone-700' }}">{{ __('12 месяцев') }}</button>
                </div>
                <label class="block">
                    <span class="text-base text-stone-600">{{ __('Дата следующей смены ставки') }}</span>
                    <x-date-input wire:model.live="newReset" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide {{ $errors->has('newReset') ? 'field-invalid' : '' }}" />
                </label>
            @endif
            @if ($newProduct === 'leasing')
                <label class="block">
                    <span class="text-base text-stone-600">{{ __('Сколько останется выкупить в конце') }}</span>
                    <input wire:model.live="newResidual" data-money inputmode="decimal" autocomplete="off" placeholder="{{ __('необязательно') }}" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide {{ $errors->has('newResidual') ? 'field-invalid' : '' }}">
                </label>
            @endif
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <label class="block">
                    <span class="text-base text-stone-600">{{ __('Ежемесячная плата') }}</span>
                    <input wire:model.live="newMonthlyFee" data-money inputmode="decimal" autocomplete="off" placeholder="{{ __('необязательно') }}" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide {{ $errors->has('newMonthlyFee') ? 'field-invalid' : '' }}">
                </label>
                <label class="block">
                    <span class="text-base text-stone-600">{{ __('Плата за выдачу') }}</span>
                    <input wire:model.live="newIssuanceFee" data-money inputmode="decimal" autocomplete="off" placeholder="{{ __('необязательно') }}" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide {{ $errors->has('newIssuanceFee') ? 'field-invalid' : '' }}">
                </label>
            </div>
        @endif
        @if ($errors->any())
            <p class="text-base text-rose-700">{{ $errors->first() }}</p>
        @endif
        @if ($preview)
            <p class="text-base text-stone-700">{{ $preview['text'] }}</p>
            @if ($preview['plan'] !== '')
                <p class="text-base text-stone-600">{{ $preview['plan'] }}</p>
            @endif
        @endif
        <button type="submit" class="w-full rounded-2xl bg-emerald-600 px-4 py-3 text-base font-medium text-white sm:w-auto">{{ __('Записать долг') }}</button>
    </form>
</section>
