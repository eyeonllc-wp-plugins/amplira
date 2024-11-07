<?php
use Amplira\Amplira_Shortcode;

/**
 * The admin-specific functionality of the plugin.
 */
class Amplira_Admin {
    private $plugin_name;
    private $version;
    private $shortcode;

    /**
     * Initialize the class
     */
    public function __construct() {
        $this->plugin_name = 'amplira';
        $this->version = AMPLIRA_VERSION;
    
        // Update to use the correct class name
        $this->shortcode = new Amplira_Shortcode();

        add_action('admin_init', array($this, 'register_settings'));
    
        // Add hooks
        add_action('admin_menu', array($this, 'add_plugin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Add AJAX handlers
        add_action('wp_ajax_amplira_get_pages_with_shortcodes', array($this, 'get_pages_with_shortcodes'));
        add_action('wp_ajax_amplira_analyze_page', array($this, 'analyze_page'));
        add_action('wp_ajax_amplira_duplicate_page', array($this, 'duplicate_page'));
        add_action('wp_ajax_amplira_get_ai_suggestion', array($this, 'get_ai_suggestion'));
        add_action('wp_ajax_amplira_find_nearby_cities', array($this, 'amplira_find_nearby_cities'));
        
        // save settings
        add_action('wp_ajax_amplira_test_connection', array($this, 'test_claude_connection'));
    }

    public function run() {
        // Initialize plugin functionality
    }

    /**
     * Register the admin menu page
     */
    public function add_plugin_menu() {
        // Main plugin page
        add_menu_page(
            'Amplira', 
            'Amplira',
            'manage_options',
            'amplira',
            array($this, 'render_main_page'),
            'dashicons-admin-generic',
            30
        );
        
        // Settings submenu
        add_submenu_page(
            'amplira',
            'Amplira Settings',
            'Settings',
            'manage_options',
            'amplira-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Enqueue admin-specific scripts and styles
     */
    public function enqueue_admin_assets($hook) {
        // Check if we're on our plugin page
        if (strpos($hook, 'amplira') !== false) {
            try {
                // Enqueue CSS first
                wp_enqueue_style(
                    'amplira-admin-style',
                    AMPLIRA_PLUGIN_URL . 'admin/css/admin-style.css',
                    array(),
                    $this->version
                );
        
                // Enqueue jQuery as a dependency
                wp_enqueue_script(
                    'amplira-admin-script',
                    AMPLIRA_PLUGIN_URL . 'admin/js/admin-script.js',
                    array('jquery', 'wp-util'),
                    filemtime( AMPLIRA_PLUGIN_URL . 'admin/js/admin-script.js' ),
                    true // Load in footer
                );
        
                // Localize script with necessary data
                wp_localize_script('amplira-admin-script', 'ampliraData', array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('amplira_nonce'),
                    'strings' => array(
                        'error' => __('An error occurred. Please try again.', 'amplira'),
                        'success' => __('Operation completed successfully.', 'amplira'),
                        'loading' => __('Loading...', 'amplira'),
                        'confirm' => __('Are you sure you want to proceed?', 'amplira')
                    )
                ));
            } catch (Exception $e) {
                // Log error but don't break the admin
                error_log('Amplira asset enqueue error: ' . $e->getMessage());
            }
        }

        // Add settings page specific script
        if ($hook === 'amplira_page_amplira-settings') {
            wp_enqueue_script(
                'amplira-admin-settings',
                AMPLIRA_PLUGIN_URL . 'admin/js/admin-settings.js',
                array('jquery'),
                filemtime( AMPLIRA_PLUGIN_URL . 'admin/js/admin-settings.js' ),
                true
            );
        }
    }
    
    /**
     * Load step templates
     */
    private function load_step_templates() {
        // Define allowed steps
        $allowed_steps = array(1, 2, 3, 4);
        
        foreach ($allowed_steps as $step) {
            $template_path = AMPLIRA_PLUGIN_DIR . 'admin/partials/step-' . $step . '.php';
            
            // Check if template exists and include it safely
            if (file_exists($template_path)) {
                // Use require_once to prevent multiple inclusions
                require_once $template_path;
            } else {
                // Log missing template
                error_log(sprintf('Amplira: Missing template for step %d at %s', $step, $template_path));
                
                // Display fallback content
                printf(
                    '<div class="amplira-step-content" data-step="%d" style="display: none;">
                        <h2>%s</h2>
                        <p>%s</p>
                    </div>',
                    esc_attr($step),
                    sprintf(__('Step %d', 'amplira'), $step),
                    __('Template not found.', 'amplira')
                );
            }
        }
    }

    /**
     * Render the main admin page
     */
    public function render_main_page() {
        include AMPLIRA_PLUGIN_DIR . 'admin/pages/amplira/index.php';
    }

     /**
     * Render the settings page
     */
    public function render_settings_page() {
        include AMPLIRA_PLUGIN_DIR . 'admin/pages/settings.php';
    }

    /**
     * AJAX handler to get template types
     */
    public function get_templates() {
        check_ajax_referer('amplira_nonce', 'nonce');

        $templates = array(
            'page' => array(
                'title' => __('Page Template', 'amplira'),
                'description' => __('Create multiple pages with dynamic content', 'amplira')
            ),
            'post' => array(
                'title' => __('Post Template', 'amplira'),
                'description' => __('Create multiple blog posts with dynamic content', 'amplira')
            ),
            'product' => array(
                'title' => __('Product Template', 'amplira'),
                'description' => __('Create multiple products with dynamic content', 'amplira')
            )
        );

        wp_send_json_success($templates);
    }

    /**
     * AJAX handler to get pages with shortcodes
     */
    public function get_pages_with_shortcodes() {
        check_ajax_referer('amplira_nonce', 'nonce');

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'page';

        $args = array(
            'post_type' => $type,
            'post_status' => 'publish',
            'posts_per_page' => -1
        );

        $pages = get_posts($args);
        $pages_with_shortcodes = array();

        foreach ($pages as $page) {
            // Use the shortcode handler to find shortcodes
            $found_shortcodes = $this->shortcode->find_shortcodes($page->post_content);
            
            if (!empty($found_shortcodes)) {
                // Extract just the shortcode names for the response
                $shortcode_names = array_map(function($shortcode) {
                    return $shortcode['name'];
                }, $found_shortcodes);

                $pages_with_shortcodes[] = array(
                    'id' => $page->ID,
                    'title' => $page->post_title,
                    'shortcodes' => $shortcode_names
                );
            }
        }

        wp_send_json_success($pages_with_shortcodes);
    }

    /**
     * AJAX handler to analyze a page
     */
    public function analyze_page() {
        check_ajax_referer('amplira_nonce', 'nonce');
    
        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
        
        if (!$page_id) {
            wp_send_json_error(__('Invalid page ID', 'amplira'));
        }
    
        $page = get_post($page_id);
        if (!$page) {
            wp_send_json_error(__('Page not found', 'amplira'));
        }
    
        // Use the shortcode handler instead of find_amp_shortcodes
        $shortcodes = $this->shortcode->find_shortcodes($page->post_content);
        
        // Extract just the shortcode names for consistency
        $shortcode_names = array_map(function($shortcode) {
            return $shortcode['name'];
        }, $shortcodes);
        
        wp_send_json_success(array(
            'shortcodes' => $shortcode_names,
            'content' => $page->post_content
        ));
    }


    /**
     * AJAX handler for page duplication
     */
    public function duplicate_page() {
        error_log('Starting page duplication process');
        
        check_ajax_referer('amplira_nonce', 'nonce');
    
        // Log incoming data
        error_log('POST data: ' . print_r($_POST, true));
    
        if (!isset($_POST['template_id']) || !isset($_POST['replacements']) || !isset($_POST['keywords'])) {
            error_log('Missing required data in duplicate_page');
            wp_send_json_error(array('message' => 'Missing required data.'));
            return;
        }
    
        try {
            $template_id = intval($_POST['template_id']);
            $replacements = $_POST['replacements'];
            $keywords = $_POST['keywords'];
            $ai_settings = isset($_POST['ai_settings']) ? $_POST['ai_settings'] : array();
    
            // Log decoded data
            error_log('Template ID: ' . $template_id);
            error_log('Replacements: ' . print_r($replacements, true));
            error_log('Keywords: ' . print_r($keywords, true));
    
            $original_page = get_post($template_id);
            if (!$original_page) {
                error_log('Template page not found: ' . $template_id);
                wp_send_json_error(array('message' => 'Template page not found.'));
                return;
            }
    
            $created_pages = array();
            $errors = array();
    
            foreach ($keywords as $keyword) {
                try {
                    error_log('Processing keyword: ' . $keyword);
                    
                    // Get the page content and replace all shortcodes
                    $new_content = $original_page->post_content;
                    $new_title = $original_page->post_title;
    
                    // First replace the primary shortcode
                    if (!empty($replacements)) {
                        $primary_shortcode = array_key_first($replacements);
                        error_log('Primary shortcode: ' . $primary_shortcode);
                        
                        $new_content = str_replace("[amp_{$primary_shortcode}]", $keyword, $new_content);
                        $new_title = str_replace("[amp_{$primary_shortcode}]", $keyword, $new_title);
    
                        // Then replace other shortcodes
                        foreach ($replacements as $code => $replacement) {
                            if ($code !== $primary_shortcode) {
                                $shortcode = "[amp_{$code}]";
                                $content = isset($replacement['content']) ? $replacement['content'] : '';
    
                                $new_content = str_replace($shortcode, $content, $new_content);
                                $new_title = str_replace($shortcode, $content, $new_title);
                            }
                        }
                    }
    
                    error_log('Creating new page with title: ' . $new_title);
    
                    // Create the new page
                    $new_page = array(
                        'post_title'    => $new_title,
                        'post_content'  => $new_content,
                        'post_status'   => 'draft',
                        'post_type'     => $original_page->post_type,
                        'post_author'   => get_current_user_id(),
                        'post_parent'   => $template_id
                    );
    
                    $new_page_id = wp_insert_post($new_page);
    
                    if (is_wp_error($new_page_id)) {
                        throw new Exception($new_page_id->get_error_message());
                    }
    
                    error_log('New page created with ID: ' . $new_page_id);
    
                    // Copy and process meta data
                    $this->copy_meta_data($template_id, $new_page_id, $replacements, $keyword);
    
                    $created_pages[] = array(
                        'id' => $new_page_id,
                        'title' => $new_title,
                        'keyword' => $keyword
                    );
    
                } catch (Exception $e) {
                    error_log('Error creating page for keyword ' . $keyword . ': ' . $e->getMessage());
                    $errors[] = "Error creating page for '{$keyword}': " . $e->getMessage();
                }
            }
    
            // Send response
            if (!empty($created_pages)) {
                $response = array(
                    'message' => sprintf(
                        '%d pages created successfully%s', 
                        count($created_pages),
                        !empty($errors) ? ' with some errors' : ''
                    ),
                    'pages' => $created_pages,
                    'errors' => $errors
                );
                error_log('Success response: ' . print_r($response, true));
                wp_send_json_success($response);
            } else {
                error_log('No pages created');
                wp_send_json_error(array(
                    'message' => 'Failed to create any pages.',
                    'errors' => $errors
                ));
            }
    
        } catch (Exception $e) {
            error_log('Major error in duplicate_page: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error(array(
                'message' => 'An error occurred while creating pages.',
                'error' => $e->getMessage()
            ));
        }
    }
    
    
    /**
     * Copy and process meta data from template to new page
     */
    private function copy_meta_data($template_id, $new_page_id, $replacements, $keyword) {
        $template_meta = get_post_meta($template_id);
        
        foreach ($template_meta as $meta_key => $meta_values) {
            foreach ($meta_values as $meta_value) {
                $processed_value = $meta_value;
    
                // Process serialized data
                if (is_serialized($meta_value)) {
                    $unserialized = maybe_unserialize($meta_value);
                    $processed_value = $this->process_meta_content($unserialized, $replacements, $keyword);
                    $processed_value = maybe_serialize($processed_value);
                } else {
                    $processed_value = $this->process_meta_content($meta_value, $replacements, $keyword);
                }
    
                update_post_meta($new_page_id, $meta_key, $processed_value);
            }
        }
    }
    
    /**
     * Process content recursively for meta data
     */
    private function process_meta_content($content, $replacements, $keyword) {
        if (is_array($content)) {
            foreach ($content as $key => $value) {
                $content[$key] = $this->process_meta_content($value, $replacements, $keyword);
            }
        } elseif (is_string($content)) {
            // Replace primary shortcode
            $primary_shortcode = array_key_first($replacements);
            $content = str_replace("[amp_{$primary_shortcode}]", $keyword, $content);
    
            // Replace other shortcodes
            foreach ($replacements as $code => $replacement) {
                if ($code !== $primary_shortcode) {
                    $content = str_replace("[amp_{$code}]", $replacement['content'], $content);
                }
            }
        }
    
        return $content;
    }

    /**
     * Check if Rank Math is active
     */
    private function is_rank_math_active() {
        return class_exists('RankMath');
    }

    /**
     * Set Rank Math metadata for the new page
     */
    private function set_rank_math_metadata($page_id, $keyword, $title) {
        // Default meta description format
        $meta_description = sprintf(
            'Discover everything about %s. Find comprehensive information, detailed guides, and valuable resources.',
            $keyword
        );

        // Set Rank Math meta title (if not already set, use the page title)
        update_post_meta($page_id, 'rank_math_title', $title);
        
        // Set Rank Math meta description
        update_post_meta($page_id, 'rank_math_description', $meta_description);
        
        // Set focus keyword
        update_post_meta($page_id, 'rank_math_focus_keyword', $keyword);

        // Set default advanced robots meta
        update_post_meta($page_id, 'rank_math_advanced_robots', array(
            'index' => 'index',
            'follow' => 'follow',
            'max-snippet' => '-1',
            'max-video-preview' => '-1',
            'max-image-preview' => 'large',
            'noarchive' => 'off',
            'nosnippet' => 'off'
        ));

        // Add basic schema markup (Optional)
        $schema = array(
            '@type' => 'WebPage',
            'headline' => $title,
            'description' => $meta_description,
            'keywords' => $keyword
        );
        update_post_meta($page_id, 'rank_math_schema', $schema);
    }

    /**
     * Update SEO metadata for a page
     */
    private function update_seo_metadata($page_id, $keyword, $seo_data) {
        // Replace keyword placeholder in formats
        $meta_title = str_replace('%keyword%', $keyword, $seo_data['title_format']);
        $meta_description = str_replace('%keyword%', $keyword, $seo_data['description_format']);
        $focus_keyword = str_replace('%keyword%', $keyword, $seo_data['focus_keyword_format']);

        if ($this->is_rank_math_active()) {
            // Update Rank Math metadata
            update_post_meta($page_id, 'rank_math_title', $meta_title);
            update_post_meta($page_id, 'rank_math_description', $meta_description);
            update_post_meta($page_id, 'rank_math_focus_keyword', $focus_keyword);
            
            // Set default Rank Math settings
            update_post_meta($page_id, 'rank_math_robots', array(
                'index' => 'index',
                'follow' => 'follow'
            ));
        }

        // Also store in standard meta fields as fallback
        update_post_meta($page_id, '_yoast_wpseo_title', $meta_title);
        update_post_meta($page_id, '_yoast_wpseo_metadesc', $meta_description);
        update_post_meta($page_id, '_seo_title', $meta_title);
        update_post_meta($page_id, '_seo_description', $meta_description);
    }


    public function register_settings() {
        // Register your plugin settings here
        register_setting('amplira_settings', 'amplira_claude_api_key');
        
        // Add settings section
        // add_settings_section(
        //     'amplira_section_id',
        //     'Amplira Settings',
        //     array($this, 'amplira_section_callback'),
        //     'amplira_settings'
        // );
        
        // // Add settings field
        // add_settings_field(
        //     'amplira_field_id',
        //     'Amplira Field',
        //     array($this, 'amplira_field_callback'),
        //     'amplira_settings',
        //     'amplira_section_id'
        // );
    }

    public function amplira_section_callback() {
        echo '<p>These are the settings for Amplira.</p>';
    }

    public function amplira_field_callback() {
        $value = get_option('amplira_option_name');
        echo '<input type="text" name="amplira_option_name" value="' . esc_attr($value) . '" />';
    }


    /**
     * AJAX handler for AI suggestions
     */
    public function get_ai_suggestion() {
        try {
            // check_ajax_referer('amplira_ai_nonce', 'nonce');

            if (!class_exists('Amplira_AI')) {
                throw new Exception('AI functionality not available');
            }

            $page_title = isset($_POST['page_title']) ? sanitize_text_field($_POST['page_title']) : '';
            $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
            $is_meta_title = isset($_POST['is_meta_title']) && $_POST['is_meta_title'] === 'true';

            if (empty($page_title) || empty($city)) {
                throw new Exception('Missing required parameters');
            }

            $ai = new Amplira_AI();
            $suggestion = $ai->generate_smart_suggestion($page_title, $city, $is_meta_title);

            if (is_wp_error($suggestion)) {
                throw new Exception($suggestion->get_error_message());
            }

            wp_send_json_success($suggestion);
        } catch (Exception $e) {
            error_log('Amplira AI Error: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    public function amplira_find_nearby_cities() {
        try {
            if (!class_exists('Amplira_AI')) {
                throw new Exception('AI functionality not available');
            }

            $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
            $count = isset($_POST['count']) ? sanitize_text_field($_POST['count']) : 5;

            if (empty($city)) {
                throw new Exception('Missing required parameters');
            }

            $ai = new Amplira_AI();
            $response = $ai->find_nearby_cities($city, $count);

            wp_send_json_success($response);
        } catch (Exception $e) {
            error_log('Amplira AI Error: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX handler for testing Claude API connection
     */
    public function test_claude_connection() {
        check_ajax_referer('amplira_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        try {
            $ai = new Amplira_AI();
            $test_result = $ai->test_connection();

            if (is_wp_error($test_result)) {
                wp_send_json_error($test_result->get_error_message());
            } else {
                wp_send_json_success('Connection successful! The Claude API is working properly.');
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

}
?>









