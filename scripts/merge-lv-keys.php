<?php

$path = 'lang/lv.json';
$map = json_decode(file_get_contents($path), true);
$extra = [
    'Удалить раздел «:name» и все коды внутри? Введите пароль аккаунта.' => 'Dzēst sadaļu «:name» un visus kodus tajā? Ievadiet konta paroli.',
    'Удалить код «:name»? Введите пароль аккаунта.' => 'Dzēst kodu «:name»? Ievadiet konta paroli.',
    'Скрыть код' => 'Slēpt kodu',
    'Показать код' => 'Rādīt kodu',
    'Почта для входа: :email. Для смены нужен текущий пароль.' => 'Pieteikšanās e-pasts: :email. Lai mainītu, vajadzīga pašreizējā parole.',
    'Удалить счёт «:name»?' => 'Dzēst kontu «:name»?',
    'Удалить «:name»?' => 'Dzēst «:name»?',
    'Удалить операцию :amount? Сумма вернётся на счёт.' => 'Dzēst operāciju :amount? Summa atgriezīsies kontā.',
    'Убрать «:title» из плана?' => 'Noņemt «:title» no plāna?',
];

foreach ($extra as $k => $v) {
    $map[$k] = $v;
}

ksort($map, SORT_STRING);
file_put_contents($path, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
echo count($map)." keys in lv.json\n";
foreach (['Войти', 'Помощь', 'Копилка', 'Добавить приход'] as $k) {
    echo "$k => ".($map[$k] ?? 'MISSING')."\n";
}
