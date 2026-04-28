# Changelog

## 🇫🇷 Français

Toutes les evolutions notables de ce plugin sont documentees ici.

---

## 1.3.1 - 2026-04-28

### Corrections

* Renforce la securite des actions admin AJAX avec validation CSRF (moderer/supprimer, toggle par page, test SMTP).
* Securise la redirection front apres soumission de commentaire pour prevenir les open redirects via `page_url`.
* Aligne la lecture des reglages runtime dans le traitement front (`minCommentLength`, `maxCommentLength`, `requireApproval`) pour eviter les incoherences pendant `init()`.
* Finalise l'i18n des vues en supprimant les chaines FR en dur (badge publie, separateur de date, info-bulle Markdown).

### Documentation

* Met a jour le template de release pour refleter les changements.

---

## 1.3.0 - 2026-04-28

### Nouvelles fonctionnalites

* Selection d'algorithmes ALTCHA dans les parametres (SHA-256, SHA-384, SHA-512).
* Ordre d'affichage des commentaires configurable (ascendant/descendant).
* Notifications email pour les reponses aux commentaires (necessite SMTP configure).
* Badge unread sur le lien commentaires du menu principal indiquant le nombre de commentaires en attente.
* Alerte optionnelle dans les parametres pour signaler la disponibilite d'une mise a jour.

### Corrections

* Corrige les chemins CSS/JS hardcodes en utilisant `$this->directoryName()` pour fiabiliser l'installation sur toutes les configurations Bludit.
* Corrige la traduction des parametres pour la longueur min/max des caracteres.
* Corrige le texte : le bouton de sauvegarde est au haut de la page, non au bas.

---

## 1.2.1 - 2026-04-17

### Corrections

* Corrige la persistance runtime des reglages admin pour appliquer correctement `rateLimitSeconds` et `commentsPerPage`.
* Corrige un cas de fatal error sur des hebergements sans extension `mbstring` (fallbacks de compatibilite).
* Corrige la detection de locale Bludit avec fallbacks robustes pour appliquer correctement FR/EN.
* Corrige l'onglet moderation pour n'afficher que les pages ou les commentaires sont actives.

### Ameliorations

* Ajoute une couche i18n complete (anglais par defaut, surcharge francaise selon la langue du site).
* Localise les messages front/admin, y compris les retours JS (etats, erreurs, sauvegarde).

---

## 1.2.0 - 2026-04-17

### Documentation et publication

* Ajoute un `README.md` bilingue FR/EN.
* Ajoute des badges de version, compatibilite Bludit et licence.
* Ajoute `SECURITY.md` et `CONTRIBUTING.md`.
* Met a jour les informations mainteneur (auteur, email, site).

---

## 1.1.0 - 2026-04-17

### Corrections

* Corrige les soumissions involontaires du formulaire d'administration (toast "modifications sauvegardees" lors du changement d'onglet).
* Corrige le rate limiting pour respecter la valeur de `rateLimitSeconds`.
* Ajoute le comportement `0 = limitation desactivee` pour le delai entre commentaires.

### Nouvelles fonctionnalites

* Implemente le parametre `commentsPerPage` dans la pagination des commentaires front.

### Publication

* Ajoute `README.md`.
* Ajoute `LICENSE` en CC BY-SA 4.0.
* Met a jour les metadonnees (`version`, `releaseDate`, `license`).

---

## 🇬🇧 English

All notable changes to this plugin are documented here.

---

## 1.3.1 - 2026-04-28

### Fixes

* Strengthens security of admin AJAX actions with CSRF validation (moderate/delete, per-page toggle, SMTP test).
* Secures front-end redirection after comment submission to prevent open redirects via `page_url`.
* Aligns runtime settings handling in front-end processing (`minCommentLength`, `maxCommentLength`, `requireApproval`) to prevent inconsistencies during `init()`.
* Finalizes view i18n by removing hardcoded French strings (published badge, date separator, Markdown tooltip).

### Documentation

* Updates the release template to reflect changes from the DEV branch.

---

## 1.3.0 - 2026-04-28

### Features

* ALTCHA algorithm selection in settings (SHA-256, SHA-384, SHA-512).
* Configurable comment display order (ascending/descending).
* Email notifications for comment replies (requires configured SMTP).
* Unread badge on the main menu comment link showing pending comments count.
* Optional alert in settings to notify about available updates.

### Fixes

* Fixes hardcoded CSS/JS paths using `$this->directoryName()` for better compatibility across Bludit setups.
* Fixes translation of settings for min/max character length.
* Fixes text: the save button is at the top of the page, not the bottom.

---

## 1.2.1 - 2026-04-17

### Fixes

* Fixes runtime persistence of admin settings to properly apply `rateLimitSeconds` and `commentsPerPage`.
* Fixes a fatal error on hosts without the `mbstring` extension (compatibility fallbacks).
* Fixes Bludit locale detection with robust fallbacks to properly apply FR/EN.
* Fixes moderation tab to only display pages where comments are enabled.

### Improvements

* Adds full i18n layer (English by default, French override based on site language).
* Localizes front/admin messages, including JS feedback (states, errors, save).

---

## 1.2.0 - 2026-04-17

### Documentation & Release

* Adds a bilingual FR/EN `README.md`.
* Adds version, Bludit compatibility, and license badges.
* Adds `SECURITY.md` and `CONTRIBUTING.md`.
* Updates maintainer information (author, email, website).

---

## 1.1.0 - 2026-04-17

### Fixes

* Fixes unintended admin form submissions (toast "changes saved" when switching tabs).
* Fixes rate limiting to respect the `rateLimitSeconds` value.
* Adds behavior `0 = disabled limit` for delay between comments.

### Features

* Implements `commentsPerPage` setting in front-end comment pagination.

### Release

* Adds `README.md`.
* Adds `LICENSE` under CC BY-SA 4.0.
* Updates metadata (`version`, `releaseDate`, `license`).
