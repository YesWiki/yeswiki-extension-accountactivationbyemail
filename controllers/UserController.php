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
use YesWiki\Accountactivationbyemail\Exception\DisposableEmail;
use YesWiki\Accountactivationbyemail\Service\AccountActivationService;
use YesWiki\Accountactivationbyemail\Service\DisposableEmailDomains;
use YesWiki\Core\Controller\UserController as CoreUserController;
use YesWiki\Core\Entity\User;
use YesWiki\Core\Exception\DeleteUserException;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\YesWikiController;

if (!class_exists(CoreUserController::class, false)) {
    class UserController extends YesWikiController
    {
    }
} else {
    class UserController extends CoreUserController
    {
        /**
         * create a user, refusing a throwaway mail address unless an admin is acting
         * @throws DisposableEmail
         * @throws Exception
         */
        public function create(array $newValues): ?User
        {
            if (
                filter_var($this->params->has('signup_refuse_disposable_email') ? $this->params->get('signup_refuse_disposable_email') : true, FILTER_VALIDATE_BOOL)
                && !$this->wiki->UserIsAdmin()
                && $this->getService(DisposableEmailDomains::class)->isDisposable((string)($newValues['email'] ?? ''))
            ) {
                throw new DisposableEmail(_t('ACCOUNTACTIVATION_BY_EMAIL_DISPOSABLE_EMAIL'));
            }

            return parent::create($newValues);
        }

        /**
         * delete a user but check if possible before
         * @param User $user
         * @throws DeleteUserException
         * @throws Exception
         */
        public function delete(User $user)
        {
            parent::delete($user);
            $tripleStore = $this->getService(TripleStore::class);
            foreach ([AccountActivationService::TRIPLE_PROPERTY_IS_ACTIVATED, AccountActivationService::TRIPLE_PROPERTY_ACTIVATION_KEY] as $property) {
                $tripleStore->delete($user['name'], $property, null, '', '');
            }
        }
    }
}
