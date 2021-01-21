<?php

/**
 * Class Libs
 */
class Libs
{
    /**
     * @param $var
     * @param string $type
     * @return array|int|string|string[]|null
     */
    public static function validate($var, $type = '')
    {
        $var = is_array($var) ? $var : trim(htmlspecialchars($var));
        $result = "";
        if (empty($var)) return $result;

        switch ($type) {
            case 'PHONE_NUMBER':
                $var = preg_replace('/[^\d+-;, ]/', '', $var);
                preg_match_all('/((8|\+7)[\- ]?)?(\(?\d{3}\)?[\- ]?)?[\d\- ]{7,10}/', $var, $matchesall);
                $result = array();
                foreach ($matchesall[0] as $matches) {
                    $num = preg_replace('/[^\d]/', '', $matches);
                    if (strlen($num) == 10) $num = '7' . $num;
                    if (strlen($num) != 11) continue;
                    $result[] = $num;
                }
                break;
            case 'EMAIL':
                if (preg_match('/^([a-z0-9_-]+\.)*[a-z0-9_-]+@[a-z0-9_-]+(\.[a-z0-9_-]+)*\.[a-z]{2,6}$/', $var)) $result = $var;
                break;
            case 'INTEGER':
            case 'INT':
                $result = (int)$var;
                break;
            case 'MAC':
            case 'MAC_ADDRESS':
                $var = preg_replace('/[^0-9a-f]/', '', strtolower($var));
                if (strlen($var) != 12) {
                    break;
                }
                $var = wordwrap($var, 2, ":", 1);
                $result = $var;
                break;
            case 'IP_ADDRESS':
                if (preg_match('/^(2[0-4]\d|25[0-5]|1\d\d|\d\d?)(\.(?1)){3}$/', $var)) $result = $var;
                break;
            case 'ADDRESS':
                if (preg_match('/^(.+)\s+(\S+?)-([\dа-яА-Яa-zA-Z\/]+)$/', $var)) $result = $var;
                break;
            case 'ARRAY':
                $result = is_array($var) ? $var : array($var);
                break;
            case 'LOGIN':
                if (preg_match('/^[a-z0-9_\-]*$/', $var)) $result = $var;
                break;
            case 'PASSWORD':
                if (preg_match('/^[0-9a-zA-Z!@#$%^&*_\-]*$/', $var)) $result = $var;
                break;
            default:
                $result = $var;
        }
        return $result;
    }

    /**
     * @param $err
     * @param bool $exit
     */
    public static function debug($err, $exit = false)
    {
        if (php_sapi_name() === 'cli') {
            if (is_array($err)) {
                echo print_r($err, true);
            } else {
                echo print_r($err . "\n", true);
            }
        } else {
            if ($_SERVER['REMOTE_ADDR'] == '172.29.128.199' || $_SERVER['REMOTE_ADDR'] == '172.24.130.240') {
                echo '<pre>' . print_r($err, true) . '</pre>';
                if ($exit) exit();
            }
        }
    }

    /**
     * @param $message
     */
    public static function writeLog($message)
    {
        $file = fopen(LOG, 'a+');
        fwrite($file, date('d.m.Y H:i:s') . ' ' . $message . "\n");
        fclose($file);
    }

}

/**
 * Class MegafonSMS
 */
class MegafonSMS
{
    /**
     * @var bool|string
     */
    var $user = 'xxxx';           // ваш логин в системе
    var $pass = 'xxx';            // ваш пароль в системе
    var $hostname = 'a2p-api.megalabs.ru';            // host замените на адрес сервера указанный в меню "Поддержка -> протокол HTTP"
    var $path = '/sms/v1/sms';

    /**
     * MegafonSMS constructor.
     *
     * @param bool $user
     * @param bool $pass
     * @param bool $hostname
     */
    function __construct($user = false, $pass = false, $hostname = false)
    {
        if ($user) $this->user = $user;
        if ($pass) $this->pass = $pass;
        if ($hostname) $this->hostname = $hostname;
    }

    /**
     * рассылка смс [mes] по телефонам [target]
     *
     * @param $mes
     * @param $target
     * @param null $sender
     * @return false|string
     */
    function post_message($mes, $target, $sender = null)
    {
        $target = (int)$target;
        return $this->post_mes($mes, $target, $sender);
    }

    /**
     * @param $mes
     * @param $target
     * @param $sender
     * @return false|string
     */
    function post_mes($mes, $target, $sender)
    {
        $in = array(
            'from' => $sender,
            'to' => $target,
            'message' => $mes
        );
        return $this->get_post_request($in);
    }

    /**
     * Запрос на сервер и получение результата
     *
     * @param $invars
     * @return false|string
     */
    function get_post_request($invars)
    {
        $auth = base64_encode($this->user . ":" . $this->pass);
        $nn = "\r\n";

        $options = [
            'http' => [
                'header' => "Authorization: Basic " . $auth . $nn . "Content-type: application/json;charset=utf-8" . $nn,
                'method' => 'POST',
                'content' => json_encode($invars)
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents("https://" . $this->hostname . $this->path, false, $context);
        return $result;
    }
}

/**
 * Class DataBase
 */
class DataBase extends PDO
{
    /**
     * DataBase constructor.
     * @param $dbtype
     * @param $dbhost
     * @param $dbuser
     * @param $dbpass
     * @param $dbname
     */
    public function __construct($dbtype, $dbhost, $dbuser, $dbpass, $dbname)
    {
        try {
            parent::__construct($dbtype . ":host=" . $dbhost . ";dbname=" . $dbname, $dbuser, $dbpass);
            parent::exec('SET NAMES utf8');
        } catch (Exception $e) {
            Libs::debug($e->getMessage());
        }
    }
}