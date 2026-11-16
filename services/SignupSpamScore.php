<?php

/* How much an account's name and mail address look like a robot's. Part of the YesWiki extension accountactivationbyemail, see LICENSE. */

namespace YesWiki\Accountactivationbyemail\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SignupSpamScore
{
    public const THRESHOLD = 3;
    public const UNTOUCHED_DAYS = 7;

    private const WORDS = '/\b(bet|bets|betting|casino|slot|slots|jackpot|poker|clb|escort|escorts|call ?girl|viagra|cialis|crypto|bitcoin|forex|essay|essays|homework|assignment|dissertation|seo|backlink|backlinks|gambling|lottery|toto|togel|judi|aviator|plinko|rummy|unblocked|apk|apks|kqxs|iptv)\b/i';
    private const GLUED = '/(keonhacai|nhacai|taixiu|soikeo|soicau|bongda|cacuoc|cakhia|sunwin|xoso|lodeonline|sattamatka|fatafat|escort|callgirl|camgirl|adultfriend|iptv|tvhub|tvchannel|freespins|modapk|apkmod|betting|casino|jackpot|calculators?|shbet|\d+bet|bet\d+|[a-z]{1,5}88[a-z]{2,}|fun79|33win|8xbet|188bet|i9bet)/i';
    private const CODE = '/^[a-z]{1,3}\d{2,3}[a-z]{3,}\d?$/';
    private const DOMAINISH = '/\d[a-z]*(com|net|org|info|xyz|top|vip|club|live|site|online|shop|store|link|fun|buzz|bio|app)\d?$/';
    private const DIGITS = '/\d{3,}/';
    private const NAME_WORD = '/[a-z]{4,}/';

    protected $params;
    protected $disposable;

    public function __construct(ParameterBagInterface $params, DisposableEmailDomains $disposable)
    {
        $this->params = $params;
        $this->disposable = $disposable;
    }

    public function threshold(): int
    {
        return max(1, (int)($this->params->has('signup_spam_threshold') ? $this->params->get('signup_spam_threshold') : self::THRESHOLD));
    }

    /** Score of what a signup form holds: points and the reasons they were given. */
    public function of(string $name, string $email): array
    {
        $name = trim($name);
        [$local, $domain] = array_pad(explode('@', strtolower(trim($email)), 2), 2, '');
        $slug = $this->slugOf($name);
        $reasons = [];

        if (
            preg_match(self::WORDS, $name) === 1
            || preg_match(self::WORDS, (string)preg_replace('/[._+-]+/', ' ', $local)) === 1
            || preg_match(self::GLUED, $slug) === 1
            || preg_match(self::GLUED, $this->slugOf($local)) === 1
            || $this->matchesWikiWords($name . ' ' . $local)
        ) {
            $reasons['words'] = 3;
        }
        if (preg_match(self::CODE, $slug) === 1 || preg_match(self::DOMAINISH, $slug) === 1) {
            $reasons['code'] = 3;
        }
        if ($domain !== '' && $this->disposable->isDisposable($email)) {
            $reasons['disposable'] = 2;
        }
        if ($name !== '' && strtolower($name) === strtolower(trim($email))) {
            $reasons['emailasname'] = 1;
        } elseif ($this->isUnrelatedNumberedAddress($name, $local)) {
            $reasons['mismatch'] = 2;
        }

        return ['score' => array_sum($reasons), 'reasons' => array_keys($reasons)];
    }

    /** Score of an existing account, which also loses a point for never having written anything. */
    public function ofAccount(array $user, int $revisions, ?int $now = null): array
    {
        $verdict = $this->of((string)($user['name'] ?? ''), (string)($user['email'] ?? ''));
        $signup = strtotime((string)($user['signuptime'] ?? ''));
        if ($revisions === 0 && $signup !== false && ($now ?? time()) - $signup >= self::UNTOUCHED_DAYS * 86400) {
            $verdict['score']++;
            $verdict['reasons'][] = 'untouched';
        }

        return $verdict;
    }

    public function isSpam(array $verdict): bool
    {
        return $verdict['score'] >= $this->threshold();
    }

    /** An address made of a stranger's name and a run of digits, as in BerthaGwinn and martarobertson126. */
    private function isUnrelatedNumberedAddress(string $name, string $local): bool
    {
        if (preg_match(self::DIGITS, $local) !== 1) {
            return false;
        }
        $letters = (string)preg_replace('/[^a-z]/', '', $local);
        preg_match_all(self::NAME_WORD, strtolower($this->ascii($name)), $words);
        $words = array_merge($words[0], [$this->slugOf($name)]);
        foreach ($words as $word) {
            if (strlen($word) >= 4 && $letters !== '' && (str_contains($letters, $word) || str_contains($word, $letters) || str_contains($letters, substr($word, 0, 5)))) {
                return false;
            }
        }

        return true;
    }

    private function matchesWikiWords(string $text): bool
    {
        $extra = $this->params->has('signup_spam_words') ? trim((string)$this->params->get('signup_spam_words')) : '';

        return $extra !== '' && @preg_match('/' . $extra . '/i', $text) === 1;
    }

    private function slugOf(string $text): string
    {
        return (string)preg_replace('/[^a-z0-9]/', '', strtolower($this->ascii($text)));
    }

    private function ascii(string $text): string
    {
        $converted = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT', $text) : false;

        return $converted === false ? $text : $converted;
    }
}
