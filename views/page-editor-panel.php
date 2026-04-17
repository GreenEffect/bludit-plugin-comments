<?php defined('BLUDIT') or die('Bludit CMS.'); ?>
<!-- Panneau commentaires injecté dans l'éditeur Bludit -->
<div id="blc-editor-panel"
     class="blc-editor-panel"
     data-page-key="<?php echo htmlspecialchars($pageKey, ENT_QUOTES, 'UTF-8'); ?>"
     data-ajax-url="<?php echo htmlspecialchars($ajaxBase, ENT_QUOTES, 'UTF-8'); ?>"
     style="display:none">
    <div class="blc-editor-panel__inner">
        <h6>
            <svg class="blc-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <?php echo htmlspecialchars($plugin->t('sidebar_comments'), ENT_QUOTES, 'UTF-8'); ?>
        </h6>
        <label class="blc-toggle blc-toggle--row">
            <input type="checkbox"
                   id="blc-page-comments-toggle"
                   class="blc-toggle__input"
                   data-page-key="<?php echo htmlspecialchars($pageKey, ENT_QUOTES, 'UTF-8'); ?>"
                   <?php echo $isEnabled ? 'checked' : ''; ?>>
            <span class="blc-toggle__track"></span>
            <span class="blc-toggle__label" id="blc-editor-toggle-label">
                <?php echo htmlspecialchars($isEnabled ? $plugin->t('status_enabled') : $plugin->t('status_disabled'), ENT_QUOTES, 'UTF-8'); ?>
            </span>
        </label>
        <span id="blc-editor-saving" class="blc-saving-indicator" aria-live="polite"></span>
    </div>
</div>
