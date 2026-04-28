<?php defined('BLUDIT') or die('Bludit CMS.'); ?>
<section id="comments"
         class="blc-front"
         aria-label="<?php echo htmlspecialchars($plugin->t('front_comments_aria'), ENT_QUOTES, 'UTF-8'); ?>"
         data-comments-per-page="<?php echo $commentsPerPage; ?>"
         data-pagination-label="<?php echo htmlspecialchars($plugin->t('front_pagination_label'), ENT_QUOTES, 'UTF-8'); ?>">

    <h3 class="blc-front__title">
        <svg class="blc-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <?php $n = count($approvedComments); ?>
        <?php echo $plugin->t('front_comments_count', ['count' => $n]); ?>
    </h3>

    <?php if ($successMsg): ?>
    <div class="blc-alert blc-alert--success" role="alert">
        <?php echo htmlspecialchars($successMsg, ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
    <div class="blc-alert blc-alert--error" role="alert">
        <?php echo htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <?php endif; ?>

    <!-- ── Liste des commentaires ─────────────── -->
    <?php if (!empty($approvedComments)): ?>
    <div class="blc-front__list" aria-live="polite">
        <?php foreach (array_reverse($approvedComments) as $comment): ?>
        <article class="blc-front__comment">
            <header class="blc-front__comment-header">
                <span class="blc-front__author">
                    <?php echo htmlspecialchars($comment['author'], ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <time class="blc-front__date"
                      datetime="<?php echo htmlspecialchars($comment['date'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php
                        $ts = strtotime($comment['date']);
                        echo date('d/m/Y', $ts) . ' à ' . date('H\hi', $ts);
                    ?>
                </time>
            </header>
            <div class="blc-front__content">
                <?php echo $plugin->parseMarkdown($comment['content']); ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="blc-front__empty"><?php echo htmlspecialchars($plugin->t('front_no_comments'), ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <!-- ── Formulaire ─────────────────────────── -->
    <div class="blc-front__form-wrap">
        <h4 class="blc-front__form-title"><?php echo htmlspecialchars($plugin->t('front_form_title'), ENT_QUOTES, 'UTF-8'); ?></h4>

        <form class="blc-front__form" method="POST" action="<?php echo htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8'); ?>" novalidate>
            <input type="hidden" name="bl_comment_submit" value="1">
            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="page_key"
                   value="<?php echo htmlspecialchars($pageKey, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="page_url"
                   value="<?php echo htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8'); ?>">

            <div class="blc-front__field">
                <label for="blc-author">
                    <?php echo htmlspecialchars($plugin->t('front_label_author'), ENT_QUOTES, 'UTF-8'); ?> <span class="blc-required" aria-hidden="true">*</span>
                </label>
                <input type="text"
                       id="blc-author"
                       name="comment_author"
                       maxlength="100"
                       required
                       autocomplete="nickname"
                       placeholder="<?php echo htmlspecialchars($plugin->t('front_placeholder_author'), ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <?php if (!empty($smtpEnabled)): ?>
            <div class="blc-front__field">
                <label for="blc-notify" class="blc-front__checkbox-label confirm__sending">
                    <input type="checkbox"
                           id="blc-notify"
                           name="comment_notify"
                           value="1">
                    <?php echo htmlspecialchars($plugin->t('front_label_email_optional'), ENT_QUOTES, 'UTF-8'); ?>
                </label>
            </div>

            <div class="blc-front__field" id="blc-email-field-wrapper" style="display:none;">
                <label for="blc-email">
                    <?php echo htmlspecialchars($plugin->t('front_label_email_field'), ENT_QUOTES, 'UTF-8'); ?>
                </label>
                <input type="email"
                       id="blc-email"
                       name="comment_email"
                       maxlength="255"
                       autocomplete="email"
                       placeholder="<?php echo htmlspecialchars($plugin->t('front_placeholder_email_optional'), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <?php endif; ?>

            <div class="blc-front__field">
                <label for="blc-content">
                    <?php echo htmlspecialchars($plugin->t('front_label_comment'), ENT_QUOTES, 'UTF-8'); ?> <span class="blc-required" aria-hidden="true">*</span>
                    <span class="blc-md-hint" title="Markdown supporté">
                        <code>**gras**</code> &nbsp;
                        <code>*italique*</code> &nbsp;
                        <code>`code`</code>
                    </span>
                </label>
                <textarea id="blc-content"
                          name="comment_content"
                          rows="6"
                          required
                          maxlength="<?php echo $maxLen; ?>"
                          placeholder="<?php echo htmlspecialchars($plugin->t('front_placeholder_comment'), ENT_QUOTES, 'UTF-8'); ?>"></textarea>
                <div class="blc-char-count">
                    <span id="blc-char-current">0</span>&nbsp;/&nbsp;<?php echo $maxLen; ?>
                </div>
            </div>

            <div class="blc-front__field">
                <label for="blc-altcha">
                    <?php echo htmlspecialchars($plugin->t('front_label_antispam'), ENT_QUOTES, 'UTF-8'); ?> <span class="blc-required" aria-hidden="true">*</span>
                </label>
                <altcha-widget
                    id="blc-altcha"
                    challengeurl="<?php echo htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8'); ?>?blc_altcha=challenge&page_key=<?php echo urlencode($pageKey); ?>"
                    hidefooter
                ></altcha-widget>
            </div>

            <div class="blc-front__actions">
                <button type="submit" class="blc-btn-submit">
                    <?php echo htmlspecialchars($plugin->t('front_submit_comment'), ENT_QUOTES, 'UTF-8'); ?>
                </button>
            </div>
        </form>
    </div>

</section>
<script type="module" src="<?php echo htmlspecialchars($pluginUrl, ENT_QUOTES, 'UTF-8'); ?>js/vendor/altcha.min.js"></script>
<script src="<?php echo htmlspecialchars($pluginUrl, ENT_QUOTES, 'UTF-8'); ?>js/front.js" defer></script>
