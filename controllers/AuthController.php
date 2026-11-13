<?php

/* Refuses to sign in an account that is not activated yet, and mails it an activation link. Part of the YesWiki extension accountactivationbyemail, see LICENSE. */

namespace YesWiki\Accountactivationbyemail\Controller;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Accountactivationbyemail\Exception\BadLogin;
use YesWiki\Accountactivationbyemail\Service\AccountActivationService;
use YesWiki\Core\Controller\AuthController as CoreAuthController;
use YesWiki\Core\Service\PasswordHasherFactory;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiController;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Wiki;
use Throwable;

if (!class_exists(CoreAuthController::class, false)) {
    class AuthController extends YesWikiController
    {
    }
} else {
    class AuthController extends CoreAuthController
    {
        protected $accountActivationService;
        private $restoringSession = false;

        public function __construct(
            ParameterBagInterface $params,
            PasswordHasherFactory $passwordHasherFactory,
            SecurityController $securityController,
            UserManager $userManager,
            AccountActivationService $accountActivationService,
            Wiki $wiki
        ) {
            parent::__construct($params, $passwordHasherFactory, $securityController, $userManager, $wiki);
            $this->accountActivationService = $accountActivationService;
            $this->wiki = $wiki;
        }

        /** Restore the user of this request from the session or the cookies. */
        public function connectUser()
        {
            $this->restoringSession = true;
            try {
                parent::connectUser();
            } catch (BadLogin $th) {
                Flash::error($th->getMessage());
                $this->logout();
            } finally {
                $this->restoringSession = false;
            }
        }

        public function login($user, $remember = 0)
        {
            $userName = empty($user['name']) ? null : $user['name'];
            if (!$this->hasLoginExtensions() &&
                !$this->wiki->UserIsAdmin($userName) &&
                in_array($this->params->get('signup_email_activation'), [1,true,'1','true'], true) &&
                !$this->accountActivationService->isActivated($userName) &&
                (empty($GLOBALS['utilisateur_wikini']) || $GLOBALS['utilisateur_wikini'] != $userName)) {
                throw new BadLogin(_t('ACCOUNTACTIVATION_BY_EMAIL_WARNING', ['message' => $this->mailActivationLink($userName)]));
            }
            parent::login($user, $remember);
        }

        /** Send the activation mail unless one went out too recently, and say which happened. */
        private function mailActivationLink(string $userName): string
        {
            if (!$this->accountActivationService->maySendActivationLink($userName, $this->restoringSession)) {
                return _t('ACCOUNTACTIVATION_BY_EMAIL_MESSAGE_ALREADY_SENT');
            }
            try {
                $this->accountActivationService->sendActivationLink($userName);
            } catch (Throwable $th) {
                return _t('ACCOUNTACTIVATION_BY_EMAIL_MESSAGE_NOT_SENT');
            }
            return _t('ACCOUNTACTIVATION_BY_EMAIL_MESSAGE_SENT');
        }

        protected function UserIsAdmin(?string $userName = null)
        {
            return $this->userManager->isInGroup(ADMIN_GROUP, $userName, false);
        }

        protected function hasLoginExtensions(): bool
        {
            return array_key_exists('logincas', $this->wiki->extensions) ||
                array_key_exists('loginldap', $this->wiki->extensions) ||
                array_key_exists('login-sso', $this->wiki->extensions);
        }
    }
}
