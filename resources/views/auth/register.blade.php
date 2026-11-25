@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-md pt-8 sm:pt-16">
        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('Регистрация') }}</h1>
        <p class="mt-2 text-stone-600">{{ __('Новый семейный бюджет в евро. Он не связан с чужими записями.') }}</p>
        <p class="mt-3 text-base"><a href="{{ route('help', ['from' => 'register']) }}" class="font-medium text-sky-800 hover:underline">{{ __('Помощь') }}</a></p>

        <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-4 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-900/5">
            @csrf
            <label class="block">
                <span class="text-base text-stone-600">{{ __('Имя') }}</span>
                <input type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" maxlength="80" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 outline-none focus:border-tide {{ $errors->has('name') ? 'field-invalid' : '' }}">
            </label>
            <label class="block">
                <span class="text-base text-stone-600">{{ __('Почта') }}</span>
                <input type="email" name="email" value="{{ old('email') }}" required autocomplete="username" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 outline-none focus:border-tide {{ $errors->has('email') ? 'field-invalid' : '' }}">
            </label>
            <label class="block">
                <span class="text-base text-stone-600">{{ __('Пароль') }}</span>
                <input type="password" name="password" required autocomplete="new-password" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 outline-none focus:border-tide {{ $errors->has('password') ? 'field-invalid' : '' }}">
            </label>
            <label class="block">
                <span class="text-base text-stone-600">{{ __('Пароль ещё раз') }}</span>
                <input type="password" name="password_confirmation" required autocomplete="new-password" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 outline-none focus:border-tide {{ $errors->has('password') ? 'field-invalid' : '' }}">
            </label>
            @if ($errors->any())
                <p class="text-base text-rose-700">{{ $errors->first() }}</p>
            @endif
            <p class="text-base text-stone-600"><a href="{{ route('privacy') }}" class="font-medium text-sky-800 hover:underline">{{ __('Политика конфиденциальности') }}</a></p>
            <button type="submit" class="w-full rounded-2xl bg-tide px-4 py-3 font-medium text-white hover:bg-tide/90">{{ __('Создать аккаунт') }}</button>
            <p class="text-center text-base text-stone-600">{{ __('Уже есть аккаунт?') }} <a href="{{ route('login') }}" class="font-medium text-sky-800 hover:underline">{{ __('Войти') }}</a></p>
        </form>
    </div>
@endsection
