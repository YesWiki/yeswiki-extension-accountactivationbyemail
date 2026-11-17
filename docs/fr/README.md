# Extension Account Activation By email

Cette extension ajoute un processus de validation par e-mail des comptes

 - Le processus est activé dès l'installation de l'extension
 - Il n'a pas été vérifié pour fonctionner avec les extensions `logincas`, `login-sso`, `loginldap`.
 - Il est possible de désactiver le processus de vérification dans la page [GererConfig](?GererConfig ':ignore') dans la partie `Activation des comptes par e-mail` paramètre `signup_email_activation`
 - Les inscriptions avec une adresse e-mail jetable (yopmail, leafflip…) sont refusées, d'après la [liste publique des domaines jetables](https://github.com/disposable-email-domains/disposable-email-domains). Le paramètre `signup_refuse_disposable_email` désactive ce refus, et `signup_disposable_email_domains` ajoute des domaines à la liste. Un admin peut toujours créer un compte avec n'importe quelle adresse.

## Robots à l'inscription

Chaque inscription reçoit un score d'après son nom et son adresse :

| Signal | Points |
|---|---|
| Mots de paris, de casino, d'IPTV ou d'escort dans le nom ou l'adresse (`tk88cards1`, `shbetinfo`, `uktvhub`) | 3 |
| Nom en forme de code ou de domaine (`e39bmwcom`, `uu88apporg2`) | 3 |
| Domaine jetable | 2 |
| Adresse chiffrée sans rapport avec le nom (`BerthaGwinn`, `martarobertson126@gmail.com`) | 2 |
| Adresse e-mail utilisée comme nom | 1 |

 - À partir de 3 points (`signup_spam_threshold`), l'inscription est refusée avec un message qui invite à écrire par mail. L'adresse donnée est `signup_spam_contact`, ou à défaut l'adresse admin de la ferme (`yeswiki-farm-admin-email`), et `signup_refuse_spam` désactive le refus. `signup_spam_words` ajoute des mots, en expression régulière.
 - En dessous, une inscription qui a des points est envoyée sur Mattermost, avec un lien vers la page de suppression du compte. Le webhook est `signup_mattermost_webhook`, ou à défaut celui de la ferme (`yeswiki-farm-mattermost-webhook`).
 - La page `?PagePrincipale/deleteaccount&username=<nom>` montre le compte (adresse, inscription, activation, révisions, pages, groupes, score) et le supprime après confirmation. Elle est réservée aux admins.

## Comptes jamais activés

Un compte qui ne s'est pas servi de son dernier lien d'activation, et qui n'a rien laissé (ni révision, ni page, ni groupe), est supprimé 3 jours après l'envoi de ce lien (`unactivated_account_lifetime`, `0` pour les garder). La purge se fait à chaque nouvelle inscription. Un compte qui n'a jamais reçu de lien, comme ceux créés avant l'installation de l'extension, n'est jamais touché.

En ligne de commande :

 - `./yeswicli activation:purge --dry-run` liste les comptes que la purge supprimerait, et sans `--dry-run` les supprime.
 - `./yeswicli activation:spam` liste les comptes au-dessus du seuil, en comptant un point de plus pour ceux qui n'ont rien écrit depuis une semaine. `--all` montre aussi ceux qui ont des points sous le seuil, et `--delete` supprime ceux qui n'ont rien laissé. Pour les autres, la commande donne le lien de la page de suppression.

## Fonctionnement

Lors de la création d'un compte, celui-ci est considéré comme non-activé.

Lors d'une tentative de connexion (via `cookies` ou par l'action `{{login}}`), un e-mail est envoyé avec un lien d'activation et un message d'erreur est affiché.

Un seul e-mail part quand la session est reprise depuis les cookies. Une nouvelle connexion avec l'action `{{login}}` en renvoie un, au plus un par quart d'heure.

En cliquant sur le lien, le compte se retrouve alors activé et fonctionnel.

**Les administrateurs sont toujours considérés comme actifs, quelque soit le statut d'activation en base de données.**

## Gestion par les administrateurs

De nouvelles options sont disponibles dans la gestion des utilisateurs, sur la page [GererUtilisateurs](?GererCGererUtilisateursonfig ':ignore').

Il est possible pour un adminstrateur de modifier le statut d'activation d'un utilisateur en cliquant sur le bouton concerné.

**Attention, modifier le statut d'un administrateur ne fait que changer le statut en base de données, mais celui-ci pourra toujours se connecter sans lien d'activation à valider**