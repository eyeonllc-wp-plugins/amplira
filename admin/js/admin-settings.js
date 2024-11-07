(function($) {
    'use strict';

    $(document).ready(function() {
        initializeAITest();
        initializeAPIKeyToggle();
        initializeStatusChecks();
    });

    function initializeAITest() {
        const $button = $('#test-ai-connection');
        const $result = $('#test-result');
        
        $button.on('click', function() {
            $button.prop('disabled', true);
            
            // Show loading state
            $result.html(`
                <div class="notice notice-info inline">
                    <p>
                        <span class="spinner is-active" style="float: left; margin: 0 8px 0 0;"></span>
                        Testing Claude API connection...
                    </p>
                </div>
            `);

            $.ajax({
                url: ampliraData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'amplira_test_connection',
                    nonce: ampliraData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html(`
                            <div class="notice notice-success inline">
                                <p><span class="dashicons dashicons-yes"></span> ${response.data}</p>
                            </div>
                        `);
                        updateStatuses(true);
                    } else {
                        $result.html(`
                            <div class="notice notice-error inline">
                                <p><span class="dashicons dashicons-warning"></span> Error: ${response.data}</p>
                            </div>
                        `);
                        updateStatuses(false);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    $result.html(`
                        <div class="notice notice-error inline">
                            <p><span class="dashicons dashicons-warning"></span> Connection test failed: ${textStatus}</p>
                        </div>
                    `);
                    updateStatuses(false);
                    console.error('Test Connection Error:', {jqXHR, textStatus, errorThrown});
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
    }

    function initializeAPIKeyToggle() {
        const $toggle = $('#toggle-api-key');
        const $input = $('#claude-api-key');

        $toggle.on('click', function() {
            const isPassword = $input.attr('type') === 'password';
            $input.attr('type', isPassword ? 'text' : 'password');
            $toggle.find('.dashicons')
                .toggleClass('dashicons-visibility')
                .toggleClass('dashicons-hidden');
        });
    }

    function initializeStatusChecks() {
        // Initial check
        const apiKey = $('#claude-api-key').val();
        updateStatuses(false); // Reset states
        
        if (apiKey && apiKey.startsWith('sk-ant-api')) {
            $('#api-key-status').addClass('status-success')
                .attr('title', 'API key format is valid');
        }

        // Check on key change
        $('#claude-api-key').on('input', function() {
            const newKey = $(this).val();
            if (newKey && newKey.startsWith('sk-ant-api')) {
                $('#api-key-status').addClass('status-success')
                    .attr('title', 'API key format is valid');
            } else {
                $('#api-key-status').removeClass('status-success')
                    .attr('title', 'API key format is invalid');
            }
        });
    }

    function updateStatuses(isConnected) {
        if (isConnected) {
            $('#connection-status, #permissions-status')
                .addClass('status-success')
                .attr('title', 'Connected and verified');
        } else {
            $('#connection-status, #permissions-status')
                .removeClass('status-success')
                .attr('title', 'Not verified');
        }
    }

})(jQuery);