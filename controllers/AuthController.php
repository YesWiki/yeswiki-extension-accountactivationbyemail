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

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Accountactivationbyemail\Exception\BadLogin;
use YesWiki\Accountactivationbyemail\Service\AccountActivationService;
use YesWiki\Core\Controller\AuthController as CoreAuthController;
use YesWiki\Core\Service\PasswordHasherFactory;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiController;
use YesWiki\Security\Controller\SecurityController;
use Throwable;

if (!class_exists(CoreAuthController::class, false)) {
    class AuthController extends YesWikiController
    {
    }
} else {
    class AuthController extends CoreAuthController
    {
        protected $accountActivationService;

        public function __construct(
            ParameterBagInterface $params,
            PasswordHasherFactory $passwordHasherFactory,
            SecurityController $securityController,
            UserManager $userManager,
            AccountActivationService $accountActivationService
        ) {
            parent::__construct($params, $passwordHasherFactory, $securityController, $userManager);
            $this->accountActivationService = $accountActivationService;
        }

        /**
         * connect a user from SESSION or COOKIES
         */
        public function connectUser()
        {
            try {
                parent::connectUser();
            } catch (BadLogin $th) {
                flash($th->getMessage(), 'error');
                $this->logout();
            }
        }

        public function login($user, $remember = 0)
        {
            $userName = empty($user['name']) ? null : $user['name'];
            if (!$this->wiki->UserIsAdmin($userName) &&
                in_array($this->params->get('signup_email_activation'), [1,true,'1','true'], true) &&
                !$this->accountActivationService->isActivated($userName) &&
                (empty($GLOBALS['utilisateur_wikini']) || $GLOBALS['utilisateur_wikini'] != $userName)) {
                try {
                    $this->accountActivationService->sendActivationLink($userName);
                } catch (Throwable $th) {
                    throw new BadLogin(_t('ACCOUNTACTIVATION_BY_EMAIL_WARNING', ['message'=>_t('ACCOUNTACTIVATION_BY_EMAIL_MESSAGE_NOT_SENT')]));
                }
                throw new BadLogin(_t('ACCOUNTACTIVATION_BY_EMAIL_WARNING', ['message'=>_t('ACCOUNTACTIVATION_BY_EMAIL_MESSAGE_SENT')]));
            }
            parent::login($user, $remember);
        }

        protected function UserIsAdmin(?string $userName = null)
        {
            return $this->userManager->isInGroup(ADMIN_GROUP, $userName, false);
        }
    }
}
