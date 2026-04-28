# Roadmap

Contributions targeting the `dev` branch are welcome on any of the items below.  
Feel free to open an issue or a pull request!

---

## 🔴 Bug fixes & security patches _(next patch)_

- [x] Move the Altcha HMAC key from the filesystem into `dbFields` to prevent exposure via direct URL
- [ ] Replace hardcoded CSS/JS paths with `$this->directoryName()` for reliable installation across all Bludit configurations
- [ ] Properly integrate the per-page comments toggle using `$site->customFields()` (current JS-based approach has a known conflict with other plugins)
- [ ] Adjust comment block CSS (`margin` and `width: auto`) for better theme compatibility
- [x] Correct the translation in the settings for the minimum and maximum number of characters

---

## 🟡 Planned features

- [x] Optional email field in the comment form, with notification sent to the commenter when a reply is published ("notify me of follow-up comments") — requires SMTP configuration (to be designed carefully to preserve the KISS philosophy)
- [ ] Display comment count below page titles in the front-office
- [x] Manageable comment display order (ascending / descending)
- [ ] Altcha algorithm selector in the settings page (SHA-256, Argon2, Scrypt)
- [x] Added an "unread comments" badge to the main menu
- [x] Added an optional parameter in settings to receive alert if update is available

---

## 🔵 Under consideration _(community feedback welcome)_

- [x] Comment pagination on the front-office
- [ ] AJAX comment submission (no page reload)
