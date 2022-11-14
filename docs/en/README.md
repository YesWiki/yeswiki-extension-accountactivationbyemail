# Extension Account Activation By email

This extension adds process to activate account by email.

 - The process is actived since the installation of the extension
 - Compatibility with extensions `logincas`, `login-sso`, `loginldap` has not been verified.
 - Checking process can be inactivated via page [GererConfig](?GererConfig ':ignore') in parte `Activation des comptes par e-mail` with parameter `signup_email_activation`

## Fonctionning

At creation, an account is considered as inactivated.

When trying to connect (via `cookies` or with action `{{login}}`), an e-mail is sent with the activation link and an error message is displayed.

By clicking on the link, the account is activated.

**Administrators are always considered as activated, even if the database status is inactivated.**

## Management by administrators

New options are available in users management, on page [GererUtilisateurs](?GererCGererUtilisateursonfig ':ignore').

For an administrator, it is possible to modify the activation status of a user by clicking on the concerned button.

**Warning, modify administrators' status only changes the status in database, but the user will ever be able to connect without validating the activation link**