<?php
/**
 * Plugin Comments — Système de commentaires pour Bludit v3
 * Fichiers JSON, modération, CSRF, rate limiting
 *
 * @author   Green Effect
 * @contact  contact@green-effect.fr
 * @website  https://www.green-effect.fr
 * @version  1.3.0
 * @license  CC BY-SA 4.0
 */

class pluginComments extends Plugin {
    private $frontCommentsRendered = false;
    private $cachedTranslations = null;

    private function safeLength(string $value): int
    {
        return function_exists('mb_strlen') ? (int) mb_strlen($value) : strlen($value);
    }

    private function safeToLower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private function safePos(string $haystack, string $needle)
    {
        return function_exists('mb_strpos') ? mb_strpos($haystack, $needle) : strpos($haystack, $needle);
    }

    private function normalizeEmail(string $value): string
    {
        $email = trim($this->safeToLower($value));
        if ($email === '') {
            return '';
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    private function currentLocale(): string
    {
        $candidates = [];

        if (defined('LANGUAGE')) {
            $candidates[] = (string) LANGUAGE;
        }
        if (defined('LOCALE')) {
            $candidates[] = (string) LOCALE;
        }
        if (defined('LANG')) {
            $candidates[] = (string) LANG;
        }

        global $site;
        if (isset($site) && is_object($site)) {
            if (method_exists($site, 'language')) {
                try {
                    $candidates[] = (string) $site->language();
                } catch (Throwable $e) {}
            }
            if (method_exists($site, 'getField')) {
                try {
                    $candidates[] = (string) $site->getField('language');
                } catch (Throwable $e) {}
            }
        }

        // Fallback robuste: lire la config site directement.
        if (defined('PATH_DATABASES')) {
            $siteDbFile = PATH_DATABASES . 'site.php';
            if (file_exists($siteDbFile)) {
                $raw = @file_get_contents($siteDbFile);
                if (is_string($raw) && $raw !== '') {
                    if (preg_match('/"language"\s*:\s*"([^"]+)"/i', $raw, $m)) {
                        $candidates[] = (string) $m[1];
                    }
                }
            }
        }

        foreach ($candidates as $candidate) {
            $candidate = strtolower(trim((string) $candidate));
            if ($candidate === '') {
                continue;
            }
            if (strpos($candidate, 'fr') === 0) {
                return 'fr_FR';
            }
            if (strpos($candidate, 'cs') === 0) {
                return 'cs_CZ';
            }
            if (strpos($candidate, 'en') === 0) {
                return 'en';
            }
        }

        return 'en';
    }

    private function loadTranslationsForLocale(string $locale): array
    {
        $file = __DIR__ . DS . 'languages' . DS . $locale . '.json';
        if (!file_exists($file)) {
            return [];
        }
        $json = json_decode((string) file_get_contents($file), true);
        return is_array($json) ? $json : [];
    }

    private function translations(): array
    {
        if (is_array($this->cachedTranslations)) {
            return $this->cachedTranslations;
        }

        $en = $this->loadTranslationsForLocale('en');
        $locale = $this->currentLocale();
        $localized = $locale === 'en' ? [] : $this->loadTranslationsForLocale($locale);

        $base = isset($en['strings']) && is_array($en['strings']) ? $en['strings'] : [];
        $extra = isset($localized['strings']) && is_array($localized['strings']) ? $localized['strings'] : [];

        $this->cachedTranslations = array_merge($base, $extra);
        return $this->cachedTranslations;
    }

    public function t(string $key, array $replace = []): string
    {
        $translations = $this->translations();
        $message = isset($translations[$key]) ? (string) $translations[$key] : $key;

        foreach ($replace as $k => $v) {
            $message = str_replace('{' . $k . '}', (string) $v, $message);
        }

        return $message;
    }

    private function runtimeSettingsFile(): string
    {
        return $this->commentsBasePath() . 'runtime-settings.json';
    }

    private function loadRuntimeSettings(): array
    {
        $file = $this->runtimeSettingsFile();
        if (!file_exists($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    private function saveRuntimeSettings(array $settings): void
    {
        file_put_contents(
            $this->runtimeSettingsFile(),
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private function getIntSetting(string $key, int $default): int
    {
        $runtime = $this->loadRuntimeSettings();
        if (array_key_exists($key, $runtime)) {
            return (int) $runtime[$key];
        }

        $value = $this->getValue($key);
        if ($value === null || $value === '') {
            return $default;
        }

        return (int) $value;
    }

    private function getStringSetting(string $key, string $default): string
    {
        $runtime = $this->loadRuntimeSettings();
        if (array_key_exists($key, $runtime)) {
            return (string) $runtime[$key];
        }

        $value = $this->getValue($key);
        if ($value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }

    private function getBoolSetting(string $key, bool $default): bool
    {
        return $this->getIntSetting($key, $default ? 1 : 0) === 1;
    }

    private function syncRuntimeSettingsFromAdminPost(): void
    {
        if (!$this->adminIsLogged()) {
            return;
        }

        $settingsKeys = ['requireApproval', 'commentsPerPage', 'minCommentLength', 'maxCommentLength', 'rateLimitSeconds', 'commentOrder', 'altchaAlgorithm'];
        $hasSettingsPost = false;
        foreach ($settingsKeys as $k) {
            if (array_key_exists($k, $_POST)) {
                $hasSettingsPost = true;
                break;
            }
        }

        if (!$hasSettingsPost) {
            return;
        }

        $current = $this->loadRuntimeSettings();
        $oldRate = isset($current['rateLimitSeconds']) ? (int) $current['rateLimitSeconds'] : null;

        // Checkbox non soumis => valeur false explicite
        $current['requireApproval']  = !empty($_POST['requireApproval']) ? 1 : 0;
        $current['commentsPerPage']  = max(1, (int) ($_POST['commentsPerPage'] ?? 10));
        $current['minCommentLength'] = max(1, (int) ($_POST['minCommentLength'] ?? 10));
        $current['maxCommentLength'] = max(1, (int) ($_POST['maxCommentLength'] ?? 1000));
        $current['rateLimitSeconds'] = max(0, (int) ($_POST['rateLimitSeconds'] ?? 300));
        $rawOrder = isset($_POST['commentOrder']) ? (string) $_POST['commentOrder'] : 'desc';
        $current['commentOrder'] = in_array($rawOrder, ['asc', 'desc'], true) ? $rawOrder : 'desc';
        $rawAlgo = isset($_POST['altchaAlgorithm']) ? strtoupper((string) $_POST['altchaAlgorithm']) : 'SHA-256';
        $current['altchaAlgorithm'] = in_array($rawAlgo, ['SHA-256', 'SHA-384', 'SHA-512'], true) ? $rawAlgo : 'SHA-256';

        $this->saveRuntimeSettings($current);

        // Purger les quotas existants si le delai change.
        if ($oldRate !== null && $oldRate !== (int) $current['rateLimitSeconds']) {
            $rateFile = $this->commentsBasePath() . 'rate_limits.json';
            if (file_exists($rateFile)) {
                @unlink($rateFile);
            }
        }
    }

    // ──────────────────────────────────────────────
    //  INITIALISATION
    // ──────────────────────────────────────────────

    public function init()
    {
        // Démarrer la session tôt pour fiabiliser CSRF + flash front.
        // Sans cela, selon le hook/theme, la session peut démarrer trop tard.
        $this->startSession();

        $this->dbFields = [
            'requireApproval'  => 1,
            'commentsPerPage'  => 10,
            'minCommentLength' => 10,
            'maxCommentLength' => 1000,
            'rateLimitSeconds' => 300,
            'commentOrder'      => 'desc',
            'altchaAlgorithm'  => 'SHA-256',
            'altchaSecret'     => '',
            'smtpEnabled'      => 0,
            'smtpHost'         => '',
            'smtpPort'         => 587,
            'smtpEncryption'   => 'tls',
            'smtpAuth'         => 1,
            'smtpUsername'     => '',
            'smtpPassword'     => '',
            'smtpFromEmail'    => '',
            'smtpFromName'     => '',
            'checkForUpdates'  => 0,
        ];

        // Créer le répertoire de données
        $base = $this->commentsBasePath();
        if (!file_exists($base)) {
            mkdir($base, 0755, true);
        }

        // Migration de la clé HMAC Altcha depuis le fichier legacy vers dbFields.
        // S'exécute une seule fois, dès le premier chargement, sans dépendre du widget JS.
        $this->migrateAltchaSecret();

        // Endpoint de challenge ALTCHA (standalone, servi par le plugin)
        if (isset($_GET['blc_altcha']) && $_GET['blc_altcha'] === 'challenge') {
            $this->outputAltchaChallenge();
            exit;
        }

        // Garantit l'usage des reglages back-office au runtime front.
        $this->syncRuntimeSettingsFromAdminPost();

        // ── Soumission commentaire (front) ─────────
        if (!empty($_POST['bl_comment_submit'])) {
            $this->processCommentSubmission();
        }

        // ── Actions admin ──────────────────────────
        if (!empty($_POST['bl_comment_action']) && $this->adminIsLogged()) {
            if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
                $this->rejectAdminCsrfRequest();
            }
            $this->processAdminAction();
        }

        // ── Toggle commentaires (AJAX éditeur) ─────
        if (!empty($_POST['bl_toggle_comments']) && $this->adminIsLogged()) {
            if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
                $this->rejectAdminCsrfRequest();
            }
            $key     = $this->cleanKey($_POST['page_key'] ?? '');
            $enabled = !empty($_POST['enabled']);
            $this->setPageCommentsEnabled($key, $enabled);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true, 'enabled' => $enabled]);
                exit;
            }
        }
    }

    // ──────────────────────────────────────────────
    //  CHEMINS
    // ──────────────────────────────────────────────

    private function commentsBasePath(): string
    {
        return PATH_DATABASES . 'bl-plugin-comments' . DS;
    }

    private function pluginDbFilePath(): string
    {
        return PATH_PLUGINS_DATABASES . $this->directoryName . DS . 'db.php';
    }

    private function loadPluginDbFromDisk(): array
    {
        $dbFile = $this->pluginDbFilePath();
        if (!file_exists($dbFile)) {
            return [];
        }

        $raw = @file_get_contents($dbFile);
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $jsonStart = strpos($raw, '{');
        if ($jsonStart === false) {
            return [];
        }

        $parsed = json_decode(substr($raw, $jsonStart), true);
        return is_array($parsed) ? $parsed : [];
    }

    private function savePluginDbToDisk(array $db): void
    {
        $dbFile = $this->pluginDbFilePath();
        $raw = file_exists($dbFile) ? (string) @file_get_contents($dbFile) : '';
        $jsonStart = strpos($raw, '{');
        $prefix = $jsonStart === false
            ? "<?php defined('BLUDIT') or die('Bludit CMS.');\n"
            : substr($raw, 0, $jsonStart);

        $json = json_encode($db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || $json === '') {
            return;
        }

        @file_put_contents($dbFile, $prefix . $json);
    }

    private function persistPluginDbField(string $key, string $value): void
    {
        if (method_exists($this, 'setValue')) {
            $this->setValue($key, $value);
        }

        $db = $this->loadPluginDbFromDisk();
        $db[$key] = $value;
        $this->savePluginDbToDisk($db);
    }

    private function pageDir(string $key): string
    {
        return $this->commentsBasePath() . $this->cleanKey($key) . DS;
    }

    private function ensurePageDir(string $key): string
    {
        $dir = $this->pageDir($key);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private function cleanKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
    }

    // ──────────────────────────────────────────────
    //  STOCKAGE COMMENTAIRES
    // ──────────────────────────────────────────────

    public function loadComments(string $pageKey, string $status = 'approved'): array
    {
        $file = $this->pageDir($pageKey) . $status . '.json';
        if (!file_exists($file)) {
            return [];
        }
        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    private function saveComments(string $pageKey, array $comments, string $status): void
    {
        $this->ensurePageDir($pageKey);
        $file = $this->pageDir($pageKey) . $status . '.json';
        file_put_contents(
            $file,
            json_encode(array_values($comments), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    // ──────────────────────────────────────────────
    //  PARAMÈTRES PAR PAGE
    // ──────────────────────────────────────────────

    private function pageSettingsFile(): string
    {
        return $this->commentsBasePath() . 'page-settings.json';
    }

    private function loadPageSettings(): array
    {
        $file = $this->pageSettingsFile();
        if (!file_exists($file)) {
            return [];
        }
        return json_decode(file_get_contents($file), true) ?: [];
    }

    private function savePageSettings(array $settings): void
    {
        file_put_contents(
            $this->pageSettingsFile(),
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    public function isCommentsEnabled(string $pageKey): bool
    {
        $pageKey = $this->cleanKey($pageKey);
        $s = $this->loadPageSettings();
        return isset($s[$pageKey]['enabled']) ? (bool) $s[$pageKey]['enabled'] : false;
    }

    private function setPageCommentsEnabled(string $pageKey, bool $enabled): void
    {
        $pageKey = $this->cleanKey($pageKey);
        $s = $this->loadPageSettings();
        $s[$pageKey]['enabled'] = $enabled;
        $this->savePageSettings($s);
    }

    // ──────────────────────────────────────────────
    //  CSRF
    // ──────────────────────────────────────────────

    private function csrfToken(): string
    {
        $this->startSession();
        if (empty($_SESSION['bl_comments_csrf'])) {
            $_SESSION['bl_comments_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['bl_comments_csrf'];
    }

    private function validateCsrf(string $token): bool
    {
        $this->startSession();
        return !empty($_SESSION['bl_comments_csrf'])
            && hash_equals($_SESSION['bl_comments_csrf'], $token);
    }

    private function rejectAdminCsrfRequest(): void
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            http_response_code(403);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'ok' => false,
                'message' => $this->t('flash_csrf_error'),
            ]);
            exit;
        }

        header('Location: ' . HTML_PATH_ADMIN_ROOT . 'configure-plugin/pluginComments#tab-settings');
        exit;
    }

    // ──────────────────────────────────────────────
    //  SESSION HELPERS
    // ──────────────────────────────────────────────

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private function setFlash(string $type, string $msg): void
    {
        $this->startSession();
        $_SESSION['bl_comment_flash_' . $type] = $msg;
    }

    private function getFlash(string $type): string
    {
        $this->startSession();
        $key = 'bl_comment_flash_' . $type;
        $val = $_SESSION[$key] ?? '';
        unset($_SESSION[$key]);
        return $val;
    }

    // ──────────────────────────────────────────────
    //  RATE LIMITING
    // ──────────────────────────────────────────────

    private function isRateLimited(string $ip): bool
    {
        $limit = $this->getIntSetting('rateLimitSeconds', 300);
        if ($limit <= 0) {
            return false;
        }

        $file  = $this->commentsBasePath() . 'rate_limits.json';
        $data  = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
        $now   = time();
        $key   = md5($ip);

        // Purge entries expirées
        foreach ($data as $k => $ts) {
            if ($now - $ts > $limit) {
                unset($data[$k]);
            }
        }

        if (isset($data[$key])) {
            file_put_contents($file, json_encode($data));
            return true;
        }

        $data[$key] = $now;
        file_put_contents($file, json_encode($data));
        return false;
    }

    // ──────────────────────────────────────────────
    //  MARKDOWN LÉGER
    // ──────────────────────────────────────────────

    public function parseMarkdown(string $text): string
    {
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        // Bold
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
        // Italic
        $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '<em>$1</em>', $text);
        // Inline code
        $text = preg_replace('/`([^`\r\n]+)`/', '<code>$1</code>', $text);
        // Liens
        $text = preg_replace(
            '/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/',
            '<a href="$2" rel="nofollow noopener" target="_blank">$1</a>',
            $text
        );
        // Sauts de ligne
        return nl2br($text);
    }

    // ──────────────────────────────────────────────
    //  TRAITEMENT — SOUMISSION COMMENTAIRE (FRONT)
    // ──────────────────────────────────────────────

    private function processCommentSubmission(): void
    {
        if (!$this->validateAltchaPayload($_POST['altcha'] ?? '')) {
            $this->setFlash('error', $this->t('flash_altcha_invalid'));
            $this->redirectToPage();
            return;
        }

        // CSRF
        if (!$this->validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->setFlash('error', $this->t('flash_csrf_error'));
            $this->redirectToPage();
            return;
        }

        $pageKey = $this->cleanKey($_POST['page_key'] ?? '');

        if (!$this->isCommentsEnabled($pageKey)) {
            $this->setFlash('error', $this->t('flash_comments_disabled'));
            $this->redirectToPage();
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if ($this->isRateLimited($ip)) {
            $mins = ceil($this->getIntSetting('rateLimitSeconds', 300) / 60);
            $this->setFlash('error', $this->t('flash_rate_limited', ['minutes' => $mins]));
            $this->redirectToPage();
            return;
        }

        $author  = trim($_POST['comment_author']  ?? '');
        $content = trim($_POST['comment_content'] ?? '');
        $emailInput = $this->normalizeEmail((string) ($_POST['comment_email'] ?? ''));
        $notifyOptIn = !empty($_POST['comment_notify']);
        $email = $notifyOptIn ? $emailInput : '';
        $notifyStored = ($notifyOptIn && $email !== '') ? 1 : 0;

        if (empty($author) || empty($content)) {
            $this->setFlash('error', $this->t('flash_author_content_required'));
            $this->redirectToPage();
            return;
        }

        if ($this->safeLength($author) > 100) {
            $this->setFlash('error', $this->t('flash_author_too_long'));
            $this->redirectToPage();
            return;
        }

        $minLen = $this->getIntSetting('minCommentLength', 10);
        $maxLen = $this->getIntSetting('maxCommentLength', 1000);

        if ($this->safeLength($content) < $minLen) {
            $this->setFlash('error', $this->t('flash_comment_too_short', ['min' => $minLen]));
            $this->redirectToPage();
            return;
        }

        if ($this->safeLength($content) > $maxLen) {
            $this->setFlash('error', $this->t('flash_comment_too_long', ['max' => $maxLen]));
            $this->redirectToPage();
            return;
        }

        $requireApproval = $this->getBoolSetting('requireApproval', true);
        $status          = $requireApproval ? 'pending' : 'approved';

        $comment = [
            'id'        => uniqid('c', true),
            'author'    => $author,
            'email'     => $email,
            'notify'    => $notifyStored,
            'content'   => $content,
            'date'      => date('Y-m-d H:i:s'),
            'timestamp' => time(),
            'ip_hash'   => md5($ip),
        ];

        $list   = $this->loadComments($pageKey, $status);
        $list[] = $comment;
        $this->saveComments($pageKey, $list, $status);

        $msg = $requireApproval
            ? $this->t('flash_comment_pending')
            : $this->t('flash_comment_published');

        $this->setFlash('success', $msg);
        $this->redirectToPage();
    }

    private function redirectToPage(): void
    {
        $rawUrl = (string) ($_POST['page_url'] ?? '');
        $safeUrl = $this->safeFrontRedirectUrl($rawUrl);
        header('Location: ' . $safeUrl . '#comments');
        exit;
    }

    private function safeFrontRedirectUrl(string $rawUrl): string
    {
        $fallback = defined('HTML_PATH_ROOT') ? HTML_PATH_ROOT : '/';
        $rawUrl = trim(str_replace(["\r", "\n"], '', $rawUrl));
        if ($rawUrl === '') {
            return $fallback;
        }

        $parts = @parse_url($rawUrl);
        if ($parts === false) {
            return $fallback;
        }

        $query = isset($parts['query']) && $parts['query'] !== ''
            ? '?' . $parts['query']
            : '';

        if (isset($parts['scheme'])) {
            $scheme = strtolower((string) $parts['scheme']);
            if (!in_array($scheme, ['http', 'https'], true)) {
                return $fallback;
            }

            $targetHost = strtolower((string) ($parts['host'] ?? ''));
            $currentHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
            if ($targetHost === '' || $currentHost === '' || $targetHost !== $currentHost) {
                return $fallback;
            }

            $path = (string) ($parts['path'] ?? '/');
            if ($path === '' || $path[0] !== '/') {
                $path = '/';
            }

            return $path . $query;
        }

        if (isset($parts['host'])) {
            return $fallback;
        }

        $path = (string) ($parts['path'] ?? '');
        if ($path === '' || $path[0] !== '/') {
            return $fallback;
        }

        return $path . $query;
    }

    private function altchaHashAlgo(string $altchaAlgorithm): string
    {
        $map = ['SHA-256' => 'sha256', 'SHA-384' => 'sha384', 'SHA-512' => 'sha512'];
        return $map[$altchaAlgorithm] ?? 'sha256';
    }

    private function outputAltchaChallenge(): void
    {
        $secret    = $this->getAltchaSecret();
        $algorithm = $this->getStringSetting('altchaAlgorithm', 'SHA-256');
        if (!in_array($algorithm, ['SHA-256', 'SHA-384', 'SHA-512'], true)) {
            $algorithm = 'SHA-256';
        }
        $phpAlgo   = $this->altchaHashAlgo($algorithm);
        $maxNumber = 100000;
        $number    = random_int(1, $maxNumber);
        $salt      = bin2hex(random_bytes(12));
        $challenge = hash($phpAlgo, $salt . $number);
        $signature = hash_hmac($phpAlgo, $challenge, $secret);

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'algorithm' => $algorithm,
            'challenge' => $challenge,
            'salt'      => $salt,
            'signature' => $signature,
            'maxnumber' => $maxNumber,
        ]);
    }

    private function validateAltchaPayload(string $payload): bool
    {
        if ($payload === '') {
            return false;
        }

        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            $decoded = json_decode($this->decodeBase64Url($payload), true);
            if (!is_array($decoded)) {
                return false;
            }
        }

        $algorithm = strtoupper((string) ($decoded['algorithm'] ?? ''));
        $challenge = (string) ($decoded['challenge'] ?? '');
        $salt      = (string) ($decoded['salt'] ?? '');
        $signature = (string) ($decoded['signature'] ?? '');
        $number    = isset($decoded['number']) ? (int) $decoded['number'] : 0;

        if (
            !in_array($algorithm, ['SHA-256', 'SHA-384', 'SHA-512'], true)
            || $challenge === ''
            || $salt === ''
            || $signature === ''
            || $number < 1
            || $number > 100000
        ) {
            return false;
        }

        $phpAlgo             = $this->altchaHashAlgo($algorithm);
        $secret              = $this->getAltchaSecret();
        $expectedChallenge   = hash($phpAlgo, $salt . $number);
        $expectedSignature   = hash_hmac($phpAlgo, $challenge, $secret);

        return hash_equals($expectedChallenge, $challenge)
            && hash_equals($expectedSignature, $signature);
    }

    private function decodeBase64Url(string $payload): string
    {
        $normalized = strtr($payload, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode($normalized, true);
        return $decoded === false ? '' : $decoded;
    }

    // ──────────────────────────────────────────────
    //  VÉRIFICATION DE VERSION
    // ──────────────────────────────────────────────

    private function getLocalVersion(): string
    {
        $file = __DIR__ . DS . 'VERSION';
        if (!file_exists($file)) {
            return '0.0.0';
        }
        return trim((string) file_get_contents($file));
    }

    private function fetchRemoteVersion(): string
    {
        $cacheFile = $this->commentsBasePath() . 'version-cache.json';
        $cacheTtl  = 86400; // 24h

        if (file_exists($cacheFile)) {
            $cache = json_decode((string) file_get_contents($cacheFile), true);
            if (
                is_array($cache)
                && isset($cache['version'], $cache['checked_at'])
                && (time() - (int) $cache['checked_at']) < $cacheTtl
            ) {
                return (string) $cache['version'];
            }
        }

        $url = 'https://raw.githubusercontent.com/GreenEffect/bludit-plugin-comments/main/VERSION';
        $ctx = stream_context_create([
            'http' => [
                'timeout'         => 5,
                'user_agent'      => 'bl-plugin-comments-update-check/1.0',
                'follow_location' => 1,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $remote = @file_get_contents($url, false, $ctx);
        if ($remote === false || trim($remote) === '') {
            return '';
        }

        $version = trim($remote);
        @file_put_contents(
            $cacheFile,
            json_encode(['version' => $version, 'checked_at' => time()])
        );

        return $version;
    }

    private function isNewerVersion(string $remote, string $local): bool
    {
        if ($remote === '' || $local === '') {
            return false;
        }
        return version_compare($remote, $local, '>');
    }

    /**
     * Appelée dans init() à chaque requête.
     * Migre la clé legacy depuis altcha-secret.txt vers dbFields une seule fois,
     * puis génère une nouvelle clé si aucune n'existe encore.
     * Sans ce déclenchement précoce, la migration dépendrait du widget JS.
     */
    private function migrateAltchaSecret(): void
    {
        // Déjà persisté en base → rien à faire.
        $db = $this->loadPluginDbFromDisk();
        if (!empty($db['altchaSecret'])) {
            return;
        }

        // Migration legacy: clé précédemment stockée dans un fichier texte exposable via URL.
        $legacyFile = $this->commentsBasePath() . 'altcha-secret.txt';
        if (file_exists($legacyFile)) {
            $legacySecret = trim((string) file_get_contents($legacyFile));
            if ($legacySecret !== '') {
                $this->persistPluginDbField('altchaSecret', $legacySecret);
                @unlink($legacyFile);
                return;
            }
        }

        // Première installation : génère et persiste une nouvelle clé.
        $this->persistPluginDbField('altchaSecret', bin2hex(random_bytes(32)));
    }

    private function getAltchaSecret(): string
    {
        $db = $this->loadPluginDbFromDisk();
        if (!empty($db['altchaSecret'])) {
            return trim((string) $db['altchaSecret']);
        }

        // Filet de sécurité : génère une clé temporaire en mémoire si la migration
        // n'a pas encore eu lieu (ne devrait pas arriver en temps normal).
        return bin2hex(random_bytes(32));
    }

    private function collectSmtpSettingsFromRequest(): array
    {
        $smtpEnabled = array_key_exists('smtpEnabled', $_POST)
            ? !empty($_POST['smtpEnabled'])
            : (bool) $this->getValue('smtpEnabled');

        $smtpAuth = array_key_exists('smtpAuth', $_POST)
            ? !empty($_POST['smtpAuth'])
            : (bool) $this->getValue('smtpAuth');

        $smtp = [
            'enabled'    => $smtpEnabled,
            'host'       => trim((string) ($_POST['smtpHost'] ?? $this->getValue('smtpHost'))),
            'port'       => max(1, (int) ($_POST['smtpPort'] ?? $this->getValue('smtpPort'))),
            'encryption' => (string) ($_POST['smtpEncryption'] ?? $this->getValue('smtpEncryption')),
            'auth'       => $smtpAuth,
            'username'   => (string) ($_POST['smtpUsername'] ?? $this->getValue('smtpUsername')),
            'password'   => (string) ($_POST['smtpPassword'] ?? $this->getValue('smtpPassword')),
            'fromEmail'  => $this->normalizeEmail((string) ($_POST['smtpFromEmail'] ?? $this->getValue('smtpFromEmail'))),
            'fromName'   => trim((string) ($_POST['smtpFromName'] ?? $this->getValue('smtpFromName'))),
        ];

        if (!in_array($smtp['encryption'], ['none', 'tls', 'ssl'], true)) {
            $smtp['encryption'] = 'tls';
        }

        return $smtp;
    }

    /**
     * Lit les parametres SMTP directement depuis db.php sur le disque.
     *
     * getValue() lit $this->db, qui n'est rempli par Bludit QU'APRES init().
     * Or processAdminAction() est appele depuis init() donc $this->db est encore vide.
     * On lit le fichier JSON directement pour contourner ce probleme.
     */
    private function getSmtpSettingsFromConfig(): array
    {
        $db = $this->loadPluginDbFromDisk();

        $smtpEncryption = isset($db['smtpEncryption']) ? (string) $db['smtpEncryption'] : 'tls';
        if (!in_array($smtpEncryption, ['none', 'tls', 'ssl'], true)) {
            $smtpEncryption = 'tls';
        }

        return [
            'enabled'    => !empty($db['smtpEnabled']),
            'host'       => isset($db['smtpHost']) ? trim((string) $db['smtpHost']) : '',
            'port'       => isset($db['smtpPort']) ? max(1, (int) $db['smtpPort']) : 587,
            'encryption' => $smtpEncryption,
            'auth'       => !empty($db['smtpAuth']),
            'username'   => isset($db['smtpUsername']) ? (string) $db['smtpUsername'] : '',
            'password'   => isset($db['smtpPassword']) ? (string) $db['smtpPassword'] : '',
            'fromEmail'  => $this->normalizeEmail(isset($db['smtpFromEmail']) ? (string) $db['smtpFromEmail'] : ''),
            'fromName'   => isset($db['smtpFromName']) ? trim((string) $db['smtpFromName']) : '',
        ];
    }

    private function canSendSmtpNotification(array $smtp): bool
    {
        if (empty($smtp['enabled'])) {
            return false;
        }

        if (empty($smtp['host']) || empty($smtp['port']) || empty($smtp['fromEmail'])) {
            return false;
        }

        if (!empty($smtp['auth']) && (empty($smtp['username']) || empty($smtp['password']))) {
            return false;
        }

        return true;
    }

    private function encodeHeaderValue(string $value): string
    {
        $clean = str_replace(["\r", "\n"], '', $value);
        if ($clean === '') {
            return '';
        }

        if (function_exists('mb_encode_mimeheader')) {
            return mb_encode_mimeheader($clean, 'UTF-8', 'B', "\r\n");
        }

        return '=?UTF-8?B?' . base64_encode($clean) . '?=';
    }

    private function smtpConnect(array $smtp, &$socket, &$errorDetail): bool
    {
        $socket = null;
        $errorDetail = '';

        $remoteHost = $smtp['encryption'] === 'ssl'
            ? 'ssl://' . $smtp['host']
            : $smtp['host'];

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            $remoteHost . ':' . (int) $smtp['port'],
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT
        );

        if ($socket === false) {
            $errorDetail = trim($errstr) !== '' ? $errstr : ('Error ' . $errno);
            return false;
        }

        stream_set_timeout($socket, 10);

        $greeting = $this->smtpReadResponse($socket);
        if ($greeting['code'] !== 220) {
            $errorDetail = $greeting['raw'] !== '' ? $greeting['raw'] : 'Invalid SMTP greeting';
            if (is_resource($socket)) {
                fclose($socket);
            }
            return false;
        }

        $helo = $this->smtpSendCommand($socket, 'EHLO localhost', [250]);
        if (!$helo['ok']) {
            $errorDetail = $helo['raw'] !== '' ? $helo['raw'] : 'EHLO failed';
            if (is_resource($socket)) {
                fclose($socket);
            }
            return false;
        }

        if ($smtp['encryption'] === 'tls') {
            $startTls = $this->smtpSendCommand($socket, 'STARTTLS', [220]);
            if (!$startTls['ok']) {
                $errorDetail = $startTls['raw'] !== '' ? $startTls['raw'] : 'STARTTLS failed';
                if (is_resource($socket)) {
                    fclose($socket);
                }
                return false;
            }

            $crypto = @stream_socket_enable_crypto(
                $socket,
                true,
                defined('STREAM_CRYPTO_METHOD_TLS_CLIENT')
                    ? STREAM_CRYPTO_METHOD_TLS_CLIENT
                    : STREAM_CRYPTO_METHOD_SSLv23_CLIENT
            );

            if (!$crypto) {
                $errorDetail = 'TLS negotiation failed';
                if (is_resource($socket)) {
                    fclose($socket);
                }
                return false;
            }

            $heloAfterTls = $this->smtpSendCommand($socket, 'EHLO localhost', [250]);
            if (!$heloAfterTls['ok']) {
                $errorDetail = $heloAfterTls['raw'] !== '' ? $heloAfterTls['raw'] : 'EHLO after TLS failed';
                if (is_resource($socket)) {
                    fclose($socket);
                }
                return false;
            }
        }

        if (!empty($smtp['auth'])) {
            $authCmd = $this->smtpSendCommand($socket, 'AUTH LOGIN', [334]);
            if (!$authCmd['ok']) {
                if (is_resource($socket)) {
                    fclose($socket);
                }
                return false;
            }

            $authUser = $this->smtpSendCommand($socket, base64_encode((string) $smtp['username']), [334]);
            if (!$authUser['ok']) {
                $errorDetail = $authUser['raw'] !== '' ? $authUser['raw'] : 'SMTP username rejected';
                if (is_resource($socket)) {
                    fclose($socket);
                }
                return false;
            }

            $authPass = $this->smtpSendCommand($socket, base64_encode((string) $smtp['password']), [235]);
            if (!$authPass['ok']) {
                $errorDetail = $authPass['raw'] !== '' ? $authPass['raw'] : 'SMTP password rejected';
                if (is_resource($socket)) {
                    fclose($socket);
                }
                return false;
            }
        }

        return true;
    }

    private function sendSmtpMail(array $smtp, string $toEmail, string $subject, string $body, ?string &$debug = null): bool
    {
        $debug = '';
        $recipient = $this->normalizeEmail($toEmail);
        if ($recipient === '') {
            $debug = 'Invalid recipient email.';
            return false;
        }

        $socket = null;
        $errorDetail = '';
        if (!$this->smtpConnect($smtp, $socket, $errorDetail)) {
            $debug = 'SMTP connect failed: ' . $errorDetail;
            return false;
        }

        if (!is_resource($socket)) {
            $debug = 'SMTP connect returned no socket resource.';
            return false;
        }

        $mailFrom = $this->smtpSendCommand($socket, 'MAIL FROM:<' . $smtp['fromEmail'] . '>', [250]);
        if (!$mailFrom['ok']) {
            $debug = 'MAIL FROM rejected: ' . ($mailFrom['raw'] !== '' ? $mailFrom['raw'] : 'Unknown SMTP error');
            fclose($socket);
            return false;
        }

        $rcptTo = $this->smtpSendCommand($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
        if (!$rcptTo['ok']) {
            $debug = 'RCPT TO rejected: ' . ($rcptTo['raw'] !== '' ? $rcptTo['raw'] : 'Unknown SMTP error');
            fclose($socket);
            return false;
        }

        $dataStart = $this->smtpSendCommand($socket, 'DATA', [354]);
        if (!$dataStart['ok']) {
            $debug = 'DATA command rejected: ' . ($dataStart['raw'] !== '' ? $dataStart['raw'] : 'Unknown SMTP error');
            fclose($socket);
            return false;
        }

        $fromName = trim((string) ($smtp['fromName'] ?? ''));
        $fromHeader = $smtp['fromEmail'];
        if ($fromName !== '') {
            $fromHeader = $this->encodeHeaderValue($fromName) . ' <' . $smtp['fromEmail'] . '>';
        }

        $subjectHeader = $this->encodeHeaderValue($subject);
        $headers = [
            'Date: ' . date('r'),
            'From: ' . $fromHeader,
            'To: <' . $recipient . '>',
            'Subject: ' . $subjectHeader,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        $bodyNormalized = str_replace(["\r\n", "\r"], "\n", $body);
        $bodyNormalized = str_replace("\n", "\r\n", $bodyNormalized);
        $bodyNormalized = preg_replace('/(^|\r\n)\./', '$1..', $bodyNormalized);

        $payload = implode("\r\n", $headers) . "\r\n\r\n" . $bodyNormalized . "\r\n.\r\n";
        fwrite($socket, $payload);

        $dataEnd = $this->smtpReadResponse($socket);
        $this->smtpSendCommand($socket, 'QUIT', [221, 250]);
        fclose($socket);

        $ok = in_array($dataEnd['code'], [250], true);
        $debug = $ok
            ? 'SMTP accepted message.'
            : ('SMTP message rejected: ' . ($dataEnd['raw'] !== '' ? $dataEnd['raw'] : ('Code ' . $dataEnd['code'])));

        return $ok;
    }

    private function notifyPreviousCommenters(string $pageKey, array $newComment, array $approvedBefore): void
    {
        $smtp = $this->getSmtpSettingsFromConfig();

        if (!$this->canSendSmtpNotification($smtp)) {
            return;
        }

        $newCommentEmail = $this->normalizeEmail((string) ($newComment['email'] ?? ''));
        $newCommentNotify = !empty($newComment['notify']);
        $recipients = [];

        // Notifier aussi l'auteur du commentaire nouvellement publie s'il est abonne.
        if ($newCommentNotify && $newCommentEmail !== '') {
            $recipients[$newCommentEmail] = true;
        }

        foreach ($approvedBefore as $existing) {
            $email = $this->normalizeEmail((string) ($existing['email'] ?? ''));
            if ($email === '') {
                continue;
            }
            if (empty($existing['notify'])) {
                continue;
            }
            $recipients[$email] = true;
        }

        if (empty($recipients)) {
            return;
        }

        $allPages = $this->getBluditPages();
        $pageTitle = isset($allPages[$pageKey]['title']) ? (string) $allPages[$pageKey]['title'] : (string) $pageKey;
        $author = trim((string) ($newComment['author'] ?? ''));
        $content = trim((string) ($newComment['content'] ?? ''));
        $content = preg_replace('/\s+/u', ' ', $content);
        if ($this->safeLength($content) > 220) {
            $content = rtrim(substr($content, 0, 217)) . '...';
        }

        $subject = $this->t('notification_new_comment_subject', ['page' => $pageTitle]);
        $body = $this->t('notification_new_comment_body', [
            'page' => $pageTitle,
            'author' => $author !== '' ? $author : $this->t('notification_author_fallback'),
            'content' => $content,
        ]);

        foreach (array_keys($recipients) as $recipientEmail) {
            $recipientDebug = '';
            $this->sendSmtpMail($smtp, $recipientEmail, $subject, $body, $recipientDebug);
        }
    }

    private function smtpReadResponse($socket): array
    {
        $raw = '';
        $code = 0;

        while (($line = fgets($socket, 515)) !== false) {
            $raw .= $line;

            if (preg_match('/^(\d{3})([ \-])/', $line, $m)) {
                $code = (int) $m[1];
                if ($m[2] === ' ') {
                    break;
                }
            }

            if (strlen($raw) > 8192) {
                break;
            }
        }

        return ['code' => $code, 'raw' => trim($raw)];
    }

    private function smtpSendCommand($socket, string $command, array $expectedCodes): array
    {
        fwrite($socket, $command . "\r\n");
        $response = $this->smtpReadResponse($socket);

        return [
            'ok' => in_array($response['code'], $expectedCodes, true),
            'code' => $response['code'],
            'raw' => $response['raw'],
        ];
    }

    private function testSmtpConnection(array $smtp): array
    {
        if (empty($smtp['enabled'])) {
            return ['ok' => false, 'message' => $this->t('smtp_test_disabled')];
        }

        if ($smtp['host'] === '') {
            return ['ok' => false, 'message' => $this->t('smtp_test_missing_host')];
        }

        if ((int) $smtp['port'] <= 0) {
            return ['ok' => false, 'message' => $this->t('smtp_test_missing_port')];
        }

        if (!empty($smtp['auth']) && trim((string) $smtp['username']) === '') {
            return ['ok' => false, 'message' => $this->t('smtp_test_missing_username')];
        }

        if (!empty($smtp['auth']) && (string) $smtp['password'] === '') {
            return ['ok' => false, 'message' => $this->t('smtp_test_missing_password')];
        }

        $remoteHost = $smtp['encryption'] === 'ssl'
            ? 'ssl://' . $smtp['host']
            : $smtp['host'];

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            $remoteHost . ':' . (int) $smtp['port'],
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT
        );

        if ($socket === false) {
            return [
                'ok' => false,
                'message' => $this->t('smtp_test_connection_failed', ['detail' => trim($errstr) !== '' ? $errstr : ('Error ' . $errno)]),
            ];
        }

        stream_set_timeout($socket, 10);

        $greeting = $this->smtpReadResponse($socket);
        if ($greeting['code'] !== 220) {
            fclose($socket);
            return [
                'ok' => false,
                'message' => $this->t('smtp_test_connection_failed', ['detail' => $greeting['raw'] !== '' ? $greeting['raw'] : 'Invalid SMTP greeting']),
            ];
        }

        $helo = $this->smtpSendCommand($socket, 'EHLO localhost', [250]);
        if (!$helo['ok']) {
            fclose($socket);
            return [
                'ok' => false,
                'message' => $this->t('smtp_test_connection_failed', ['detail' => $helo['raw'] !== '' ? $helo['raw'] : 'EHLO failed']),
            ];
        }

        if ($smtp['encryption'] === 'tls') {
            $startTls = $this->smtpSendCommand($socket, 'STARTTLS', [220]);
            if (!$startTls['ok']) {
                fclose($socket);
                return [
                    'ok' => false,
                    'message' => $this->t('smtp_test_connection_failed', ['detail' => $startTls['raw'] !== '' ? $startTls['raw'] : 'STARTTLS failed']),
                ];
            }

            $crypto = @stream_socket_enable_crypto(
                $socket,
                true,
                defined('STREAM_CRYPTO_METHOD_TLS_CLIENT')
                    ? STREAM_CRYPTO_METHOD_TLS_CLIENT
                    : STREAM_CRYPTO_METHOD_SSLv23_CLIENT
            );

            if (!$crypto) {
                fclose($socket);
                return [
                    'ok' => false,
                    'message' => $this->t('smtp_test_connection_failed', ['detail' => 'TLS negotiation failed']),
                ];
            }

            $heloAfterTls = $this->smtpSendCommand($socket, 'EHLO localhost', [250]);
            if (!$heloAfterTls['ok']) {
                fclose($socket);
                return [
                    'ok' => false,
                    'message' => $this->t('smtp_test_connection_failed', ['detail' => $heloAfterTls['raw'] !== '' ? $heloAfterTls['raw'] : 'EHLO after TLS failed']),
                ];
            }
        }

        if (!empty($smtp['auth'])) {
            $authCmd = $this->smtpSendCommand($socket, 'AUTH LOGIN', [334]);
            if (!$authCmd['ok']) {
                fclose($socket);
                return [
                    'ok' => false,
                    'message' => $this->t('smtp_test_connection_failed', ['detail' => $authCmd['raw'] !== '' ? $authCmd['raw'] : 'AUTH LOGIN failed']),
                ];
            }

            $authUser = $this->smtpSendCommand($socket, base64_encode((string) $smtp['username']), [334]);
            if (!$authUser['ok']) {
                fclose($socket);
                return [
                    'ok' => false,
                    'message' => $this->t('smtp_test_connection_failed', ['detail' => $authUser['raw'] !== '' ? $authUser['raw'] : 'SMTP username rejected']),
                ];
            }

            $authPass = $this->smtpSendCommand($socket, base64_encode((string) $smtp['password']), [235]);
            if (!$authPass['ok']) {
                fclose($socket);
                return [
                    'ok' => false,
                    'message' => $this->t('smtp_test_connection_failed', ['detail' => $authPass['raw'] !== '' ? $authPass['raw'] : 'SMTP password rejected']),
                ];
            }
        }

        $this->smtpSendCommand($socket, 'QUIT', [221, 250]);
        fclose($socket);

        return ['ok' => true, 'message' => $this->t('smtp_test_success')];
    }

    // ──────────────────────────────────────────────
    //  TRAITEMENT — ACTIONS ADMIN
    // ──────────────────────────────────────────────

    private function processAdminAction(): void
    {
        $action    = $_POST['bl_comment_action'] ?? '';
        $pageKey   = $this->cleanKey($_POST['page_key']    ?? '');
        $commentId = $_POST['comment_id'] ?? '';

        switch ($action) {
            case 'approve':
                $pending  = $this->loadComments($pageKey, 'pending');
                $approved = $this->loadComments($pageKey, 'approved');
                $approvedBefore = $approved;
                $publishedComment = null;
                foreach ($pending as $i => $c) {
                    if ($c['id'] === $commentId) {
                        $publishedComment = $c;
                        $approved[] = $c;
                        unset($pending[$i]);
                        break;
                    }
                }
                $this->saveComments($pageKey, $pending, 'pending');
                $this->saveComments($pageKey, $approved, 'approved');
                if (is_array($publishedComment)) {
                    $this->notifyPreviousCommenters($pageKey, $publishedComment, $approvedBefore);
                }
                break;

            case 'delete_pending':
                $pending = array_filter(
                    $this->loadComments($pageKey, 'pending'),
                    fn($c) => $c['id'] !== $commentId
                );
                $this->saveComments($pageKey, $pending, 'pending');
                break;

            case 'delete_approved':
                $approved = array_filter(
                    $this->loadComments($pageKey, 'approved'),
                    fn($c) => $c['id'] !== $commentId
                );
                $this->saveComments($pageKey, $approved, 'approved');
                break;

            case 'clear_pending':
                $this->saveComments($pageKey, [], 'pending');
                break;

            case 'clear_all':
                $this->saveComments($pageKey, [], 'pending');
                $this->saveComments($pageKey, [], 'approved');
                break;

            case 'test_smtp':
                $result = $this->testSmtpConnection($this->collectSmtpSettingsFromRequest());
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode($result);
                exit;
        }

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => true]);
            exit;
        }

        $returnUrl = HTML_PATH_ADMIN_ROOT . 'configure-plugin/pluginComments';
        header('Location: ' . $returnUrl . '#tab-moderation');
        exit;
    }

    // ──────────────────────────────────────────────
    //  HELPER — ADMIN CONNECTÉ
    // ──────────────────────────────────────────────

    private function adminIsLogged(): bool
    {
        global $login;
        return isset($login) && method_exists($login, 'isLogged') && $login->isLogged();
    }

    // ──────────────────────────────────────────────
    //  HELPER — PAGES BLUDIT
    // ──────────────────────────────────────────────

    public function getBluditPages(): array
    {
        $result = [];
        global $pages;

        try {
            if (isset($pages) && is_object($pages) && isset($pages->db)) {
                foreach ($pages->db as $key => $data) {
                    if (!empty($data['type']) && $data['type'] === 'static') {
                        continue; // Skip static pages if desired
                    }
                    $title = (string) ($data['title'] ?? $key);
                    $titleLower = $this->safeToLower($title);
                    if (
                        $this->safePos($titleLower, '[sauvegarde automatique]') !== false
                        || $this->safePos($titleLower, '[autosave]') !== false
                    ) {
                        continue;
                    }
                    $result[$key] = [
                        'key'   => $key,
                        'title' => $title,
                    ];
                }
                return $result;
            }
        } catch (Throwable $e) {}

        // Fallback — scan répertoire
        $pagesPath = defined('PATH_PAGES') ? PATH_PAGES : PATH_CONTENT . 'pages' . DS;
        if (is_dir($pagesPath)) {
            foreach (scandir($pagesPath) as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }
                if (is_dir($pagesPath . $dir)) {
                    $result[$dir] = ['key' => $dir, 'title' => $dir];
                }
            }
        }
        return $result;
    }

    public function getPagesWithComments(): array
    {
        $allEntries = [];
        $result   = [];
        $base     = $this->commentsBasePath();
        $settings = $this->loadPageSettings();
        $allPages = $this->getBluditPages();

        if (is_dir($base)) {
            foreach (scandir($base) as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $fullPath = $base . $entry;
                if (!is_dir($fullPath)) {
                    continue;
                }
                $allEntries[$entry] = [
                    'key'      => $entry,
                    'title'    => $allPages[$entry]['title'] ?? $entry,
                    'pending'  => $this->loadComments($entry, 'pending'),
                    'approved' => $this->loadComments($entry, 'approved'),
                    'enabled'  => isset($settings[$entry]['enabled']) ? (bool) $settings[$entry]['enabled'] : false,
                ];
            }
        }

        // Ajouter les pages avec settings mais sans commentaires encore
        foreach ($settings as $key => $cfg) {
            if (!isset($allEntries[$key])) {
                $allEntries[$key] = [
                    'key'      => $key,
                    'title'    => $allPages[$key]['title'] ?? $key,
                    'pending'  => [],
                    'approved' => [],
                    'enabled'  => (bool) ($cfg['enabled'] ?? false),
                ];
            }
        }

        // Onglet moderation: ne garder que les pages avec commentaires actives.
        foreach ($allEntries as $key => $entry) {
            if (!empty($entry['enabled'])) {
                $result[$key] = $entry;
            }
        }

        // Tri par nombre de commentaires en attente (desc)
        uasort($result, fn($a, $b) => count($b['pending']) <=> count($a['pending']));

        return $result;
    }

    // ──────────────────────────────────────────────
    //  HOOKS FRONTEND
    // ──────────────────────────────────────────────

    public function siteHead(): string
    {
        $url = HTML_PATH_PLUGINS . $this->directoryName() . '/css/front.css';
        return '<link rel="stylesheet" href="' . $url . '">' . "\n";
    }

    private function renderFrontComments(): string
    {
        global $page, $WHERE_AM_I;

        if ($this->frontCommentsRendered) {
            return '';
        }

        if ($WHERE_AM_I !== 'page' || !isset($page)) {
            return '';
        }

        $pageKey = $page->key();

        if (!$this->isCommentsEnabled($pageKey)) {
            return '';
        }

        $approvedComments = $this->loadComments($pageKey, 'approved');
        $csrfToken        = $this->csrfToken();
        $pageUrl          = $page->permalink();
        $successMsg       = $this->getFlash('success');
        $errorMsg         = $this->getFlash('error');
        $commentsPerPage  = max(1, $this->getIntSetting('commentsPerPage', 10));
        $commentOrder     = $this->getStringSetting('commentOrder', 'desc');
        if (!in_array($commentOrder, ['asc', 'desc'], true)) {
            $commentOrder = 'desc';
        }
        $maxLen           = (int)  $this->getValue('maxCommentLength');
        $smtpEnabled      = (bool) $this->getValue('smtpEnabled');
        $pluginUrl        = HTML_PATH_PLUGINS . $this->directoryName() . '/';
        $plugin           = $this;

        ob_start();
        include __DIR__ . '/views/front.php';
        $this->frontCommentsRendered = true;
        return ob_get_clean();
    }

    public function pageEnd(): string
    {
        return $this->renderFrontComments();
    }

    public function siteBodyEnd(): string
    {
        return $this->renderFrontComments();
    }

    // ──────────────────────────────────────────────
    //  HOOKS ADMIN
    // ──────────────────────────────────────────────

    public function adminHead(): string
    {
        $cssUrl = HTML_PATH_PLUGINS . $this->directoryName() . '/css/admin.css';
        $jsUrl  = HTML_PATH_PLUGINS . $this->directoryName() . '/js/admin.js';
        return '<link rel="stylesheet" href="' . $cssUrl . '">' . "\n"
             . '<script src="' . $jsUrl . '" defer></script>' . "\n";
    }

    public function adminBodyBegin(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        // Détecter l'éditeur de page
        if (!preg_match('/(new-content|edit-content)(\/([^\/?\s]+))?/', $uri, $m)) {
            return '';
        }

        $pageKey   = $m[3] ?? '';
        $isEnabled = $pageKey ? $this->isCommentsEnabled($pageKey) : false;
        $csrfToken = $this->csrfToken();
        $ajaxBase  = HTML_PATH_ROOT;
        $plugin    = $this;

        ob_start();
        include __DIR__ . '/views/page-editor-panel.php';
        return ob_get_clean();
    }

    public function adminSidebar(): string
    {
        $url = HTML_PATH_ADMIN_ROOT . 'configure-plugin/pluginComments';
        $totalPending = 0;
        foreach ($this->getPagesWithComments() as $pageComments) {
            $totalPending += count($pageComments['pending']);
        }

        $badge = $totalPending > 0
            ? '<span class="blc-sidebar-badge" aria-label="' . (int) $totalPending . ' pending comments">' . (int) $totalPending . '</span>'
            : '';

        return '<li class="nav-item">'
             . '<a class="nav-link blc-sidebar-link" href="' . $url . '">'
             . '<span class="blc-sidebar-link__label"><i class="fa fa-comments-o"></i>&nbsp;&nbsp;' . htmlspecialchars($this->t('sidebar_comments'), ENT_QUOTES, 'UTF-8') . ' ' . $badge .'</span>'
             . '</a></li>' . "\n";
    }

    // ──────────────────────────────────────────────
    //  FORMULAIRE DE CONFIGURATION (admin)
    // ──────────────────────────────────────────────

    public function form(): string
    {
        $pagesWithComments = $this->getPagesWithComments();
        $allBluditPages    = $this->getBluditPages();
        $pageSettings      = $this->loadPageSettings();

        // Compteurs globaux
        $totalPending  = 0;
        $totalApproved = 0;
        foreach ($pagesWithComments as $p) {
            $totalPending  += count($p['pending']);
            $totalApproved += count($p['approved']);
        }

        // Récupération des valeurs de config
        $requireApproval  = (bool) $this->getValue('requireApproval');
        $commentsPerPage  = max(1, $this->getIntSetting('commentsPerPage', 10));
        $minCommentLength = (int)  $this->getValue('minCommentLength');
        $maxCommentLength = (int)  $this->getValue('maxCommentLength');
        $rateLimitSeconds = max(0, $this->getIntSetting('rateLimitSeconds', 300));
        $smtpEnabled      = (bool) $this->getValue('smtpEnabled');
        $smtpHost         = (string) $this->getValue('smtpHost');
        $smtpPort         = max(1, (int) $this->getValue('smtpPort'));
        $smtpEncryption   = (string) $this->getValue('smtpEncryption');
        if (!in_array($smtpEncryption, ['none', 'tls', 'ssl'], true)) {
            $smtpEncryption = 'tls';
        }
        $smtpAuth         = (bool) $this->getValue('smtpAuth');
        $smtpUsername     = (string) $this->getValue('smtpUsername');
        $smtpPassword     = (string) $this->getValue('smtpPassword');
        $smtpFromEmail    = (string) $this->getValue('smtpFromEmail');
        $smtpFromName     = (string) $this->getValue('smtpFromName');
        $checkForUpdates  = (bool)   $this->getValue('checkForUpdates');
        $commentOrder     = $this->getStringSetting('commentOrder', 'desc');
        if (!in_array($commentOrder, ['asc', 'desc'], true)) {
            $commentOrder = 'desc';
        }
        $altchaAlgorithm  = $this->getStringSetting('altchaAlgorithm', 'SHA-256');
        if (!in_array($altchaAlgorithm, ['SHA-256', 'SHA-384', 'SHA-512'], true)) {
            $altchaAlgorithm = 'SHA-256';
        }

        $updateAvailable = false;
        $latestVersion   = '';
        if ($checkForUpdates) {
            $latestVersion   = $this->fetchRemoteVersion();
            $updateAvailable = $this->isNewerVersion($latestVersion, $this->getLocalVersion());
        }

        $csrfToken = $this->csrfToken();
        $plugin = $this;

        ob_start();
        include __DIR__ . '/views/admin.php';
        return ob_get_clean();
    }
}
