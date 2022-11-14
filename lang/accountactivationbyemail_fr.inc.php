<?php

/*
 * This file is part of the YesWiki Extension groupmanagement.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [
    'ACCOUNTACTIVATION_BY_EMAIL_SUBJECT' => 'Lien d activation de votre compte %{userName}',
    'ACCOUNTACTIVATION_BY_EMAIL_TEXT' => "Bonjour,\n\nVotre compte %{userName} doit être activé en cliquant sur le lien ci-dessous ou en le copiant dans la barre de navigateur internet.",
    'ACCOUNTACTIVATION_BY_EMAIL_WARNING' => 'Votre compte doit être activé au préalable. %{message}',
    'ACCOUNTACTIVATION_BY_EMAIL_MESSAGE_SENT' => 'Un e-mail d\'activation vous a été envoyé. Veuillez suivre les instructions qu\'il contient.',
    'ACCOUNTACTIVATION_BY_EMAIL_MESSAGE_NOT_SENT' => 'Il n\'a pas été possible de vous envoyer un e-mail d\'activation. Veuillez contacter les adminsitrateurs du site',
    'ACCOUNTACTIVATION_BY_EMAIL_EMPTY_USERNAME' => 'Le nom d\'utilisateur est vide',
    'ACCOUNTACTIVATION_BY_EMAIL_EMPTY_KEY' => 'La clé est vide',
    'ACCOUNTACTIVATION_BY_EMAIL_ACTIVATION_ERROR' => 'Il n\'a pas été possible d\'activer votre compte parce que \'%{error}\'',
    'ACCOUNTACTIVATION_BY_EMAIL_ACTIVATION_SUCCESS' => 'Votre compte a bien été activé.',
    'ACCOUNTACTIVATION_BY_EMAIL_ALREADY_ACTIVATED' => 'Votre compte a déjà été activé.',

    'EDIT_CONFIG_HINT_SIGNUP_EMAIL_ACTIVATION' => 'Activer la vérification des e-mails (true ou false)',
    'EDIT_CONFIG_HINT_USER_ACTIVATION_KEY_LENGTH' => 'Longueur de la clé d\'activation des utilisateurs',
    'EDIT_CONFIG_GROUP_ACCOUNTACTIVATIONBYEMAIL' => 'Activation des comptes par e-mail',
];
