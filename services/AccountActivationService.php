<?php

/* Activation status, keys and activation mails of the wiki's accounts. Part of the YesWiki extension accountactivationbyemail, see LICENSE. */

namespace YesWiki\Accountactivationbyemail\Service;

use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Accountactivationbyemail\Exception\BadKey;
use YesWiki\Accountactivationbyemail\Exception\BadUserName;
use YesWiki\Core\Service\Mailer;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\Service\UserManager;
use YesWiki\Wiki;

class AccountActivationService
{
    public const TRIPLE_PROPERTY_IS_ACTIVATED = 'https://yeswiki.net/vocabulary/account-is-activated';
    public const TRIPLE_PROPERTY_ACTIVATION_KEY = 'https://yeswiki.net/vocabulary/activation-key';
    public const TRIPLE_PROPERTY_MAIL_SENT_AT = 'https://yeswiki.net/vocabulary/activation-mail-sent-at';
    public const TRIPLE_ACTIVATED_VALUE = 'true';
    public const TRIPLE_NOT_ACTIVATED_VALUE = 'false';
    public const RESEND_DELAY = 900;

    protected $params;
    protected $templateEngine;
    protected $tripleStore;
    protected $userManager;
    protected $wiki;

    public function __construct(
        ParameterBagInterface $params,
        TemplateEngine $templateEngine,
        TripleStore $tripleStore,
        UserManager $userManager,
        Wiki $wiki
    ) {
        $this->params = $params;
        $this->templateEngine = $templateEngine;
        $this->tripleStore = $tripleStore;
        $this->userManager = $userManager;
        $this->wiki = $wiki;
    }

    /** Activate an account, with its key unless forced. */
    public function activate(string $userName, string $key, bool $force = false)
    {
        $this->checkValidity($userName, $key, $force, 'activate');

        $status = $this->getActivationStatus($userName);
        if ($status !== self::TRIPLE_ACTIVATED_VALUE) {
            if (!$force && !$this->isValidateActivationKey($userName, $key)) {
                throw new BadKey("The activation key for user $userName is invalid. Security issue ? ");
            }
            if (is_null($status)) {
                $res = $this->tripleStore->create(
                    $userName,
                    self::TRIPLE_PROPERTY_IS_ACTIVATED,
                    self::TRIPLE_ACTIVATED_VALUE,
                    '',
                    ''
                );
                if ($res != 0) {
                    throw new Exception("Cannot create activation status for user $userName (error code = $res)");
                }
            } else {
                $res = $this->tripleStore->update(
                    $userName,
                    self::TRIPLE_PROPERTY_IS_ACTIVATED,
                    self::TRIPLE_NOT_ACTIVATED_VALUE,
                    self::TRIPLE_ACTIVATED_VALUE,
                    '',
                    ''
                );
                if ($res != 0) {
                    throw new Exception("Cannot update activation status for user $userName (error code = $res)");
                }
            }
            $this->deleteActivationKeys($userName);
        }
    }

    /** Mark an account as not activated. */
    public function inactivate(string $userName)
    {
        $this->checkValidity($userName, "", true, 'inactivate');

        $status = $this->getActivationStatus($userName);
        if ($status !== self::TRIPLE_NOT_ACTIVATED_VALUE) {
            if (is_null($status)) {
                $res = $this->tripleStore->create(
                    $userName,
                    self::TRIPLE_PROPERTY_IS_ACTIVATED,
                    self::TRIPLE_NOT_ACTIVATED_VALUE,
                    '',
                    ''
                );
                if ($res != 0) {
                    throw new Exception("Cannot create activation status for user $userName (error code = $res)");
                }
            } else {
                $res = $this->tripleStore->update(
                    $userName,
                    self::TRIPLE_PROPERTY_IS_ACTIVATED,
                    self::TRIPLE_ACTIVATED_VALUE,
                    self::TRIPLE_NOT_ACTIVATED_VALUE,
                    '',
                    ''
                );
                if ($res != 0) {
                    throw new Exception("Cannot update activation status for user $userName (error code = $res)");
                }
            }
        }
    }

    /** Whether a mail may go out now: once when the session is restored, every RESEND_DELAY seconds when the person signs in. */
    public function maySendActivationLink(string $userName, bool $restoringSession): bool
    {
        $sentAt = $this->activationLinkSentAt($userName);
        if ($restoringSession) {
            return $sentAt === null && !$this->hasActivationKey($userName);
        }

        return $sentAt === null || time() - $sentAt >= self::RESEND_DELAY;
    }

    /** Send the account's owner a mail with a new activation link. */
    public function sendActivationLink(?string $userName)
    {
        if (empty($userName)) {
            throw new Exception('Can not send an activation email for empty userName');
        }
        $user = $this->userManager->getOneByName($userName);
        if (empty($user['name'])) {
            throw new Exception("Can not send an activation email for a not existing user ($userName)");
        }
        $link = $this->getActivationLink($user['name']);
        $this->rememberActivationLinkSent($user['name']);
        $mailer = $this->wiki->services->get(Mailer::class);

        $baseUrl = $this->getBaseUrl();
        $subject = $this->templateEngine->render(
            '@accountactivationbyemail/activation-email-subject.twig',
            [
                'userName' => $user['name'],
                'baseUrl' => $baseUrl
            ]
        );
        $text = $this->templateEngine->render(
            '@accountactivationbyemail/activation-email-text.twig',
            [
                'userName' => $user['name'],
                'baseUrl' => $baseUrl,
                'link' => $link
            ]
        );
        $html = $this->templateEngine->render(
            '@accountactivationbyemail/activation-email-html.twig',
            [
                'userName' => $user['name'],
                'baseUrl' => $baseUrl,
                'link' => $link
            ]
        );
        $mailer->sendEmailFromAdmin($user['email'], $subject, $text, $html);
    }

    /** Forget everything this extension stored about an account. */
    public function forget(string $userName)
    {
        foreach ([self::TRIPLE_PROPERTY_IS_ACTIVATED, self::TRIPLE_PROPERTY_ACTIVATION_KEY, self::TRIPLE_PROPERTY_MAIL_SENT_AT] as $property) {
            $this->tripleStore->delete($userName, $property, null, '', '');
        }
    }

    protected function checkValidity(string $userName, string $key, bool $force, string $mode = 'activate')
    {
        if (empty($userName)) {
            throw new BadUserName("Trying to $mode a user with bad parameters (empty userName).");
        }
        $user = $this->userManager->getOneByName($userName);
        if (empty($user)) {
            throw new BadUserName("Trying to $mode an inexistent user.");
        }
        if (!$force) {
            if (empty($key)) {
                throw new BadKey("Trying to $mode a user with bad parameters (empty key).");
            }
            if (!preg_match('/[A-Za-z0-9+\/=]+/', $key)) {
                throw new BadKey("The key to $mode user $userName is in an invalid format !");
            }
        }
    }

    protected function getActivationStatus(string $userName): ?string
    {
        $value = $this->tripleStore->getOne(
            $userName,
            self::TRIPLE_PROPERTY_IS_ACTIVATED,
            '',
            ''
        );
        return is_string($value) ? $value : null;
    }

    public function isActivated(string $userName): bool
    {
        return $this->getActivationStatus($userName) === self::TRIPLE_ACTIVATED_VALUE;
    }

    protected function isValidateActivationKey(string $userName, string $key): bool
    {
        return !is_null($this->tripleStore->exist(
            $userName,
            self::TRIPLE_PROPERTY_ACTIVATION_KEY,
            $key,
            '',
            ''
        ));
    }

    protected function hasActivationKey(string $userName): bool
    {
        return $this->tripleStore->getOne($userName, self::TRIPLE_PROPERTY_ACTIVATION_KEY, '', '') !== null;
    }

    protected function activationLinkSentAt(string $userName): ?int
    {
        $value = $this->tripleStore->getOne($userName, self::TRIPLE_PROPERTY_MAIL_SENT_AT, '', '');

        return is_string($value) && ctype_digit($value) ? (int)$value : null;
    }

    protected function rememberActivationLinkSent(string $userName)
    {
        $this->tripleStore->delete($userName, self::TRIPLE_PROPERTY_MAIL_SENT_AT, null, '', '');
        $this->tripleStore->create($userName, self::TRIPLE_PROPERTY_MAIL_SENT_AT, (string)time(), '', '');
    }

    /** Drop every activation key of an account, and when its last mail went out. */
    protected function deleteActivationKeys(string $userName)
    {
        $this->tripleStore->delete($userName, self::TRIPLE_PROPERTY_ACTIVATION_KEY, null, '', '');
        $this->tripleStore->delete($userName, self::TRIPLE_PROPERTY_MAIL_SENT_AT, null, '', '');
    }

    /** Store a new activation key for an account and return the link that uses it. */
    protected function getActivationLink(string $userName): string
    {
        $length = $this->params->get('user_activation_key_length');
        $length = (is_scalar($length) && intval($length) > 6) ? intval($length) : 20;
        $key = base64_encode(random_bytes($length));

        $res = $this->tripleStore->create(
            $userName,
            self::TRIPLE_PROPERTY_ACTIVATION_KEY,
            $key,
            '',
            ''
        );
        if ($res != 0) {
            throw new Exception("Error when creating activation key for $userName");
        }
        return $this->wiki->Href('emailactivation', $this->params->get('root_page'), [
            'username' => $userName,
            'key' => $key
        ], false);
    }

    private function getBaseUrl(): string
    {
        return preg_replace('/(\\/wakka\\.php\\?wiki=|\\/\\?wiki=|\\/\\?|\\/)$/m', '', $this->params->get('base_url')) ;
    }
}
