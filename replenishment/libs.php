<?php
define('URFA', '/netup/utm5/bin/utm5_urfaclient');
define('DBTYPE', 'mysql');
define('DBHOST', '192.168.1.1');
define('DBUSER', 'webadmin');
define('DBPASS', 'riigahgheek7AeXoy9eu');
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
function getMainAccount()
{
    global $db;
    $sth = $db->query("SELECT `basic_account` 
                         FROM `groups_account` 
                         GROUP BY `basic_account`
        ")->fetchAll(PDO::FETCH_ASSOC);
    return $sth;
}

/**
 * @return array
 */
function getListAccount($accId)
{
    global $db;
    $data = array();
    $sth = $db->prepare("SELECT `linked_account` 
                         FROM `groups_account` 
                         WHERE `basic_account` = :accountId
        ");
    $sth->execute(array(
        'accountId' => $accId,
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
function getBalance($accId)
{
    global $db;
    $sth = $db->prepare('SELECT `balance`, `credit` FROM `accounts` WHERE `id` = :accountId AND `is_deleted` = 0');
    $sth->execute(array(
        'accountId' => $accId,
    ));
    $result = $sth->fetch(PDO::FETCH_ASSOC);
    return $result['balance'];
}

/**
 * @param $accId
 * @return string
 */
function getAmount($accId)
{
    $all_cost = 0;
    $result = shell_exec(URFA . ' -a get_allcost -account_id ' . $accId);
    $xml = new SimpleXMLElement($result);
    for ($i = 0; $i < count($xml->array); $i++) {
        $cost = $xml->array[$i]->dim[0];
        $all_cost += (int)$cost;
    }
    return round($all_cost / date("t")) + 1;
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
 * @param $amount
 */
function changeBalance($accId, $amount)
{
    shell_exec(URFA . ' -a change_balance -account_id ' . $accId . ' -payment ' . $amount);
}


