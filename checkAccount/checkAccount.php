<?php

require_once('libs.php');
$mainblock = 0;
$dateblock = 0;
$laststate = getLastState();
$wassuspend = getSuspend();

if ($laststate === false || $wassuspend === false) {
    $state = getNowState();
    setLastState($state);
    $list = getListAccount();
    clearSuspend();
    foreach ($list as $accId) {
        $dateblock = getDateBlocking($accId);
        if ($dateblock) {
            if ($state == 'negative') {
                $mainblock = getDateBlocking(MAINACCOUNT);
                if ($mainblock && $dateblock && $dateblock < $mainblock) {
                    setSuspend($accId);
                }

            } elseif ($state == 'positive') {
                if ($dateblock) setSuspend($accId);
            }
        }
    }
    die();
}

$state = getNowState();
if ($state) setLastState($state);
else die();

if ($laststate == $state) die();
else {
    if ($state == 'negative') {
        clearSuspend();
        $mainblock = getDateBlocking(MAINACCOUNT);
    }
    $list = getListAccount();
    foreach ($list as $accId) {
        $dateblock = getDateBlocking($accId);
        if ($mainblock && $dateblock && $dateblock < $mainblock) {
            setSuspend($accId);
            continue;
        }
        if (in_array($accId, $wassuspend)) continue;
        if ($state == 'positive') unsetBloking($accId);
        elseif ($state == 'negative') setBloking($accId);
        else exit();
    }
}
