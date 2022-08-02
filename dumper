/**
 * @param mixed ...$errs
 */
function dd(...$errs)
{
    if (call_user_func_array('dump', $errs)) exit();
}

/**
 * @param mixed ...$errs
 * @return bool
 */
function dump(...$errs)
{
    if (php_sapi_name() === 'cli') {
        foreach ($errs as $err) {
            if (is_array($err) || is_object($err)) {
                echo print_r($err, true);
            } else {
                echo print_r($err . "\n", true);
            }
        }
        return true;
    } else {
        if ((new Network(ADMIN_IPS))->checkIp($_SERVER['REMOTE_ADDR'])) {
            foreach ($errs as $err) {
                echo '<pre>' . print_r($err, true) . '</pre>';
            }
            return true;
        }
    }
    return false;
}
