<?php

namespace YesWiki\Test\Accountactivationbyemail\Controller;

use YesWiki\Accountactivationbyemail\Exception\BadLogin;
use YesWiki\Accountactivationbyemail\Service\AccountActivationService;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** An account waiting for activation gets one mail, not one per page it loads. */
class AuthControllerTest extends YesWikiTestCase
{
    private const USER = 'ActivationLoopUser';

    private $wiki;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->auth()->logout();
        $this->dropUser();
        $this->wiki->services->get(UserManager::class)->create(self::USER, 'activation-loop@example.org', 'activation-loop-password');
    }

    protected function tearDown(): void
    {
        $this->auth()->logout();
        $this->dropUser();
    }

    public function testRestoringTheSessionOnEveryPageSendsASingleMail()
    {
        for ($page = 0; $page < 5; $page++) {
            $_SESSION['user'] = ['name' => self::USER, 'lastConnection' => time(), 'remember' => false];
            $this->auth()->connectUser();
            $this->assertEmpty($_SESSION['user'] ?? null);
        }

        $this->assertSame(1, $this->mailsSent());
    }

    public function testSigningInAgainResendsOnlyOnceTheDelayHasPassed()
    {
        $this->assertRefused();
        $this->assertRefused();
        $this->assertSame(1, $this->mailsSent());

        $tripleStore = $this->wiki->services->get(TripleStore::class);
        $tripleStore->delete(self::USER, AccountActivationService::TRIPLE_PROPERTY_MAIL_SENT_AT, null, '', '');
        $tripleStore->create(self::USER, AccountActivationService::TRIPLE_PROPERTY_MAIL_SENT_AT, (string)(time() - AccountActivationService::RESEND_DELAY), '', '');
        $this->assertRefused();

        $this->assertSame(2, $this->mailsSent());
    }

    public function testActivationClearsWhenTheLastMailWentOut()
    {
        $this->assertRefused();
        $service = $this->wiki->services->get(AccountActivationService::class);
        $service->activate(self::USER, '', true);

        $this->assertNull($this->wiki->services->get(TripleStore::class)->getOne(self::USER, AccountActivationService::TRIPLE_PROPERTY_MAIL_SENT_AT, '', ''));
    }

    private function assertRefused(): void
    {
        try {
            $this->auth()->login($this->wiki->services->get(UserManager::class)->getOneByName(self::USER));
            $this->fail('an account waiting for activation was signed in');
        } catch (BadLogin $refused) {
            $this->assertStringContainsString(_t('ACCOUNTACTIVATION_BY_EMAIL_WARNING', ['message' => '']), $refused->getMessage());
        }
    }

    private function mailsSent(): int
    {
        return count($this->wiki->services->get(TripleStore::class)->getAll(self::USER, AccountActivationService::TRIPLE_PROPERTY_ACTIVATION_KEY, '', ''));
    }

    private function auth(): AuthController
    {
        return $this->wiki->services->get(AuthController::class);
    }

    private function dropUser(): void
    {
        $userManager = $this->wiki->services->get(UserManager::class);
        if ($user = $userManager->getOneByName(self::USER)) {
            $userManager->delete($user);
        }
        $this->wiki->services->get(AccountActivationService::class)->forget(self::USER);
    }
}
