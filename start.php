#!/usr/bin/php
<?php
//define("TELESCRIPTS", TRUE);
define("DIR", __DIR__);
require_once('config/database.php');
require('libs/Libs.php');
require('libs/DataBase.php');

spl_autoload_register(function ($class_name) {
    if (file_exists(DIR . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . $class_name . '.php')) {
        require_once DIR . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . $class_name . '.php';
    }
});
$scripts = [
    'initSwitch' => 'Инициализация свитча',
    'rateLimit' => 'Записть скорость',
    'register' => 'Регистрация абонента на свитче',
];
//$id = 1;
$switch = false;
if (!isset($argv[1])) {
    foreach ($scripts as $script => $name) echo $script . " - " . $name . "\n";
    echo "\nВведите название скрипта для запуска (etc. exit): ";
    $method = fgets(STDIN);
    //$switch = true;
} else $method = $argv[1];

if ($method && array_key_exists($method, $scripts)) {
    spl_autoload_register(function ($class_name) {
        $class_name = str_replace('\\', '/', $class_name);
        if (file_exists(DIR . DIRECTORY_SEPARATOR . $class_name . '.php')) {
            require DIR . DIRECTORY_SEPARATOR . $class_name . '.php';
        }
    });
    require DIR . DIRECTORY_SEPARATOR . $method . DIRECTORY_SEPARATOR . 'index.php';
} else {
    echo "Не верно указан название скрипта!\n";
}







