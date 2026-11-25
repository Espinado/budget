@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-md pt-8 sm:pt-16">
        <x-back-link :href="route('login')">{{ __('Вернуться ко входу') }}</x-back-link>
        <h1 class="mt-4 text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('Восстановление пароля') }}</h1>
        <p class="mt-2 text-stone-600">{{ __('Напишите почту аккаунта. Мы отправим ссылку для нового пароля.') }}</p>

        <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-4 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-900/5">
            @csrf
            <label class="block">
                <span class="text-base text-stone-600">{{ __('Почта') }}</span>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 outline-none focus:border-tide {{ $errors->has('email') ? 'field-invalid' : '' }}">
            </label>
            @if (session('status'))
                <p class="text-base text-tide">{{ session('status') }}</p>
            @endif
            @if ($errors->any())
                <p class="text-base text-rose-700">{{ $errors->first() }}</p>
            @endif
            <button type="submit" class="w-full rounded-2xl bg-tide px-4 py-3 font-medium text-white hover:bg-tide/90">{{ __('Отправить ссылку') }}</button>
        </form>
    </div>
@endsection
