<?php

/* Page where an admin reviews an account and deletes it, the target of the Mattermost links. Part of the YesWiki extension accountactivationbyemail, see LICENSE. */

namespace YesWiki\Accountactivationbyemail;

use YesWiki\Accountactivationbyemail\Service\AccountActivationService;
use YesWiki\Accountactivationbyemail\Service\AccountCleaner;
use YesWiki\Accountactivationbyemail\Service\SignupSpamScore;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use YesWiki\Core\Controller\UserController;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiHandler;

class DeleteAccountHandler extends YesWikiHandler
{
    public function run()
    {
        if (!$this->wiki->UserIsAdmin()) {
            return $this->renderInSquelette('@accountactivationbyemail/delete-account.twig', [
                'login' => $this->wiki->Format('{{login signupurl="0"}}'),
            ]);
        }
        $name = trim((string)$this->getRequest()->query->get('username', ''));
        $user = $name === '' ? null : $this->getService(UserManager::class)->getOneByName($name);
        if (empty($user)) {
            return $this->alert('warning', _t('ACCOUNTACTIVATION_BY_EMAIL_DELETE_GONE', ['userName' => $name]));
        }

        $cleaner = $this->getService(AccountCleaner::class);
        $pages = $cleaner->ownedPages($user['name']);
        if ($this->getRequest()->isMethod('POST')) {
            $token = new CsrfToken('main', (string)$this->getRequest()->request->get('csrf-token', ''));
            if (!$this->getService(CsrfTokenManager::class)->isTokenValid($token)) {
                return $this->alert('danger', _t('CSRF_TOKEN_FAIL_ERROR'));
            }
            try {
                $this->getService(UserController::class)->delete($user);
            } catch (\Throwable $throwable) {
                return $this->alert('danger', $throwable->getMessage());
            }

            return $this->renderInSquelette('@accountactivationbyemail/delete-account.twig', [
                'deleted' => $user['name'],
                'pages' => $pages,
            ]);
        }

        $revisions = $cleaner->revisionsByUser()[$user['name']] ?? 0;

        return $this->renderInSquelette('@accountactivationbyemail/delete-account.twig', [
            'user' => $user,
            'activated' => $this->getService(AccountActivationService::class)->isActivated($user['name']),
            'revisions' => $revisions,
            'pages' => $pages,
            'groups' => $this->getService(UserManager::class)->groupsWhereIsMember($user, false),
            'verdict' => $this->getService(SignupSpamScore::class)->ofAccount($user->getArrayCopy(), $revisions),
        ]);
    }

    private function alert(string $type, string $message): string
    {
        return $this->renderInSquelette('@templates/alert-message.twig', ['type' => $type, 'message' => $message]);
    }
}
