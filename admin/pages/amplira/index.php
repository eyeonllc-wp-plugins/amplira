<div class="wrap amplira-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- Steps Progress Bar -->
    <div class="amplira-steps">
        <?php
        $steps = array(
            1 => __('Select Template', 'amplira'),
            2 => __('Choose Page', 'amplira'),
            3 => __('Number of Pages', 'amplira'),
            4 => __('Replace Content', 'amplira'),
            5 => __('Confirm and Create Pages', 'amplira')
        );

        foreach ($steps as $step_num => $step_title) {
            $class = $step_num === 1 ? 'active' : '';
            ?>
            <div class="amplira-step <?php echo esc_attr($class); ?>" data-step="<?php echo esc_attr($step_num); ?>">
                <div class="amplira-step-circle"><?php echo esc_html($step_num); ?></div>
                <div class="amplira-step-title"><?php echo esc_html($step_title); ?></div>
            </div>
            <?php
        }
        ?>
    </div>

    <!-- Content Area -->
    <div class="amplira-content">
        <?php
        // Load step templates from partials
        for ($i = 1; $i <= 4; $i++) {
            $template_path = AMPLIRA_PLUGIN_DIR . 'admin/pages/amplira/steps/step-' . $i . '.php';
            if (file_exists($template_path)) {
                include $template_path;
            } else {
                // Fallback if template doesn't exist
                ?>
                <div class="amplira-step-content" data-step="<?php echo esc_attr($i); ?>" style="display: <?php echo $i === 1 ? 'block' : 'none'; ?>">
                    <h2><?php printf(__('Step %d', 'amplira'), $i); ?></h2>
                    <p><?php _e('Content for this step is being developed.', 'amplira'); ?></p>
                </div>
                <?php
            }
        }
        ?>
    </div>

    <!-- Navigation Buttons -->
    <div class="amplira-navigation">
        <button type="button" class="button button-secondary" id="prev-step" style="display: none;">
            <?php _e('Previous', 'amplira'); ?>
        </button>
        <button type="button" class="button button-primary" id="next-step">
            <?php _e('Next', 'amplira'); ?>
        </button>
    </div>
</div>

<!-- Templates -->
<script type="text/template" id="page-item-template">
    <div class="amplira-page-item" data-page-id="{id}">
        <h3>{title}</h3>
        <div class="amplira-shortcodes">{shortcodes}</div>
    </div>
</script>