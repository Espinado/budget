import './styles.css'
import { accountBalance, descendantIds, totals } from './budget.js'
import { addRecord, ensureSeed, loadAll, openDatabase } from './db.js'
import { formatRuDate, money, monthBounds, monthKey, monthSpan, monthTitle, monthsBetween, parseAmount, shiftMonth, todayKey, weekBounds } from './money.js'

const app = document.querySelector('#app')
const database = await openDatabase()
await ensureSeed(database)

const state = {
  month: monthKey(),
  screen: 'budget',
  statsRange: 'month',
  statsFrom: monthBounds(monthKey())[0],
  statsTo: monthBounds(monthKey())[1],
  open: new Set(),
  form: null,
  error: '',
}

app.addEventListener('click', onClick)
app.addEventListener('submit', onSubmit)
app.addEventListener('change', onChange)
await draw()

async function draw() {
  const data = await loadAll(database)
  app.innerHTML = view(data)
}

function view(data) {
  return `${shell()}${pages(data)}`
}

function shell() {
  const item = (screen, label) => `<button type="button" data-action="screen" data-screen="${screen}" class="${state.screen === screen ? 'on' : ''}">${label}</button>`

  return `
    <header class="topbar">
      <div class="frame">
        <button type="button" class="brand" data-action="screen" data-screen="budget">
          <img src="./icons/family-money-192.png" alt="">
          <span>Family Money</span>
        </button>
        <nav>
          ${item('settings', 'Настройки')}
          ${item('statistics', 'Статистика')}
          ${item('help', 'Помощь')}
          <button type="button" data-action="quit">Выйти</button>
        </nav>
      </div>
    </header>
  `
}

function pages(data) {
  if (state.screen === 'statistics') {
    return statisticsPage(data)
  }
  if (state.screen === 'settings') {
    return settingsPage(data)
  }
  if (state.screen === 'help') {
    return helpPage()
  }

  return budgetPage(data)
}

function budgetPage(data) {
  const balances = data.accounts.map((account) => ({
    name: account.name,
    balance: accountBalance(account, data.transactions),
  }))
  const cash = balances.reduce((sum, account) => sum + account.balance, 0)
  const stillIncome = still(data, 'income')
  const stillExpense = still(data, 'expense')
  const planIncome = planned(data, 'income')
  const planExpense = planned(data, 'expense')
  const periodBalance = planIncome - planExpense
  const ended = todayKey() > monthBounds(state.month)[1]
  const forecast = ended ? cash : cash + stillIncome - stillExpense
  const span = monthSpan(state.month)
  const wallet = balances.map((account) => `
    <div class="wallet-row"><span>${escapeHtml(account.name)}</span><span>${money(account.balance)}</span></div>
  `).join('')

  return `
    <main class="page frame">
      <section class="month-bar">
        <button class="month-nav" data-action="month" data-delta="-1" aria-label="Предыдущий месяц">${chevron('left')}</button>
        <div class="month-copy">
          <span class="day-ring" style="background: conic-gradient(#1c1917 ${span.percent}%, #e7e5e4 0)"><span></span></span>
          <div>
            <h1>${escapeHtml(monthTitle(state.month))}</h1>
            <p>${escapeHtml(span.label)}</p>
          </div>
        </div>
        <button class="month-nav" data-action="month" data-delta="1" aria-label="Следующий месяц">${chevron('right')}</button>
      </section>
      <section class="summary">
        <article class="wallet">
          <div class="wallet-total"><span>Всего денег на счетах</span><b>${money(cash)}</b></div>
          ${wallet}
          <div class="wallet-split ${periodBalance < 0 ? 'down' : 'up'}">
            <div class="wallet-total"><span>Сводка</span><b>${periodBalance > 0 ? '+' : ''}${money(periodBalance)}</b></div>
            <div class="wallet-row up"><span>Доход</span><span>${money(planIncome)}</span></div>
            <div class="wallet-row"><span>Расход</span><span>${money(planExpense)}</span></div>
          </div>
        </article>
        <article class="balance-card">
          <div class="balance-line">
            <p>Баланс ${escapeHtml(monthTitle(state.month))}</p>
            <b class="${forecast < 0 ? 'down' : 'up'}">${money(forecast)}</b>
          </div>
          <p class="hint">${ended ? 'Столько денег было в кошельках в конце месяца.' : 'Деньги на счетах, плюс доход по плану, который ещё не пришёл, минус расход по плану, который ещё не сделан.'}</p>
          <p class="structure">Структура бюджета</p>
          ${pie(data)}
        </article>
      </section>
      <div class="budget-actions">
        <button type="button" class="action income ${state.form?.kind === 'quick' && state.form.type === 'income' ? 'on' : ''}" data-action="quick" data-type="income">Добавить приход</button>
        <button type="button" class="action expense ${state.form?.kind === 'quick' && state.form.type === 'expense' ? 'on' : ''}" data-action="quick" data-type="expense">Добавить расход</button>
      </div>
      ${state.form?.kind === 'quick' ? quickForm(data) : ''}
      ${section(data, 'income', 'Доходы')}
      ${section(data, 'expense', 'Расходы')}
      <button type="button" class="action plan ${state.form?.kind === 'plan' ? 'on' : ''}" data-action="plan">Добавить запись в бюджет</button>
      ${state.form?.kind === 'plan' ? planForm(data) : ''}
    </main>
  `
}

function planned(data, type) {
  return data.categories
    .filter((category) => category.type === type)
    .reduce((sum, category) => sum + data.planLines
      .filter((line) => line.categoryId === category.id && line.month === state.month)
      .reduce((total, line) => total + line.amountCents, 0), 0)
}

function still(data, type) {
  return data.categories
    .filter((category) => category.type === type)
    .reduce((sum, category) => {
      const planned = data.planLines
        .filter((line) => line.categoryId === category.id && line.month === state.month)
        .reduce((total, line) => total + line.amountCents, 0)
      const actual = data.transactions
        .filter((row) => row.categoryId === category.id && row.occurredOn.startsWith(state.month))
        .reduce((total, row) => total + row.amountCents, 0)

      return sum + Math.max(0, planned - actual)
    }, 0)
}

function pie(data) {
  const colors = ['#38bdf8', '#34d399', '#fde68a', '#fb923c', '#fb7185', '#a78bfa', '#94a3b8', '#f472b6', '#2dd4bf']
  const slices = data.categories
    .filter((category) => category.type === 'expense' && category.parentId === null)
    .map((category) => ({
      name: category.name,
      planned: totals(data.categories, data.planLines, data.transactions, category.id, state.month).planned,
    }))
    .filter((slice) => slice.planned > 0)
  const total = slices.reduce((sum, slice) => sum + slice.planned, 0)
  if (total === 0) {
    return '<p class="hint">Пока нет запланированных расходов.</p>'
  }

  let cursor = 0
  const parts = slices.map((slice, index) => {
    const share = slice.planned / total * 100
    const color = colors[index % colors.length]
    const part = `${color} ${cursor}% ${cursor + share}%`
    cursor += share
    slice.color = color

    return part
  })
  const legend = slices.map((slice) => `<li><i style="background:${slice.color}"></i><span>${escapeHtml(slice.name)}</span></li>`).join('')

  return `
    <div class="pie-row">
      <div class="pie" style="background: conic-gradient(${parts.join(', ')})"></div>
      <ul class="legend">${legend}</ul>
    </div>
  `
}

function activeCategory(data, category) {
  const ownLine = data.planLines.some((line) => line.categoryId === category.id && line.month === state.month)
  const ownActual = data.transactions.some((row) => row.categoryId === category.id && row.occurredOn.startsWith(state.month) && row.type !== 'transfer')
  if (ownLine || ownActual) {
    return true
  }

  return data.categories.some((child) => child.parentId === category.id && activeCategory(data, child))
}

function section(data, type, title) {
  const roots = data.categories
    .filter((category) => category.type === type && category.parentId === null && activeCategory(data, category))
    .sort((left, right) => left.sort - right.sort)
  if (roots.length === 0) {
    return ''
  }

  const planned = roots.reduce((sum, category) => sum + totals(data.categories, data.planLines, data.transactions, category.id, state.month).planned, 0)
  const actual = roots.reduce((sum, category) => sum + totals(data.categories, data.planLines, data.transactions, category.id, state.month).actual, 0)

  return `
    <section class="sheet-section ${type}">
      <div class="sheet-head">
        <span>${title}</span>
        <span>План ${money(planned)} · Факт ${money(actual)}</span>
      </div>
    </section>
    ${roots.map((category) => card(data, category, 0)).join('')}
  `
}

function card(data, category, depth) {
  const figure = totals(data.categories, data.planLines, data.transactions, category.id, state.month)
  const children = data.categories
    .filter((item) => item.parentId === category.id)
    .sort((left, right) => left.sort - right.sort)
  const lines = data.planLines.filter((line) => line.categoryId === category.id && line.month === state.month)
  const loose = data.transactions.filter((row) => row.categoryId === category.id && row.planLineId === null && row.occurredOn.startsWith(state.month))
  const visibleChildren = children.filter((child) => activeCategory(data, child))
  const single = visibleChildren.length === 0 && lines.length === 1
  if (single) {
    return `<article class="sheet-card ${depth ? 'child' : ''}">${planRow(data, category, lines[0], true)}${loose.length ? looseRow(loose) : ''}</article>`
  }

  const open = state.open.has(category.id) || visibleChildren.length === 0
  const over = category.type === 'expense' && figure.actual > figure.planned
  const done = !over && figure.planned > 0 && figure.actual >= figure.planned
  const percent = figure.planned > 0 ? Math.round(figure.actual * 100 / figure.planned) : null
  const width = percent === null ? (figure.actual > 0 ? 100 : 0) : Math.min(100, Math.max(0, percent))

  return `
    <article class="sheet-card ${depth ? 'child' : ''} ${over ? 'over' : ''}">
      <button class="name" data-action="toggle" data-id="${category.id}">
        ${visibleChildren.length ? `<span class="chevron ${over ? 'over' : ''}">${chevron(open ? 'down' : 'right')}</span>` : ''}
        ${mark(category.name, category.type)}
        <span>${escapeHtml(category.name)}</span>
      </button>
      ${ratio(figure.actual, figure.planned, done, over)}
      ${meter(width, done, over, percent)}
      ${open ? lines.map((line) => planRow(data, category, line, false)).join('') : ''}
      ${open && loose.length ? looseRow(loose) : ''}
    </article>
    ${open ? visibleChildren.map((child) => card(data, child, depth + 1)).join('') : ''}
  `
}

function planRow(data, category, line, single) {
  const actual = data.transactions
    .filter((row) => row.planLineId === line.id && row.occurredOn.startsWith(state.month))
    .reduce((sum, row) => sum + row.amountCents, 0)
  const over = category.type === 'expense' && actual > line.amountCents
  const done = !over && line.amountCents > 0 && actual >= line.amountCents
  const percent = line.amountCents > 0 ? Math.round(actual * 100 / line.amountCents) : null
  const width = percent === null ? (actual > 0 ? 100 : 0) : Math.min(100, Math.max(0, percent))
  const recording = state.form?.kind === 'record' && state.form.lineId === line.id
  const title = single ? category.name : line.title
  const note = single && line.title !== category.name ? line.title : ''
  const when = line.expectedOn ? shortDate(line.expectedOn) : ''
  const meta = [when, note].filter(Boolean).join(' · ')

  return `
    <div class="line ${over ? 'over' : ''}">
      <div class="name plain">${mark(title, category.type)}<span>${escapeHtml(title)}${meta ? `<small>${escapeHtml(meta)}</small>` : ''}</span></div>
      <div class="ratio-row">
        ${ratio(actual, line.amountCents, done, over)}
        <button class="record" data-action="record" data-id="${line.id}">Записать</button>
      </div>
      ${meter(width, done, over, percent)}
      ${recording ? recordForm(data) : ''}
    </div>
  `
}

function looseRow(rows) {
  const actual = rows.reduce((sum, row) => sum + row.amountCents, 0)

  return `<div class="line over"><div class="muted">Без строки плана</div>${ratio(actual, 0, false, true)}${meter(100, false, true, null)}</div>`
}

function ratio(actual, planned, done, over) {
  return `<div class="ratio ${done ? 'done' : ''} ${over ? 'over' : ''}"><span>${money(actual)}</span><span>/ ${money(planned)}</span></div>`
}

function meter(width, done, over, percent) {
  return `
    <div class="meter ${done ? 'done' : ''} ${over ? 'over' : ''}">
      <div class="meter-track"><span style="width: ${width}%"></span></div>
      ${percent === null ? '' : `<b>${percent}%</b>`}
    </div>
  `
}

function chevron(direction) {
  const path = direction === 'left'
    ? 'M15 6l-6 6 6 6'
    : direction === 'down'
      ? 'M6 9l6 6 6-6'
      : 'M9 6l6 6-6 6'

  return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${path}" /></svg>`
}

function mark(name, type) {
  const key = name.toLocaleLowerCase('ru')
  const icon = key.includes('питан') ? 'food' : key.includes('транспорт') || key.includes('авто') ? 'car' : type === 'income' ? 'coins' : 'dot'
  const paths = {
    food: '<path d="M6 3v8a3 3 0 0 0 6 0V3"/><path d="M9 14v7"/><path d="M16 3v18"/><path d="M16 8h3a2 2 0 0 1 0 4h-3"/>',
    car: '<path d="M4 15h16"/><path d="M5 15l2-5h10l2 5"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="16.5" cy="17.5" r="1.5"/>',
    coins: '<ellipse cx="12" cy="7" rx="7" ry="3"/><path d="M5 7v4.5c0 1.7 3.1 3 7 3s7-1.3 7-3V7"/><path d="M5 11.5V16c0 1.7 3.1 3 7 3s7-1.3 7-3v-4.5"/>',
    dot: '<circle cx="12" cy="12" r="3"/>',
  }

  return `<span class="mark ${icon}">${iconSvg(paths[icon])}</span>`
}

function iconSvg(body) {
  return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${body}</svg>`
}

function planForm(data) {
  const type = state.form.type || 'expense'
  const options = data.categories
    .filter((category) => category.type === type)
    .map((category) => `<option value="${category.id}">${escapeHtml(category.name)}</option>`)
    .join('')

  return `
    <form class="sheet-card" data-form="plan">
      <h2>Запись в ${escapeHtml(monthTitle(state.month))}</h2>
      <div class="type-switch">
        <button type="button" data-action="plan-type" data-type="expense" class="${type === 'expense' ? 'on' : ''}">Расход</button>
        <button type="button" data-action="plan-type" data-type="income" class="${type === 'income' ? 'on' : ''}">Приход</button>
      </div>
      <label>Категория<select name="category" required><option value="">Выберите</option>${options}</select></label>
      <label>Примечание, можно пусто<input name="title" maxlength="80" placeholder="Например, страховка машины"></label>
      <label>Сумма, €<input name="amount" inputmode="decimal" placeholder="25,00" required></label>
      <label>Дата платежа, можно пусто<input name="date" type="date"></label>
      ${errorBox()}
      <div class="form-actions">
        <button class="text-button" type="button" data-action="cancel">Отмена</button>
        <button class="save" type="submit">Добавить</button>
      </div>
    </form>
  `
}

function quickForm(data) {
  const type = state.form.type
  const options = data.categories
    .filter((category) => category.type === type)
    .map((category) => `<option value="${category.id}">${escapeHtml(category.name)}</option>`)
    .join('')
  const lines = data.planLines.filter((line) => line.categoryId === state.form.categoryId && line.month === state.month)
  const lineOptions = lines.length > 1
    ? `<label>Пункт плана<select name="line" required><option value="">Выберите пункт</option>${lines.map((line) => `<option value="${line.id}">${escapeHtml(line.title)}</option>`).join('')}</select></label>`
    : ''

  return `
    <form class="sheet-card" data-form="quick">
      <h2>${type === 'income' ? 'Новый приход' : 'Новый расход'}</h2>
      <label>Сумма, €<input name="amount" inputmode="decimal" placeholder="10,00" required></label>
      <label>Дата<input name="date" type="date" value="${defaultDate()}" required></label>
      <label>Категория<select name="category" required><option value="">Выберите категорию</option>${options}</select></label>
      ${lineOptions}
      <label>Счёт${accountSelect(data)}</label>
      <label>Примечание<input name="note" maxlength="200" placeholder="Например, магазин"></label>
      ${errorBox()}
      <div class="form-actions">
        <button class="text-button" type="button" data-action="cancel">Отмена</button>
        <button class="save" type="submit">Записать</button>
      </div>
    </form>
  `
}

function recordForm(data) {
  return `
    <form data-form="record">
      <p class="muted">Каждую операцию записываем отдельно, не всю сумму сразу.</p>
      <label>Сумма операции, €<input name="amount" inputmode="decimal" placeholder="4,50" required></label>
      <label>Дата<input name="date" type="date" value="${defaultDate()}" required></label>
      <label>Счёт${accountSelect(data)}</label>
      <label>Заметка<input name="note" maxlength="200" placeholder="Например, поездка"></label>
      ${errorBox()}
      <div class="form-actions">
        <button class="text-button" type="button" data-action="cancel">Отмена</button>
        <button class="save" type="submit">Добавить</button>
      </div>
    </form>
  `
}

function accountSelect(data) {
  const options = data.accounts.map((account) => `<option value="${account.id}">${escapeHtml(account.name)}</option>`).join('')

  return `<select name="account" required><option value="">Выберите счёт</option>${options}</select>`
}

function shortDate(value) {
  const [year, month, day] = value.split('-').map(Number)

  return new Date(year, month - 1, day).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long' })
}

function categoryForm(data, type) {
  const options = data.categories
    .filter((category) => category.type === type)
    .map((category) => `<option value="${category.id}">${escapeHtml(category.name)}</option>`)
    .join('')

  return `
    <form class="sheet-card" data-form="category">
      <input name="name" placeholder="Категория" required>
      <select name="parent"><option value="">В корень</option>${options}</select>
      ${errorBox()}
      <div class="form-actions">
        <button class="text-button" type="button" data-action="cancel">Отмена</button>
        <button class="save" type="submit">Сохранить</button>
      </div>
    </form>
  `
}

function statisticsPage(data) {
  const [from, to] = statsBounds()
  const invalid = state.statsRange === 'custom' && (!from || !to || from > to)
  const rangeButton = (value, label) => `<button type="button" data-action="stats" data-range="${value}" class="${state.statsRange === value ? 'on' : ''}">${label}</button>`
  const income = rangeSum(data, 'income', from, to)
  const expense = rangeSum(data, 'expense', from, to)
  const count = invalid ? 0 : data.transactions.filter((row) => row.occurredOn >= from && row.occurredOn <= to).length

  return `
    <main class="page frame">
      <a class="back" data-action="screen" data-screen="budget">← К бюджету</a>
      <h1 class="page-title">Статистика</h1>
      <p class="lead">Сколько пришло и ушло за выбранные дни. Переводы в доходы и расходы не входят.</p>
      <section class="panel">
        <div class="pills">
          ${rangeButton('month', 'Этот месяц')}
          ${rangeButton('week', 'Эта неделя')}
          ${rangeButton('custom', 'Свой период')}
        </div>
        ${state.statsRange === 'custom' ? `
          <div class="dates">
            <label>С<input type="date" name="statsFrom" value="${escapeHtml(state.statsFrom)}"></label>
            <label>По<input type="date" name="statsTo" value="${escapeHtml(state.statsTo)}"></label>
          </div>
        ` : ''}
        ${invalid ? '<p class="error">Сначала должна идти более ранняя дата.</p>' : `<p class="hint">${escapeHtml(formatRuDate(from))} — ${escapeHtml(formatRuDate(to))} · операций ${count}</p>`}
      </section>
      ${invalid ? '' : `
        <section class="panel">
          <div class="pair"><span>Расходы</span><b>${money(expense.actual)}</b></div>
          <div class="pair tide"><span>Доход</span><span>${money(income.actual)}</span></div>
          <div class="pair ${income.actual - expense.actual < 0 ? 'down' : 'up'}"><span>Разница</span><span>${money(income.actual - expense.actual)}</span></div>
        </section>
        ${statsSection(data, 'income', 'Доходы', from, to)}
        ${statsSection(data, 'expense', 'Расходы', from, to)}
      `}
    </main>
  `
}

function statsBounds() {
  if (state.statsRange === 'week') {
    return weekBounds()
  }
  if (state.statsRange === 'custom') {
    return [state.statsFrom, state.statsTo]
  }

  return monthBounds(monthKey())
}

function rangeSum(data, type, from, to) {
  return data.categories
    .filter((category) => category.type === type && category.parentId === null)
    .reduce((sum, category) => {
      const figure = rangeTotals(data, category.id, from, to)
      return { planned: sum.planned + figure.planned, actual: sum.actual + figure.actual }
    }, { planned: 0, actual: 0 })
}

function rangeTotals(data, categoryId, from, to) {
  const ids = new Set(descendantIds(data.categories, categoryId))
  const months = new Set(monthsBetween(from, to))
  const planned = data.planLines
    .filter((line) => months.has(line.month) && ids.has(line.categoryId))
    .reduce((sum, line) => sum + line.amountCents, 0)
  const actual = data.transactions
    .filter((row) => ids.has(row.categoryId) && row.occurredOn >= from && row.occurredOn <= to)
    .reduce((sum, row) => sum + row.amountCents, 0)

  return { planned, actual }
}

function statsSection(data, type, title, from, to) {
  const rows = data.categories
    .filter((category) => category.type === type && category.parentId === null)
    .map((category) => ({ category, figure: rangeTotals(data, category.id, from, to) }))
    .filter((row) => row.figure.actual > 0)
  if (rows.length === 0) {
    return ''
  }

  const sectionTotal = rows.reduce((sum, row) => sum + row.figure.actual, 0)

  return `
    <section class="sheet-section ${type}">
      <div class="sheet-head">
        <span>${title}</span>
        <span>${money(sectionTotal)}</span>
      </div>
    </section>
    ${rows.map((row) => `
        <article class="sheet-card">
          <div class="name plain">${mark(row.category.name, type)}<span>${escapeHtml(row.category.name)}</span></div>
          <div class="ratio"><span>${money(row.figure.actual)}</span></div>
        </article>
      `).join('')}
  `
}

function settingsPage(data) {
  const accounts = data.accounts.map((account) => `
    <li><span>${escapeHtml(account.name)}</span><span>${money(accountBalance(account, data.transactions))}</span></li>
  `).join('')
  const categories = data.categories
    .slice()
    .sort((left, right) => left.sort - right.sort)
    .map((category) => `<li class="${category.parentId ? 'child' : ''}"><span>${escapeHtml(category.name)}</span><span>${category.type === 'income' ? 'Поступление' : 'Расход'}</span></li>`)
    .join('')

  return `
    <main class="page frame">
      <a class="back" data-action="screen" data-screen="budget">← К периоду</a>
      <h1 class="page-title">Категории</h1>
      <p class="lead">Название можно поменять. Материнскую категорию можно назначить, сменить или убрать. Категорию вместе с её подкатегориями можно вложить в другую.</p>
      <section class="panel">
        <h2>Счета</h2>
        <ul class="plain-list">${accounts}</ul>
        ${state.form?.kind === 'account' ? `
          <form data-form="account">
            <input name="name" placeholder="Новый счёт" required>
            ${errorBox()}
            <div class="form-actions">
              <button class="text-button" type="button" data-action="cancel">Отмена</button>
              <button class="save" type="submit">Сохранить</button>
            </div>
          </form>
        ` : `<button class="wide-button" type="button" data-action="account">Создать новый счёт</button>`}
      </section>
      <section class="panel">
        <h2>Категории</h2>
        <ul class="plain-list">${categories}</ul>
        ${state.form?.kind === 'category' ? categoryForm(data, state.form.type || 'expense') : `<button class="wide-button" type="button" data-action="category" data-type="expense">Новая категория</button>`}
      </section>
    </main>
  `
}

function helpPage() {
  return `
    <main class="page frame">
      <a class="back" data-action="screen" data-screen="budget">← К бюджету</a>
      <h1 class="page-title">Помощь</h1>
      <section class="panel">
        <p class="lead">Бюджет месяца — это план и факт. В карточке слева уже прошедшая сумма, справа план. Полоска показывает, какая часть плана закрыта.</p>
        <p class="lead">Статистика показывает только факт за выбранные дни: сумму по категории, без плана, полоски и процента.</p>
        <p class="lead">Настройки — счета и категории. База этой программы лежит только на этом компьютере.</p>
      </section>
    </main>
  `
}

function errorBox() {
  return state.error ? `<p class="error">${escapeHtml(state.error)}</p>` : ''
}

async function onClick(event) {
  const button = event.target.closest('[data-action]')
  if (!button) {
    return
  }

  const action = button.dataset.action
  state.error = ''

  if (action === 'quit') {
    window.close()
    return
  } else if (action === 'screen') {
    state.screen = button.dataset.screen
    state.form = null
  } else if (action === 'stats') {
    state.statsRange = button.dataset.range
  } else if (action === 'account') {
    state.form = { kind: 'account' }
  } else if (action === 'month') {
    state.month = shiftMonth(state.month, Number(button.dataset.delta))
    state.form = null
  } else if (action === 'toggle') {
    const id = Number(button.dataset.id)
    state.open.has(id) ? state.open.delete(id) : state.open.add(id)
  } else if (action === 'plan') {
    state.form = state.form?.kind === 'plan' ? null : { kind: 'plan', type: 'expense' }
  } else if (action === 'plan-type') {
    state.form = { kind: 'plan', type: button.dataset.type }
  } else if (action === 'quick') {
    const type = button.dataset.type
    state.form = state.form?.kind === 'quick' && state.form.type === type ? null : { kind: 'quick', type }
  } else if (action === 'record') {
    state.form = { kind: 'record', lineId: Number(button.dataset.id) }
  } else if (action === 'category') {
    state.form = { kind: 'category', type: button.dataset.type }
  } else if (action === 'cancel') {
    state.form = null
  } else {
    return
  }

  await draw()
}

async function onChange(event) {
  const input = event.target
  if (input.name === 'statsFrom') {
    state.statsFrom = input.value
  } else if (input.name === 'statsTo') {
    state.statsTo = input.value
  } else if (input.name === 'category' && state.form?.kind === 'quick') {
    state.form = { ...state.form, categoryId: Number(input.value) }
  } else {
    return
  }

  await draw()
}

async function onSubmit(event) {
  const form = event.target.closest('form')
  if (!form) {
    return
  }

  event.preventDefault()
  const data = await loadAll(database)

  if (form.dataset.form === 'plan') {
    const category = data.categories.find((item) => item.id === Number(form.category.value) && item.type === state.form.type)
    const amount = parseAmount(form.amount.value)
    const date = form.date.value
    if (!category) {
      state.error = 'Выберите категорию.'
      await draw()
      return
    }
    if (amount === null) {
      state.error = 'Сумма должна быть больше нуля.'
      await draw()
      return
    }
    if (date && !date.startsWith(state.month)) {
      state.error = 'Дата должна быть в этом месяце.'
      await draw()
      return
    }
    await addRecord(database, 'planLines', {
      categoryId: category.id,
      month: state.month,
      title: form.title.value.trim() || category.name,
      amountCents: amount,
      expectedOn: date || null,
    })
    state.open.add(category.id)
  } else if (form.dataset.form === 'record' || form.dataset.form === 'quick') {
    const saved = await saveTransaction(form, data)
    if (!saved) {
      await draw()
      return
    }
  } else if (form.dataset.form === 'account') {
    const name = form.name.value.trim()
    if (!name) {
      state.error = 'Нужно название.'
      await draw()
      return
    }
    await addRecord(database, 'accounts', { name, openingCents: 0 })
  } else if (form.dataset.form === 'category') {
    const name = form.name.value.trim()
    if (!name) {
      state.error = 'Нужно название.'
      await draw()
      return
    }
    const parentId = form.parent.value ? Number(form.parent.value) : null
    await addRecord(database, 'categories', {
      type: state.form.type,
      name,
      parentId,
      sort: 100,
    })
    if (parentId) {
      state.open.add(parentId)
    }
  }

  state.form = null
  state.error = ''
  await draw()
}

async function saveTransaction(form, data) {
  const amount = parseAmount(form.amount.value)
  const accountId = Number(form.account.value)
  const account = data.accounts.find((item) => item.id === accountId)
  if (amount === null) {
    state.error = 'Сумма должна быть больше нуля.'
    return false
  }
  if (!account) {
    state.error = 'Выберите счёт.'
    return false
  }
  if (!form.date.value) {
    state.error = 'Укажите дату.'
    return false
  }

  let category
  let planLineId = null
  if (form.dataset.form === 'record') {
    const line = data.planLines.find((item) => item.id === state.form.lineId)
    category = data.categories.find((item) => item.id === line?.categoryId)
    planLineId = line?.id ?? null
    if (!line || !category) {
      state.error = 'Строка плана не найдена.'
      return false
    }
    if (!form.date.value.startsWith(state.month)) {
      state.error = 'Дата должна быть в открытом месяце.'
      return false
    }
  } else {
    category = data.categories.find((item) => item.id === Number(form.category.value) && item.type === state.form.type)
    if (!category) {
      state.error = 'Выберите категорию.'
      return false
    }
    const month = form.date.value.slice(0, 7)
    const lines = data.planLines.filter((line) => line.categoryId === category.id && line.month === month)
    if (lines.length > 1) {
      const line = lines.find((item) => item.id === Number(form.line.value))
      if (!line) {
        state.error = 'Выберите пункт плана.'
        return false
      }
      planLineId = line.id
    } else {
      planLineId = lines[0]?.id ?? null
    }
    state.month = month
  }

  await addRecord(database, 'transactions', {
    categoryId: category.id,
    planLineId,
    accountId: account.id,
    type: category.type,
    amountCents: amount,
    occurredOn: form.date.value,
    note: form.note.value.trim(),
  })

  return true
}

function defaultDate() {
  const today = todayKey()

  return today.startsWith(state.month) ? today : `${state.month}-01`
}

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  })[char])
}
