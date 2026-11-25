export function money(cents) {
  const sign = cents < 0 ? '−' : ''
  const absolute = Math.abs(cents)
  const whole = Math.trunc(absolute / 100)
  const fraction = String(absolute % 100).padStart(2, '0')
  const grouped = String(whole).replace(/\B(?=(\d{3})+(?!\d))/g, ' ')

  return `${sign}${grouped},${fraction} €`
}

export function parseAmount(value, allowZero = false) {
  const cleaned = String(value ?? '').trim().replace(/\s/g, '').replace('.', ',')
  if (!/^\d+(,\d{1,2})?$/.test(cleaned)) {
    return null
  }

  const [whole, fraction = ''] = cleaned.split(',')
  const cents = Number(whole) * 100 + Number(fraction.padEnd(2, '0'))

  if (cents > 0 || (allowZero && cents === 0)) {
    return cents
  }

  return null
}

export function monthKey(date = new Date()) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')

  return `${year}-${month}`
}

export function shiftMonth(key, delta) {
  const [year, month] = key.split('-').map(Number)
  const date = new Date(year, month - 1 + delta, 1)

  return monthKey(date)
}

export function monthTitle(key) {
  const [year, month] = key.split('-').map(Number)
  const name = new Date(year, month - 1, 1).toLocaleDateString('ru-RU', { month: 'long' })

  return `${name.charAt(0).toUpperCase()}${name.slice(1)} ${year}`
}

export function monthSpan(key) {
  const [year, month] = key.split('-').map(Number)
  const start = new Date(year, month - 1, 1)
  const end = new Date(year, month, 0)
  const day = (date) => {
    const number = String(date.getDate()).padStart(2, '0')
    const month = String(date.getMonth() + 1).padStart(2, '0')

    return `${number}.${month}.${date.getFullYear()}`
  }
  const today = new Date()
  const current = todayKey()
  let note = 'Период уже закончился'
  let elapsed = end.getDate()

  if (current.startsWith(key)) {
    elapsed = today.getDate()
    const remaining = end.getDate() - elapsed
    note = `день ${elapsed} из ${end.getDate()}`
    if (remaining > 0) {
      note += ` · ещё ${ruDays(remaining)}`
    }
  } else if (today < start) {
    elapsed = 0
    note = 'Период ещё не начался'
  }

  return {
    label: `${day(start)} — ${day(end)} · ${note}`,
    percent: Math.round(elapsed * 100 / end.getDate()),
  }
}

function ruDays(count) {
  const mod10 = count % 10
  const mod100 = count % 100
  if (mod10 === 1 && mod100 !== 11) {
    return `${count} день`
  }
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
    return `${count} дня`
  }
  return `${count} дней`
}

export function isoDate(date) {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${date.getFullYear()}-${month}-${day}`
}

export function monthBounds(key) {
  const [year, month] = key.split('-').map(Number)
  const last = new Date(year, month, 0).getDate()

  return [`${key}-01`, `${key}-${String(last).padStart(2, '0')}`]
}

export function weekBounds(date = new Date()) {
  const day = date.getDay()
  const monday = new Date(date)
  monday.setDate(date.getDate() + (day === 0 ? -6 : 1 - day))
  const sunday = new Date(monday)
  sunday.setDate(monday.getDate() + 6)

  return [isoDate(monday), isoDate(sunday)]
}

export function monthsBetween(from, to) {
  const months = []
  let cursor = from.slice(0, 7)
  const end = to.slice(0, 7)

  while (cursor <= end) {
    months.push(cursor)
    cursor = shiftMonth(cursor, 1)
  }

  return months
}

export function formatDay(value) {
  const match = String(value ?? '').match(/^(\d{4})-(\d{2})-(\d{2})$/)

  return match ? `${match[3]}.${match[2]}.${match[1]}` : ''
}

export function parseDay(value) {
  const text = String(value ?? '').trim()
  const dotted = text.match(/^(\d{2})\.(\d{2})\.(\d{4})$/)

  if (dotted) {
    return realIso(Number(dotted[3]), Number(dotted[2]), Number(dotted[1]), dotted[3], dotted[2], dotted[1])
  }

  const iso = text.match(/^(\d{4})-(\d{2})-(\d{2})$/)

  if (!iso) {
    return null
  }

  return realIso(Number(iso[1]), Number(iso[2]), Number(iso[3]), iso[1], iso[2], iso[3])
}

function realIso(year, month, day, yearText, monthText, dayText) {
  const date = new Date(year, month - 1, day)

  if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
    return null
  }

  return `${yearText}-${monthText}-${dayText}`
}

export function formatRuDate(value) {
  return formatDay(value)
}

export function todayKey() {
  const date = new Date()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${date.getFullYear()}-${month}-${day}`
}
