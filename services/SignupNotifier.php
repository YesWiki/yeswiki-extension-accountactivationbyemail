<?php

/* Tells a Mattermost channel about signups that look odd, with a link to delete them. Part of the YesWiki extension accountactivationbyemail, see LICENSE. */

namespace YesWiki\Accountactivationbyemail\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\HttpClient;
use YesWiki\Core\Entity\User;

class SignupNotifier
{
    private const TIMEOUT = 3;
    private const WEBHOOK_KEYS = ['signup_mattermost_webhook', 'yeswiki-farm-mattermost-webhook'];

    protected $params;
    protected $cleaner;

    public function __construct(ParameterBagInterface $params, AccountCleaner $cleaner)
    {
        $this->params = $params;
        $this->cleaner = $cleaner;
    }

    public function isConfigured(): bool
    {
        return $this->webhook() !== '';
    }

    /** Post a signup that scored, without ever failing the signup itself. */
    public function suspicious(User $user, array $verdict): void
    {
        if ($verdict['score'] <= 0 || !$this->isConfigured()) {
            return;
        }
        $link = $this->cleaner->deletionLink($user['name']);
        $this->send([
            'color' => '#f0ad4e',
            'title' => _t('ACCOUNTACTIVATION_BY_EMAIL_HOOK_SUSPICIOUS', ['userName' => $user['name']]),
            'title_link' => $link,
            'fields' => [
                ['short' => true, 'title' => _t('ACCOUNTACTIVATION_BY_EMAIL_HOOK_EMAIL'), 'value' => (string)$user['email']],
                ['short' => true, 'title' => _t('ACCOUNTACTIVATION_BY_EMAIL_HOOK_SCORE'), 'value' => (string)$verdict['score']],
                ['short' => false, 'title' => _t('ACCOUNTACTIVATION_BY_EMAIL_HOOK_REASONS'), 'value' => implode(', ', array_map(fn ($reason) => _t('ACCOUNTACTIVATION_BY_EMAIL_SPAM_' . strtoupper($reason)), $verdict['reasons']))],
            ],
            'text' => '**[⛔ ' . _t('ACCOUNTACTIVATION_BY_EMAIL_HOOK_DELETE') . '](' . $link . ')**',
        ]);
    }

    protected function send(array $attachment): void
    {
        try {
            HttpClient::create()->request('POST', $this->webhook(), [
                'json' => ['attachments' => [$attachment]],
                'timeout' => self::TIMEOUT,
            ])->getStatusCode();
        } catch (\Throwable $throwable) {
            error_log('accountactivationbyemail: mattermost: ' . $throwable->getMessage());
        }
    }

    private function webhook(): string
    {
        foreach (self::WEBHOOK_KEYS as $key) {
            $webhook = $this->params->has($key) ? trim((string)$this->params->get($key)) : '';
            if ($webhook !== '') {
                return $webhook;
            }
        }

        return '';
    }
}
