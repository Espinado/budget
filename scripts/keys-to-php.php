<?php

$k = json_decode(file_get_contents('storage/i18n-keys.json'), true);
file_put_contents('storage/i18n-keys.php', '<?php return '.var_export($k, true).';');
echo count($k)."\n";
