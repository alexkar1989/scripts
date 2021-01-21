<?php
define('MAXSPEED', 1024000);
define('IN', 'RS-in');
define('OUT', 'RS-out');

use rateLimit\inc\rateLimit;

rateLimit::Run();
