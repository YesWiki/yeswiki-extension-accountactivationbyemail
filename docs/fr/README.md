# Extension Account Activation By email

Cette extension ajoute un processus de validation par e-mail des comptes

 - Le processus est activé dès l'installation de l'extension
 - Il n'a pas été vérifié pour fonctionner avec les extensions `logincas`, `login-sso`, `loginldap`.
 - Il est possible de désactiver le processus de vérification dans la page [GererConfig](?GererConfig ':ignore') dans la partie `Activation des comptes par e-mail` paramètre `signup_email_activation`
 - Les inscriptions avec une adresse e-mail jetable (yopmail, leafflip…) sont refusées, d'après la [liste publique des domaines jetables](https://github.com/disposable-email-domains/disposable-email-domains). Le paramètre `signup_refuse_disposable_email` désactive ce refus, et `signup_disposable_email_domains` ajoute des domaines à la liste. Un admin peut toujours créer un compte avec n'importe quelle adresse.

## Fonctionnement

Lors de la création d'un compte, celui-ci est considéré comme non-activé.

Lors d'une tentative de connexion (via `cookies` ou par l'action `{{login}}`), un e-mail est envoyé avec un lien d'activation et un message d'erreur est affiché.

En cliquant sur le lien, le compte se retrouve alors activé et fonctionnel.

**Les administrateurs sont toujours considérés comme actifs, quelque soit le statut d'activation en base de données.**

## Gestion par les administrateurs

De nouvelles options sont disponibles dans la gestion des utilisateurs, sur la page [GererUtilisateurs](?GererCGererUtilisateursonfig ':ignore').

Il est possible pour un adminstrateur de modifier le statut d'activation d'un utilisateur en cliquant sur le bouton concerné.

**Attention, modifier le statut d'un administrateur ne fait que changer le statut en base de données, mais celui-ci pourra toujours se connecter sans lien d'activation à valider**