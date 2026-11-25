<?php

$j = json_decode(file_get_contents('lang/lv.json'), true);
foreach (['Регистрация', 'Содержание', 'Помощь', 'Сводка', 'Всего денег на счетах'] as $k) {
    echo "$k => ".($j[$k] ?? 'MISSING')."\n";
}
