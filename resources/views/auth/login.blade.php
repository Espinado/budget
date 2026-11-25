@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-md pt-8 sm:pt-16">
        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('Войти') }}</h1>
        <p class="mt-2 text-stone-600">{{ __('Почта и пароль от Family Money.') }}</p>

        <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-4 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-900/5">
            @csrf
            <label class="block">
                <span class="text-base text-stone-600">{{ __('Почта') }}</span>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 outline-none focus:border-tide {{ $errors->has('email') ? 'field-invalid' : '' }}">
            </label>
            <label class="block">
                <span class="text-base text-stone-600">{{ __('Пароль') }}</span>
                <input type="password" name="password" required autocomplete="current-password" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 outline-none focus:border-tide {{ $errors->has('password') ? 'field-invalid' : '' }}">
            </label>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <label class="flex items-center gap-2 text-base text-stone-600">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="rounded border-stone-300 text-tide">
                    {{ __('Запомнить меня') }}
                </label>
                <a href="{{ route('password.request') }}" class="text-base font-medium text-sky-800 hover:underline">{{ __('Забыли пароль?') }}</a>
            </div>
            @if ($errors->any())
                <p class="text-base text-rose-700">{{ $errors->first() }}</p>
            @endif
            <button type="submit" class="w-full rounded-2xl bg-tide px-4 py-3 font-medium text-white hover:bg-tide/90">{{ __('Войти') }}</button>
            <p class="text-center text-base text-stone-600">{{ __('Нет аккаунта?') }} <a href="{{ route('register') }}" class="font-medium text-sky-800 hover:underline">{{ __('Зарегистрироваться') }}</a></p>
        </form>

        <div class="mt-6">
            <x-support-form />
        </div>
    </div>
@endsection
