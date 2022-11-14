<?php

/*
 * This file is part of the YesWiki Extension accountactivationbyemail.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Accountactivationbyemail;

use YesWiki\Core\Service\AssetsManager;
use YesWiki\Core\YesWikiAction;

class __UsersTableAction extends YesWikiAction
{
    public function run()
    {
        if ($this->wiki->UserIsAdmin() && $this->wiki->services->has(AssetsManager::class)) {
            $assetsManager = $this->getService(AssetsManager::class);
            $assetsManager->AddJavascriptFile('tools/accountactivationbyemail/javascripts/users-table-addon.js');
        }
    }
}
