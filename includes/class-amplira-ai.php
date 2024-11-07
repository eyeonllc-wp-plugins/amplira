<?php
/**
 * Handles AI content generation and optimization using Claude
 */
class Amplira_AI {
    /**
     * Claude API key
     */
    private $api_key;

    /**
     * Claude API endpoint
     */
    private $api_endpoint = 'https://api.anthropic.com/v1/messages';

    /**
     * Initialize the class
     */
    public function __construct() {
        $this->api_key = get_option('amplira_claude_api_key', '');
    }

    /**
     * Check if AI is configured and ready
     */
    public function is_ready() {
        return !empty($this->api_key) && strpos($this->api_key, 'sk-ant-api') === 0;
    }

    /**
     * Render settings section
     */
    public function render_settings_section() {
        echo '<p>' . __('Configure your Claude AI settings for content generation.', 'amplira') . '</p>';
    }

    /**
     * Generate content variations based on template and context
     */
    public function generate_content($template_content, $primary_keyword, $context = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Claude API key is not configured.', 'amplira'));
        }

        $prompt = $this->build_prompt($template_content, $primary_keyword, $context);

        try {
            $response = $this->call_claude_api($prompt);
            return $this->process_ai_response($response);
        } catch (Exception $e) {
            return new WP_Error('ai_error', $e->getMessage());
        }
    }

    /**
     * Build the prompt for Claude
     */
    private function build_prompt($template_content, $primary_keyword, $context) {
        $prompt = "Generate unique, SEO-optimized content for a webpage about {$primary_keyword}. ";
        $prompt .= "Use this template as a guide but create unique variations:\n\n";
        $prompt .= $template_content . "\n\n";
        
        if (!empty($context)) {
            $prompt .= "Additional context:\n";
            foreach ($context as $key => $value) {
                $prompt .= "- {$key}: {$value}\n";
            }
        }

        $prompt .= "\nMake sure the content is:\n";
        $prompt .= "1. Unique and engaging\n";
        $prompt .= "2. Naturally optimized for SEO\n";
        $prompt .= "3. Maintains the same structure as the template\n";
        $prompt .= "4. Relevant to {$primary_keyword}\n";

        return $prompt;
    }

    /**
     * Make API call to Claude
     */
    // private function call_claude_api($prompt) {
    //     $response = wp_remote_post($this->api_endpoint, array(
    //         'headers' => array(
    //             'anthropic-version' => '2023-06-01',
    //             'x-api-key' => $this->api_key,
    //             'content-type' => 'application/json',
    //         ),
    //         'timeout' => 45,
    //         'body' => json_encode(array(
    //             'model' => 'claude-3-sonnet-20240229',
    //             'messages' => array(
    //                 array(
    //                     'role' => 'user',
    //                     'content' => $prompt
    //                 )
    //             ),
    //             'max_tokens' => 4000,
    //             'temperature' => 0.7,
    //         ))
    //     ));

    //     if (is_wp_error($response)) {
    //         throw new Exception($response->get_error_message());
    //     }

    //     $body = json_decode(wp_remote_retrieve_body($response), true);

    //     if (isset($body['error'])) {
    //         throw new Exception($body['error']['message']);
    //     }

    //     return $body;
    // }

    /**
     * Process the Claude API response
     */
    private function process_ai_response($response) {
        if (!isset($response['content'][0]['text'])) {
            throw new Exception(__('Invalid response from Claude', 'amplira'));
        }

        return $response['content'][0]['text'];
    }

    /**
     * Optimize content for SEO
     */
    public function optimize_content($content, $primary_keyword) {
        if (empty($this->api_key)) {
            return $content;
        }

        $prompt = "Optimize the following content for SEO, focusing on the keyword '{$primary_keyword}' while maintaining natural readability:\n\n";
        $prompt .= $content;

        try {
            $response = $this->call_claude_api($prompt);
            return $this->process_ai_response($response);
        } catch (Exception $e) {
            // If optimization fails, return original content
            return $content;
        }
    }

    /**
     * Check if AI is configured and ready
     */
    public function test_connection() {
        if (!$this->is_ready()) {
            return new WP_Error('invalid_key', __('Invalid API key format', 'amplira'));
        }
    
        try {
            $response = wp_remote_post($this->api_endpoint, array(
                'headers' => array(
                    'anthropic-version' => '2023-06-01',
                    'x-api-key' => $this->api_key,
                    'content-type' => 'application/json',
                ),
                'body' => json_encode(array(
                    'model' => 'claude-3-sonnet-20240229',
                    'messages' => array(
                        array(
                            'role' => 'user',
                            'content' => 'Test connection.'
                        )
                    ),
                    'max_tokens' => 10
                )),
                'timeout' => 15,
                'sslverify' => true
            ));
    
            if (is_wp_error($response)) {
                error_log('Amplira Claude API Test Error: ' . $response->get_error_message());
                return new WP_Error('connection_failed', $response->get_error_message());
            }
    
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $response_code = wp_remote_retrieve_response_code($response);
    
            error_log('Amplira Claude API Test Response Code: ' . $response_code);
            error_log('Amplira Claude API Test Response Body: ' . print_r($body, true));
    
            if ($response_code !== 200) {
                return new WP_Error(
                    'api_error',
                    isset($body['error']['message']) ? $body['error']['message'] : 'API request failed'
                );
            }
    
            return true;
        } catch (Exception $e) {
            error_log('Amplira Claude API Test Exception: ' . $e->getMessage());
            return new WP_Error('connection_failed', $e->getMessage());
        }
    }

    /**
     * Generate smart suggestion for meta title or description
     */
    public function generate_smart_suggestion($page_title, $city, $is_meta_title, $template_data = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Claude API key is not configured.', 'amplira'));
        }
    
        // Build the prompt based on the type of content needed
        if ($is_meta_title) {
            $prompt = $this->build_meta_title_prompt($page_title, $city, $template_data);
        } else {
            $prompt = $this->build_meta_description_prompt($page_title, $city, $template_data);
        }
    
        try {
            $response = $this->call_claude_api($prompt);
            $suggestion = $this->process_ai_response($response);
            
            // Format the response appropriately
            if ($is_meta_title) {
                return wp_trim_words($suggestion, 10); // Limit title length
            } else {
                return wp_trim_words($suggestion, 30); // Limit description length
            }
        } catch (Exception $e) {
            return new WP_Error('ai_error', $e->getMessage());
        }
    }

    public function find_nearby_cities($city, $count = 5) {
        $prompt = $this->build_find_cities_prompt($city, $count);
        $response = $this->call_claude_api($prompt);
        $suggestion = $this->process_ai_response($response);
        $response = json_decode($suggestion, true);
        return $response;
    }

    private function build_find_cities_prompt($city, $count) {
        $jsonExample = json_encode([
            ["city" => "CityName1", "distance_km" => 50],
            ["city" => "CityName2", "distance_km" => 75]
        ]);
        $prompt = "I am located in $city. Provide a list of the $count nearest major cities to this location, ordered by proximity. Please return the response in a JSON array format, like this:\n\n$jsonExample\n\nOnly include major or well-known cities within a 300 km radius if possible.";
        return $prompt;
    }
    
    private function build_meta_title_prompt($page_title, $city, $template_data) {
        $prompt = "As an SEO expert, create a compelling meta title for a webpage about {$page_title} in {$city}.\n\n";
        
        if (!empty($template_data['metaTitle'])) {
            $prompt .= "Use this example as inspiration (but create a unique variation):\n";
            $prompt .= "{$template_data['metaTitle']}\n\n";
        }
    
        $prompt .= "Requirements:\n";
        $prompt .= "1. Maximum 60 characters\n";
        $prompt .= "2. Include both the city name and main service/topic\n";
        $prompt .= "3. Use engaging, action-oriented language\n";
        $prompt .= "4. Maintain local SEO best practices\n";
        
        if (!empty($template_data['useUnique'])) {
            $prompt .= "5. Make it distinctly different from the example while maintaining effectiveness\n";
        }
    
        return $prompt;
    }
    
    private function build_meta_description_prompt($page_title, $city, $template_data) {
        $prompt = "As an SEO expert, create a compelling meta description for a webpage about {$page_title} in {$city}.\n\n";
        
        if (!empty($template_data['metaDesc'])) {
            $prompt .= "Use this example as inspiration (but create a unique variation):\n";
            $prompt .= "{$template_data['metaDesc']}\n\n";
        }
    
        $prompt .= "Requirements:\n";
        $prompt .= "1. Maximum 155 characters\n";
        $prompt .= "2. Include a clear value proposition\n";
        $prompt .= "3. Include both the city name and main service/topic\n";
        $prompt .= "4. Use a clear call-to-action\n";
        $prompt .= "5. Maintain natural, engaging language\n";
        
        if (!empty($template_data['useUnique'])) {
            $prompt .= "6. Make it distinctly different from the example while maintaining effectiveness\n";
        }
    
        return $prompt;
    }

    /**
     * Generate meta content in batch
     */
    public function generate_meta_batch($items, $template_data = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Claude API key is not configured.', 'amplira'));
        }

        $results = [];
        $errors = [];
        $delay = 1000000; // 1 second delay between requests

        foreach ($items as $index => $item) {
            try {
                // Add delay after first request
                if ($index > 0) {
                    usleep($delay);
                }

                // Generate meta title
                $title = $this->generate_smart_suggestion(
                    $item['page_title'],
                    $item['city'],
                    true,
                    $template_data
                );

                // Add another small delay
                usleep($delay);

                // Generate meta description
                $description = $this->generate_smart_suggestion(
                    $item['page_title'],
                    $item['city'],
                    false,
                    $template_data
                );

                $results[] = [
                    'city' => $item['city'],
                    'meta_title' => is_wp_error($title) ? '' : $title,
                    'meta_description' => is_wp_error($description) ? '' : $description,
                    'success' => true
                ];

            } catch (Exception $e) {
                $errors[] = [
                    'city' => $item['city'],
                    'error' => $e->getMessage()
                ];
                
                $results[] = [
                    'city' => $item['city'],
                    'meta_title' => '',
                    'meta_description' => '',
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'results' => $results,
            'errors' => $errors
        ];
    }

    /**
     * Enhanced error handling for API calls
     */
    private function call_claude_api($prompt) {
        static $last_request_time = 0;
        $min_request_interval = 1.0; // Minimum time between requests in seconds

        // Rate limiting
        $current_time = microtime(true);
        $time_since_last_request = $current_time - $last_request_time;
        if ($time_since_last_request < $min_request_interval) {
            usleep(($min_request_interval - $time_since_last_request) * 1000000);
        }

        try {
            $response = wp_remote_post($this->api_endpoint, array(
                'headers' => array(
                    'anthropic-version' => '2023-06-01',
                    'x-api-key' => $this->api_key,
                    'content-type' => 'application/json',
                ),
                'timeout' => 45,
                'body' => json_encode(array(
                    'model' => 'claude-3-sonnet-20240229',
                    'messages' => array(
                        array(
                            'role' => 'user',
                            'content' => $prompt
                        )
                    ),
                    'max_tokens' => 4000,
                    'temperature' => 0.7,
                ))
            ));

            $last_request_time = microtime(true);

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            $response_code = wp_remote_retrieve_response_code($response);

            if ($response_code === 429) {
                throw new Exception('API rate limit exceeded. Please try again later.');
            }

            if ($response_code !== 200) {
                throw new Exception(
                    isset($body['error']['message']) 
                        ? $body['error']['message'] 
                        : 'API request failed with status ' . $response_code
                );
            }

            if (isset($body['error'])) {
                throw new Exception($body['error']['message']);
            }

            return $body;

        } catch (Exception $e) {
            error_log('Amplira Claude API Error: ' . $e->getMessage());
            throw $e;
        }
    }

}
