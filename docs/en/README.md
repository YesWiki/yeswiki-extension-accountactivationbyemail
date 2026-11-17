# Extension Account Activation By email

This extension adds process to activate account by email.

 - The process is actived since the installation of the extension
 - Compatibility with extensions `logincas`, `login-sso`, `loginldap` has not been verified.
 - Checking process can be inactivated via page [GererConfig](?GererConfig ':ignore') in parte `Activation des comptes par e-mail` with parameter `signup_email_activation`
 - Signups whose name or address look like a robot's (betting or IPTV words, code-like names, a stranger's numbered address) are refused from 3 points (`signup_spam_threshold`, `signup_refuse_spam`, `signup_spam_contact`, `signup_spam_words`). Lower scores are posted to Mattermost (`signup_mattermost_webhook`, or the farm's `yeswiki-farm-mattermost-webhook`) with a link to `?PagePrincipale/deleteaccount&username=<name>`, where an admin reviews and deletes the account.
 - Accounts that left nothing are deleted 3 days after their last unused activation link (`unactivated_account_lifetime`, `0` keeps them), at each signup or with `./yeswicli activation:purge`. `./yeswicli activation:spam` lists suspicious accounts, `--delete` removes those that left nothing.

## Fonctionning

At creation, an account is considered as inactivated.

When trying to connect (via `cookies` or with action `{{login}}`), an e-mail is sent with the activation link and an error message is displayed.

Only one e-mail goes out when the session is restored from cookies. Signing in again with `{{login}}` sends another one, at most once every fifteen minutes.

By clicking on the link, the account is activated.

**Administrators are always considered as activated, even if the database status is inactivated.**

## Management by administrators

New options are available in users management, on page [GererUtilisateurs](?GererCGererUtilisateursonfig ':ignore').

For an administrator, it is possible to modify the activation status of a user by clicking on the concerned button.

**Warning, modify administrators' status only changes the status in database, but the user will ever be able to connect without validating the activation link**