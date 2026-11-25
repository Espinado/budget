@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-3xl pt-4 sm:pt-8">
        <div class="flex flex-wrap items-center gap-6">
            <div class="min-w-0 flex-1" style="flex: 1 1 16rem">
                <p class="text-base font-medium text-tide">{{ __('Семейный бюджет в евро') }}</p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('Видно, на что хватит денег до конца месяца.') }}</h1>
                <p class="mt-4 text-lg text-stone-700">{{ __('Без таблиц и без спора «куда всё ушло». План, счета и долги семьи в одном приложении. Его скачивают на компьютер и открывают как обычную программу.') }}</p>
                <div class="mt-6 flex flex-wrap items-center gap-3">
                    <a href="{{ asset('downloads/FamilyMoney-Setup.exe') }}" download class="inline-flex rounded-2xl bg-tide px-4 py-3 font-medium text-white hover:bg-tide/90">{{ __('Скачать') }}</a>
                    {{-- Для обычного браузера. Установленное приложение само открывает бюджет. Когда приложение будет в Google Play, эту кнопку можно убрать. --}}
                    @auth
                        <a href="{{ route('dashboard') }}" class="inline-flex rounded-2xl bg-white px-4 py-3 font-medium text-ink ring-1 ring-stone-900/10 hover:bg-stone-50">{{ __('К бюджету') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex rounded-2xl bg-white px-4 py-3 font-medium text-ink ring-1 ring-stone-900/10 hover:bg-stone-50">{{ __('Уже есть аккаунт') }}</a>
                    @endauth
                </div>
            </div>
            <img src="{{ asset('images/help/month.jpg') }}" alt="{{ __('Месяц: какая часть уже прошла и сколько дней осталось.') }}" class="rounded-2xl shadow-sm ring-1 ring-stone-900/10" style="width: 12.5rem; max-height: 11rem; object-fit: cover; object-position: top">
        </div>

        <div class="mt-8 space-y-3">
            <article class="flex flex-wrap items-center gap-4 rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
                <img src="{{ asset('images/help/food.jpg') }}" alt="{{ __('Питание: уже потрачено и план на одной строке.') }}" class="rounded-2xl ring-1 ring-stone-900/10" style="width: 12.5rem; max-height: 11rem; object-fit: cover; object-position: top">
                <div class="min-w-0 flex-1" style="flex: 1 1 16rem">
                    <h2 class="text-lg font-medium">{{ __('Просто') }}</h2>
                    <p class="mt-2 text-lg text-stone-700">{{ __('План и то, что уже потратили, стоят рядом. Сразу видно, сколько можно тратить в день и не остаться без денег к концу месяца.') }}</p>
                </div>
            </article>
            <article class="flex flex-wrap items-center gap-4 rounded-3xl bg-tide-soft p-4 ring-1 ring-stone-900/5 sm:p-5">
                <img src="{{ asset('images/help/family.jpg') }}" alt="{{ __('Семья: приглашение второго человека в тот же бюджет.') }}" class="rounded-2xl bg-white ring-1 ring-stone-900/10" style="width: 12.5rem; max-height: 11rem; object-fit: cover; object-position: top">
                <div class="min-w-0 flex-1" style="flex: 1 1 16rem">
                    <h2 class="text-lg font-medium">{{ __('Вместе') }}</h2>
                    <p class="mt-2 text-lg text-stone-700">{{ __('Муж и жена ведут один бюджет. Когда один записал покупку, второй видит те же суммы, без пересылки скриншотов.') }}</p>
                </div>
            </article>
            <article class="flex flex-wrap items-center gap-4 rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
                <img src="{{ asset('images/help/wallets.jpg') }}" alt="{{ __('Счета: наличные, карты и копилка отдельно от накоплений.') }}" class="rounded-2xl ring-1 ring-stone-900/10" style="width: 12.5rem; max-height: 11rem; object-fit: cover; object-position: top">
                <div class="min-w-0 flex-1" style="flex: 1 1 16rem">
                    <h2 class="text-lg font-medium">{{ __('Где лежат деньги') }}</h2>
                    <p class="mt-2 text-lg text-stone-700">{{ __('Наличные, карты и копилка на одном экране. Перекладка между своими счетами не выглядит как расход. Запас легко не потратить случайно.') }}</p>
                </div>
            </article>
            <article class="flex flex-wrap items-center gap-4 rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
                <img src="{{ asset('images/help/expenses.jpg') }}" alt="{{ __('Расходы: видно, где план уже закрыт и где потратили больше.') }}" class="rounded-2xl ring-1 ring-stone-900/10" style="width: 12.5rem; max-height: 11rem; object-fit: cover; object-position: top">
                <div class="min-w-0 flex-1" style="flex: 1 1 16rem">
                    <h2 class="text-lg font-medium">{{ __('Перерасход виден сразу') }}</h2>
                    <p class="mt-2 text-lg text-stone-700">{{ __('Карточка краснеет, только когда потратили больше плана. Пока укладываетесь, она остаётся спокойной.') }}</p>
                </div>
            </article>
            <article class="flex flex-wrap items-center gap-4 rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
                <img src="{{ asset('images/help/balance.jpg') }}" alt="{{ __('Баланс месяца и круг, из чего состоят расходы.') }}" class="rounded-2xl ring-1 ring-stone-900/10" style="width: 12.5rem; max-height: 11rem; object-fit: cover; object-position: top">
                <div class="min-w-0 flex-1" style="flex: 1 1 16rem">
                    <h2 class="text-lg font-medium">{{ __('Куда ушли деньги') }}</h2>
                    <p class="mt-2 text-lg text-stone-700">{{ __('В конце месяца видно, сколько останется. Круг показывает, какие траты заняли большую часть.') }}</p>
                </div>
            </article>
        </div>

        <div class="mt-8 rounded-3xl bg-tide px-5 py-6 text-white">
            <h2 class="text-2xl font-semibold tracking-tight">{{ __('Один спокойный экран вместо нескольких таблиц.') }}</h2>
            <p class="mt-2 text-lg">{{ __('Поставьте Family Money на компьютер. Внутри сразу есть «Зарплата», счета и категории, их потом можно поменять.') }}</p>
            <a href="{{ asset('downloads/FamilyMoney-Setup.exe') }}" download class="mt-5 inline-flex rounded-2xl bg-white px-4 py-3 font-medium text-tide hover:bg-tide-soft">{{ __('Скачать') }}</a>
            <p class="mt-3 text-base">{{ __('Установочный файл для Windows.') }}</p>
        </div>
        <p class="mt-4 text-base"><a href="{{ route('privacy') }}" class="font-medium text-sky-800 underline-offset-2 hover:underline">{{ __('Политика конфиденциальности') }}</a></p>
    </div>
@endsection
