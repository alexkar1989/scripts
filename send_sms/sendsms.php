<?php
require_once('config.php');
require_once('lib.php');

if (date("H:i") > '10:00' && date("H:i") < '21:00') SendSms::Run('Maxima+');
else Libs::debug('Не подходящее время');

class SendSms
{
    protected $libs;
    protected $basic_account;
    private $utm;
    public $balance_barrier = "Да"; //наименование вида смс информирования
    public $days_before_block = 2;
    public $arenda_stb_discount = array(478);
    public $sender;

    /**
     * SendSms constructor.
     * @param $sender
     */
    public function __construct($sender)
    {
        $this->sender = $sender;
        $this->utm = new DataBase(DBTYPE, DBHOST, DBUSER, DBPASS, DBBASE);
        $this->sms = new MegafonSMS('<login>', '<pass>');
    }

    /**
     *
     */
    private function send()
    {
        $sth = $this->utm->prepare("SELECT `users`.`basic_account`,
                                        `users`.`sms_sended`,
                                        `users`.`mobile_telephone`,
                                        ROUND(`accounts`.`balance`, 0) as `balance`,
                                        ROUND(SUM(`periodic_services_data`.`cost`)/30, 2) as `discount`,
                                        ROUND(`accounts`.`balance` + `accounts`.`credit`, 2) as `amount`
                                    FROM `users`
                                    LEFT JOIN `accounts` ON `accounts`.`id` = `users`.`basic_account`
                                        AND `accounts`.`is_deleted` = 0
                                        AND `accounts`.`is_blocked` = 0
                                    LEFT JOIN `user_additional_params` ON `user_additional_params`.`userid` = `users`.`id`
                                    LEFT JOIN `service_links` ON `service_links`.`account_id` = `users`.`basic_account`
                                        AND `service_links`.`is_deleted` = 0
                                    LEFT JOIN `periodic_services_data` ON `service_links`.`service_id` = `periodic_services_data`.`id`
                                        AND `periodic_services_data`.`is_deleted` = 0
                                    WHERE `user_additional_params`.`paramid` = :sms_paramid
                                        AND `user_additional_params`.`value` LIKE :balance_barrier
                                        AND `users`.`is_deleted` = 0
                                    GROUP BY `users`.`id`
        ");

        $sth->execute(array(
            'sms_paramid' => SMSPARAMID,
            'balance_barrier' => '%' . $this->balance_barrier . '%',
        ));
        $result = $sth->fetchAll(PDO::FETCH_ASSOC);

        foreach ($result as $row) {
            $this->basic_account = $row['basic_account'];
            $balance = $row['balance'];
            $mobile_telephones = $row['mobile_telephone'];

            if ($row['amount'] < ($row['discount'] * $this->days_before_block)) {
                if ($row['sms_sended'] == 0) {
                    $smsText = 'На Вашем лицевом счете ' . $this->basic_account . ' осталось ' . $balance . ' руб. Maxima';
                    $phones = Libs::validate($mobile_telephones, 'PHONE_NUMBER');
                    if ($phones) {
                        foreach ($phones as $phone) {
                            Libs::writeLog("Отправляем SMS, заносим в базу. Л.С.: " . $this->basic_account . "; Телефон;" . $phone . "; Баланс: " . $balance . "; Отправлена: " . $row['sms_sended'] . "; Снимается в день: " . $row['discount']);
                            $this->sms->post_message($smsText, $phone, $this->sender);
                            $sth = $this->utm->prepare("INSERT INTO `sms_sended` (`account_id`,`phone_number`) VALUES (:basic_account, :phone)");
                            $sth->execute(array(
                                'basic_account' => $this->basic_account,
                                'phone' => $phone,
                            ));
                        }
                        $sth = $this->utm->prepare("UPDATE `users` SET `sms_sended` = 1 WHERE `basic_account` = :basic_account");
                        $sth->execute(array(
                            'basic_account' => $this->basic_account,
                        ));
                    }
                }
            } else {
                if ($row['sms_sended'] == 1) {
                    Libs::writeLog("Удаляем из базы. Л.С.: " . $this->basic_account . "; Баланс: " . $balance . "; Отправлена: " . $row['sms_sended'] . "; Снимается в день: " . $row['discount']);
                    $sth = $this->utm->prepare("UPDATE `users` SET `sms_sended` = 0 WHERE `basic_account` = :basic_account");
                    $sth->execute(array(
                        'basic_account' => $this->basic_account,
                    ));
                }
            }
        }
    }

    /**
     * Отчистка пользователя который отказался от услуги смс, но смс было отослана
     */
    private function clearSmsSended()
    {
        $sth = $this->utm->prepare("SELECT `users`.`id`
                                    FROM `users`
                                    LEFT JOIN `user_additional_params` ON `user_additional_params`.`userid` = `users`.`id`
                                    WHERE `user_additional_params`.`paramid` = :sms_paramid
                                        AND `user_additional_params`.`value` NOT LIKE :balance_barrier
                                        AND `users`.`is_deleted` = 0
                                        AND `users`.`sms_sended` = 1
        ");
        $sth->execute(array(
            'sms_paramid' => SMSPARAMID,
            'balance_barrier' => '%' . $this->balance_barrier . '%',
        ));
        $result = $sth->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $res) {
                $sth = $this->utm->prepare("UPDATE `users` SET `users`.`sms_sended` = 0 WHERE `users`.`id` = :id");
                $sth->execute(array('id' => $res['id']));
                Libs::writeLog('Отчистка пользователя который отказался от услуги смс, но смс было отослана. Id: ' . $res['id']);
            }
        }
    }

    /**
     * @param $sender
     */
    static function Run($sender)
    {
        $sms = new SendSms($sender);
        $sms->send();
        $sms->clearSmsSended();
    }

}


