<?php defined('BLUDIT') or die('Bludit CMS.'); ?>

<div class="blc-admin" id="blc-admin-root"
     data-label-enabled="<?php echo htmlspecialchars($plugin->t('status_enabled'), ENT_QUOTES, 'UTF-8'); ?>"
     data-label-disabled="<?php echo htmlspecialchars($plugin->t('status_disabled'), ENT_QUOTES, 'UTF-8'); ?>"
     data-error-action="<?php echo htmlspecialchars($plugin->t('admin_error_action'), ENT_QUOTES, 'UTF-8'); ?>"
     data-saving-ok="<?php echo htmlspecialchars($plugin->t('admin_saved_ok'), ENT_QUOTES, 'UTF-8'); ?>"
     data-saving-error="<?php echo htmlspecialchars($plugin->t('admin_saved_error'), ENT_QUOTES, 'UTF-8'); ?>"
     data-saving-network-error="<?php echo htmlspecialchars($plugin->t('admin_saved_network_error'), ENT_QUOTES, 'UTF-8'); ?>"
     data-smtp-test-running="<?php echo htmlspecialchars($plugin->t('setting_smtp_test_running'), ENT_QUOTES, 'UTF-8'); ?>">

    <!-- ── En-tête ────────────────────────────── -->
    <div class="blc-admin-header">
        <h2 class="blc-admin-header__title">
            <svg class="blc-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <?php echo htmlspecialchars($plugin->t('admin_title'), ENT_QUOTES, 'UTF-8'); ?>
        </h2>
        <?php if (!empty($updateAvailable)): ?>
        <a class="blc-update-notice"
           href="https://github.com/GreenEffect/bludit-plugin-comments/releases"
           target="_blank"
           rel="noopener noreferrer">
            <?php echo htmlspecialchars($plugin->t('admin_update_available', ['version' => $latestVersion]), ENT_QUOTES, 'UTF-8'); ?>
        </a>
        <?php endif; ?>
        <div class="blc-stats-bar">
            <span class="blc-stat blc-stat--pending">
                <strong><?php echo $totalPending; ?></strong> <?php echo htmlspecialchars($plugin->t('admin_stat_pending'), ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <span class="blc-stat blc-stat--approved">
                <strong><?php echo $totalApproved; ?></strong> <?php echo htmlspecialchars($plugin->t('admin_stat_published'), ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <span class="blc-stat">
                <strong><?php echo count($pagesWithComments); ?></strong> <?php echo htmlspecialchars($plugin->t('admin_stat_active_pages'), ENT_QUOTES, 'UTF-8'); ?>
            </span>
        </div>
    </div>

    <!-- ── Tabs ───────────────────────────────── -->
    <nav class="blc-tabs" role="tablist">
        <button type="button"
                class="blc-tab active"
                data-tab="moderation"
                role="tab"
                aria-selected="true"
                aria-controls="blc-tab-moderation"
                id="tab-moderation-btn">
            <?php echo htmlspecialchars($plugin->t('tab_moderation'), ENT_QUOTES, 'UTF-8'); ?>
            <?php if ($totalPending > 0): ?>
            <span class="blc-badge blc-badge--alert"><?php echo $totalPending; ?></span>
            <?php endif; ?>
        </button>
        <button type="button"
                class="blc-tab"
                data-tab="pages"
                role="tab"
                aria-selected="false"
                aria-controls="blc-tab-pages"
                id="tab-pages-btn">
            <?php echo htmlspecialchars($plugin->t('tab_pages'), ENT_QUOTES, 'UTF-8'); ?>
        </button>
        <button type="button"
                class="blc-tab"
                data-tab="settings"
                role="tab"
                aria-selected="false"
                aria-controls="blc-tab-settings"
                id="tab-settings-btn">
            <?php echo htmlspecialchars($plugin->t('tab_settings'), ENT_QUOTES, 'UTF-8'); ?>
        </button>
    </nav>

    <!-- ══════════════════════════════════════════
         TAB 1 — MODÉRATION
    ═══════════════════════════════════════════════ -->
    <div class="blc-tab-content active" id="blc-tab-moderation" role="tabpanel">

        <?php if (empty($pagesWithComments)): ?>
        <div class="blc-empty-state">
            <svg viewBox="0 0 64 64" aria-hidden="true"><path d="M32 8C18.7 8 8 18.7 8 32s10.7 24 24 24 24-10.7 24-24S45.3 8 32 8zm0 4c11.1 0 20 8.9 20 20s-8.9 20-20 20S12 43.1 12 32s8.9-20 20-20zm-2 10v12l8 4.8-1.6 2.7L28 36V22h2z" fill="currentColor"/></svg>
            <p><?php echo htmlspecialchars($plugin->t('admin_empty_state'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <?php else: ?>

        <?php foreach ($pagesWithComments as $pg): ?>
        <?php
            $countP = count($pg['pending']);
            $countA = count($pg['approved']);
        ?>
        <div class="blc-page-block">
            <div class="blc-page-block__header" data-toggle="page-<?php echo htmlspecialchars($pg['key'], ENT_QUOTES, 'UTF-8'); ?>">
                <span class="blc-page-block__title">
                    <?php echo htmlspecialchars($pg['title'], ENT_QUOTES, 'UTF-8'); ?>
                    <span class="blc-page-key"><?php echo htmlspecialchars($pg['key'], ENT_QUOTES, 'UTF-8'); ?></span>
                </span>
                <span class="blc-page-block__badges">
                    <?php if ($countP > 0): ?>
                    <span class="blc-badge blc-badge--pending"><?php echo $countP; ?> <?php echo htmlspecialchars($plugin->t('admin_stat_pending'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    <?php if ($countA > 0): ?>
                    <span class="blc-badge blc-badge--approved"><?php echo $countA; ?> publié<?php echo $countA > 1 ? 's' : ''; ?></span>
                    <?php endif; ?>
                    <svg class="blc-chevron" viewBox="0 0 24 24" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                </span>
            </div>

            <div class="blc-page-block__body" id="page-<?php echo htmlspecialchars($pg['key'], ENT_QUOTES, 'UTF-8'); ?>">

                <!-- EN ATTENTE -->
                <?php if (!empty($pg['pending'])): ?>
                <div class="blc-section-label blc-section-label--pending">
                    <?php echo htmlspecialchars($plugin->t('admin_pending_section'), ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <div class="blc-comments-group">
                    <?php foreach ($pg['pending'] as $c): ?>
                    <div class="blc-comment blc-comment--pending">
                        <div class="blc-comment__meta">
                            <div class="blc-comment__header">
                                <span class="blc-comment__author">
                                    <?php echo htmlspecialchars($c['author'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <time class="blc-comment__date">
                                    <?php echo date('d/m/Y H\hi', strtotime($c['date'])); ?>
                                </time>
                            </div>
                            <div class="blc-comment__content">
                                <?php echo htmlspecialchars($c['content'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </div>
                        <div class="blc-comment__actions">
                            <button type="button"
                                    class="blc-btn blc-btn--approve blc-action-btn"
                                    data-action="approve"
                                    data-page-key="<?php echo htmlspecialchars($pg['key'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-comment-id="<?php echo htmlspecialchars($c['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($plugin->t('admin_action_publish'), ENT_QUOTES, 'UTF-8'); ?>
                            </button>
                            <button type="button"
                                    class="blc-btn blc-btn--delete blc-action-btn"
                                    data-action="delete_pending"
                                    data-page-key="<?php echo htmlspecialchars($pg['key'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-comment-id="<?php echo htmlspecialchars($c['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($plugin->t('admin_action_delete'), ENT_QUOTES, 'UTF-8'); ?>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($pg['pending']) > 1): ?>
                <div class="blc-bulk-action">
                    <button type="button"
                            class="blc-btn blc-btn--delete-all blc-action-btn"
                            data-action="clear_pending"
                            data-page-key="<?php echo htmlspecialchars($pg['key'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-comment-id="">
                        <?php echo htmlspecialchars($plugin->t('admin_action_delete_all_pending'), ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <!-- APPROUVÉS -->
                <?php if (!empty($pg['approved'])): ?>
                <div class="blc-section-label blc-section-label--approved">
                    <?php echo htmlspecialchars($plugin->t('admin_published_section'), ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <div class="blc-comments-group">
                    <?php foreach (array_reverse($pg['approved']) as $c): ?>
                    <div class="blc-comment blc-comment--approved">
                        <div class="blc-comment__meta">
                            <div class="blc-comment__header">
                                <span class="blc-comment__author">
                                    <?php echo htmlspecialchars($c['author'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <time class="blc-comment__date">
                                    <?php echo date('d/m/Y H\hi', strtotime($c['date'])); ?>
                                </time>
                            </div>
                            <div class="blc-comment__content">
                                <?php echo htmlspecialchars($c['content'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </div>
                        <div class="blc-comment__actions">
                            <button type="button"
                                    class="blc-btn blc-btn--delete blc-action-btn"
                                    data-action="delete_approved"
                                    data-page-key="<?php echo htmlspecialchars($pg['key'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-comment-id="<?php echo htmlspecialchars($c['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($plugin->t('admin_action_delete'), ENT_QUOTES, 'UTF-8'); ?>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (empty($pg['pending']) && empty($pg['approved'])): ?>
                <p class="blc-no-comments"><?php echo htmlspecialchars($plugin->t('admin_no_comments_this_page'), ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>

                <!-- Tout supprimer -->
                <?php if (!empty($pg['pending']) || !empty($pg['approved'])): ?>
                <div class="blc-bulk-action blc-bulk-action--danger">
                    <button type="button"
                            class="blc-btn blc-btn--danger blc-action-btn"
                            data-action="clear_all"
                            data-page-key="<?php echo htmlspecialchars($pg['key'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-comment-id=""
                            data-confirm="<?php echo htmlspecialchars($plugin->t('admin_confirm_clear_all'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($plugin->t('admin_action_clear_all'), ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                </div>
                <?php endif; ?>

            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ══════════════════════════════════════════
         TAB 2 — PAGES (activer / désactiver)
    ═══════════════════════════════════════════════ -->
    <div class="blc-tab-content" id="blc-tab-pages" role="tabpanel">
        <p class="blc-help-intro">
            <?php echo htmlspecialchars($plugin->t('admin_pages_help'), ENT_QUOTES, 'UTF-8'); ?>
        </p>

        <?php if (empty($allBluditPages)): ?>
        <p class="blc-empty"><?php echo htmlspecialchars($plugin->t('admin_no_pages_found'), ENT_QUOTES, 'UTF-8'); ?></p>
        <?php else: ?>
        <div class="blc-pages-table">
            <?php foreach ($allBluditPages as $bp): ?>
            <?php $isEnabled = $plugin->isCommentsEnabled($bp['key']); ?>
            <div class="blc-pages-row">
                <span class="blc-pages-row__title">
                    <?php echo htmlspecialchars($bp['title'], ENT_QUOTES, 'UTF-8'); ?>
                    <small><?php echo htmlspecialchars($bp['key'], ENT_QUOTES, 'UTF-8'); ?></small>
                </span>
                <label class="blc-toggle" title="<?php echo htmlspecialchars(($isEnabled ? $plugin->t('toggle_disable') : $plugin->t('toggle_enable')) . ' ' . $plugin->t('sidebar_comments'), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="checkbox"
                           class="blc-toggle__input blc-page-toggle"
                           data-page-key="<?php echo htmlspecialchars($bp['key'], ENT_QUOTES, 'UTF-8'); ?>"
                           <?php echo $isEnabled ? 'checked' : ''; ?>>
                    <span class="blc-toggle__track"></span>
                    <span class="blc-toggle__status">
                        <?php echo htmlspecialchars($isEnabled ? $plugin->t('status_enabled') : $plugin->t('status_disabled'), ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ══════════════════════════════════════════
         TAB 3 — RÉGLAGES
    ═══════════════════════════════════════════════ -->
    <div class="blc-tab-content" id="blc-tab-settings" role="tabpanel">

        <div class="blc-settings-grid">

            <div class="blc-setting">
                <label class="blc-setting__label">
                    <input type="checkbox"
                           name="requireApproval"
                           value="1"
                           <?php echo $requireApproval ? 'checked' : ''; ?>>
                    <?php echo htmlspecialchars($plugin->t('setting_require_approval'), ENT_QUOTES, 'UTF-8'); ?>
                </label>
                <p class="blc-setting__help"><?php echo htmlspecialchars($plugin->t('setting_require_approval_help'), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <div class="blc-setting">
                <label class="blc-setting__label" for="s-minlen">
                    <?php echo htmlspecialchars($plugin->t('setting_min_comment_length'), ENT_QUOTES, 'UTF-8'); ?>
                </label>
                <input type="number"
                       id="s-minlen"
                       name="minCommentLength"
                       value="<?php echo $minCommentLength; ?>"
                       min="1" max="500">
                <p class="blc-setting__help">
                    <?php echo htmlspecialchars($plugin->t('setting_min_char_length'), ENT_QUOTES, 'UTF-8'); ?>
                </p>
            </div>

            <div class="blc-setting">
                <label class="blc-setting__label" for="s-maxlen">
                    <?php echo htmlspecialchars($plugin->t('setting_max_comment_length'), ENT_QUOTES, 'UTF-8'); ?>
                </label>
                <input type="number"
                       id="s-maxlen"
                       name="maxCommentLength"
                       value="<?php echo $maxCommentLength; ?>"
                       min="50" max="10000">
                <p class="blc-setting__help">
                    <?php echo htmlspecialchars($plugin->t('setting_max_char_length'), ENT_QUOTES, 'UTF-8'); ?>
                </p>
            </div>

            <div class="blc-setting">
                <label class="blc-setting__label" for="s-rate">
                    <?php echo htmlspecialchars($plugin->t('setting_rate_limit'), ENT_QUOTES, 'UTF-8'); ?>
                </label>
                <input type="number"
                       id="s-rate"
                       name="rateLimitSeconds"
                       value="<?php echo $rateLimitSeconds; ?>"
                       min="0" max="86400">
                <p class="blc-setting__help"><?php echo htmlspecialchars($plugin->t('setting_rate_limit_help'), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <div class="blc-setting">
                <label class="blc-setting__label" for="s-perpage">
                    <?php echo htmlspecialchars($plugin->t('setting_comments_per_page'), ENT_QUOTES, 'UTF-8'); ?>
                </label>
                <input type="number"
                       id="s-perpage"
                       name="commentsPerPage"
                       value="<?php echo $commentsPerPage; ?>"
                       min="1" max="100">
                <p class="blc-setting__help"><?php echo htmlspecialchars($plugin->t('setting_comments_per_page_help'), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <div class="blc-setting">
                <label class="blc-setting__label">
                    <input type="hidden" name="checkForUpdates" value="0">
                    <input type="checkbox"
                           name="checkForUpdates"
                           value="1"
                           <?php echo $checkForUpdates ? 'checked' : ''; ?>>
                    <?php echo htmlspecialchars($plugin->t('setting_check_for_updates'), ENT_QUOTES, 'UTF-8'); ?>
                </label>
                <p class="blc-setting__help"><?php echo htmlspecialchars($plugin->t('setting_check_for_updates_help'), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <div class="blc-setting">
                <label class="blc-setting__label">
                    <input type="hidden" name="smtpEnabled" value="0">
                    <input type="checkbox"
                           name="smtpEnabled"
                           value="1"
                           <?php echo $smtpEnabled ? 'checked' : ''; ?>>
                    <?php echo htmlspecialchars($plugin->t('setting_smtp_enabled'), ENT_QUOTES, 'UTF-8'); ?>
                </label>
                <p class="blc-setting__help"><?php echo htmlspecialchars($plugin->t('setting_smtp_enabled_help'), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <div class="blc-setting blc-setting--smtp" <?php echo $smtpEnabled ? '' : 'style="display:none;"'; ?>>
                <label class="blc-setting__label" for="s-smtp-host">
                    <?php echo htmlspecialchars($plugin->t('setting_smtp_host'), ENT_QUOTES, 'UTF-8'); ?>
                </label>
                <input type="text"
                       id="s-smtp-host"
                       name="smtpHost"
                       value="<?php echo htmlspecialchars($smtpHost, ENT_QUOTES, 'UTF-8'); ?>"
                       maxlength="255"
                       placeholder="smtp.example.com">
            </div>

            <div class="blc-setting blc-setting--smtp" <?php echo $smtpEnabled ? '' : 'style="display:none;"'; ?>>
                <label class="blc-setting__label" for="s-smtp-port">
                    <?php echo htmlspecialchars($plugin->t('setting_smtp_port'), ENT_QUOTES, 'UTF-8'); ?>
                </label>
                <input type="number"
                       id="s-smtp-port"
                       name="smtpPort"
                       value="<?php echo $smtpPort; ?>"
                       min="1" max="65535">
            </div>

            <div class="blc-setting blc-setting--smtp" <?php echo $smtpEnabled ? '' : 'style="display:none;"'; ?>>
                <label class="blc-setting__label" for="s-smtp-encryption">
                    <?php echo htmlspecialchars($plugin->t('setting_smtp_encryption'), ENT_QUOTES, 'UTF-8'); ?>
                </label>
                <select id="s-smtp-encryption" name="smtpEncryption">
                    <option value="none" <?php echo $smtpEncryption === 'none' ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($plugin->t('setting_smtp_encryption_none'), ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                    <option value="tls" <?php echo $smtpEncryption === 'tls' ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($plugin->t('setting_smtp_encryption_tls'), ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                    <option value="ssl" <?php echo $smtpEncryption === 'ssl' ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($plugin->t('setting_smtp_encryption_ssl'), ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                </select>
            </div>

            <div class="blc-setting blc-setting--smtp" <?php echo $smtpEnabled ? '' : 'style="display:none;"'; ?>>
                <label class="blc-setting__label">
                    <input type="hidden" name="smtpAuth" value="0">
                    <input type="checkbox"
                           name="smtpAuth"
                           value="1"
                           <?php echo $smtpAuth ? 'checked' : ''; ?>>
                    <?php echo htmlspecialchars($plugin->t('setting_smtp_auth'), ENT_QUOTES, 'UTF-8'); ?>
                </label>
            </div>

            <div class="blc-setting blc-setting--smtp blc-setting--smtp-auth" <?php echo ($smtpEnabled && $smtpAuth) ? '' : 'style="display:none;"'; ?>>
                <label class="blc-setting__label" for="s-smtp-username">
                    <?php echo htmlspecialchars($plugin->t('setting_smtp_username'), ENT_QUOTES, 'UTF-8'); ?>
                </label>
                <input type="text"
                       id="s-smtp-username"
                       name="smtpUsername"
                       value="<?php echo htmlspecialchars($smtpUsername, ENT_QUOTES, 'UTF-8'); ?>"
                       maxlength="255"
                       autocomplete="off">
            </div>

            <div class="blc-setting blc-setting--smtp blc-setting--smtp-auth" <?php echo ($smtpEnabled && $smtpAuth) ? '' : 'style="display:none;"'; ?>>
                <label class="blc-setting__label" for="s-smtp-password">
                    <?php echo htmlspecialchars($plugin->t('setting_smtp_password'), ENT_QUOTES, 'UTF-8'); ?>
                </label>
                <input type="password"
                       id="s-smtp-password"
                       name="smtpPassword"
                       value="<?php echo htmlspecialchars($smtpPassword, ENT_QUOTES, 'UTF-8'); ?>"
                       maxlength="255"
                       autocomplete="new-password">
            </div>

            <div class="blc-setting blc-setting--smtp" <?php echo $smtpEnabled ? '' : 'style="display:none;"'; ?>>
                <label class="blc-setting__label" for="s-smtp-from-email">
                    <?php echo htmlspecialchars($plugin->t('setting_smtp_from_email'), ENT_QUOTES, 'UTF-8'); ?>
                </label>
                <input type="email"
                       id="s-smtp-from-email"
                       name="smtpFromEmail"
                       value="<?php echo htmlspecialchars($smtpFromEmail, ENT_QUOTES, 'UTF-8'); ?>"
                       maxlength="255"
                       placeholder="noreply@example.com">
            </div>

            <div class="blc-setting blc-setting--smtp" <?php echo $smtpEnabled ? '' : 'style="display:none;"'; ?>>
                <label class="blc-setting__label" for="s-smtp-from-name">
                    <?php echo htmlspecialchars($plugin->t('setting_smtp_from_name'), ENT_QUOTES, 'UTF-8'); ?>
                </label>
                <input type="text"
                       id="s-smtp-from-name"
                       name="smtpFromName"
                       value="<?php echo htmlspecialchars($smtpFromName, ENT_QUOTES, 'UTF-8'); ?>"
                       maxlength="255"
                       placeholder="Website">
            </div>

            <div class="blc-setting blc-setting--smtp" <?php echo $smtpEnabled ? '' : 'style="display:none;"'; ?>>
                <button type="button" id="blc-smtp-test-btn" class="blc-btn blc-btn--approve btn-w100">
                    <?php echo htmlspecialchars($plugin->t('setting_smtp_test_button'), ENT_QUOTES, 'UTF-8'); ?>
                </button>
                <p id="blc-smtp-test-result" class="blc-setting__help" role="status" aria-live="polite"></p>
            </div>

        </div>

        <p class="blc-settings-save-hint">
            <?php echo htmlspecialchars($plugin->t('admin_save_hint'), ENT_QUOTES, 'UTF-8'); ?>
        </p>
    </div>

</div><!-- /.blc-admin -->

<script>
(function(){
    // Synchronise les onglets via le hash de l'URL
    var hash = window.location.hash;
    if (hash === '#tab-moderation') blcActivateTab('moderation');
    else if (hash === '#tab-pages') blcActivateTab('pages');
    else if (hash === '#tab-settings') blcActivateTab('settings');

    function blcActivateTab(name) {
        document.querySelectorAll('.blc-tab').forEach(function(t){ t.classList.remove('active'); t.setAttribute('aria-selected','false'); });
        document.querySelectorAll('.blc-tab-content').forEach(function(c){ c.classList.remove('active'); });
        var btn = document.querySelector('.blc-tab[data-tab="'+name+'"]');
        var panel = document.getElementById('blc-tab-'+name);
        if (btn) { btn.classList.add('active'); btn.setAttribute('aria-selected','true'); }
        if (panel) panel.classList.add('active');
    }

    var smtpEnabledInput = document.querySelector('input[name="smtpEnabled"][type="checkbox"]');
    var smtpAuthInput = document.querySelector('input[name="smtpAuth"][type="checkbox"]');
    var smtpSettingBlocks = document.querySelectorAll('.blc-setting--smtp');

    function blcToggleSmtpSettings() {
        if (!smtpEnabledInput || !smtpSettingBlocks.length) {
            return;
        }
        var smtpEnabled = smtpEnabledInput.checked;
        var smtpAuthEnabled = smtpAuthInput ? smtpAuthInput.checked : false;

        smtpSettingBlocks.forEach(function(block){
            var requiresAuth = block.classList.contains('blc-setting--smtp-auth');
            var shouldDisplay = smtpEnabled && (!requiresAuth || smtpAuthEnabled);
            block.style.display = shouldDisplay ? '' : 'none';
        });
    }

    if (smtpEnabledInput && smtpSettingBlocks.length) {
        smtpEnabledInput.addEventListener('change', blcToggleSmtpSettings);
        if (smtpAuthInput) {
            smtpAuthInput.addEventListener('change', blcToggleSmtpSettings);
        }
        blcToggleSmtpSettings();
    }
})();
</script>
