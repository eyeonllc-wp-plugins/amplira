<?php
namespace Amplira;

/**
 * Handles all shortcode-related functionality
 *
 * @package Amplira
 */
class Amplira_Shortcode {
    /**
     * Initialize the class
     */
    public function __construct() {
        // Register the shortcode
        add_shortcode('amp', array($this, 'process_shortcode'));
    }

    /**
     * Process the shortcode
     */
    public function process_shortcode($atts, $content = null) {
        // Extract shortcode attributes
        $atts = shortcode_atts(array(
            'key' => '',
            'default' => ''
        ), $atts);

        return $atts['default']; // Return default value if set
    }

    /**
     * Find all amp_ shortcodes in content
     */
    public function find_shortcodes($content) {
        $pattern = '/\[amp_([^\]]*)\]/';
        $shortcodes = array();

        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[1] as $match) {
                // Ensure each shortcode name is properly sanitized
                $name = sanitize_text_field($match);
                if ($this->validate_shortcode('amp_' . $name)) {
                    $shortcodes[] = array(
                        'name' => $name,
                        'full_tag' => '[amp_' . $name . ']',
                        'type' => 'text' // Default type, can be modified as needed
                    );
                }
            }
        }

        // Remove duplicates while preserving the complete object structure
        $unique_shortcodes = array_values(array_unique(array_map('serialize', $shortcodes)));
        return array_map('unserialize', $unique_shortcodes);
    }

    /**
     * Validate shortcode syntax
     */
    public function validate_shortcode($shortcode) {
        // Check if shortcode follows the amp_ pattern
        if (!preg_match('/^amp_[a-zA-Z0-9_-]+$/', $shortcode)) {
            return false;
        }

        // Check for invalid characters
        if (preg_match('/[^\w\-]/', str_replace('amp_', '', $shortcode))) {
            return false;
        }

        return true;
    }

    /**
     * Replace shortcodes in content
     */
    public function replace_shortcodes($content, $replacements) {
        foreach ($replacements as $key => $value) {
            $shortcode = '[amp_' . $key . ']';
            $content = str_replace($shortcode, $value, $content);
        }

        return $content;
    }

    /**
     * Convert shortcodes to JSON format
     */
    public function to_json($shortcodes) {
        return json_encode(array_map(function($shortcode) {
            return is_array($shortcode) ? $shortcode : array(
                'name' => $shortcode,
                'full_tag' => '[amp_' . $shortcode . ']',
                'type' => 'text'
            );
        }, $shortcodes));
    }
}

?>
