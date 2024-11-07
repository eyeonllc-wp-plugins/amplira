<?php
/**
 * Step 4: Replace Content
 */
?>
<div class="amplira-step-content" data-step="4" style="display: none;">
    <h2><?php _e('Replace Content', 'amplira'); ?></h2>
    <p class="step-description"><?php _e('Define the content that will replace each shortcode in your duplicated pages.', 'amplira'); ?></p>

    <!-- Main content container -->
    <div class="replacement-content-wrapper">
        <!-- Loading state -->
        <div class="amplira-loading-state" style="display: none;">
            <span class="spinner is-active"></span>
            <?php _e('Loading shortcodes...', 'amplira'); ?>
        </div>

        <!-- Error state -->
        <div class="amplira-error-state" style="display: none;">
            <p class="error-message"></p>
            <button class="button retry-button"><?php _e('Retry', 'amplira'); ?></button>
        </div>

        <input type="text" id="sample-city-name" placeholder="Enter Sample City Name" />

        <!-- Controls -->
        <div class="table-controls" style="display: none;">
            <div class="control-group">
                <button type="button" class="button ai-generate-meta" disabled>
                    <span class="dashicons dashicons-admin-site"></span>
                    <?php _e('Generate Meta with AI', 'amplira'); ?>
                </button>
                <div class="ai-settings" style="display: none;">
                    <label>
                        <input type="checkbox" id="ai-unique-content" checked>
                        <?php _e('Generate unique variations', 'amplira'); ?>
                    </label>
                    <label>
                        <input type="checkbox" id="ai-seo-optimize" checked>
                        <?php _e('Optimize for SEO', 'amplira'); ?>
                    </label>
                </div>
            </div>
        </div>

        <!-- Main form container -->
        <div class="replacement-forms-container">
            <!-- Table will be inserted here by JavaScript -->
        </div>

        <!-- Preview Panel -->
        <div class="preview-pane" style="display: none;">
            <h3><?php _e('Content Preview', 'amplira'); ?></h3>
            <div class="search-result-preview">
                <div class="preview-title"></div>
                <div class="preview-url"></div>
                <div class="preview-description"></div>
            </div>
        </div>

        <!-- Action buttons -->
        <div class="form-actions" style="display: none;">
            <button type="button" class="button button-primary" id="save-replacements">
                <?php _e('Save Replacements', 'amplira'); ?>
            </button>
            <button type="button" class="button" id="reset-form">
                <?php _e('Reset Form', 'amplira'); ?>
            </button>
        </div>
    </div>
</div>
