<?php

/*
 * This file is part of the YesWiki Extension accountactivationbyemail.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Accountactivationbyemail\Controller;

use Exception;
use YesWiki\Accountactivationbyemail\Service\AccountActivationService;
use YesWiki\Core\Controller\UserController as CoreUserController;
use YesWiki\Core\Entity\User;
use YesWiki\Core\Exception\DeleteUserException;
use YesWiki\Core\YesWikiController;

if (!class_exists(CoreUserController::class, false)) {
    class UserController extends YesWikiController
    {
    }
} else {
    class UserController extends CoreUserController
    {
        /**
         * delete a user but check if possible before
         * @param User $user
         * @throws DeleteUserException
         * @throws Exception
         */
        public function delete(User $user)
        {
            parent::delete($user);
            // delete activation status
            $this->tripleStore->delete(
                $user['name'],
                AccountActivationService::TRIPLE_PROPERTY_IS_ACTIVATED,
                null,
                '',
                ''
            );
            $this->tripleStore->delete(
                $user['name'],
                AccountActivationService::TRIPLE_PROPERTY_ACTIVATION_KEY,
                null,
                '',
                ''
            );
        }
    }
}
