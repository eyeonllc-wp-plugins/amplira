(function($) {
    'use strict';

    // Store state
    const state = {
        currentStep: 1,
        selectedTemplate: null,
        selectedPage: null,
        duplicateCount: 1,
        shortcodes: [],
        replacements: [],
        placeholders: {},
        isLoadingPages: false,
        hasLoadedPages: false,
        stepContent: {},
        creationInProgress: false,
        totalPagesCreated: 0,
        creationErrors: []
    };

    let generateRowCount = 0;

    // Initialize only on our plugin page
    $(document).ready(function() {
        // Check if we're on the plugin page
        if ($('.amplira-wrap').length === 0) {
            return; // Exit if not on our plugin page
        }

        try {
            initializeSteps();
            bindEvents();
            initializeStep5();
        } catch (error) {
            console.error('Initialization error:', error);
            // Prevent white screen by showing error message
            $('.amplira-wrap').prepend(
                '<div class="notice notice-error"><p>Error initializing plugin. Please refresh the page.</p></div>'
            );
        }
    });

    function initializeSteps() {
        $('.amplira-step-content').not('[data-step="1"]').hide();
        updateStepNavigation();
    }

    function bindEvents() {
        // Template selection
        $(document).on('click', '.amplira-template-card', function(e) {
            logElementData($(this));
            selectTemplate.call(this);
        });

        // Step 3: Number of Pages
        $('#duplicate-count').on('change', handleDuplicateCount);
    
        // Navigation
        $('#next-step').on('click', nextStep);
        $('#prev-step').on('click', previousStep);
    
        // Step 2: Page Selection
        $(document).on('click', '.amplira-page-item', selectPage);
        $('#page-search').on('input', debounce(filterPages, 300));
    
        // Step 3: Duplicate Count
        $('#duplicate-count').on('change', handleDuplicateCount);
    
        // Step 4: Content Replacement
        $(document).on('change', '.amplira-content-type', handleContentTypeChange);
        $(document).on('click', '.upload-media', initMediaUploader);
    
        // Step 5: Creation
        $('#start-creation').on('click', startPageCreation);
        $('#go-back').on('click', goBackToPreviousStep);
        $('#view-pages').on('click', viewCreatedPages);
        $('#start-over').on('click', resetProcess);
    }

    function logElementData($element) {
        // console.log('Element:', $element[0]);
        // console.log('data-template:', $element.attr('data-template'));
        // console.log('data-type:', $element.attr('data-type'));
        // console.log('jQuery data:', $element.data());
        // console.log('Classes:', $element.attr('class'));
    }

    // Navigation Functions
    function nextStep() {
        if (!validateCurrentStep()) {
            return;
        }

        if (state.currentStep < 5) {
            state.currentStep++;
            updateUI();
            
            if (state.currentStep === 2 && state.selectedTemplate && !state.hasLoadedPages) {
                loadPagesWithShortcodes();
            }
        } else {
            createPages();
        }
    }

    function previousStep() {
        if (state.currentStep > 1) {
            state.currentStep--;
            updateUI();
        }
    }

    function updateUI() {
        // Hide all step content first
        $('.amplira-step-content').hide();
        
        // Show only current step content
        $(`.amplira-step-content[data-step="${state.currentStep}"]`).show();

        // Clear previous step content if moving backwards
        if (state.currentStep === 1) {
            $('.amplira-page-list').empty();
            state.hasLoadedPages = false;
            state.isLoadingPages = false;
        }

        // Update progress indicators
        $('.amplira-step').removeClass('active completed');
        for (let i = 1; i <= 5; i++) {
            const $step = $(`.amplira-step[data-step="${i}"]`);
            if (i < state.currentStep) {
                $step.addClass('completed');
            } else if (i === state.currentStep) {
                $step.addClass('active');
            }
        }

        updateStepNavigation();
        loadStepContent();
    }

    function updateStepNavigation() {
        const $prev = $('#prev-step');
        const $next = $('#next-step');

        $prev.toggle(state.currentStep > 1);
        $next.text(state.currentStep === 5 ? 'Create Pages' : 'Next');
    }

    function loadStepContent() {
        switch (state.currentStep) {
            case 2:
                if (state.selectedTemplate && !state.hasLoadedPages) {
                    loadPagesWithShortcodes();
                }
                break;
                case 3:
                    if (!state.isLoadingShortcodes && state.duplicateCount) {
                        handleDuplicateCount();
                }
                break;
                case 4:
                    if (state.selectedPage && state.duplicateCount) {
                        $.ajax({
                            url: ampliraData.ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'amplira_analyze_page',
                                nonce: ampliraData.nonce,
                                page_id: state.selectedPage.id
                            },
                            success: function(rawResponse) {
                                try {
                                    let response;
                                    if (typeof rawResponse === 'string') {
                                        try {
                                            response = JSON.parse(rawResponse);
                                        } catch (e) {
                                            throw new Error('Failed to parse JSON response');
                                        }
                                    } else {
                                        response = rawResponse;
                                    }

                                    if (!response || typeof response !== 'object') {
                                        throw new Error('Response is not an object');
                                    }

                                    if (!response.success) {
                                        throw new Error(response.data?.message || 'Server returned error status');
                                    }

                                    if (!response.data || !response.data.shortcodes) {
                                        throw new Error('Response missing shortcodes data');
                                    }

                                    const shortcodesArray = Array.isArray(response.data.shortcodes) 
                                        ? response.data.shortcodes 
                                        : [response.data.shortcodes];

                                    const placeholders = {};
                                    shortcodesArray.forEach(code => {
                                        placeholders[code] = {
                                            type: 'text',
                                            required: true
                                        };
                                    });

                                    renderDuplicateForm(placeholders, state.duplicateCount);
                                } catch (error) {
                                    console.error('Error details:', error);
                                    $('.replacement-forms-container').html(
                                        `<div class="notice notice-error">
                                            <p>Error processing shortcodes: ${error.message}</p>
                                            <p>Please check the console for more details.</p>
                                        </div>`
                                    );
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('AJAX error:', {
                                    status: status,
                                    error: error,
                                    response: xhr.responseText
                                });
                                $('.replacement-forms-container').html(
                                    `<div class="notice notice-error">
                                        <p>Server error: ${error}</p>
                                        <p>Status: ${status}</p>
                                    </div>`
                                );
                            }
                        });
                    } else {
                        $('.replacement-forms-container').html(
                            '<div class="notice notice-warning"><p>Please complete previous steps first.</p></div>'
                        );
                    }
                break;

        }
    }

    //step 4 Preview and Update
    function updateDuplicatePreview() {
        const $preview = $('.preview-list');
        $preview.empty();

        if (!state.duplicateCount) {
            return;
        }

        for (let i = 1; i <= state.duplicateCount; i++) {
            $preview.append(`
                <div class="preview-item">
                    <span class="preview-number">${i}</span>
                    ${state.shortcodes.length ? 
                        `<span class="shortcodes-preview">(${state.shortcodes.join(', ')})</span>` :
                        '<span class="shortcodes-preview">(Content fields will be created in next step)</span>'
                    }
                </div>
            `);
        }
    }

    // Step 5 Functions
    function initializeStep5() {
        updateConfirmationSummary();
        bindStep5Events();
    }

    function bindStep5Events() {
        $('#start-creation').on('click', startPageCreation);
        $('#go-back').on('click', goBackToPreviousStep);
        $('#view-pages').on('click', function(e) {
            if (!state.selectedTemplate) {
                e.preventDefault();
                showError('Template type not selected');
            }
        });
        $('#start-over').on('click', startOver);
    }

    function updateConfirmationSummary() {
        $('#template-type').text(state.selectedTemplate || 'Not selected');
        $('#source-page').text(state.selectedPage?.title || 'Not selected');
        $('#pages-count').text(state.duplicateCount || 0);
        $('#total-pages').text(state.duplicateCount || 0);

        $('.amplira-confirm-creation').show();
        $('.amplira-progress, .amplira-completion-message').hide();
    }

    function startPageCreation() {
        if (state.creationInProgress) {
            console.log('Page creation already in progress');
            return;
        }

        state.creationInProgress = true;
        state.totalPagesCreated = 0;
        state.creationErrors = [];

        $('.amplira-confirm-creation').hide();
        $('.amplira-progress').show();
        $('.amplira-completion-message').hide();

        updateProgressBar(0, state.duplicateCount);
        createPages();
    }

    function createPages() {
        if (!validateCreatePages()) {
            state.creationInProgress = false;
            return;
        }

        const submissionData = {
            action: 'amplira_duplicate_page',
            nonce: ampliraData.nonce,
            template_id: state.selectedPage.id,
            replacements: state.replacements,
            template_type: state.selectedTemplate,
            duplicate_count: state.duplicateCount,
            page_title: state.selectedPage.title
        };

        $.ajax({
            url: ampliraData.ajaxurl,
            type: 'POST',
            data: submissionData,
            beforeSend: function() {
                $('#start-creation, #go-back, #next-step, #prev-step').prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    showCompletionMessage(response.data);
                } else {
                    showError(response.data?.message || 'Error creating pages');
                    $('.amplira-confirm-creation').show();
                    $('.amplira-progress').hide();
                }
            },
            error: function(xhr, status, error) {
                console.error('Page creation error:', { xhr, status, error });
                showError('Server error occurred. Please try again.');
                $('.amplira-confirm-creation').show();
                $('.amplira-progress').hide();
            },
            complete: function() {
                state.creationInProgress = false;
                $('#start-creation, #go-back, #next-step, #prev-step').prop('disabled', false);
            }
        });
    }

    function updateProgressBar(current, total) {
        const percentage = (current / total) * 100;
        $('.amplira-progress-bar').css('width', percentage + '%');
        $('#current-progress').text(current);
        $('#total-pages').text(total);
    }

    function showCompletionMessage(data) {
        $('.amplira-progress').hide();
        $('.amplira-completion-message').show();

        const $message = $('.amplira-success-message');
        
        if (data.errors && data.errors.length > 0) {
            $message.find('h3').text('Pages Created with Some Issues');
            $message.append(`
                <div class="amplira-warnings">
                    <h4>Warnings:</h4>
                    <ul>
                        ${data.errors.map(error => `<li>${error}</li>`).join('')}
                    </ul>
                </div>
            `);
        }

        $('#view-pages').attr('href', `edit.php?post_type=${state.selectedTemplate}`);
    }

    // Template and Page Selection Functions
    function selectTemplate() {
        const $card = $(this);
        const templateType = $card.data('template');
        
        if (!templateType) {
            console.error('Invalid template type');
            return;
        }

        $('.amplira-template-card').removeClass('selected');
        $card.addClass('selected');
        state.selectedTemplate = templateType;
        state.hasLoadedPages = false;
    }

    function loadPagesWithShortcodes() {
        if (state.isLoadingPages) {
            return;
        }
    
        state.isLoadingPages = true;
        const $pageList = $('.amplira-page-list');
        $pageList.html('<div class="amplira-loading">Loading pages...</div>');
    
        $.ajax({
            url: ampliraData.ajaxurl,
            type: 'POST',
            data: {
                action: 'amplira_get_pages_with_shortcodes',
                nonce: ampliraData.nonce,
                type: state.selectedTemplate
            },
            success: function(rawResponse) {
                // Parse the response if it's a string
                let response;
                try {
                    response = typeof rawResponse === 'string' ? JSON.parse(rawResponse) : rawResponse;
                } catch (e) {
                    console.error('Failed to parse response:', rawResponse);
                    $pageList.html('<p class="error">Server response format error.</p>');
                    return;
                }
    
                if (!response.success || !response.data) {
                    console.error('Basic validation failed:', response);
                    $pageList.html('<p class="error">Invalid response from server.</p>');
                    return;
                }
    
                const pages = response.data;
                const pageHtml = pages.map(page => {
                    const shortcodes = Array.isArray(page.shortcodes) ? page.shortcodes : [page.shortcodes];
                    const shortcodeTags = shortcodes
                        .map(code => `<span class="amplira-shortcode-tag">[amp_${code}]</span>`)
                        .join('');
    
                    return `
                        <div class="amplira-page-item" data-page-id="${page.id}">
                            <h3>${page.title}</h3>
                            <div class="amplira-shortcodes">${shortcodeTags}</div>
                        </div>
                    `;
                }).join('');
    
                $pageList.html(pageHtml || '<p>No pages with shortcodes found.</p>');
                state.hasLoadedPages = true;
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX request failed:', textStatus, errorThrown);
                $pageList.html('<p class="error">Failed to load pages.</p>');
            },
            complete: function() {
                state.isLoadingPages = false;
            }
        });
    }
    

    function renderPageList(pages) {
        const $pageList = $('.amplira-page-list');
        
        if (!Array.isArray(pages) || pages.length === 0) {
            $pageList.html('<p>No pages with [amp_*] shortcodes found.</p>');
            return;
        }
    
        try {
            const pageItems = pages.map(page => {
                if (!page.id || !page.title || !Array.isArray(page.shortcodes)) {
                    console.warn('Invalid page data:', page);
                    return '';
                }
    
                const shortcodeTags = page.shortcodes
                    .map(code => `<span class="amplira-shortcode-tag">[amp_${code}]</span>`)
                    .join('');
                
                return `
                    <div class="amplira-page-item" data-page-id="${page.id}">
                        <h3>${page.title}</h3>
                        <div class="amplira-shortcodes">${shortcodeTags}</div>
                    </div>
                `;
            }).join('');
    
            $pageList.html(pageItems);
            console.log('Page list rendered successfully');
        } catch (error) {
            console.error('Error rendering page list:', error);
            $pageList.html('<p class="error">Error rendering pages. Check console for details.</p>');
        }
    }

    function selectPage() {
        const $page = $(this);
        $('.amplira-page-item').removeClass('selected');
        $page.addClass('selected');
        
        const title = $page.find('h3').text();
        const id = $page.data('page-id');
        
        state.selectedPage = {
            id: id,
            title: title
        };
    }

    function handleContentTypeChange() {
        const $field = $(this).closest('.shortcode-field');
        const contentType = $(this).val();
        const $input = $field.find('input[type="text"], textarea');
        
        // Update placeholder
        switch (contentType) {
            case 'html':
                $input.attr('placeholder', 'Enter HTML content');
                break;
            case 'text':
                $input.attr('placeholder', 'Enter text content');
                break;
            case 'image':
                $input.attr('placeholder', 'Select or enter image URL');
                break;
            default:
                $input.attr('placeholder', 'Enter content');
        }
    
        // Update preview if in table row
        const $row = $(this).closest('tr');
        if ($row.length) {
            updatePreview($row);
        }
    
        console.log('Content type changed:', {
            field: $field.data('field'),
            type: contentType
        });
    }

    // Form Handling and Validation Functions
    function validateCurrentStep() {
        switch (state.currentStep) {
            case 1:
                if (!state.selectedTemplate) {
                    showError('Please select a template type.');
                    return false;
                }
                return true;

            case 2:
                if (!state.selectedPage) {
                    showError('Please select a page with shortcodes.');
                    return false;
                }
                return true;

            case 3:
                const count = state.duplicateCount;
                if (isNaN(count) || count < 1 || count > 50) {
                    showError('Please enter a valid number of duplicates (1-50).');
                    return false;
                }
                return true;

            case 4:
                return validateReplacements();

            case 5:
                return validateCreatePages();
        }
        return true;
    }

    function validateCreatePages() {
        if (!state.selectedTemplate || !state.selectedPage) {
            showError('Missing template or page selection.');
            return false;
        }

        if (!state.duplicateCount || state.duplicateCount < 1) {
            showError('Invalid number of pages to create.');
            return false;
        }

        if (state.creationInProgress) {
            showError('Page creation already in progress.');
            return false;
        }

        return true;
    }

    function validateReplacements() {
        // Get all form inputs
        const $inputs = $('.amplira-pages-table tbody input, .amplira-pages-table tbody textarea');
        let isValid = true;
        
        $inputs.each(function() {
            if (!$(this).val().trim()) {
                isValid = false;
                $(this).addClass('error');
            } else {
                $(this).removeClass('error');
            }
        });
    
        if (!isValid) {
            showError('Please fill in all required fields');
            return false;
        }
    
        return true;
    }

    function validateFormData(replacements) {
        if (!Array.isArray(replacements)) {
            console.error('Replacements must be an array');
            return false;
        }

        return replacements.every((pageData, index) => {
            if (!pageData || typeof pageData !== 'object') {
                console.error(`Invalid data for page ${index + 1}`);
                return false;
            }

            return state.shortcodes.every(shortcode => {
                if (!pageData[shortcode]) {
                    console.error(`Missing data for shortcode ${shortcode} in page ${index + 1}`);
                    return false;
                }

                if (shortcode.startsWith('image_')) {
                    return pageData[shortcode].url && pageData[shortcode].id;
                } else {
                    return pageData[shortcode].content && pageData[shortcode].type;
                }
            });
        });
    }

    function handleDuplicateCount() {
        // Add a loading flag to prevent duplicate calls
        if (state.isLoadingShortcodes) {
            return;
        }
    
        const count = parseInt($('#duplicate-count').val(), 10);
        generateRowCount = count;
    
        if (!isNaN(count) && count > 0 && count <= 50) {
            state.duplicateCount = count;
    
            const $preview = $('.preview-list');
            $preview.empty();
    
            if (state.selectedPage && state.selectedPage.id) {
                state.isLoadingShortcodes = true;
                
                // Clear any existing previews
                $preview.html('<div class="loading">Loading shortcodes...</div>');
    
                $.ajax({
                    url: ampliraData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'amplira_analyze_page',
                        nonce: ampliraData.nonce,
                        page_id: state.selectedPage.id
                    },
                    success: function(rawResponse) {
                        try {
                            const response = typeof rawResponse === 'string' ? JSON.parse(rawResponse) : rawResponse;
    
                            $preview.empty(); // Clear loading message
    
                            if (response.success && response.data && response.data.shortcodes) {
                                state.shortcodes = response.data.shortcodes;
                                
                                // Render preview items
                                for (let i = 1; i <= count; i++) {
                                    $preview.append(`
                                        <div class="preview-item">
                                            <div class="page-number">Page ${i}</div>
                                            <div class="shortcodes-list">
                                                ${state.shortcodes.map(code => 
                                                    `<span class="shortcode-tag">[amp_${code}]</span>`
                                                ).join('')}
                                            </div>
                                        </div>
                                    `);
                                }
                            } else {
                                $preview.html('<div class="error">No shortcodes found in template page</div>');
                            }
                        } catch (error) {
                            console.error('Error processing shortcodes:', error);
                            $preview.html('<div class="error">Error processing shortcodes</div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', {xhr, status, error});
                        $preview.html('<div class="error">Failed to load shortcodes</div>');
                    },
                    complete: function() {
                        state.isLoadingShortcodes = false;
                    }
                });
            } else {
                // Basic preview if no page selected
                for (let i = 1; i <= count; i++) {
                    $preview.append(`
                        <div class="preview-item">
                            <div class="page-title">Page ${i}</div>
                            <div class="preview-message">
                                (Select a template page to load shortcodes)
                            </div>
                        </div>
                    `);
                }
            }
    
            // Update feedback message
            const message = count === 1 ? 'Creating 1 page' : `Creating ${count} pages`;
            $('.duplicate-count .amplira-validation-feedback').remove();
            $('.duplicate-count').append(`
                <div class="amplira-validation-feedback success">
                    ${message}
                </div>
            `);
        } else {
            $('#duplicate-count').val(state.duplicateCount || 1);
            showError('Please enter a valid number between 1 and 50');
        }
    }
    
    // Add this helper function for showing errors
    function showError(message) {
        const $error = $('<div class="notice notice-error"><p>' + message + '</p></div>');
        $('.amplira-content').prepend($error);
        setTimeout(() => {
            $error.fadeOut(500, function() {
                $(this).remove();
            });
        }, 3000);
    }

    function renderDuplicateForm(placeholders, duplicateCount) {
        const $container = $('.replacement-forms-container');
        if (!$container.length) {
            console.error('Container not found');
            return;
        }

        // Clear container
        $container.empty();

        // Validate inputs
        if (!placeholders || Object.keys(placeholders).length === 0) {
            $container.html('<div class="notice notice-error">No shortcodes found in template</div>');
            return;
        }
    
        if (!duplicateCount || duplicateCount < 1) {
            $container.html('<div class="notice notice-error">Invalid page count</div>');
            return;
        }
    
        // Sort keys to ensure consistent order
        const sortedKeys = Object.keys(placeholders).sort((a, b) => {
            if (a === 'city') return -1;
            if (b === 'city') return 1;
            return a.localeCompare(b);
        });
    
        // Build table HTML
        let html = `
        <div class="amplira-table-wrapper">
            <table class="amplira-pages-table widefat">
                <thead>
                    <tr>
                        <th>#</th>
                        ${sortedKeys.map(code => `
                            <th>
                                ${code}
                                <div class="column-controls">
                                    <button class="bulk-copy-btn" data-field="${code}">📋</button>
                                    ${code === 'city' ? '<button class="quick-fill-btn">🏙️</button>' : ''}
                                </div>
                            </th>
                        `).join('')}
                        <th>Meta Title</th>
                        <th>Meta Description</th>
                    </tr>
                </thead>
                <tbody>
        `;

        // Add rows
        for (let i = 1; i <= duplicateCount; i++) {
            html += `
                <tr>
                    <td>${i}</td>
                    ${sortedKeys.map(code => `
                        <td>
                            <input type="text" 
                                class="widefat" 
                                name="content[${i}][${code}]" 
                                placeholder="Enter ${code}"
                                data-row="${i}"
                                data-field="${code}">
                        </td>
                    `).join('')}
                    <td>
                        <input type="text" 
                            class="widefat" 
                            name="content[${i}][meta_title]" 
                            placeholder="Meta title"
                            data-row="${i}"
                            data-field="meta_title">
                    </td>
                    <td>
                        <textarea 
                            class="widefat" 
                            name="content[${i}][meta_description]" 
                            placeholder="Meta description"
                            data-row="${i}"
                            data-field="meta_description"></textarea>
                    </td>
                </tr>
            `;
        }

        html += `
                </tbody>
            </table>
        </div>
        <div class="table-actions">
            <button type="button" class="button button-primary" id="validate-content">Validate Content</button>
            <button type="button" class="button" id="clear-content">Clear All</button>
        </div>
        `;

        // Insert table into container
        $container.html(html);

        // Initialize table features
        initializeTableFeatures();

        // Show controls and preview pane
        $('.table-controls, .preview-pane').show();

        // Update initial preview
        updatePreview($('.amplira-pages-table tbody tr:first'));
    }

    // Add this function before initializeTableFeatures
    function enableKeyboardNavigation($table) {
        $table.on('keydown', 'input, textarea', function(e) {
            const $current = $(this);
            const $row = $current.closest('tr');
            const $cell = $current.closest('td');
            
            switch(e.keyCode) {
                case 38: // Up arrow
                    const $prevRow = $row.prev('tr');
                    if ($prevRow.length) {
                        const index = $cell.index();
                        $prevRow.find('td').eq(index).find('input, textarea').focus();
                    }
                    break;
                    
                case 40: // Down arrow
                    const $nextRow = $row.next('tr');
                    if ($nextRow.length) {
                        const index = $cell.index();
                        $nextRow.find('td').eq(index).find('input, textarea').focus();
                    }
                    break;
                    
                case 9: // Tab
                    if (!e.shiftKey && $current.is('input:last-child, textarea:last-child')) {
                        const $nextRow = $row.next('tr');
                        if ($nextRow.length) {
                            e.preventDefault();
                            $nextRow.find('input:first, textarea:first').focus();
                        }
                    }
                    break;
            }
        });
    }

    // Modify initializeTableFeatures to make keyboard navigation optional
    function initializeTableFeatures() {
        const $table = $('.amplira-pages-table');
        if (!$table.length) return;
    
        // Initialize basic table features
        initializeBasicFeatures($table);
    
        // Remove any existing icons from all column headers
        $table.find('th .column-controls').remove();
    
        // Track first row completion for AI
        const $firstRow = $table.find('tbody tr:first');
        let firstRowTimer;
    
        $('#sample-city-name').on('input', debounce(function() {
            console.log('sample city name', $(this).val());
            if ($(this).val().trim()) {
                $('.ai-generate-meta').prop('disabled', false);
                $('.ai-settings').slideDown();
            } else {
                $('.ai-generate-meta').prop('disabled', true);
                $('.ai-settings').slideUp();
            }
        }, 500));
    
        // Bulk edit mode toggle
        $('.bulk-edit-toggle').off('click').on('click', function() {
            const $btn = $(this);
            const isEnabled = $table.hasClass('bulk-edit-mode');
            
            $table.toggleClass('bulk-edit-mode');
            $btn.text(isEnabled ? 'Enable Bulk Edit' : 'Disable Bulk Edit');
            
            if (!isEnabled) {
                $table.find('tbody tr:first').addClass('template-row');
            } else {
                $table.find('tbody tr:first').removeClass('template-row');
            }
        });
    
        // Enhanced right-click context menu
        $table.off('contextmenu', 'input, textarea').on('contextmenu', 'input, textarea', function(e) {
            e.preventDefault();
            const $input = $(this);
            const fieldName = $input.data('field');
            
            $('.context-menu').remove();
            
            let menuItems = [
                { action: 'copy-down', text: 'Copy Down', icon: '↓' },
                { action: 'copy-all', text: 'Copy to All', icon: '⋮' },
                { action: 'clear', text: 'Clear Field', icon: '✕' }
            ];
    
            // Add AI suggestion for meta fields
            if (fieldName === 'meta_title' || fieldName === 'meta_description') {
                menuItems.push({ action: 'ai-suggest', text: 'AI Suggestion', icon: '🤖' });
            }
    
            const menuHtml = `
                <div class="context-menu" style="top: ${e.pageY}px; left: ${e.pageX}px;">
                    ${menuItems.map(item => `
                        <div class="menu-item" data-action="${item.action}">
                            <span class="menu-icon">${item.icon}</span>
                            ${item.text}
                        </div>
                    `).join('')}
                </div>
            `;
    
            $('body').append(menuHtml);
    
            // Handle menu actions
            $('.context-menu .menu-item').on('click', async function() {
                const action = $(this).data('action');
                const value = $input.val();
                const $currentRow = $input.closest('tr');
    
                switch(action) {
                    case 'copy-down':
                        $currentRow.nextAll('tr')
                            .find(`[data-field="${fieldName}"]`)
                            .val(value)
                            .trigger('change');
                        break;
    
                    case 'copy-all':
                        $table.find(`[data-field="${fieldName}"]`)
                            .val(value)
                            .trigger('change');
                        break;
    
                    case 'clear':
                        $input.val('').trigger('change');
                        break;
    
                    case 'ai-suggest':
                        try {
                            const city = $currentRow.find('[data-field="city"]').val();
                            if (!city) {
                                showError('Please enter a city first');
                                break;
                            }
    
                            const suggestion = await getAISuggestion(
                                state.selectedPage?.title || '',
                                city,
                                fieldName === 'meta_title'
                            );
    
                            $input.val(suggestion).trigger('change');
                            updatePreview($currentRow);
                        } catch (error) {
                            showError('Failed to get AI suggestion');
                        }
                        break;
                }
                
                $('.context-menu').remove();
            });
        });
    
        // AI Generation Button
        $('.ai-generate-meta').on('click', async function() {
            const $btn = $(this).prop('disabled', true);
            const $overlay = $('<div class="ai-loading-overlay">Generating AI content...</div>');
            
            try {
                $table.parent().append($overlay);
                
                // Get template data from first row
                const templateData = {
                    city: $firstRow.find('[data-field="city"]').val(),
                    metaTitle: $firstRow.find('[data-field="meta_title"]').val(),
                    metaDesc: $firstRow.find('[data-field="meta_description"]').val(),
                    pageTitle: state.selectedPage?.title || '',
                    useUnique: $('#ai-unique-content').is(':checked'),
                    useSEO: $('#ai-seo-optimize').is(':checked')
                };

                // Process all other rows
                const $otherRows = $table.find('tbody tr');
                let completed = 0;
    
                const sampleCityName = $('#sample-city-name').val();
                const citites = await findNearByCities(sampleCityName, generateRowCount);
                citites.forEach(function(cityObj, index) {
                    $($otherRows[index]).find('[data-field="city"]')
                        .val(cityObj.city)
                        .trigger('change')
                        .addClass('ai-generated');
                });

                for (const row of $otherRows) {
                    const $row = $(row);
                    const cityInput = $row.find('[data-field="city"]');
                    const cityValue = cityInput.val();
                    
                    try {
                        // Generate and set meta title
                        const title = await getAISuggestion(templateData.pageTitle, cityValue, true);
                        $row.find('[data-field="meta_title"]')
                            .val(title)
                            .trigger('change')
                            .addClass('ai-generated');
    
                        // Generate and set meta description
                        const desc = await getAISuggestion(templateData.pageTitle, cityValue, false);
                        $row.find('[data-field="meta_description"]')
                            .val(desc)
                            .trigger('change')
                            .addClass('ai-generated');
    
                        completed++;
                        updateProgressInOverlay($overlay, completed, $otherRows.length);
                    } catch (error) {
                        console.error('AI generation error:', error);
                        $row.addClass('ai-error');
                    }
    
                    // Small delay between rows
                    await new Promise(resolve => setTimeout(resolve, 500));
                }
    
            } catch (error) {
                showError('Error generating content: ' + error.message);
            } finally {
                $btn.prop('disabled', false);
                $overlay.remove();
                $('.ai-generated').removeClass('ai-generated');
            }
        });
    
        // Close context menu on outside click
        $(document).on('click', function() {
            $('.context-menu').remove();
        });
    
        // Enhanced keyboard navigation
        $table.off('keydown', 'input, textarea').on('keydown', function(e) {
            const $current = $(e.target);
            const $row = $current.closest('tr');
            const $cell = $current.closest('td');
            
            switch(e.key) {
                case 'Enter':
                    e.preventDefault();
                    const $nextRow = $row.next('tr');
                    if ($nextRow.length) {
                        const $nextField = $nextRow.find(`[data-field="${$current.data('field')}"]`);
                        if ($nextField.length) $nextField.focus();
                    }
                    break;
    
                case 'Tab':
                    if (!e.shiftKey && $current.is('textarea') && e.target.value.length === 0) {
                        e.preventDefault();
                        const $nextRow = $row.next('tr');
                        if ($nextRow.length) {
                            $nextRow.find('input:first').focus();
                        }
                    }
                    break;
            }
        });
    }

    function validateFirstRow($row) {
        let isValid = true;
        $row.find('input[type="text"], textarea').each(function() {
            if (!$(this).val().trim()) {
                isValid = false;
                return false;
            }
        });
        
        // Make sure AI button reflects validation state
        $('.ai-generate-meta').prop('disabled', !isValid);
        return isValid;
    }
    
    function updateProgressInOverlay($overlay, current, total) {
        const percent = Math.round((current / total) * 100);
        $overlay.html(`
            Generating AI content... (${current}/${total})<br>
            <div class="progress-bar">
                <div class="progress" style="width: ${percent}%"></div>
            </div>
        `);
    }

    // Add this helper function to handle basic features
    function initializeBasicFeatures($table) {
        // Add bulk edit toggle button above table
        $table.before(`
            <div class="table-controls">
                <button type="button" class="button" id="toggle-bulk-edit">
                    Enable Bulk Edit Mode
                </button>
                <button type="button" class="button" id="apply-template" style="display: none;">
                    Apply Template Row
                </button>
            </div>

            
        `);

        // Bulk edit mode toggle
        $('#toggle-bulk-edit').on('click', function() {
            const $btn = $(this);
            const isEnabled = $table.hasClass('bulk-edit-mode');
            
            $table.toggleClass('bulk-edit-mode');
            $('#apply-template').toggle(!isEnabled);
            $btn.text(isEnabled ? 'Enable Bulk Edit Mode' : 'Disable Bulk Edit Mode');
            
            if (!isEnabled) {
                $table.find('tbody tr:first').addClass('template-row');
            } else {
                $table.find('tbody tr:first').removeClass('template-row');
            }
        });

        // Apply template row
        $('#apply-template').on('click', function() {
            const $templateRow = $table.find('tbody tr:first');
            const $otherRows = $table.find('tbody tr:not(:first)');
            
            $templateRow.find('input, textarea').each(function() {
                const fieldName = $(this).data('field');
                const templateValue = $(this).val();
                
                $otherRows.each(function() {
                    $(this).find(`[data-field="${fieldName}"]`).val(templateValue).trigger('input');
                });
            });
        });

        // Column quick-copy buttons
        $('.bulk-copy-btn').on('click', function() {
            const fieldName = $(this).data('field');
            const $firstInput = $table.find(`tbody tr:first [data-field="${fieldName}"]`);
            const value = $firstInput.val();

            $table.find(`tbody tr:not(:first) [data-field="${fieldName}"]`).each(function() {
                $(this).val(value).trigger('input');
            });
        });

        // Add copy/paste functionality for each cell
        $table.on('focus', 'input, textarea', function() {
            $(this).select(); // Select all text on focus
        });

        // Add keyboard shortcuts
        $table.on('keydown', 'input, textarea', function(e) {
            // Ctrl+C or Cmd+C
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 67) {
                // Default copy behavior
                return true;
            }
            
            // Ctrl+V or Cmd+V
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 86) {
                // Default paste behavior
                return true;
            }

            // Enter key - move to next row
            if (e.keyCode === 13) {
                e.preventDefault();
                const $nextRow = $(this).closest('tr').next('tr');
                if ($nextRow.length) {
                    $nextRow.find(`[data-field="${$(this).data('field')}"]`).focus();
                }
            }
        });

        // Add right-click context menu
        $table.on('contextmenu', 'input, textarea', function(e) {
            e.preventDefault();
            const $input = $(this);
            
            // Remove any existing context menus
            $('.context-menu').remove();
            
            // Create context menu
            const $menu = $(`
                <div class="context-menu">
                    <div class="menu-item copy-down">Copy Down</div>
                    <div class="menu-item copy-all">Copy to All</div>
                    <div class="menu-item clear-field">Clear Field</div>
                </div>
            `).css({
                top: e.pageY,
                left: e.pageX
            });
            
            // Add menu to body
            $('body').append($menu);
            
            // Handle menu item clicks
            $('.copy-down').on('click', function() {
                const value = $input.val();
                const fieldName = $input.data('field');
                const $currentRow = $input.closest('tr');
                $currentRow.nextAll('tr').find(`[data-field="${fieldName}"]`).val(value).trigger('input');
                $menu.remove();
            });
            
            $('.copy-all').on('click', function() {
                const value = $input.val();
                const fieldName = $input.data('field');
                $table.find(`[data-field="${fieldName}"]`).val(value).trigger('input');
                $menu.remove();
            });
            
            $('.clear-field').on('click', function() {
                $input.val('').trigger('input');
                $menu.remove();
            });
            
            // Remove menu when clicking outside
            $(document).on('click', function() {
                $menu.remove();
            });
        });
    }

    function updatePreview($row) {
        const title = $row.find('[data-field="meta_title"]').val() || 'Title';
        const description = $row.find('[data-field="meta_description"]').val() || 'Description';
        const city = $row.find('[data-field="city"]').val() || 'City';
        
        $('.preview-title').text(title);
        $('.preview-url').text(`yourwebsite.com/${city.toLowerCase().replace(/\s+/g, '-')}`);
        $('.preview-description').text(description);
    }

    // Utility Functions
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    function showError(message) {
        alert(message); // Could be replaced with a more sophisticated error display
    }

    function resetProcess() {
        state.currentStep = 1;
        state.selectedTemplate = null;
        state.selectedPage = null;
        state.duplicateCount = 1;
        state.shortcodes = [];
        state.replacements = [];
        state.hasLoadedPages = false;
        state.isLoadingPages = false;
        state.creationInProgress = false;
        updateUI();
    }

    function startOver() {
        if (state.creationInProgress) {
            if (!confirm('Page creation is in progress. Are you sure you want to start over?')) {
                return;
            }
        }
        resetProcess();
    }

    function goBackToPreviousStep() {
        if (!state.creationInProgress) {
            state.currentStep = Math.max(1, state.currentStep - 1);
            updateUI();
        }
    }

    function viewCreatedPages() {
        if (state.selectedTemplate) {
            window.location.href = `edit.php?post_type=${state.selectedTemplate}`;
        } else {
            showError('No template type selected');
        }
    }


    function filterPages(event) {
        const searchTerm = $(this).val().toLowerCase();
        $('.amplira-page-item').each(function() {
            const title = $(this).find('h3').text().toLowerCase();
            const shortcodes = $(this).find('.amplira-shortcode-tag').text().toLowerCase();
            $(this).toggle(title.includes(searchTerm) || shortcodes.includes(searchTerm));
        });
    }

    function validateDuplicateCount(count) {
        return !isNaN(count) && count > 0 && count <= 50;
    }

    function findNearByCities(city, count) {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: ampliraData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'amplira_find_nearby_cities',
                    city,
                    count
                },
                success: function(response) {
                    if (response.success) {
                        resolve(response.data);
                    } else {
                        reject(response.data);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.log('error', jqXHR, textStatus, errorThrown);
                    reject(errorThrown);
                }
            });
        });
    }

    // AI Integration Functions
    function getAISuggestion(pageTitle, city, isMetaTitle) {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: ampliraData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'amplira_get_ai_suggestion',
                    page_title: pageTitle,
                    city: city,
                    is_meta_title: isMetaTitle
                },
                success: function(response) {
                    if (response.success) {
                        resolve(response.data);
                    } else {
                        reject(response.data);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    reject(errorThrown);
                }
            });
        });
    }

    // Media Handling Functions
    function initMediaUploader() {
        const $field = $(this).closest('.shortcode-field');
        const mediaUploader = wp.media({
            title: 'Select Media',
            button: { text: 'Use this media' },
            multiple: false
        });

        mediaUploader.on('select', function() {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            $field.find('.media-preview').html(`<img src="${attachment.url}" alt="Preview">`);
            $field.find('.media-url').val(attachment.url);
            $field.find('.media-id').val(attachment.id);
        });

        mediaUploader.open();
    }

    function initializeMediaFields() {
        if (typeof wp !== 'undefined' && wp.media && wp.media.editor) {
            $('.media-field').each(function() {
                const $field = $(this);
                const $preview = $field.find('.media-preview');
                const $urlInput = $field.find('.media-url');

                if ($urlInput.val()) {
                    $preview.html(`<img src="${$urlInput.val()}" alt="Preview">`);
                }
            });
        }
    }

    initializeAIButton();
    
    // Add this to your initialization code
    function initializeAIButton() {
        const $firstRow = $('.amplira-pages-table tbody tr:first');
        const $aiButton = $('.ai-generate-meta');
        
        // Initially disable the button
        $aiButton.prop('disabled', true);
        
        // Check first row completion on input
        $firstRow.find('input, textarea').on('input', debounce(function() {
            const isFirstRowComplete = validateFirstRow($firstRow);
            $aiButton.prop('disabled', !isFirstRowComplete);
            
            if (isFirstRowComplete) {
                $('.ai-settings').slideDown();
            } else {
                $('.ai-settings').slideUp();
            }
        }, 500));
    }

})(jQuery);