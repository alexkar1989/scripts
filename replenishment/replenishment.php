<?php
require_once('libs.php');

$mainAccounts = getMainAccount();
foreach ($mainAccounts as $account) {
    $balance = getBalance($account['basic_account']);
    if ($balance > 0) {
        $list = getListAccount($account['basic_account']);
        foreach ($list as $accId) {
            $is_blocked = getDateBlocking($accId);
            if ($is_blocked) continue;
            $amount = getAmount($accId);
            $beforebalance = getBalance($accId);
            changeBalance($account['basic_account'], 0 - $amount);
            changeBalance($accId, $amount);
            $afterbalance = getBalance($accId);
            if (round($afterbalance, 2) != round($beforebalance + $amount, 2)) {
                debug('Final balance is incorrect');
                changeBalance($accId, 0 - $amount);
                changeBalance($account['basic_account'], $amount);
            }
        }
    }
}
