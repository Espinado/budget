<?php

$j = json_decode(file_get_contents('lang/lv.json'), true);
echo count($j).PHP_EOL;
foreach (['Войти', 'Помощь', 'Настройки', 'Хранение конфиденциальной информации', 'Добавить приход', 'Сводка', '← К бюджету', 'день', 'дней', 'Копилка'] as $k) {
    echo $k.' => '.($j[$k] ?? 'MISSING').PHP_EOL;
}
