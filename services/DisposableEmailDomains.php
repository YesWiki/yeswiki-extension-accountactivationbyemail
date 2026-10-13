<?php

namespace YesWiki\Accountactivationbyemail\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Mail domains that hand out throwaway addresses: the public list shipped in
 * data/ (github.com/disposable-email-domains, CC0) and the wiki's own additions.
 */
class DisposableEmailDomains
{
    public const FILE = __DIR__ . '/../data/disposable-email-domains.txt';

    protected $params;
    private $domains;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
    }

    public function isDisposable(string $email): bool
    {
        $at = strrpos($email, '@');
        $domain = $at === false ? '' : strtolower(trim(substr($email, $at + 1)));

        return $domain !== '' && isset($this->domains()[$domain]);
    }

    /** @return array<string,true> */
    private function domains(): array
    {
        if ($this->domains === null) {
            $listed = is_readable(self::FILE) ? (file(self::FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [];
            $added = $this->params->has('signup_disposable_email_domains')
                ? (preg_split('/[\s,|]+/', (string)$this->params->get('signup_disposable_email_domains')) ?: [])
                : [];
            $this->domains = array_fill_keys(array_filter(array_map(fn ($domain) => strtolower(trim($domain)), array_merge($listed, $added))), true);
        }

        return $this->domains;
    }
}
