@php
    $supportErrors = $errors->getBag('support');
    $supportEmail = old('email', auth()->user()?->email);
    $supportOpen = $supportErrors->isNotEmpty();
@endphp
@if (session('support_sent'))
    <p class="mb-3 rounded-2xl bg-emerald-50 px-4 py-3 text-base text-emerald-800">{{ session('support_sent') }}</p>
@endif
<details id="support" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5" @if ($supportOpen) open @endif>
    <summary class="inline-flex cursor-pointer list-none items-center rounded-2xl bg-tide px-4 py-3 text-base font-medium text-white [&::-webkit-details-marker]:hidden">{{ __('Поддержка') }}</summary>
    <form method="POST" action="{{ route('support.store') }}" class="mt-4 space-y-4">
        @csrf
        <p class="text-lg text-stone-700">{{ __('Напишите, если что-то не получается. Письмо уйдёт в поддержку, ответ придёт на указанную почту.') }}</p>
        <label class="block">
            <span class="text-base text-stone-600">{{ __('Почта') }}</span>
            <input type="email" name="email" value="{{ $supportEmail }}" required autocomplete="email" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 outline-none focus:border-tide {{ $supportErrors->has('email') ? 'field-invalid' : '' }}">
        </label>
        <label class="block">
            <span class="text-base text-stone-600">{{ __('Тема') }}</span>
            <input type="text" name="subject" value="{{ old('subject') }}" maxlength="200" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 outline-none focus:border-tide {{ $supportErrors->has('subject') ? 'field-invalid' : '' }}">
        </label>
        <label class="block">
            <span class="text-base text-stone-600">{{ __('Сообщение') }}</span>
            <textarea name="message" required rows="5" maxlength="5000" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 outline-none focus:border-tide {{ $supportErrors->has('message') ? 'field-invalid' : '' }}">{{ old('message') }}</textarea>
        </label>
        @if ($supportErrors->isNotEmpty())
            <p class="text-base text-rose-700">{{ $supportErrors->first() }}</p>
        @endif
        <button type="submit" class="w-full rounded-2xl bg-tide px-4 py-3 font-medium text-white hover:bg-tide/90 sm:w-auto">{{ __('Отправить') }}</button>
    </form>
</details>
