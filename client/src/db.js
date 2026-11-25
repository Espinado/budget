const DB_NAME = 'family-money'
const DB_VERSION = 1

export function openDatabase() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION)

    request.onupgradeneeded = () => {
      const database = request.result
      database.createObjectStore('accounts', { keyPath: 'id', autoIncrement: true })
      database.createObjectStore('categories', { keyPath: 'id', autoIncrement: true })
      database.createObjectStore('planLines', { keyPath: 'id', autoIncrement: true })
      database.createObjectStore('transactions', { keyPath: 'id', autoIncrement: true })
    }

    request.onsuccess = () => resolve(request.result)
    request.onerror = () => reject(request.error)
  })
}

function readAll(database, name) {
  return new Promise((resolve, reject) => {
    const transaction = database.transaction(name, 'readonly')
    const result = transaction.objectStore(name).getAll()
    result.onsuccess = () => resolve(result.result)
    result.onerror = () => reject(result.error)
  })
}

function add(database, name, value) {
  return new Promise((resolve, reject) => {
    const transaction = database.transaction(name, 'readwrite')
    const result = transaction.objectStore(name).add(value)
    result.onsuccess = () => resolve(result.result)
    result.onerror = () => reject(result.error)
  })
}

export async function ensureSeed(database) {
  const categories = await readAll(database, 'categories')
  if (categories.length > 0) {
    return
  }

  await add(database, 'accounts', { name: 'Наличные', openingCents: 0 })
  await add(database, 'categories', { type: 'income', name: 'Доход', parentId: null, sort: 10 })
  await add(database, 'categories', { type: 'expense', name: 'Питание', parentId: null, sort: 20 })
  await add(database, 'categories', { type: 'expense', name: 'Транспорт', parentId: null, sort: 30 })
}

export async function loadAll(database) {
  const [accounts, categories, planLines, transactions] = await Promise.all([
    readAll(database, 'accounts'),
    readAll(database, 'categories'),
    readAll(database, 'planLines'),
    readAll(database, 'transactions'),
  ])

  return { accounts, categories, planLines, transactions }
}

export function addRecord(database, name, value) {
  return add(database, name, value)
}
