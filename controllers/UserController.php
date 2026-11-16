<?php

/* Refuses throwaway addresses and robot-like names at signup, and cleans activation data when an account goes. Part of the YesWiki extension accountactivationbyemail, see LICENSE. */

namespace YesWiki\Accountactivationbyemail\Controller;

use YesWiki\Accountactivationbyemail\Exception\DisposableEmail;
use YesWiki\Accountactivationbyemail\Exception\SpamAccount;
use YesWiki\Accountactivationbyemail\Service\AccountActivationService;
use YesWiki\Accountactivationbyemail\Service\AccountCleaner;
use YesWiki\Accountactivationbyemail\Service\DisposableEmailDomains;
use YesWiki\Accountactivationbyemail\Service\SignupNotifier;
use YesWiki\Accountactivationbyemail\Service\SignupSpamScore;
use YesWiki\Core\Controller\UserController as CoreUserController;
use YesWiki\Core\Entity\User;
use YesWiki\Core\YesWikiController;

if (!class_exists(CoreUserController::class, false)) {
    class UserController extends YesWikiController
    {
    }
} else {
    class UserController extends CoreUserController
    {
        /** Create a user, refusing throwaway addresses and robot-like names unless an admin is acting. */
        public function create(array $newValues): ?User
        {
            $verdict = null;
            if (!$this->wiki->UserIsAdmin()) {
                $email = (string)($newValues['email'] ?? '');
                if ($this->isOn('signup_refuse_disposable_email') && $this->getService(DisposableEmailDomains::class)->isDisposable($email)) {
                    throw new DisposableEmail(_t('ACCOUNTACTIVATION_BY_EMAIL_DISPOSABLE_EMAIL'));
                }
                $score = $this->getService(SignupSpamScore::class);
                $verdict = $score->of((string)($newValues['name'] ?? ''), $email);
                if ($this->isOn('signup_refuse_spam') && $score->isSpam($verdict)) {
                    throw new SpamAccount($this->spamMessage());
                }
            }

            $user = parent::create($newValues);
            if (!empty($user)) {
                if ($verdict !== null) {
                    $this->getService(SignupNotifier::class)->suspicious($user, $verdict);
                }
                try {
                    $this->getService(AccountCleaner::class)->purgeStale();
                } catch (\Throwable $throwable) {
                    error_log('accountactivationbyemail: purge: ' . $throwable->getMessage());
                }
            }

            return $user;
        }

        /** Delete a user and what this extension stored about it. */
        public function delete(User $user)
        {
            parent::delete($user);
            $this->getService(AccountActivationService::class)->forget($user['name']);
        }

        private function isOn(string $key): bool
        {
            return filter_var($this->params->has($key) ? $this->params->get($key) : true, FILTER_VALIDATE_BOOL);
        }

        private function spamMessage(): string
        {
            $contact = '';
            foreach (['signup_spam_contact', 'yeswiki-farm-admin-email'] as $key) {
                $contact = $contact !== '' ? $contact : ($this->params->has($key) ? trim((string)$this->params->get($key)) : '');
            }

            return $contact === ''
                ? _t('ACCOUNTACTIVATION_BY_EMAIL_SPAM_REFUSED')
                : _t('ACCOUNTACTIVATION_BY_EMAIL_SPAM_REFUSED_CONTACT', ['contact' => $contact]);
        }
    }
}
