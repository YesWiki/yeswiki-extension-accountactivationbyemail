<?php

/* What an account left on the wiki, and the removal of accounts that never came to activate. Part of the YesWiki extension accountactivationbyemail, see LICENSE. */

namespace YesWiki\Accountactivationbyemail\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Contact\Service\MailSubscriptions;
use YesWiki\Core\Entity\User;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\UserManager;
use YesWiki\Wiki;

class AccountCleaner
{
    public const LIFETIME_DAYS = 3;

    protected $params;
    protected $dbService;
    protected $userManager;
    protected $activation;
    protected $wiki;

    public function __construct(ParameterBagInterface $params, DbService $dbService, UserManager $userManager, AccountActivationService $activation, Wiki $wiki)
    {
        $this->params = $params;
        $this->dbService = $dbService;
        $this->userManager = $userManager;
        $this->activation = $activation;
        $this->wiki = $wiki;
    }

    /** Days an account may wait for its activation before it goes, 0 to keep it forever. */
    public function lifetimeDays(): int
    {
        return max(0, (int)($this->params->has('unactivated_account_lifetime') ? $this->params->get('unactivated_account_lifetime') : self::LIFETIME_DAYS));
    }

    /** @return array<string,int> account name => revisions it wrote */
    public function revisionsByUser(): array
    {
        $rows = $this->dbService->loadAll("SELECT `user`, COUNT(*) AS `revisions` FROM {$this->dbService->prefixTable('pages')} GROUP BY `user`");

        return array_column(array_map(fn ($row) => [(string)$row['user'], (int)$row['revisions']], $rows), 1, 0);
    }

    /** @return array<string,string> account name => its activation status, for accounts that went through signup */
    public function activationByUser(): array
    {
        $properties = implode(',', array_map(fn ($property) => '"' . $this->dbService->escape($property) . '"', [
            AccountActivationService::TRIPLE_PROPERTY_IS_ACTIVATED,
            AccountActivationService::TRIPLE_PROPERTY_ACTIVATION_KEY,
            AccountActivationService::TRIPLE_PROPERTY_MAIL_SENT_AT,
        ]));
        $rows = $this->dbService->loadAll("SELECT `resource`, `property`, `value` FROM {$this->dbService->prefixTable('triples')} WHERE `property` IN ($properties)");
        $status = [];
        foreach ($rows as $row) {
            $name = (string)$row['resource'];
            if ($row['property'] === AccountActivationService::TRIPLE_PROPERTY_IS_ACTIVATED) {
                $status[$name] = (string)$row['value'];
            } else {
                $status[$name] ??= AccountActivationService::TRIPLE_NOT_ACTIVATED_VALUE;
            }
        }

        return $status;
    }

    /** @return string[] tags of the latest pages the account owns */
    public function ownedPages(string $name): array
    {
        $rows = $this->dbService->loadAll("SELECT `tag` FROM {$this->dbService->prefixTable('pages')} WHERE `owner` = \"{$this->dbService->escape($name)}\" AND `latest` = \"Y\" ORDER BY `tag`");

        return array_column($rows, 'tag');
    }

    /** Whether an account has nothing on the wiki: no revision, no page, no group. */
    public function leftNothing(User $user, int $revisions): bool
    {
        return $revisions === 0
            && $this->ownedPages($user['name']) === []
            && $this->userManager->groupsWhereIsMember($user, false) === [];
    }

    /**
     * Accounts that signed up, were sent an activation link, never used it, and are older than the lifetime.
     *
     * @return User[]
     */
    public function stale(?int $now = null): array
    {
        $days = $this->lifetimeDays();
        if ($days === 0) {
            return [];
        }
        $limit = ($now ?? time()) - $days * 86400;
        $status = $this->activationByUser();
        $revisions = $this->revisionsByUser();

        return array_values(array_filter($this->userManager->getAll(), function (User $user) use ($limit, $status, $revisions) {
            $signup = strtotime((string)$user['signuptime']);

            return ($status[$user['name']] ?? AccountActivationService::TRIPLE_ACTIVATED_VALUE) !== AccountActivationService::TRIPLE_ACTIVATED_VALUE
                && $signup !== false && $signup < $limit
                && $this->leftNothing($user, $revisions[$user['name']] ?? 0);
        }));
    }

    /** @return string[] names of the stale accounts, removed unless it is a dry run */
    public function purgeStale(bool $dryRun = false, ?int $now = null): array
    {
        $names = [];
        foreach ($this->stale($now) as $user) {
            if (!$dryRun) {
                $this->remove($user);
            }
            $names[] = $user['name'];
        }

        return $names;
    }

    /** Remove an account that left nothing behind, without the admin check of the web interface. */
    public function remove(User $user): void
    {
        if ($this->wiki->services->has(MailSubscriptions::class)) {
            $this->wiki->services->get(MailSubscriptions::class)->forgetUser($user['name']);
        }
        $this->userManager->delete($user);
        $this->activation->forget($user['name']);
    }

    /** Link to the page where an admin reviews and deletes an account. */
    public function deletionLink(string $name): string
    {
        return $this->wiki->Href('deleteaccount', $this->params->get('root_page'), ['username' => $name], false);
    }
}
