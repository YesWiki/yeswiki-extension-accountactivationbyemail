<?php

/*
 * This file is part of the YesWiki Extension accountactivationbyemail.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
    public const TRIPLE_ACTIVATED_VALUE = 'true';
    public const TRIPLE_NOT_ACTIVATED_VALUE = 'false';

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

    /**
     * activate a user
     * @param string $userName
     * @param string $key
     * @param bool $force
     * @throws BadUserName
     * @throws BadKey
     * @throws Exception
     */
    public function activate(string $userName, string $key, bool $force = false)
    {
        $this->checkValidity($userName, $key, $force, 'activate');

        $status = $this->getActivationStatus($userName);
        if ($status !== self::TRIPLE_ACTIVATED_VALUE) {
            if (!$force && !$this->isValidateActivationKey($userName, $key)) {
                throw new BadKey("The activation key for user $userName is invalid. Security issue ? ");
            }
            if (is_null($status)) {
                // TODO check if following line is needed
                // if (!$force){
                //     throw new Exception('It should not occures : the activation process should have added the IsActivated key before.');
                // }

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
            // success
            // We remove all obsolete activation keys from the database
            $this->deleteActivationKeys($userName);
        }
    }

    /**
     * inactivate a user
     * @param string $userName
     * @throws BadUserName
     * @throws BadKey
     * @throws Exception
     */
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
            // success
        }
    }

    /**
     * send an email with activation link
     * @param null|string $userName
     * @throws Exception
     */
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
        // get Mailer dynamically because of circular reference via __construct
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

    /**
     * check validity of params
     * @param string $userName
     * @param string $key
     * @param bool $force
     * @param string $mode
     * @throws BadUserName
     * @throws BadKey
     */
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

    /**
     * get activation status
     * @param string $userName
     * @return null|string
     */
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

    /**
     * check if user is activate
     * @param string $userName
     * @return bool
     */
    public function isActivated(string $userName): bool
    {
        return $this->getActivationStatus($userName) === self::TRIPLE_ACTIVATED_VALUE;
    }

    /**
     * check activation key
     * @param string $userName
     * @param string $key
     * @return bool
     */
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

    /**
     * delete all activation keys for user
     * @param string $userName
     */
    protected function deleteActivationKeys(string $userName)
    {
        $this->tripleStore->delete(
            $userName,
            self::TRIPLE_PROPERTY_ACTIVATION_KEY,
            null,
            '',
            ''
        );
    }

    /**
     * getActivationLink for a userName
     * @param string $userName
     * @return string
     * @throws Exception
     */
    protected function getActivationLink(string $userName): string
    {
        // Let's create an activation key suitable for a email
        $length = $this->params->get('user_activation_key_length');
        $length = (is_scalar($length) && intval($length) > 6) ? intval($length) : 20;// default 20 if error
        $key = base64_encode(random_bytes($length));

        // Store the key in the TripleStore
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
