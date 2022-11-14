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

use YesWiki\Accountactivationbyemail\Exception\BadKey;
use YesWiki\Accountactivationbyemail\Exception\BadUserName;
use YesWiki\Accountactivationbyemail\Service\AccountActivationService;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiHandler;

class EmailActivationHandler extends YesWikiHandler
{
    public function run()
    {
        // get Services
        $accountActivationService = $this->getService(AccountActivationService::class);
        $userManager = $this->getService(UserManager::class);

        try {
            // get params
            $userName = filter_input(INPUT_GET, 'username', FILTER_UNSAFE_RAW);
            $userName = in_array($userName, [false,null], true) ? "" : $userName;
            if (empty($userName)) {
                throw new BadUserName(_t('ACCOUNTACTIVATION_BY_EMAIL_EMPTY_USERNAME'));
            }
            $key = filter_input(INPUT_GET, 'key', FILTER_UNSAFE_RAW);
            $key = in_array($key, [false,null], true) ? "" : $key;
            if (empty($key)) {
                throw new BadKey(_t('ACCOUNTACTIVATION_BY_EMAIL_EMPTY_KEY'));
            }
            $currentUser = $userManager->getLoggedUser();
            if (!empty($currentUser['name']) &&
                $userName == $currentUser['name'] &&
                $accountActivationService->isActivated($currentUser['name'])) {
                return $this->renderInSquelette('@templates/alert-message.twig', [
                    'type' => 'success',
                    'message' => _t('ACCOUNTACTIVATION_BY_EMAIL_ALREADY_ACTIVATED')
                ]);
            }
            $accountActivationService->activate($userName, $key);
            return $this->renderInSquelette('@templates/alert-message.twig', [
                'type' => 'primary',
                'message' => _t('ACCOUNTACTIVATION_BY_EMAIL_ACTIVATION_SUCCESS')
            ]);
        } catch (BadUserName | BadKey $th) {
            return $this->renderInSquelette('@templates/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('ACCOUNTACTIVATION_BY_EMAIL_ACTIVATION_ERROR', ['error'=>$th->getMessage()])
            ]);
        }
    }
}
