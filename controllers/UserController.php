<?php

/* Refuses throwaway mail addresses at signup and cleans activation data when an account goes. Part of the YesWiki extension accountactivationbyemail, see LICENSE. */

namespace YesWiki\Accountactivationbyemail\Controller;

use Exception;
use YesWiki\Accountactivationbyemail\Exception\DisposableEmail;
use YesWiki\Accountactivationbyemail\Service\AccountActivationService;
use YesWiki\Accountactivationbyemail\Service\DisposableEmailDomains;
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
        /** Create a user, refusing a throwaway mail address unless an admin is acting. */
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

        /** Delete a user and what this extension stored about it. */
        public function delete(User $user)
        {
            parent::delete($user);
            $this->getService(AccountActivationService::class)->forget($user['name']);
        }
    }
}
