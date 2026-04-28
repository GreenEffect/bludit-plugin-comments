# Bludit Plugin Comments

![Version](https://img.shields.io/badge/version-1.3.0-blue)
![Bludit](https://img.shields.io/badge/Bludit-3.x-5b32a3)
![License](https://img.shields.io/badge/license-CC%20BY--SA%204.0-lightgrey)

FR: Plugin de commentaires pour Bludit 3.x avec moderation, anti-spam ALTCHA, protection CSRF, validation de longueur, moderation, pagination, notifications email et ordre de tri configurable.

EN: Comment plugin for Bludit 3.x with ALTCHA anti-spam, CSRF protection, length validation, moderation, pagination, email notifications, and configurable comment ordering.

![Capture d'écran de l'application](screenshot.png)

## FR - Fonctionnalites

- Activation/desactivation des commentaires par page (configuration plugin ou editeur de page).
- Moderation des commentaires (en attente, publication, suppression, purge).
- Protection CSRF pour le formulaire front.
- Verification anti-spam avec ALTCHA avec selection d'algorithmes (SHA-256, SHA-384, SHA-512).
- Limitation de frequence configurable (`Delai entre deux commentaires (secondes)`).
- Pagination front configurable (`Commentaires affiches par page`).
- **Ordre d'affichage des commentaires configurable** (ascendant/descendant).
- **Notifications email** pour les reponses aux commentaires (optionnel).
- **Badge unread** affichant le nombre de commentaires en attente de moderation.
- **Alerte de mise a jour disponible** dans les parametres.
- Stockage JSON dans `bl-content/databases/bl-plugin-comments/`.

## EN - Features

- Enable/disable comments per page (plugin settings or page editor).
- Comment moderation workflow (pending, approve, delete, clear).
- CSRF protection for front-end submission.
- ALTCHA anti-spam verification with algorithm selection (SHA-256, SHA-384, SHA-512).
- Configurable rate limiting (`Delay between two comments (seconds)`).
- Configurable front pagination (`Comments displayed per page`).
- **Configurable comment ordering** (ascending/descending).
- **Email notifications** for comment replies (optional).
- **Unread badge** showing pending comment count.
- **Update availability alert** in settings.
- JSON file-based storage in `bl-content/databases/bl-plugin-comments/`.

## FR - Installation

1. Copier le dossier du plugin dans `bl-plugins/bl-plugin-comments/`.
2. Activer le plugin depuis l'administration Bludit.
3. Ouvrir la page de configuration du plugin pour ajuster les reglages.

## EN - Installation

1. Copy the plugin folder to `bl-plugins/bl-plugin-comments/`.
2. Enable the plugin from Bludit administration.
3. Open the plugin configuration page and adjust settings.

## FR - Reglages importants

- `Moderation avant publication` : impose une validation manuelle avant affichage public.
- `Delai entre deux commentaires (secondes)` :
  - `0` desactive la limitation
  - `> 0` applique un blocage par IP sur la duree definie
- `Commentaires affiches par page` : nombre de commentaires visibles avant pagination.

## EN - Important settings

- `Moderation before publishing`: requires manual validation before public display.
- `Delay between two comments (seconds)`:
  - `0` disables rate limiting
  - `> 0` applies IP-based cooldown
- `Comments displayed per page`: number of comments visible before pagination.

## Maintainer

- Author: Green Effect
- Contact: `contact@green-effect.fr`
- Website: [www.green-effect.fr](https://www.green-effect.fr)

## Changelog

See `CHANGELOG.md`.

## License

This project is distributed under [Creative Commons Attribution - ShareAlike 4.0 International](https://creativecommons.org/licenses/by-sa/4.0/).
See `LICENSE`.
