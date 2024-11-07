<?php
/**
 * Handles page duplication functionality
 */
class Amplira_Duplicator {
    private $shortcode_handler;

    /**
     * Initialize the class
     */
    public function __construct() {
        $this->shortcode_handler = new Amplira_Shortcode();
    }

    /**
     * Create duplicate pages
     */
    public function create_duplicates($template_id, $replacements, $keywords) {
        $results = array(
            'success' => array(),
            'failed' => array()
        );

        // Get the template post
        $template = get_post($template_id);
        if (!$template) {
            return new WP_Error('invalid_template', 'Template not found');
        }

        // Create a duplicate for each keyword
        foreach ($keywords as $index => $keyword) {
            $duplicate_id = $this->create_single_duplicate($template, $replacements, $keyword);
            
            if (is_wp_error($duplicate_id)) {
                $results['failed'][] = array(
                    'keyword' => $keyword,
                    'error' => $duplicate_id->get_error_message()
                );
            } else {
                $results['success'][] = array(
                    'id' => $duplicate_id,
                    'keyword' => $keyword
                );
            }
        }

        return $results;
    }

    /**
     * Create a single duplicate page
     */
    private function create_single_duplicate($template, $replacements, $primary_keyword) {
        // Prepare the content with replacements
        $new_content = $this->prepare_content($template->post_content, $replacements, $primary_keyword);
        
        // Prepare the title
        $new_title = $this->prepare_title($template->post_title, $primary_keyword);

        // Create the new post
        $new_post = array(
            'post_title'    => $new_title,
            'post_content'  => $new_content,
            'post_status'   => 'draft',
            'post_type'     => $template->post_type,
            'post_author'   => get_current_user_id(),
            'post_excerpt'  => $template->post_excerpt,
            'post_parent'   => $template->ID
        );

        // Insert the post
        $new_post_id = wp_insert_post($new_post, true);

        if (is_wp_error($new_post_id)) {
            return $new_post_id;
        }

        // Copy and update meta data
        $this->copy_meta_data($template->ID, $new_post_id, $replacements, $primary_keyword);

        return $new_post_id;
    }

    /**
     * Prepare content with replacements
     */
    private function prepare_content($content, $replacements, $primary_keyword) {
        // Replace the primary keyword first
        $primary_shortcode = array_key_first($replacements);
        $content = str_replace('[amp_' . $primary_shortcode . ']', $primary_keyword, $content);

        // Replace other shortcodes
        foreach ($replacements as $key => $value) {
            if ($key !== $primary_shortcode) {
                $content = str_replace('[amp_' . $key . ']', $value['content'], $content);
            }
        }

        return $content;
    }

    /**
     * Prepare the title with the primary keyword
     */
    private function prepare_title($title, $primary_keyword) {
        // You can customize how the title is generated
        return $title . ' - ' . $primary_keyword;
    }

    /**
     * Copy and update meta data
     */
    private function copy_meta_data($template_id, $new_post_id, $replacements, $primary_keyword) {
        $meta_keys = get_post_custom_keys($template_id);
        
        if (empty($meta_keys)) {
            return;
        }

        foreach ($meta_keys as $key) {
            // Skip internal WordPress meta keys
            if (is_protected_meta($key, 'post')) {
                continue;
            }

            $values = get_post_custom_values($key, $template_id);
            
            foreach ($values as $value) {
                $value = $this->process_meta_value($value, $replacements, $primary_keyword);
                update_post_meta($new_post_id, $key, $value);
            }
        }
    }

    /**
     * Process meta value for shortcodes
     */
    private function process_meta_value($value, $replacements, $primary_keyword) {
        // If the value is serialized, unserialize it first
        $unserialized = maybe_unserialize($value);
        
        if ($unserialized === $value) {
            // Value wasn't serialized, process it directly
            return $this->replace_shortcodes_in_value($value, $replacements, $primary_keyword);
        }

        // Process serialized data recursively
        $processed = $this->process_serialized_data($unserialized, $replacements, $primary_keyword);
        
        return maybe_serialize($processed);
    }

    /**
     * Process serialized data recursively
     */
    private function process_serialized_data($data, $replacements, $primary_keyword) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $data[$key] = $this->process_serialized_data($value, $replacements, $primary_keyword);
                } elseif (is_string($value)) {
                    $data[$key] = $this->replace_shortcodes_in_value($value, $replacements, $primary_keyword);
                }
            }
        } elseif (is_object($data)) {
            foreach (get_object_vars($data) as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $data->$key = $this->process_serialized_data($value, $replacements, $primary_keyword);
                } elseif (is_string($value)) {
                    $data->$key = $this->replace_shortcodes_in_value($value, $replacements, $primary_keyword);
                }
            }
        }

        return $data;
    }

    /**
     * Replace shortcodes in a single value
     */
    private function replace_shortcodes_in_value($value, $replacements, $primary_keyword) {
        // Replace the primary keyword first
        $primary_shortcode = array_key_first($replacements);
        $value = str_replace('[amp_' . $primary_shortcode . ']', $primary_keyword, $value);

        // Replace other shortcodes
        foreach ($replacements as $key => $replacement) {
            if ($key !== $primary_shortcode) {
                $value = str_replace('[amp_' . $key . ']', $replacement['content'], $value);
            }
        }

        return $value;
    }
}