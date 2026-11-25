@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-md pt-8 sm:pt-16">
        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('Новый пароль') }}</h1>
        <p class="mt-2 text-stone-600">{{ __('Придумайте пароль не короче 8 символов.') }}</p>

        <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-4 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-900/5">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label class="block">
                <span class="text-base text-stone-600">{{ __('Почта') }}</span>
                <input type="email" name="email" value="{{ old('email', $email) }}" required autofocus autocomplete="username" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 outline-none focus:border-tide {{ $errors->has('email') ? 'field-invalid' : '' }}">
            </label>
            <label class="block">
                <span class="text-base text-stone-600">{{ __('Новый пароль') }}</span>
                <input type="password" name="password" required autocomplete="new-password" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 outline-none focus:border-tide {{ $errors->has('password') ? 'field-invalid' : '' }}">
            </label>
            <label class="block">
                <span class="text-base text-stone-600">{{ __('Пароль ещё раз') }}</span>
                <input type="password" name="password_confirmation" required autocomplete="new-password" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 outline-none focus:border-tide {{ $errors->has('password') ? 'field-invalid' : '' }}">
            </label>
            @if ($errors->any())
                <p class="text-base text-rose-700">{{ $errors->first() }}</p>
            @endif
            <button type="submit" class="w-full rounded-2xl bg-tide px-4 py-3 font-medium text-white hover:bg-tide/90">{{ __('Сохранить пароль') }}</button>
        </form>
    </div>
@endsection
