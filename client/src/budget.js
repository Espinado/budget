export function descendantIds(categories, id) {
  const ids = [id]

  for (const category of categories) {
    if (category.parentId === id) {
      ids.push(...descendantIds(categories, category.id))
    }
  }

  return ids
}

export function totals(categories, planLines, transactions, categoryId, month) {
  const ids = new Set(descendantIds(categories, categoryId))
  const planned = planLines
    .filter((line) => line.month === month && ids.has(line.categoryId))
    .reduce((sum, line) => sum + line.amountCents, 0)
  const actual = transactions
    .filter((row) => row.occurredOn.startsWith(month) && ids.has(row.categoryId))
    .reduce((sum, row) => sum + row.amountCents, 0)

  return { planned, actual }
}

export function accountBalance(account, transactions) {
  return transactions.reduce((balance, row) => {
    if (row.accountId !== account.id) {
      return balance
    }

    return balance + (row.type === 'income' ? row.amountCents : -row.amountCents)
  }, account.openingCents)
}
