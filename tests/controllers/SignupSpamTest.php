<?php

namespace YesWiki\Test\Accountactivationbyemail\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use YesWiki\Accountactivationbyemail\Exception\SpamAccount;
use YesWiki\Accountactivationbyemail\Service\AccountActivationService;
use YesWiki\Accountactivationbyemail\Service\AccountCleaner;
use YesWiki\Accountactivationbyemail\Service\SignupNotifier;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Controller\UserController;
use YesWiki\Core\Exception\ExitException;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A robot is refused at signup, a stranger's address is posted with a deletion link, an unactivated account goes after three days. */
class SignupSpamTest extends YesWikiTestCase
{
    private const NAMES = ['tk88cards1', 'BerthaGwinn', 'SpamTestWaiting', 'SpamTestActive', 'SpamTestWriter', 'SpamTestLegacy'];
    private const PAGE = 'SpamTestWriterPage';

    private $wiki;
    private $previousRequest;
    private $previousMethod;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['flash_messages'] ??= [];
        $this->previousRequest = $this->wiki->request;
        $this->previousMethod = $this->wiki->method;
        $this->auth()->logout();
        $this->dropUsers();
    }

    protected function tearDown(): void
    {
        $this->auth()->logout();
        $this->dropUsers();
        $this->wiki->services->get(PageManager::class)->deleteOrphaned(self::PAGE);
        $this->wiki->request = $this->previousRequest;
        $this->wiki->method = $this->previousMethod;
    }

    public function testARobotNameIsRefusedWithAWayToReachUs()
    {
        try {
            $this->signUp('tk88cards1', 'vinhthuongchu3@gmail.com');
            $this->fail('a robot signed up');
        } catch (SpamAccount $refused) {
            $this->assertStringContainsString('Veuillez nous contacter directement par mail si jamais votre compte est légitime', $refused->getMessage());
        }

        $this->assertNull($this->users()->getOneByName('tk88cards1'));
    }

    public function testTheRefusalGivesTheContactAddress()
    {
        $params = $this->wiki->services->get(\Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface::class);
        $previous = $params->has('signup_spam_contact') ? $params->get('signup_spam_contact') : '';
        $params->set('signup_spam_contact', 'contact+ferme@yeswiki.net');
        try {
            $this->signUp('tk88cards1', 'vinhthuongchu3@gmail.com');
            $this->fail('a robot signed up');
        } catch (SpamAccount $refused) {
            $this->assertStringEndsWith(': contact+ferme@yeswiki.net', $refused->getMessage());
        } finally {
            $params->set('signup_spam_contact', $previous);
        }
    }

    public function testAnAdminCreatesAnyAccount()
    {
        $this->auth()->connectFirstAdmin();

        $this->assertNotEmpty($this->signUp('tk88cards1', 'vinhthuongchu3@gmail.com'));
    }

    public function testAStrangersAddressIsPostedWithItsDeletionLink()
    {
        $notifier = new class($this->wiki->services->get(\Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface::class), $this->cleaner()) extends SignupNotifier {
            public $sent = [];

            public function isConfigured(): bool
            {
                return true;
            }

            protected function send(array $attachment): void
            {
                $this->sent[] = $attachment;
            }
        };
        $user = $this->signUp('BerthaGwinn', 'martarobertson126@gmail.com');
        $notifier->suspicious($user, ['score' => 2, 'reasons' => ['mismatch']]);
        $notifier->suspicious($user, ['score' => 0, 'reasons' => []]);

        $this->assertCount(1, $notifier->sent);
        $this->assertStringContainsString('deleteaccount', $notifier->sent[0]['text']);
        $this->assertStringContainsString('username=BerthaGwinn', $notifier->sent[0]['text']);
    }

    public function testOnlyAnAccountSentItsLinkDaysAgoThatLeftNothingIsPurged()
    {
        foreach (['SpamTestWaiting', 'SpamTestActive', 'SpamTestWriter', 'SpamTestLegacy'] as $name) {
            $this->users()->create($name, strtolower($name) . '@example.org', 'spam-test-password');
            $this->ageAccount($name, 30);
        }
        foreach (['SpamTestWaiting', 'SpamTestActive', 'SpamTestWriter'] as $name) {
            $this->triples()->create($name, AccountActivationService::TRIPLE_PROPERTY_ACTIVATION_KEY, 'key-' . $name, '', '');
            $this->triples()->create($name, AccountActivationService::TRIPLE_PROPERTY_MAIL_SENT_AT, (string)strtotime('-4 days'), '', '');
        }
        $this->triples()->create('SpamTestLegacy', AccountActivationService::TRIPLE_PROPERTY_ACTIVATION_KEY, 'key-never-mailed', '', '');
        $this->wiki->services->get(AccountActivationService::class)->activate('SpamTestActive', '', true);
        $this->wiki->services->get(DbService::class)->query(
            'INSERT INTO ' . $this->wiki->services->get(DbService::class)->prefixTable('pages')
            . " (tag, time, body, body_r, owner, user, latest) VALUES ('" . self::PAGE . "', NOW(), 'texte', '', 'SpamTestWriter', 'SpamTestWriter', 'Y')"
        );

        $this->assertSame(['SpamTestWaiting'], $this->cleaner()->purgeStale(true));
        $this->assertSame([], $this->cleaner()->purgeStale(true, strtotime('-2 days')));
        $this->assertSame(['SpamTestWaiting'], $this->cleaner()->purgeStale());
        $this->assertNull($this->users()->getOneByName('SpamTestWaiting'));
        $this->assertNull($this->triples()->getOne('SpamTestWaiting', AccountActivationService::TRIPLE_PROPERTY_ACTIVATION_KEY, '', ''));
        foreach (['SpamTestActive', 'SpamTestWriter', 'SpamTestLegacy'] as $name) {
            $this->assertNotNull($this->users()->getOneByName($name), "$name was purged");
        }
    }

    public function testTheDeletionPageAsksForAnAdmin()
    {
        $this->users()->create('BerthaGwinn', 'martarobertson126@gmail.com', 'spam-test-password');

        $output = $this->runHandler('GET');

        $this->assertStringNotContainsString('name="csrf-token"', $output);
        $this->assertNotNull($this->users()->getOneByName('BerthaGwinn'));
    }

    public function testTheDeletionPageShowsTheAccountThenDeletesItOnAPost()
    {
        $this->users()->create('BerthaGwinn', 'martarobertson126@gmail.com', 'spam-test-password');
        $this->auth()->connectFirstAdmin();

        $output = $this->runHandler('GET');
        $this->assertStringContainsString('martarobertson126@gmail.com', $output);
        $this->assertMatchesRegularExpression('/<form method="post">\s*<input type="hidden" name="csrf-token" value="[^"]+">/', $output);

        $this->runHandler('POST');
        $this->assertNotNull($this->users()->getOneByName('BerthaGwinn'), 'deleted without a token');

        $this->runHandler('POST', ['csrf-token' => $this->wiki->services->get(CsrfTokenManager::class)->getToken('main')->getValue()]);
        $this->assertNull($this->users()->getOneByName('BerthaGwinn'));
    }

    private function signUp(string $name, string $email)
    {
        return $this->wiki->services->get(UserController::class)->create([
            'name' => $name,
            'email' => $email,
            'password' => 'spam-test-password',
        ]);
    }

    private function runHandler(string $method, array $parameters = []): string
    {
        $this->wiki->request = Request::create('/?' . $this->wiki->config['root_page'] . '/deleteaccount&username=BerthaGwinn', $method, $parameters);
        $this->wiki->request->query->set('username', 'BerthaGwinn');
        $this->wiki->method = 'deleteaccount';

        try {
            return (string)$this->wiki->Method('deleteaccount');
        } catch (ExitException $exit) {
            return '';
        }
    }

    private function ageAccount(string $name, int $days): void
    {
        $db = $this->wiki->services->get(DbService::class);
        $db->query('UPDATE ' . $db->prefixTable('users') . ' SET signuptime = DATE_SUB(NOW(), INTERVAL ' . $days . " DAY) WHERE name = '" . $db->escape($name) . "'");
    }

    private function dropUsers(): void
    {
        foreach (self::NAMES as $name) {
            if ($user = $this->users()->getOneByName($name)) {
                $this->users()->delete($user);
            }
            $this->wiki->services->get(AccountActivationService::class)->forget($name);
        }
    }

    private function users(): UserManager
    {
        return $this->wiki->services->get(UserManager::class);
    }

    private function triples(): TripleStore
    {
        return $this->wiki->services->get(TripleStore::class);
    }

    private function cleaner(): AccountCleaner
    {
        return $this->wiki->services->get(AccountCleaner::class);
    }

    private function auth(): AuthController
    {
        return $this->wiki->services->get(AuthController::class);
    }
}
