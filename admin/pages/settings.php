<?php
/**
 * Template for the settings page
 */
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form action="options.php" method="post">
        <?php
        settings_fields('amplira_settings');
        do_settings_sections('amplira_settings');
        ?>

        <input type="hidden" name="_wp_http_referer" value="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" />
        
        <div class="amplira-settings-card">
            <h2><?php _e('Claude AI Configuration', 'amplira'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Claude API Key', 'amplira'); ?></th>
                    <td>
                        <input type="password" 
                               name="amplira_claude_api_key" 
                               id="claude-api-key"
                               value="<?php echo esc_attr(get_option('amplira_claude_api_key')); ?>" 
                               class="regular-text"
                        />
                        <button type="button" class="button button-secondary" id="toggle-api-key">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                        <p class="description">
                            <?php _e('Enter your Claude API key (starts with sk-ant-api...)', 'amplira'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button('Save Settings'); ?>
        </div>
    </form>

    <div class="amplira-settings-card" style="margin-top: 20px;">
        <h2><?php _e('Test AI Integration', 'amplira'); ?></h2>
        <p><?php _e('Verify your Claude API connection before using AI features.', 'amplira'); ?></p>
        
        <div class="amplira-test-section">
            <button type="button" class="button button-primary amplira-test-button" id="test-ai-connection">
                <span class="dashicons dashicons-admin-site"></span>
                <?php _e('Test Connection', 'amplira'); ?>
            </button>
            <div id="test-result" style="margin-top: 10px;"></div>
        </div>

        <div class="amplira-ai-status" style="margin-top: 20px;">
            <h3><?php _e('AI Features Status', 'amplira'); ?></h3>
            <ul class="amplira-status-list">
                <li>
                    <span class="status-indicator" id="api-key-status"></span>
                    <?php _e('API Key Configured', 'amplira'); ?>
                </li>
                <li>
                    <span class="status-indicator" id="connection-status"></span>
                    <?php _e('Connection Active', 'amplira'); ?>
                </li>
                <li>
                    <span class="status-indicator" id="permissions-status"></span>
                    <?php _e('Permissions Valid', 'amplira'); ?>
                </li>
            </ul>
        </div>
    </div>
</div>
