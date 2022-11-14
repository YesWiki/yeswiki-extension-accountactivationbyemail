<?php

/*
 * This file is part of the YesWiki Extension accountactivationbyemail.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Accountactivationbyemail\Exception;

use Exception;
use YesWiki\Login\Exception\LoginException;

if (file_exists('tools/login/exceptions/LoginException.php')) {
    include_once('tools/login/exceptions/LoginException.php');
}

if (class_exists(LoginException::class, false)) {
    class BadLogin extends LoginException
    {
    }
} else {
    class BadLogin extends Exception
    {
    }
}
