<?php
require_once __DIR__ . '/config/database.php';
echo "== STUDENTS COLUMNS ==", PHP_EOL;
foreach (db()->query('SHOW COLUMNS FROM students') as $c) echo $c['Field'], ' | ', $c['Type'], PHP_EOL;
