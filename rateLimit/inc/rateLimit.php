<?php

namespace rateLimit\inc;

use libs\Database;
use libs\Libs;
use \PDO;

/**
 * Class Ratelimit
 */
class rateLimit
{
    /**
     * @var DataBase
     */
    private $utm;
    private $date;

    // public $ratio = 0;
    // public $type = 0;

    /**
     * Ratelimit constructor.
     */
    public function __construct()
    {
        $this->time = microtime(true);
        $this->date = date('H:i:s');
        $this->utm = new DataBase(DBTYPE, DBUTM_HOST_SLAVE, DBUTM_USER, DBUTM_PASS, DBUTM_NAME);
    }

    /**
     * @return array
     */
    private function getIpaddress()
    {
        return $this->utm->query("SELECT ip_groups.account_id, 
                                 INET_NTOA(ip_groups.ip & 0xFFFFFFFF) as ip,
                                 INET_NTOA(ip_groups.mask & 0xFFFFFFFF) as mask,
                                 services_data.tariff_id,      
                                 iptraffic_service_links.downloaded_id
                                 FROM ip_groups
                                    LEFT JOIN iptraffic_service_links ON iptraffic_service_links.ip_group_id = ip_groups.ip_group_id AND iptraffic_service_links.is_deleted = 0
                                    LEFT JOIN service_links ON service_links.id = iptraffic_service_links.id AND service_links.is_deleted = 0
                                    LEFT JOIN accounts ON accounts.id = service_links.account_id AND accounts.is_deleted = 0
                                    LEFT JOIN services_data ON services_data.id = service_links.service_id AND services_data.is_deleted = 0
                                 WHERE ip_groups.is_deleted = 0;
                            ")->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_OBJ);
    }

    /**
     *
     */
    private function writeRateLimit()
    {
        $result = $this->getIpaddress();
        $tariffs = $this->getTariffInfo();
        $downloads = $this->getDownloadTraffic();
        $groups = $this->getGroups();
        if ($result) {
            $in = fopen(__DIR__ . "/" . IN, 'w');
            $out = fopen(__DIR__ . "/" . OUT, 'w');
            foreach ($result as $var) {
                $ips = array();
                $mbytes = (int)(@$downloads[$var[0]->downloaded_id] / 1073741824); //1024^3
                $rate = @$this->getSpeed($tariffs[$var[0]->tariff_id], $mbytes) * 1024;
                if (@Libs::in_array_recursive(110001, $groups[$var[0]->id]) && $rate != (300 * 1024 * 1024)) $rate = 200 * 1024 * 1024;
                foreach ($var as $res) {
                    $ips[] = $res->ip . '/' . Libs::mask2cidr($res->mask);
                }
                $ips = implode(",", array_unique($ips));
                $this->writeTariff($in, $ips, $rate, IN);
                $this->writeTariff($out, $ips, $rate, OUT);
            }
            fclose($in);
            fclose($out);
        }
        Libs::debug(microtime(true) - $this->time);
    }


    /**
     * @return array
     */
    private function getGroups()
    {
        return $this->utm->query("SELECT `user_id`, `group_id` FROM `users_groups_link`")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * @return array
     */
    private function getDownloadTraffic()
    {
        return $this->utm->query("SELECT downloaded_id, SUM(qnt) as qnt
                                    FROM `downloaded`
                                    WHERE `downloaded`.`is_deleted` = '0'
                                        AND `downloaded`.`tclass_id` IN (10,20)
                                    GROUP BY downloaded_id")->fetchAll(PDO::FETCH_KEY_PAIR);

    }

    /**
     * @return array
     */
    private function getTariffInfo()
    {
        return $this->temp->query("SELECT `tariffid`,`speed`,`limit(G)`, `speedunlimit`,`timelimit_start`,`timelimit_end` 
                                    FROM `tarifflist`")->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE | PDO::FETCH_NUM);
    }

    /**
     * Получение текущей скорости тарифа
     * @param $tariffInfo
     * @param $mbytes
     * @return int|mixed
     */
    private function getSpeed($tariffInfo, $mbytes)
    {
        /** Если нет тарифа. */
        if (!$tariffInfo) return MAXSPEED;
        list($speed, $limit, $speedunlimit, $timelimit_start, $timelimit_end) = $tariffInfo;
        /** Если тариф без доступа в интернет. */
        if ($speed === '0') return 0;
        /** Если есть ограничения по времени. */
        if ($timelimit_start && $timelimit_end) $speed = $this->date >= $timelimit_start && $this->date <= $timelimit_end ? $speedunlimit : $speed;
        /** Если есть ограничения по скачаному трафику. */
        if ($limit) $speed = $mbytes < $limit ? $speedunlimit : $speed;
        /** Если ни одно из условий не выполнелось. */
        if (!$speed) return MAXSPEED;
        return $speed;
    }

    /**
     * @param $flink
     * @param $ip
     * @param $rate
     * @param $file
     */
    private function writeTariff($flink, $ip, $rate, $file)
    {
        /**
         * @ - Для обновления, если без - Нужно сперва удалить запись иначе выдаст ошибку
         */
        fwrite($flink, "echo +" . $ip . " " . $rate . " > /proc/net/ipt_ratelimit/" . $file . "\n");
    }

    /**
     * Запуск скрипта
     */
    public static function Run()
    {
        $rm = New rateLimit();
        $rm->writeRateLimit();
    }
}


