<?php
/**
 * Plugin Comments — Système de commentaires pour Bludit v3
 * Fichiers JSON, modération, CSRF, rate limiting
 *
 * @author   Green Effect
 * @contact  contact@green-effect.fr
 * @website  https://www.green-effect.fr
 * @version  1.2.0
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

    private function syncRuntimeSettingsFromAdminPost(): void
    {
        if (!$this->adminIsLogged()) {
            return;
        }

        $settingsKeys = ['requireApproval', 'commentsPerPage', 'minCommentLength', 'maxCommentLength', 'rateLimitSeconds'];
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
        ];

        // Endpoint de challenge ALTCHA (standalone, servi par le plugin)
        if (isset($_GET['blc_altcha']) && $_GET['blc_altcha'] === 'challenge') {
            $this->outputAltchaChallenge();
            exit;
        }

        // Créer le répertoire de données
        $base = $this->commentsBasePath();
        if (!file_exists($base)) {
            mkdir($base, 0755, true);
        }

        // Garantit l'usage des reglages back-office au runtime front.
        $this->syncRuntimeSettingsFromAdminPost();

        // ── Soumission commentaire (front) ─────────
        if (!empty($_POST['bl_comment_submit'])) {
            $this->processCommentSubmission();
        }

        // ── Actions admin ──────────────────────────
        if (!empty($_POST['bl_comment_action']) && $this->adminIsLogged()) {
            $this->processAdminAction();
        }

        // ── Toggle commentaires (AJAX éditeur) ─────
        if (!empty($_POST['bl_toggle_comments']) && $this->adminIsLogged()) {
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

        $minLen = (int) $this->getValue('minCommentLength');
        $maxLen = (int) $this->getValue('maxCommentLength');

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

        $requireApproval = (bool) $this->getValue('requireApproval');
        $status          = $requireApproval ? 'pending' : 'approved';

        $comment = [
            'id'        => uniqid('c', true),
            'author'    => $author,
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
        $url = $_POST['page_url'] ?? '/';
        header('Location: ' . $url . '#comments');
        exit;
    }

    private function outputAltchaChallenge(): void
    {
        $secret    = $this->getAltchaSecret();
        $algorithm = 'SHA-256';
        $maxNumber = 100000;
        $number    = random_int(1, $maxNumber);
        $salt      = bin2hex(random_bytes(12));
        $challenge = hash('sha256', $salt . $number);
        $signature = hash_hmac('sha256', $challenge, $secret);

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'algorithm' => $algorithm,
            'challenge' => $challenge,
            'salt' => $salt,
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
            $algorithm !== 'SHA-256'
            || $challenge === ''
            || $salt === ''
            || $signature === ''
            || $number < 1
            || $number > 100000
        ) {
            return false;
        }

        $secret              = $this->getAltchaSecret();
        $expectedChallenge   = hash('sha256', $salt . $number);
        $expectedSignature   = hash_hmac('sha256', $challenge, $secret);

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

    private function getAltchaSecret(): string
    {
        $file = $this->commentsBasePath() . 'altcha-secret.txt';

        if (file_exists($file)) {
            $secret = trim((string) file_get_contents($file));
            if ($secret !== '') {
                return $secret;
            }
        }

        $secret = bin2hex(random_bytes(32));
        file_put_contents($file, $secret);
        return $secret;
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
                foreach ($pending as $i => $c) {
                    if ($c['id'] === $commentId) {
                        $approved[] = $c;
                        unset($pending[$i]);
                        break;
                    }
                }
                $this->saveComments($pageKey, $pending, 'pending');
                $this->saveComments($pageKey, $approved, 'approved');
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
        $url = HTML_PATH_PLUGINS . 'bl-plugin-comments/css/front.css';
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
        $maxLen           = (int)  $this->getValue('maxCommentLength');
        $pluginUrl        = HTML_PATH_PLUGINS . 'bl-plugin-comments/';
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
        $cssUrl = HTML_PATH_PLUGINS . 'bl-plugin-comments/css/admin.css';
        $jsUrl  = HTML_PATH_PLUGINS . 'bl-plugin-comments/js/admin.js';
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
        $ajaxBase  = HTML_PATH_ROOT;
        $plugin    = $this;

        ob_start();
        include __DIR__ . '/views/page-editor-panel.php';
        return ob_get_clean();
    }

    public function adminSidebar(): string
    {
        $url = HTML_PATH_ADMIN_ROOT . 'configure-plugin/pluginComments';
        return '<li class="nav-item">'
             . '<a class="nav-link" href="' . $url . '">'
             . '<i class="fa fa-comments-o"></i>&nbsp;&nbsp;' . htmlspecialchars($this->t('sidebar_comments'), ENT_QUOTES, 'UTF-8')
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

        $plugin = $this;

        ob_start();
        include __DIR__ . '/views/admin.php';
        return ob_get_clean();
    }
}
