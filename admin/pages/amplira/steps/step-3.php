<?php
/**
 * Template part for step three - Replace Content
 */
?>
<div class="amplira-step-content" data-step="3" style="display: none;">
    <h2><?php _e('Replace Content', 'amplira'); ?></h2>
    
    <div class="step-controls">
        <div class="duplicate-count">
            <h3><?php _e('Number of Pages to Create', 'amplira'); ?></h3>
            <input type="number" id="duplicate-count" min="1" max="50" value="1">
            <p class="description"><?php _e('Enter the number of duplicate pages you want to create. Maximum: 50', 'amplira'); ?></p>
        </div>
        <div class="preview-list"></div>
    </div>
</div>