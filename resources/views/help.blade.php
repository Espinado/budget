@extends('layouts.app')

@php
    $backToRegister = request('from') === 'register';
    $helpReturnUrl = $backToRegister ? route('register') : route('dashboard');
    $helpReturnLabel = $backToRegister ? __('← К регистрации') : __('← К бюджету');
@endphp

@section('content')
    <article class="help-page space-y-6">
        <header>
            <x-back-link :href="$helpReturnUrl">{{ $helpReturnLabel }}</x-back-link>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('Помощь') }}</h1>
            <div class="mt-4">
                <x-support-form />
            </div>
            <p class="mt-4 text-lg text-stone-600">{{ __('Коротко, что нажимать. На снимках учебный бюджет: суммы и названия там для примера.') }}</p>
            <p class="mt-3 text-lg"><a href="{{ route('privacy', ['from' => 'help']) }}" class="font-medium text-sky-800 underline-offset-2 hover:underline">{{ __('Политика конфиденциальности') }}</a></p>
        </header>

        <nav class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
            <h2 class="text-lg font-medium">{{ __('Содержание') }}</h2>
            <ol class="mt-3 space-y-2 text-lg">
                <li><a href="#start" class="text-sky-800 underline-offset-2 hover:underline">{{ __('С чего начать?') }}</a></li>
                <li><a href="#support" class="text-sky-800 underline-offset-2 hover:underline">{{ __('Поддержка') }}</a></li>
                <li><a href="#login" class="text-sky-800 underline-offset-2 hover:underline">{{ __('Вход') }}</a></li>
                <li><a href="#family" class="text-sky-800 underline-offset-2 hover:underline">{{ __('Семья') }}</a></li>
                <li><a href="#month" class="text-sky-800 underline-offset-2 hover:underline">{{ __('Месяц') }}</a></li>
                <li><a href="#money" class="text-sky-800 underline-offset-2 hover:underline">{{ __('Счета и сводка') }}</a></li>
                <li><a href="#balance" class="text-sky-800 underline-offset-2 hover:underline">{{ __('Баланс месяца') }}</a></li>
                <li><a href="#actions" class="text-sky-800 underline-offset-2 hover:underline">{{ __('Кнопки над планом') }}</a></li>
                <li><a href="#debts" class="text-sky-800 underline-offset-2 hover:underline">{{ __('Долги') }}</a></li>
                <li><a href="#objects" class="text-sky-800 underline-offset-2 hover:underline">{{ __('Объекты') }}</a></li>
                <li><a href="#cards" class="text-sky-800 underline-offset-2 hover:underline">{{ __('Карточки плана') }}</a></li>
                <li><a href="#record" class="text-sky-800 underline-offset-2 hover:underline">{{ __('Записать операцию') }}</a></li>
                <li><a href="#deals" class="text-sky-800 underline-offset-2 hover:underline">{{ __('Список операций') }}</a></li>
                <li><a href="#edit" class="text-sky-800 underline-offset-2 hover:underline">{{ __('Правка и удаление строки') }}</a></li>
                <li><a href="#transfer" class="text-sky-800 underline-offset-2 hover:underline">{{ __('Перевод') }}</a></li>
                <li><a href="#statistics" class="text-sky-800 underline-offset-2 hover:underline">{{ __('Статистика') }}</a></li>
                <li><a href="#shopping" class="text-sky-800 underline-offset-2 hover:underline">{{ __('Список покупок') }}</a></li>
                <li><a href="#settings" class="text-sky-800 underline-offset-2 hover:underline">{{ __('Настройки') }}</a></li>
                <li><a href="#delete-account" class="text-sky-800 underline-offset-2 hover:underline">{{ __('Удалить аккаунт') }}</a></li>
                <li><a href="#vault" class="text-sky-800 underline-offset-2 hover:underline">{{ __('Хранение конфиденциальной информации') }}</a></li>
                <li><a href="#phone" class="text-sky-800 underline-offset-2 hover:underline">{{ __('Телефон') }}</a></li>
            </ol>
        </nav>

        <section id="start" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
            <h2 class="text-lg font-medium">{{ __('С чего начать?') }}</h2>
            <div class="help-wrap mt-3 space-y-3 text-lg text-stone-700">
                <p>{{ __('Новая семья начинается на странице входа: ссылка «Зарегистрироваться». На форме — имя, почта и пароль. Пароль вводится дважды и не короче 8 символов. Кнопка «Создать аккаунт».') }}</p>
                <p>{{ __('Сразу появляется семья «Наша семья» и четыре счёта: Наличные деньги, Дебетная карта, Кредитная карта и Копилка. В доходах сразу стоит «Зарплата», остальные категории — расходы. Имя семьи, счета и категории потом меняются в настройках.') }}</p>
            </div>
            <div class="help-shots mt-4 clear-both grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                <figure class="w-full max-w-sm">
                    <img src="{{ asset('images/help/register.jpg') }}" alt="{{ __('Форма регистрации: имя, почта, пароль два раза и кнопка Создать аккаунт.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Регистрация нового аккаунта.') }}</figcaption>
                </figure>
                <figure class="w-full max-w-sm">
                    <img src="{{ asset('images/help/opening.jpg') }}" alt="{{ __('Начальные остатки: четыре счёта, Сохранить и продолжить, Пропустить.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Сколько сейчас на счетах. Пустое поле — ноль.') }}</figcaption>
                </figure>
            </div>
            <div class="mt-4 space-y-3 text-lg text-stone-700">
                <p>{{ __('Следующий экран — «Начальные остатки». Впишите, сколько сейчас на каждом счёте. Пустое поле означает ноль. «Пропустить» тоже оставляет нули и открывает месяц. «Сохранить и продолжить» записывает суммы и открывает месяц.') }}</p>
                <p>{{ __('Дальше заполняется план месяца: доходы и расходы. Как устроены месяц, счета и запись операций — в разделах ниже.') }}</p>
            </div>
        </section>

        <section id="login" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
            <h2 class="text-lg font-medium">{{ __('Вход') }}</h2>
            <div class="help-wrap mt-3 md:flow-root">
                <figure class="help-shot mb-3 w-full max-w-sm md:float-left md:mr-5 md:mb-4">
                    <img src="{{ asset('images/help/login.jpg') }}" alt="{{ __('Форма входа: почта, пароль, запомнить меня, забыли пароль, войти.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Страница входа.') }}</figcaption>
                </figure>
                <div class="space-y-3 text-lg text-stone-700">
                    <p>{{ __('Войдите по почте и паролю. «Запомнить меня» оставляет вход на этом устройстве. «Забыли пароль?» присылает письмо для нового пароля. Нет аккаунта — «Зарегистрироваться».') }}</p>
                    <p>{{ __('Новой семье сразу создаются расчётные счета и Копилка. Дальше программа спрашивает, сколько сейчас на каждом. Пустое поле — ноль, «Пропустить» тоже оставляет нули.') }}</p>
                </div>
            </div>
        </section>

        <section id="family" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
            <h2 class="text-lg font-medium">{{ __('Семья') }}</h2>
            <div class="help-wrap mt-3 md:flow-root">
                <figure class="help-shot mb-3 w-full max-w-sm md:float-left md:mr-5 md:mb-4">
                    <img src="{{ asset('images/help/family.jpg') }}" alt="{{ __('Блок Семья: участник Демо, поля имя и почта, кнопка Пригласить.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Имя, почта и «Пригласить». В списке те, кто уже ведёт этот бюджет.') }}</figcaption>
                </figure>
                <p class="text-lg text-stone-700">{{ __('В настройках, в блоке «Семья», укажите имя и почту мужа или жены и нажмите «Пригласить». На почту придёт ссылка, чтобы задать свой пароль. В списке под заголовком — кто уже в бюджете. После входа оба видят один бюджет. Когда один записывает приход, расход, перевод или строку плана, у другого внизу экрана появляется уведомление, а суммы на странице обновляются сами. Пока открыта форма и идёт набор текста, страница подождёт и обновится, когда поле освободится.') }}</p>
            </div>
        </section>

        <section id="month" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
            <h2 class="text-lg font-medium">{{ __('Месяц') }}</h2>
            <div class="help-wrap mt-3 md:flow-root">
                <figure class="help-shot mb-3 w-full max-w-sm md:float-left md:mr-5 md:mb-4">
                    <img src="{{ asset('images/help/month.jpg') }}" alt="{{ __('Октябрь 2026, стрелки влево и вправо, кружок прогресса месяца.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Переключение месяца.') }}</figcaption>
                </figure>
                <div class="space-y-3 text-lg text-stone-700">
                    <p>{{ __('Стрелки переключают месяц. Кружок рядом с названием показывает, какая часть месяца уже прошла. Под названием — даты и сколько дней осталось.') }}</p>
                    <p>{{ __('Прошлый месяц закрыт. Стрелка влево открывает его только для просмотра: под названием написано «Период уже закончился». Строки плана на месте, но «Записать», корзины и «Добавить запись в бюджет» пропадают. Поправить сумму плана, операцию или дату задним числом нельзя. Текущий месяц и следующие по-прежнему меняются.') }}</p>
                </div>
            </div>
            <div class="help-shots mt-4 clear-both grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                <figure class="w-full max-w-sm">
                    <img src="{{ asset('images/help/closed-month.jpg') }}" alt="{{ __('Сентябрь 2026: под датами написано, что период уже закончился.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Закрытый месяц можно открыть, но не изменить.') }}</figcaption>
                </figure>
                <figure class="w-full max-w-sm">
                    <img src="{{ asset('images/help/closed-plan.jpg') }}" alt="{{ __('Карточки плана сентября без кнопок Записать и без корзины.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('В закрытом месяце остаются суммы. Кнопок правки нет.') }}</figcaption>
                </figure>
            </div>
        </section>

        <section id="money" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
            <h2 class="text-lg font-medium">{{ __('Счета и сводка') }}</h2>
            <div class="help-wrap mt-3 md:flow-root">
                <figure class="help-shot mb-3 w-full max-w-sm md:float-left md:mr-5 md:mb-4">
                    <img src="{{ asset('images/help/wallets.jpg') }}" alt="{{ __('Всего денег на счетах, Копилка в накоплениях и сводка: доход и расход.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Счета, Копилка и сводка.') }}</figcaption>
                </figure>
                <div class="space-y-3 text-lg text-stone-700">
                    <p><span class="font-medium text-ink">{{ __('Всего денег на счетах') }}</span> {{ __('— сколько сейчас на расчётных счетах: наличные и карты. То, что уже потратили или получили, уже внутри этих сумм. У месяца, который ещё не начался, этого списка нет: это сегодняшние деньги, а не деньги будущего месяца. Сводка и баланс есть у любого месяца: без плана — нули, с частью данных — считаем по тому, что уже есть.') }}</p>
                    <p><span class="font-medium text-ink">{{ __('Накопления') }}</span> {{ __('— Копилка и другие накопительные счета. В баланс месяца они не входят. Если у счёта задан % годовых, проценты начисляются ежедневно на текущий остаток.') }}</p>
                    <p><span class="font-medium text-ink">{{ __('Сводка') }}</span> {{ __('— план открытого месяца. «Доход» — запланированная сумма. «Расход» — план, а если по строке уже потрачено больше плана, то факт. Число у слова «Сводка» — доход минус этот расход. Оплата внутри плана сводку не меняет. Перерасход и дополнительный платёж по кредиту сверх строки уменьшают сводку на эту лишнюю сумму. Под ней кнопка «Долговые обязательства»: зелёным — дебитовые, красным — кредитные.') }}</p>
                </div>
            </div>
        </section>

        <section id="balance" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
            <h2 class="text-lg font-medium">{{ __('Баланс месяца') }}</h2>
            <div class="help-wrap mt-3 md:flow-root">
                <figure class="help-shot mb-3 w-full max-w-sm md:float-left md:mr-5 md:mb-4">
                    <img src="{{ asset('images/help/balance.jpg') }}" alt="{{ __('Зелёная плашка баланса октября и круг структуры бюджета.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Баланс месяца и доли плана.') }}</figcaption>
                </figure>
                <div class="space-y-3 text-lg text-stone-700">
                    <p>{{ __('Баланс отвечает на один вопрос: сколько денег останется в последний день месяца, если остаток плана ещё случится. Берутся деньги на расчётных счетах, прибавляется доход по плану, который ещё не пришёл, и вычитается расход по плану, который ещё не сделан. Копилка в этот счёт не входит.') }}</p>
                    <p>{{ __('Пример. На расчётных счетах 1 000 €. План дохода 500 €, план расхода 200 €. Сводка +300 €. Пока ничего не потрачено, баланс месяца: 1 000 + 500 − 200 = 1 300 €. Если из 200 € уже потратили 50 €, на счетах 950 €, а баланс тот же: 950 + 500 − 150 = 1 300 €. Сводка по-прежнему +300 €: эта оплата уже ушла со счёта и больше не сидит в «ещё не сделано».') }}</p>
                    <p>{{ __('Если из тех же 200 € потратили 250 €, на счетах 750 €. Сводка становится 500 − 250 = +250 €. Баланс: 750 + 500 − 0 = 1 250 €. Лишние 50 € уменьшают и сводку, и баланс. Вторая ещё не оплаченная строка в той же категории из-за этого не уменьшается: её план вычитается целиком. Дополнительный платёж по кредиту сверх своей строки считается так же.') }}</p>
                    <p>{{ __('Недобор остаётся на счетах. Пока месяц не кончился, неизрасходованный план ещё считается будущим расходом. В конце месяца в балансе остаётся то, что реально лежит на счетах.') }}</p>
                    <p>{{ __('Зелёная плашка — в конце месяца деньги будут. Красная — по этому расчёту их не хватит. Круг «Структура бюджета» показывает, какую долю плана занимают крупные группы расходов.') }}</p>
                    <p>{{ __('Пока месяц не кончился, его план ещё учитывается в следующем. У месяца, который ещё не начался, своего баланса нет: повторяющиеся расходы туда уже попали, а доход может быть ещё не запланирован. Когда открыт следующий месяц, виден баланс текущего.') }}</p>
                </div>
            </div>
        </section>

        <section id="actions" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
            <h2 class="text-lg font-medium">{{ __('Кнопки над планом') }}</h2>
            <div class="help-wrap mt-3 md:flow-root">
                <figure class="help-shot mb-3 w-full max-w-sm md:float-left md:mr-5 md:mb-4">
                    <img src="{{ asset('images/help/actions.jpg') }}" alt="{{ __('Кнопки: копировать бюджет, перевод, добавить приход, добавить расход.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Копирование, перевод, приход и расход.') }}</figcaption>
                </figure>
                <div class="space-y-3 text-lg text-stone-700">
                    <p>{{ __('«Копировать бюджет» переносит строки плана в следующий месяц и сразу открывает его со сводкой. Уже скопированные второй раз не добавляются: открывается тот же месяц, строки не дублируются. Пустая строка той же категории (например из «Повторяющихся») при копировании заполняется суммой и датой, а не создаёт вторую. Повторяющаяся зарплата так не двоится. Если после копирования сумму изменили, повторное нажатие старую строку не обновляет. Строки из раздела долгов копирование не переносит: следующий платёж берётся из графика договора. Когда последний платёж оплачен, в следующем месяце этой строки уже нет.') }}</p>
                    <p>{{ __('«Перевод денег на другой счёт» двигает деньги между счетами и в категории бюджета не попадает. «Добавить приход» и «Добавить расход» записывают факт сразу, без строки плана.') }}</p>
                </div>
            </div>
        </section>

        <section id="debts" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
            <h2 class="text-lg font-medium">{{ __('Долги') }}</h2>
            <div class="help-wrap mt-3 md:flow-root">
                <figure class="help-shot mb-3 w-full max-w-sm md:float-left md:mr-5 md:mb-4">
                    <img src="{{ asset('images/help/debt-choices.jpg') }}" alt="{{ __('Форма нового долга: кредитный или дебитовый, новый или существующий договор, кредит или лизинг.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Сначала направление, потом договор и вид: кредит или лизинг.') }}</figcaption>
                </figure>
                <div class="space-y-3 text-lg text-stone-700">
                    <p>{{ __('Долги открываются кнопкой «Долговые обязательства» под сводкой. Сверху два блока: «Личные» — долги с людьми, «Кредиты» — банк и лизинг. В каждом списке дебиторские и кредиторские строки: имя, ближайший платёж и остаток. Нажатие открывает обязательство целиком: договор, ставка и таблица по месяцам. В таблице видны шесть месяцев, остальные платежи листаются внутри неё. Новую ставку вводят уже там.') }}</p>
                    <p>{{ __('Кредит, который мы должны, не записывается доходом: остаток просто числится за кредитором. В план каждого месяца платежа встаёт одна строка на сумму, которая уйдёт со счёта: основная сумма, проценты и плата вместе. Эти строки ставит раздел долгов, а не копирование бюджета. В день оплаты факт записывается на эту сумму, а остаток долга уменьшается только на основную сумму. Проценты и плата основную сумму не уменьшают.') }}</p>
                    <p>{{ __('Кнопка «Новый долг» создаёт кредитное обязательство, когда должны мы, или дебитовое, когда должны нам. Для кредита сначала выбирается «Новый договор» или «Существующий договор», затем «Кредит» или «Лизинг». Новый договор спрашивает сумму выдачи, число месяцев и дату первого платежа. Существующий договор спрашивает остаток основной суммы, дату следующего платежа и дату окончания из банка: число платежей считается само. EURIBOR человек вписывает сам, из выписки или из предложения банка, вместе с надбавкой и сроком 3, 6 или 12 месяцев. У лизинга есть сумма выкупа в конце. Дебитовый долг остаётся проще: полный возврат одной суммой или по частям. Номер договора и референс можно не указывать. Дата тоже необязательна: если она есть, возврат встаёт в план этого месяца. Без даты в плане строки нет. Ежемесячная плата и плата за выдачу необязательны. Ежемесячная входит в каждый платёж, плата за выдачу только в первый.') }}</p>
                </div>
            </div>
            <div class="help-shots mt-4 clear-both grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                <figure class="w-full max-w-sm">
                    <img src="{{ asset('images/help/debt-lend.jpg') }}" alt="{{ __('Дебитовый долг: полный возврат или возврат по частям.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Дебитовый долг возвращают целиком или по частям.') }}</figcaption>
                </figure>
                <figure class="w-full max-w-sm">
                    <img src="{{ asset('images/help/debt-running.jpg') }}" alt="{{ __('Существующий договор: остаток основной суммы, дата следующего платежа, дата окончания и ставка с EURIBOR.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('У существующего договора остаток основной суммы и ставка. EURIBOR вписывают сами.') }}</figcaption>
                </figure>
                <figure class="w-full max-w-sm">
                    <img src="{{ asset('images/help/debt-preview.jpg') }}" alt="{{ __('Предпросмотр платежа существующего договора с фразой сверьте платёж с банком.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Для уже идущего договора внизу написано: сверьте платёж с банком.') }}</figcaption>
                </figure>
                <figure class="w-full max-w-sm">
                    <img src="{{ asset('images/help/debt-lease.jpg') }}" alt="{{ __('Лизинг: поле сколько останется выкупить в конце, заполнено круглой суммой 2000.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Операционный лизинг: сумма выкупа в конце. У финансового лизинга это поле оставляют пустым.') }}</figcaption>
                </figure>
            </div>
            <div class="help-wrap mt-4 md:flow-root">
                <figure class="help-shot mb-3 w-full max-w-sm md:float-left md:mr-5 md:mb-4">
                    <img src="{{ asset('images/help/solvency.jpg') }}" alt="{{ __('Справа от ссылки к бюджету красная карточка: платежеспособность нет, предварительно.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Строка «Платежеспособность: да» и значок «i». «Предварительно» пишется в пояснении.') }}</figcaption>
                </figure>
                <div class="space-y-3 text-lg text-stone-700">
                    <p>{{ __('Календарь плана листается вперёд не больше чем на шесть месяцев. Долг может быть длиннее: дальние месяцы хранятся у контрагента и попадают в план, когда до них остаётся не больше шести месяцев. Платёж больше строки сокращает срок с конца: ближайшие взносы остаются той же суммой, лишние месяцы пропадают. «Оплатить» списывает деньги только после выбора расчётного счёта.') }}</p>
                    <p>{{ __('Creditea и SMScredit копят процент каждый день. На экране долга видны тело и уже набежавшие проценты. Luminor и Inbank остаются на графике: оплата следующие строки не пересобирает.') }}</p>
                    <p>{{ __('Карточка — одна строка «Платежеспособность: да» или «нет» и значок «i». Доля считается от среднего полученного дохода, не больше чем за шесть месяцев. Запланированный доход, который ещё не пришёл, в среднее не входит. Взносы — ближайший неоплаченный платёж каждого банковского кредита. «Да», если доля не больше 40 %, остаток долга не больше шести годовых доходов и после плановых обычных расходов деньги остаются. Пока месяцев с доходом меньше шести, в пояснении написано «Предварительно». Там же запас под новый платёж и тот же запас, если ставка с EURIBOR станет на 2 пункта выше. Если условия уже идущего долга меняются, их вводят у этого контрагента. Дата окончания договора остаётся. Новая ставка действует с ближайшего неоплаченного платежа, и сумма может стать больше или меньше.') }}</p>
                </div>
            </div>
            <div class="help-shots mt-4 clear-both grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                <figure class="w-full max-w-sm">
                    <img src="{{ asset('images/help/solvency-tip.jpg') }}" alt="{{ __('Открытое пояснение платежеспособности: доля кредитных платежей, порог 40 процентов и пометка предварительно.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Знак «i» открывает, как считается доля и когда ответ «Да».') }}</figcaption>
                </figure>
            </div>
        </section>

        <section id="objects" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
            <h2 class="text-lg font-medium">{{ __('Объекты') }}</h2>
            <div class="help-wrap mt-3 space-y-3 text-lg text-stone-700">
                <p>{{ __('Карточка объекта открывается иконкой дома. Это место для вещи: недвижимое или движимое имущество. Карточка связывает уже существующие долги и категории расходов. План она не копирует.') }}</p>
                <p>{{ __('Место задаётся на карточке и потом меняется там же. «Под категорией» ставит объект под уже существующую категорию, например под «Дом». «Сам родитель» делает категорию объекта верхней. Переезжает только категория этого объекта.') }}</p>
                <p>{{ __('Название карточки — это название её категории. Короткая подпись категорию не переименовывает. Расходы внутри остаются со своими названиями.') }}</p>
                <p>{{ __('Одно название расхода — одно место в бюджете.') }}</p>
                <p>{{ __('«Новый расход» записывает одну обычную строку плана в месяц её даты.') }}</p>
                <p>{{ __('«Новый контракт» — госпошлина или страховка. Есть название и необязательная заметка: страховщик и номер полиса. Заметка остаётся на контракте и строкой плана не становится. Платёж может быть один или из нескольких частей. У части свои дата и сумма. Часть попадает только в план своего месяца: июньская видна в июне, не в мае и не в июле.') }}</p>
                <p>{{ __('«Новый кредит» открывает обычную форму кредита или лизинга. Долг привязывается к объекту. Его платёж виден вместе с объектом и второй раз в заголовок «Кредит» не добавляется.') }}</p>
                <p>{{ __('«Удалить карточку» не удаляет её, пока привязан долг. Сначала долг отвязывают и сохраняют. Долги, строки плана и папка, в которой они уже лежат, остаются. Пустая папка карточки убирается, чтобы то же название не осталось вторым местом.') }}</p>
                <p>{{ __('В списке расходов стоят категории, не отдельные суммы. Категория графика этого долга в списке не повторяется.') }}</p>
            </div>
        </section>

        <section id="cards" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
            <h2 class="text-lg font-medium">{{ __('Карточки плана') }}</h2>
            <div class="help-wrap mt-3 md:flow-root">
                <figure class="help-shot mb-3 w-full max-w-sm md:float-left md:mr-5 md:mb-4">
                    <img src="{{ asset('images/help/income.jpg') }}?v=2" alt="{{ __('Карточка аванса: план 200 евро, 0 процентов, корзина и кнопка Записать.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Пока операций нет, слева ноль, справа план. Корзина и «Записать» на строке.') }}</figcaption>
                </figure>
                <div class="space-y-3 text-lg text-stone-700">
                    <p>{{ __('Список делится на доходы и расходы. В шапке раздела — план и факт. На карточке одна строка: сколько уже прошло и план через косую черту, например 12,49 € / 30,00 €. Ниже полоска и процент. Если плана нет, процента нет.') }}</p>
                    <p>{{ __('У категории план — сумма планов вложенных категорий. У дочерней категории план — сумма её строк. Факт считается так же, по уже записанным операциям. Полоска становится бирюзовой, когда факт догнал план. Красной карточка становится, только когда расход больше плана. Доход, который ещё не пришёл, остаётся спокойным.') }}</p>
                    <p>{{ __('Стрелка раскрывает категорию. «Записать» есть только у строки плана, не у названия категории, и открывает форму сразу под этой строкой. Под полоской расхода написано «в день»: сколько из оставшегося плана можно тратить каждый день до конца месяца. Если уже потрачено больше плана, там ноль. Перерасход уменьшает и сводку, и баланс месяца. Корзина у названия убирает строку, только если по ней в этом месяце ещё нет операций.') }}</p>
                </div>
            </div>
            <div class="help-shots mt-4 clear-both grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                <figure class="w-full max-w-sm">
                    <img src="{{ asset('images/help/expenses.jpg') }}?v=2" alt="{{ __('Строка Tet с фактом, полоской на 100 процентов и планом, и красная строка сигарет, где факт больше плана.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Бирюзовая полоска — факт догнал план. Красная карточка — расход больше плана.') }}</figcaption>
                </figure>
                <figure class="w-full max-w-sm">
                    <img src="{{ asset('images/help/food.jpg') }}?v=2" alt="{{ __('Категория Питание без кнопки Записать: факт, полоска и план как сумма строк.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('У категории кнопки «Записать» нет. Её план — сумма вложенных строк.') }}</figcaption>
                </figure>
                <figure class="w-full max-w-sm">
                    <img src="{{ asset('images/help/nested.jpg') }}?v=2" alt="{{ __('Раскрытое питание: продукты домой и кофе, у строк есть Записать.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Стрелка открывает вложенные строки. «Записать» стоит на строке.') }}</figcaption>
                </figure>
            </div>
        </section>

        <section id="record" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
            <h2 class="text-lg font-medium">{{ __('Записать операцию') }}</h2>
            <div class="help-wrap mt-3 md:flow-root">
                <figure class="help-shot mb-3 w-full max-w-sm md:float-left md:mr-5 md:mb-4">
                    <img src="{{ asset('images/help/record.jpg') }}" alt="{{ __('Форма операции по строке плана: сумма, дата, счёт, заметка, чек.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Операция по строке плана.') }}</figcaption>
                </figure>
                <div class="space-y-3 text-lg text-stone-700">
                    <p>{{ __('«Записать» на строке плана открывает форму сразу под нажатой строкой. Каждую покупку вносят отдельно, не одной суммой за месяц. Укажите сумму, дату, счёт и примечание. Даты в списках записаны как дд.мм.гггг. Чек — фотография, на телефоне её можно снять камерой.') }}</p>
                    <p>{{ __('«Добавить расход» и «Добавить приход» — та же запись, если строки плана ещё нет. Выберите категорию и, если их несколько, нужную строку плана.') }}</p>
                </div>
            </div>
            <div class="help-shots mt-4 clear-both grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                <figure class="w-full max-w-sm">
                    <img src="{{ asset('images/help/expense.jpg') }}" alt="{{ __('Форма нового расхода: сумма, дата, категория, счёт, примечание и чек.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Быстрый расход. Приход выглядит так же.') }}</figcaption>
                </figure>
                <figure class="w-full max-w-sm">
                    <img src="{{ asset('images/help/plan.jpg') }}" alt="{{ __('Форма новой строки плана: расход или приход, категория, примечание, сумма и дата.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('«Добавить запись в бюджет» — новая строка плана, не факт.') }}</figcaption>
                </figure>
            </div>
        </section>

        <section id="deals" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
            <h2 class="text-lg font-medium">{{ __('Список операций') }}</h2>
            <div class="help-wrap mt-3 md:flow-root">
                <figure class="help-shot mb-3 w-full max-w-sm md:float-left md:mr-5 md:mb-4">
                    <img src="{{ asset('images/help/deals.jpg') }}" alt="{{ __('Под строкой Tet операция: дата, заметка, сумма, чек, изменить и удалить.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Операции под строкой плана.') }}</figcaption>
                </figure>
                <p class="text-lg text-stone-700">{{ __('Название строки, по которой уже есть операции, открывает список: дата, примечание и сумма. «Чек» открывает снимок. Если фото нет, надпись серая. «Изменить» правит операцию. «Удалить» возвращает сумму на счёт. Операцию с чеком удалить нельзя. Если вместо плана случайно записан факт, в правке выбирается месяц и нажимается «Перенести в план»: факт исчезает, сумма становится строкой плана, деньги возвращаются на счёт. Менять можно только текущий месяц. Прошлый месяц закрыт: его операции и план остаются как есть, и датой задним числом туда ничего не записывается.') }}</p>
            </div>
        </section>

        <section id="edit" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
            <h2 class="text-lg font-medium">{{ __('Правка и удаление строки') }}</h2>
            <div class="help-wrap mt-3 md:flow-root">
                <figure class="help-shot mb-3 w-full max-w-sm md:float-left md:mr-5 md:mb-4">
                    <img src="{{ asset('images/help/edit.jpg') }}" alt="{{ __('Правка строки плана: сумма, категория, дата, счёт, сохранить и отмена.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Правка строки плана.') }}</figcaption>
                </figure>
                <p class="text-lg text-stone-700">{{ __('Сумма на карточке или название строки открывает правку позиции плана: сумма, категория, дата и счёт. «Сохранить» записывает изменения. «Удалить» спрашивает подтверждение и убирает строку, только если операций по ней ещё нет. В закрытом месяце эта правка не открывается.') }}</p>
            </div>
            <div class="help-shots mt-4 clear-both grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                <figure class="w-full max-w-sm">
                    <img src="{{ asset('images/help/confirm.jpg') }}" alt="{{ __('Окно подтверждения: убрать аванс из плана, кнопки удалить и отмена.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Перед удалением программа спрашивает ещё раз.') }}</figcaption>
                </figure>
            </div>
        </section>

        <section id="transfer" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
            <h2 class="text-lg font-medium">{{ __('Перевод') }}</h2>
            <div class="help-wrap mt-3 md:flow-root">
                <figure class="help-shot mb-3 w-full max-w-sm md:float-left md:mr-5 md:mb-4">
                    <img src="{{ asset('images/help/transfer.jpg') }}" alt="{{ __('Форма перевода: откуда, куда, сумма, дата, перевести и отмена.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Перевод между счетами.') }}</figcaption>
                </figure>
                <p class="text-lg text-stone-700">{{ __('Выберите «Откуда», «Куда», сумму и дату, затем «Перевести». Перевод не меняет сводку и его нельзя удалить. Список переводов — внизу статистики.') }}</p>
            </div>
        </section>

        <section id="statistics" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
            <h2 class="text-lg font-medium">{{ __('Статистика') }}</h2>
            <div class="help-wrap mt-3 md:flow-root">
                <figure class="help-shot mb-3 w-full max-w-sm md:float-left md:mr-5 md:mb-4">
                    <img src="{{ asset('images/help/stats-button.jpg') }}" alt="{{ __('Под кругом структуры бюджета широкая синяя кнопка Статистика.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Кнопка «Статистика» под структурой бюджета.') }}</figcaption>
                </figure>
                <div class="space-y-3 text-lg text-stone-700">
                    <p>{{ __('Кнопка «Статистика» под структурой бюджета считает, сколько пришло и ушло: этот месяц, эта неделя или свой период. Даты для правки появляются только у своего периода. Сверху одна сводка: крупно расходы, под ними доход и разница. Это факт выбранных дней, без плана. Красным подсвечивается только отрицательная разница. Переводы в неё не входят.') }}</p>
                    <p>{{ __('Дальше карточки с фактической суммой, без полоски и процента. Крупные сверху. Категория со вложенными открывается стрелкой. Последняя категория открывает покупки прямо под карточкой. Переводы внизу: со счёта на счёт.') }}</p>
                </div>
            </div>
            <div class="help-shots mt-4 clear-both grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                <figure class="w-full max-w-sm">
                    <img src="{{ asset('images/help/stats.jpg') }}" alt="{{ __('Период статистики и сводка: расходы, доход и красная отрицательная разница.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Период и сводка. Разница красная, когда расходы больше доходов.') }}</figcaption>
                </figure>
                <figure class="w-full max-w-sm">
                    <img src="{{ asset('images/help/stats-cards.jpg') }}" alt="{{ __('Карточки расходов: сигареты, питание со стрелкой и телеком, у каждой полоска и процент.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Карточка показывает факт. Стрелка открывает вложенные.') }}</figcaption>
                </figure>
                <figure class="w-full max-w-sm">
                    <img src="{{ asset('images/help/stats-buy.jpg') }}" alt="{{ __('Под карточкой кафе покупка: дата, заметка латте и сумма.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Покупки открываются под карточкой.') }}</figcaption>
                </figure>
                <figure class="w-full max-w-sm">
                    <img src="{{ asset('images/help/stats-transfer.jpg') }}" alt="{{ __('Перевод с дебетовой карты на наличные, дата и сумма.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Переводы внизу и в разницу не входят.') }}</figcaption>
                </figure>
            </div>
        </section>

        <section id="shopping" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
            <h2 class="text-lg font-medium">{{ __('Список покупок') }}</h2>
            <div class="help-wrap mt-3 space-y-3 text-lg text-stone-700">
                <p>{{ __('Это список «что купить», не расход. В шапке корзина, на бюджете — кнопка «Список покупок». При создании сразу выбирается категория из дерева Питание. Потом можно добавить ещё категории Питания: один семейный список, сгруппированный по ним.') }}</p>
                <p>{{ __('У продукта название обязательно, количество и цена — по желанию. Галочка значит «куплено»; расход в бюджет сама не пишется. Отмеченное можно убрать в архив. Если указали цены и их сумма больше остатка плана по категории, показывается только предупреждение.') }}</p>
            </div>
        </section>

        <section id="settings" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
            <h2 class="text-lg font-medium">{{ __('Настройки') }}</h2>
            <div class="help-wrap mt-3 md:flow-root">
                <figure class="help-shot mb-3 w-full max-w-sm md:float-left md:mr-5 md:mb-4">
                    <img src="{{ asset('images/help/header.jpg') }}?v=2" alt="{{ __('Шапка: логотип Family Money, ключ, шестерёнка, знак вопроса и дверь со стрелкой.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Хранение конфиденциальной информации, настройки, помощь и выход.') }}</figcaption>
                </figure>
                <div class="space-y-3 text-lg text-stone-700">
                    <p>{{ __('В шапке значки. Корзина — список покупок, ключ — хранение конфиденциальной информации, шестерёнка — настройки, знак вопроса — эту страницу, дверь со стрелкой — выход. Подсказка у значка: «Список покупок», «Хранение конфиденциальной информации», «Настройки», «Помощь» или «Выйти».') }}</p>
                    <p>{{ __('«Профиль»: текущая почта, «Сменить почту» и «Сменить пароль». Для смены нужен текущий пароль. Поля открываются по кнопке и после сохранения снова прячутся.') }}</p>
                    <p>{{ __('Счета лежат в двух списках: «Расчётный счёт» и «Накопительный счёт». Список открывается по нажатию. «Создать новый счёт» показывает поля. При изменении счёта можно править остаток. У накопительного счёта можно указать процент годовых: каждый день к остатку добавляется сложный процент (ставка ÷ 365). Удалить счёт можно, только если на нём ноль и переводов не было.') }}</p>
                    <p>{{ __('Новая категория бывает поступлением или расходом. Если выбрать материнскую, она станет подкатегорией. Значок выбирается из набора. Удалить категорию нельзя, если внутри есть подкатегории или по ней уже есть план, операции либо повторяющаяся строка.') }}</p>
                    <p>{{ __('«Повторяется каждый период» отмечает категории, которые сами попадают в план каждого месяца. Дата — день, который повторяется. Снятая галочка убирает строку начиная со следующего месяца, в текущем она остаётся.') }}</p>
                </div>
            </div>
            <div class="help-shots mt-4 clear-both grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                <figure class="w-full max-w-sm">
                    <img src="{{ asset('images/help/profile.jpg') }}" alt="{{ __('Профиль: почта, кнопки сменить почту и сменить пароль.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Почта и пароль.') }}</figcaption>
                </figure>
                <figure class="w-full max-w-sm">
                    <img src="{{ asset('images/help/accounts.jpg') }}" alt="{{ __('Открытый список расчётных счетов и свёрнутый накопительный.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Два списка счетов. Копилка лежит в накопительном.') }}</figcaption>
                </figure>
                <figure class="w-full max-w-sm">
                    <img src="{{ asset('images/help/category.jpg') }}" alt="{{ __('Новая категория: название, расход или поступление, материнская и набор значков.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Новая категория и значок.') }}</figcaption>
                </figure>
                <figure class="w-full max-w-sm">
                    <img src="{{ asset('images/help/repeat.jpg') }}" alt="{{ __('Список категорий с галочками «повторяется каждый период».') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Галочка ставит категорию в план каждого месяца.') }}</figcaption>
                </figure>
            </div>
        </section>

        <section id="delete-account" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
            <h2 class="text-lg font-medium">{{ __('Удалить аккаунт') }}</h2>
            <div class="help-wrap mt-3 space-y-3 text-lg text-stone-700">
                <p>{{ __('Внизу настроек есть кнопка «Удалить аккаунт». Она спрашивает текущий пароль и просит подтвердить. После этого программа выходит на главную.') }}</p>
                <p>{{ __('Удаляется вход этого человека, его личные записи сейфа и загруженные документы. Если в семье остаётся другой человек, общий бюджет остаётся у него.') }}</p>
                <p>{{ __('Если уходит последний участник, вместе с аккаунтом удаляется и бюджет: счета, операции, долги, списки покупок и чеки.') }}</p>
            </div>
        </section>

        <section id="vault" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
            <h2 class="text-lg font-medium">{{ __('Хранение конфиденциальной информации') }}</h2>
            <div class="help-wrap mt-3 md:flow-root">
                <figure class="help-shot mb-3 w-full max-w-sm md:float-left md:mr-5 md:mb-4">
                    <img src="{{ asset('images/help/vault-gate.jpg') }}" alt="{{ __('Вход в раздел хранения конфиденциальной информации: поле пароля аккаунта и кнопка Открыть.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Пароль аккаунта. Запомнить нельзя.') }}</figcaption>
                </figure>
                <div class="space-y-3 text-lg text-stone-700">
                    <p>{{ __('Раздел с ключом хранит личные коды, PIN и документы, например банковский договор. Их видит только тот, кто вошёл. Другие члены семьи этот список не получают.') }}</p>
                    <p>{{ __('Документы устроены так же: сначала раздел с названием, внутри него файлы. Кнопка «Сфотографировать» на телефоне открывает камеру. «Выбрать файлы» добавляет PDF или уже снятые снимки, несколько сразу. Файл до 10 МБ шифруется. Его можно посмотреть на странице, скачать или отправить на почту, адрес которой вы вводите сами. Удаление раздела или файла снова спрашивает пароль.') }}</p>
                    <p>{{ __('Сначала нужно ввести пароль своего аккаунта. Запомнить пароль для этого раздела нельзя. После трёх неверных попыток раздел блокируется. «Запомнить меня» при входе в Family Money блокировку не снимает: нужен выход и новый вход с паролем. После входа можно создать раздел, например «Банковские коды», а внутри — название вроде «pin1» и сам код. Код на экране закрыт точками; глаз показывает или снова прячет его. Удаление раздела или кода снова спрашивает пароль.') }}</p>
                </div>
            </div>
            <div class="help-shots mt-4 clear-both grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                <figure class="w-full max-w-sm">
                    <img src="{{ asset('images/help/vault-groups.jpg') }}" alt="{{ __('Список разделов: Банковские коды и кнопка Новый раздел.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Разделы. Можно создать новый или открыть существующий.') }}</figcaption>
                </figure>
                <figure class="w-full max-w-sm">
                    <img src="{{ asset('images/help/vault-codes.jpg') }}" alt="{{ __('Коды в разделе Банковские коды: pin1 и интернет-банк закрыты точками, глаз, изменить и удалить.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Код закрыт точками. Глаз показывает его.') }}</figcaption>
                </figure>
                <figure class="w-full max-w-sm">
                    <img src="{{ asset('images/help/vault-delete.jpg') }}" alt="{{ __('Удаление кода: запрос пароля аккаунта, кнопки Удалить и Отмена.') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Удаление снова спрашивает пароль.') }}</figcaption>
                </figure>
            </div>
        </section>

        <section id="phone" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
            <h2 class="text-lg font-medium">{{ __('Телефон') }}</h2>
            <div class="help-wrap mt-3 md:flow-root">
                <figure class="help-shot mb-3 w-full max-w-sm md:float-left md:mr-5 md:mb-4">
                    <img src="{{ asset('images/help/install.jpg') }}" alt="{{ __('Плашка обновления и установка на экран: кнопка Установить и шаги Safari «На экран Домой».') }}" class="w-full rounded-2xl ring-1 ring-stone-900/10">
                    <figcaption class="mt-2 text-base text-stone-600">{{ __('Обновление и установка. На iPhone — Safari и «На экран Домой».') }}</figcaption>
                </figure>
                <div class="space-y-3 text-lg text-stone-700">
                    <p>{{ __('Family Money можно поставить на экран телефона или компьютера. Вверху появляется предложение «Установить». На Android нажмите «Установить» или выберите это в меню браузера. На компьютере — значок установки в адресной строке.') }}</p>
                    <p>{{ __('На iPhone откройте сайт в Safari. Нажмите «Поделиться» внизу экрана — квадрат со стрелкой вверх — и выберите «На экран Домой», затем «Добавить». В Chrome на iPhone так поставить нельзя: только Safari.') }}</p>
                    <p>{{ __('Когда выходит новая версия, вверху написано «Вышло обновление». Нажмите «Обновить» или просто обновите страницу.') }}</p>
                    <p>{{ __('Кнопка «Наверх» внизу справа возвращает к началу этой страницы.') }}</p>
                </div>
            </div>
        </section>

        <p>
            <x-back-link :href="$helpReturnUrl">{{ $helpReturnLabel }}</x-back-link>
        </p>
    </article>

    <button
        type="button"
        onclick="window.scrollTo({ top: 0, behavior: 'smooth' })"
        class="inline-grid place-items-center rounded-full bg-sky-800 text-white shadow-sm"
        style="position: fixed; right: 1rem; bottom: 1rem; z-index: 40; width: 3rem; height: 3rem; touch-action: manipulation"
        title="{{ __('Наверх') }}"
        aria-label="{{ __('Наверх') }}"
    >
        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6 15l6-6 6 6" />
        </svg>
    </button>
@endsection
