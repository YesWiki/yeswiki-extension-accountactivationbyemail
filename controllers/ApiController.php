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

use Symfony\Component\Routing\Annotation\Route;
use YesWiki\Accountactivationbyemail\Service\AccountActivationService;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiController;

class ApiController extends YesWikiController
{
    /**
     * @Route("/api/users",methods={"GET"}, options={"acl":{"public"}},priority=3)
     */
    public function getAllUsers($userFields = ['name', 'email', 'signuptime'])
    {
        $this->denyAccessUnlessAdmin();

        $users = $this->getService(UserManager::class)->getAll($userFields);

        $users = array_map(function ($user) use ($userFields) {
            if (!is_array($user)) {
                $user = $user->getArrayCopy();
            }
            return array_filter($user, function ($k) use ($userFields) {
                return in_array($k, $userFields);
            }, ARRAY_FILTER_USE_KEY);
        }, $users);

        $accountActivationService = $this->getService(AccountActivationService::class);
        $users = array_map(function ($user) use ($accountActivationService) {
            $user['isAdmin'] = $this->wiki->UserIsAdmin($user['name']);
            $user['activatedStatus'] = $accountActivationService->isActivated($user['name']);
            return $user;
        }, $users);

        return new ApiResponse($users);
    }

    /**
     * @Route("/api/emailactivation/{userId}/activate", methods={"POST"},options={"acl":{"public","@admins"}})
     */
    public function activateUser($userId)
    {
        $this->denyAccessUnlessAdmin();
        $this->getService(AccountActivationService::class)->activate($userId, "", true);
        return new ApiResponse(null);
    }

    /**
     * @Route("/api/emailactivation/{userId}/inactivate", methods={"POST"},options={"acl":{"public","@admins"}})
     */
    public function inactivateUser($userId)
    {
        $this->denyAccessUnlessAdmin();
        $this->getService(AccountActivationService::class)->inactivate($userId);
        return new ApiResponse(null);
    }
}
