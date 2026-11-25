@php
    $dealPad = $dealPad ?? null;
    $dealShift = $dealShift ?? null;
    $dealClass = $dealClass ?? '';
@endphp
@if (($forceShow ?? false) || ($dealsLineId === $lineId && $transactions->isNotEmpty()))
    @foreach ($transactions as $transaction)
        @if ($editingTransactionId === $transaction->id)
            <tr class="budget-deals {{ $dealClass }}" wire:key="deal-edit-{{ $transaction->id }}" @if ($dealShift) style="margin-left: {{ $dealShift }}" @endif>
                <td colspan="6" class="px-3 py-3" @if ($dealPad) style="padding-left: {{ $dealPad }}" @endif>
                            <form wire:submit="saveEditedTransaction" class="grid grid-cols-1 gap-2 rounded-2xl bg-sky-50 p-3 sm:flex sm:flex-wrap sm:items-end">
                                <label class="block w-28">
                                    <span class="text-base text-stone-500">{{ __('Сумма, €') }}</span>
                                    <input wire:model="editAmount" data-money inputmode="decimal" class="mt-1 w-full rounded-xl border border-stone-200 px-2 py-3 text-lg outline-none focus:border-sky-500 {{ $errors->has('editAmount') ? 'field-invalid' : '' }}">
                                </label>
                                <label class="block">
                                    <span class="text-base text-stone-500">{{ __('Дата') }}</span>
                                    <input type="date" wire:model="editDate" min="{{ $factFrom }}" max="{{ $factTo }}" class="mt-1 w-full min-w-0 rounded-xl border border-stone-200 px-2 py-3 text-lg outline-none focus:border-sky-500 sm:w-auto {{ $errors->has('editDate') ? 'field-invalid' : '' }}">
                                </label>
                                <div class="block min-w-36">
                                    <span class="text-base text-stone-500">{{ __('Категория') }}</span>
                                    @include('livewire.budget.partials.category-picker', [
                                        'property' => 'editCategoryId',
                                        'value' => $editCategoryId,
                                        'categories' => $categories->where('type.value', $editType),
                                        'placeholder' => null,
                                    ])
                                </div>
                                <label class="block min-w-36">
                                    <span class="text-base text-stone-500">{{ __('Счёт') }}</span>
                                    <select wire:model="editAccountId" class="mt-1 w-full rounded-xl border border-stone-200 px-2 py-3 text-lg outline-none focus:border-sky-500 {{ $errors->has('editAccountId') ? 'field-invalid' : '' }}">
                                        <option value="">{{ __('Выберите счёт') }}</option>
                                        @foreach ($accounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block min-w-40 flex-1">
                                    <span class="text-base text-stone-500">{{ __('Примечание') }}</span>
                                    <input wire:model="editNote" list="note-suggestions-{{ $transaction->type->value }}" autocomplete="off" maxlength="200" class="mt-1 w-full rounded-xl border border-stone-200 px-2 py-3 text-lg outline-none focus:border-sky-500 {{ $errors->has('editNote') ? 'field-invalid' : '' }}">
                                </label>
                                <label class="block">
                                    <span class="text-base text-stone-500">{{ __('Месяц плана') }}</span>
                                    <input type="month" wire:model="planMonth" min="{{ substr($factFrom, 0, 7) }}" class="mt-1 w-full rounded-xl border border-stone-200 px-2 py-3 text-lg outline-none focus:border-sky-500 {{ $errors->has('planMonth') ? 'field-invalid' : '' }}">
                                </label>
                                @if ($transaction->type->value === 'expense')
                                    <div class="receipt-edit w-full basis-full rounded-2xl bg-paper px-3 py-3">
                                        <span class="text-base text-stone-500">{{ __('Чек') }}</span>
                                        @if ($transaction->receipt_path)
                                            @php($receiptUrl = asset('storage/'.$transaction->receipt_path))
                                            <button type="button" class="receipt-edit-open" onclick="this.nextElementSibling.showModal()" aria-label="{{ __('Открыть чек') }}">
                                                <img class="receipt-edit-shot" src="{{ $receiptUrl }}" alt="{{ __('Предпросмотр чека') }}">
                                            </button>
                                            @include('livewire.budget.partials.receipt-viewer')
                                            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2">
                                                <a href="{{ $receiptUrl }}" download target="_blank" rel="noopener" class="text-base font-medium text-tide">{{ __('Скачать') }}</a>
                                                <button type="button" wire:click="removeTransactionReceipt" wire:confirm="{{ __('Удалить чек? Файл будет удалён.') }}" class="text-base font-medium text-rose-700">{{ __('Удалить чек') }}</button>
                                            </div>
                                        @endif
                                        @error('editReceipt') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
                                        @error('editReceiptCamera') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
                                        @if ($editReceipt)
                                            <img src="{{ $editReceipt->temporaryUrl() }}" alt="{{ __('Предпросмотр чека') }}" class="receipt-edit-shot mt-2">
                                        @elseif ($editReceiptCamera)
                                            <img src="{{ $editReceiptCamera->temporaryUrl() }}" alt="{{ __('Предпросмотр чека') }}" class="receipt-edit-shot mt-2">
                                        @endif
                                        <div class="receipt-edit-actions">
                                            <label class="receipt-edit-btn receipt-edit-btn-file {{ $errors->has('editReceipt') ? 'field-invalid' : '' }}">
                                                {{ __('Загрузить файл') }}
                                                <input type="file" wire:model="editReceipt" accept="image/*" class="sr-only">
                                            </label>
                                            <label class="receipt-edit-btn receipt-edit-btn-camera {{ $errors->has('editReceiptCamera') ? 'field-invalid' : '' }}">
                                                {{ __('Сфотографировать') }}
                                                <input type="file" wire:model="editReceiptCamera" accept="image/*" capture="environment" class="sr-only">
                                            </label>
                                        </div>
                                    </div>
                                @endif
                                <button type="submit" class="rounded-full bg-sky-800 px-3 py-3 text-base font-medium text-white">{{ __('Сохранить') }}</button>
                                <button type="button" wire:click="moveTransactionToPlan" wire:confirm="Убрать этот факт и записать сумму в план выбранного месяца? Деньги вернутся на счёт." class="rounded-full bg-amber-50 px-3 py-3 text-base font-medium text-amber-800 ring-1 ring-stone-900/10">{{ __('Перенести в план') }}</button>
                                <button type="button" wire:click="cancelEdit" class="rounded-full bg-rose-600 px-3 py-3 text-base font-medium text-white">{{ __('Отмена') }}</button>
                            </form>
                            @error('editAmount') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
                            @error('editDate') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
                            @error('editCategoryId') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
                            @error('editAccountId') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
                            @error('editNote') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
                            @error('planMonth') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
                </td>
            </tr>
        @else
            <tr wire:key="deal-{{ $transaction->id }}" class="budget-deals {{ $dealClass }}" @if ($dealShift) style="margin-left: {{ $dealShift }}" @endif>
                <td colspan="6" class="px-4 py-2" @if ($dealPad) style="padding-left: {{ $dealPad }}" @endif>
                    <div class="deal-line">
                        <span class="deal-date text-lg text-stone-700">{{ $transaction->occurred_on->format('d/m/Y') }}</span>
                        <span class="deal-note text-lg text-stone-600">{{ $transaction->note ?: '—' }}</span>
                        <span class="deal-amount text-lg font-medium tabular-nums">{{ money($transaction->amount_cents) }}</span>
                        <span class="deal-tools">
                            @if ($transaction->receipt_path)
                                @php($receiptUrl = asset('storage/'.$transaction->receipt_path))
                                <button type="button" class="receipt-open" onclick="this.nextElementSibling.showModal()" aria-label="{{ __('Открыть чек') }}">
                                    <img class="receipt-preview" src="{{ $receiptUrl }}" width="48" height="64" alt="{{ __('Предпросмотр чека') }}">
                                    <span class="receipt-label">{{ __('Чек') }}</span>
                                </button>
                                @include('livewire.budget.partials.receipt-viewer')
                            @else
                                <span class="receipt-label receipt-label-empty">{{ __('Чек') }}</span>
                            @endif
                            @if ($monthCurrent)
                                <button type="button" wire:click="editTransaction({{ $transaction->id }})" class="text-base font-medium text-sky-800">{{ __('Изменить') }}</button>
                                @unless ($transaction->receipt_path)
                                    <button type="button" wire:click="deleteTransaction({{ $transaction->id }})" wire:confirm="{{ __('Удалить операцию :amount? Сумма вернётся на счёт.', ['amount' => money($transaction->amount_cents)]) }}" class="text-base font-medium text-rose-700">{{ __('Удалить') }}</button>
                                @endunless
                            @endif
                        </span>
                    </div>
                </td>
            </tr>
        @endif
    @endforeach
@endif
