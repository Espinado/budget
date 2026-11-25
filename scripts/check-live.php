<?php

foreach (['storage/live-lv-login.html', 'storage/live-ru-login.html', 'storage/live-lv-help.html'] as $f) {
    $c = file_get_contents($f);
    echo $f.PHP_EOL;
    foreach (['lang="lv"', 'lang="ru"', 'Ienākt', 'Войти', 'Palīdzība', 'Помощь', 'Saturs', 'Содержание'] as $n) {
        echo '  '.$n.': '.(str_contains($c, $n) ? 'yes' : 'no').PHP_EOL;
    }
}
