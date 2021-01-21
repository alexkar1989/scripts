<?php
define('URFA', '/netup/utm5/bin/utm5_urfaclient');
define('DBTYPE', 'mysql');
define('DBHOST', '192.168.1.1');
define('DBUSER', '');
define('DBPASS', '');
define('DBBASE', 'UTM5');
define('MAINACCOUNT', 59659);
$db = new PDO(DBTYPE . ":host=" . DBHOST . ";dbname=" . DBBASE, DBUSER, DBPASS);
/**
 * @return bool
 */
function isCommandLineInterface()
{
    return (php_sapi_name() === 'cli');
}

/**
 * @return false|string
 */
function getLastState()
{
    if (file_exists('state.txt')) return file_get_contents('state.txt');
    else false;
}

/**
 * @param $state
 */
function setLastState($state)
{
    file_put_contents('state.txt', $state);
}

function clearSuspend()
{
    file_put_contents('wassuspend.txt', null);
}

/**
 * @return array|bool
 */
function getSuspend()
{
    $res = array();
    if (file_exists('wassuspend.txt')) $file = fopen('wassuspend.txt', "r");
    else return false;
    while (($buffer = fgets($file, 20)) !== false) {
        if (empty($buffer)) continue;
        $res[] = (int)$buffer;
    }
    return $res;
}

/**
 * @return false|string
 */
function setSuspend($text)
{
    if (!file_exists('wassuspend.txt')) clearSuspend();
    $file = file_get_contents('wassuspend.txt');
    $file .= $text . "\n";
    file_put_contents('wassuspend.txt', $file);
}

/**
 * @param $err
 */
function debug($err)
{
    if (isCommandLineInterface()) {
        if (is_array($err)) {
            echo print_r($err, true);
        } else {
            echo print_r($err . "\n", true);
        }
    } else {
        echo '<pre>' . print_r($err, true) . '</pre>';
    }
}

/**
 * @return array
 */
function getListAccount()
{
    global $db;
    $data = array();
    $sth = $db->prepare("SELECT `linked_account` 
                         FROM `groups_account` 
                         WHERE `basic_account` = :accountId
        ");
    $sth->execute(array(
        'accountId' => MAINACCOUNT,
    ));
    $result = $sth->fetchAll(PDO::FETCH_ASSOC);
    if ($result) {
        foreach ($result as $res) {
            if ($res["linked_account"] != 0) {
                $data[] = $res["linked_account"];
            }
        }
    }
    return $data;
}

/**
 * @param $accId
 * @return mixed
 */
function getDateBlocking($accId)
{
    global $db;
    $query = "SELECT `start_date`                   
              FROM `blocks_info` 
              WHERE `account_id` = :accountId 
              AND `is_deleted` = 0
     ";
    if ($accId != MAINACCOUNT) {
        $query .= "AND `block_type` = 2";
    }
    $sth = $db->prepare($query);
    $sth->execute(array(
        'accountId' => $accId,

    ));
    $data = $sth->fetch(PDO::FETCH_ASSOC);
    return $data['start_date'];
}

/**
 * @param $accId
 * @return mixed
 */
function getBalance($accId)
{
    global $db;
    $sth = $db->prepare('SELECT `balance`, `credit` FROM `accounts` WHERE `id` = :accountId AND `is_deleted` = 0');
    $sth->execute(array(
        'accountId' => $accId,
    ));
    $result = $sth->fetch(PDO::FETCH_ASSOC);
    return $result['balance'] + $result['credit'];
}

/**
 * @return string
 */
function getNowState()
{
    $balance = getBalance(MAINACCOUNT);
    if ($balance < 0) return 'negative';
    elseif ($balance >= 0) return 'positive';
}

/**
 * @param $accId
 */
function setBloking($accId)
{
    shell_exec(URFA . ' -a set_suspence -account_id ' . $accId . ' -set 1');
}

/**
 * @param $accId
 */
function unsetBloking($accId)
{
    shell_exec(URFA . ' -a set_suspence -account_id ' . $accId . ' -set 0');
}
