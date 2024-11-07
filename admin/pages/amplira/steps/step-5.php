<?php
/**
 * Template part for step five - Confirmation and Page Creation
 */
?>
<div class="amplira-step-content" data-step="5" style="display: none;">
    <h2><?php _e('Confirm and Create Pages', 'amplira'); ?></h2>
    
    <div class="amplira-creation-status">
        <div class="amplira-status-summary">
            <h3><?php _e('Creation Summary', 'amplira'); ?></h3>
            <ul class="amplira-status-list">
                <li>
                    <span class="dashicons dashicons-yes"></span>
                    <?php _e('Template selected:', 'amplira'); ?> 
                    <strong id="template-type"></strong>
                </li>
                <li>
                    <span class="dashicons dashicons-yes"></span>
                    <?php _e('Source page:', 'amplira'); ?> 
                    <strong id="source-page"></strong>
                </li>
                <li>
                    <span class="dashicons dashicons-yes"></span>
                    <?php _e('Pages to create:', 'amplira'); ?> 
                    <strong id="pages-count"></strong>
                </li>
            </ul>
        </div>

        <div class="amplira-confirm-creation">
            <button id="start-creation" class="button button-primary"><?php _e('Start Page Creation', 'amplira'); ?></button>
            <button id="go-back" class="button button-secondary"><?php _e('Go Back', 'amplira'); ?></button>
        </div>

        <div class="amplira-progress" style="display: none;">
            <div class="amplira-progress-bar"></div>
            <div class="amplira-progress-status">
                <?php _e('Creating pages...', 'amplira'); ?> 
                <span id="current-progress">0</span>/<span id="total-pages">0</span>
            </div>
        </div>

        <div class="amplira-completion-message" style="display: none;">
            <div class="amplira-success-message">
                <span class="dashicons dashicons-yes-alt"></span>
                <h3><?php _e('Pages Created Successfully!', 'amplira'); ?></h3>
                <p><?php _e('All pages have been created and are ready for review.', 'amplira'); ?></p>
            </div>
            <div class="amplira-action-buttons">
                <a href="#" class="amplira-button amplira-button-primary" id="view-pages">
                    <?php _e('View Created Pages', 'amplira'); ?>
                </a>
                <button type="button" class="amplira-button amplira-button-secondary" id="start-over">
                    <?php _e('Start New Batch', 'amplira'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
