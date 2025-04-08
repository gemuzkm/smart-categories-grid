/**
 * Smart Categories Grid - Admin JavaScript
 * Handles admin interface interactions including media uploads and cache management
 */
jQuery(document).ready(function($) {
    // Constants for commonly used selectors and values
    const SELECTORS = {
        UPLOAD_BTN: '.scg-upload-image',
        CLEAR_CACHE_BTN: '#scg-clear-cache',
        NOTICE: '.notice.scg-notice',
        SETTINGS_WRAP: '.scg-settings-wrap h1'
    };

    const NOTICE_TIMEOUT = 5000;
    const FADE_DURATION = 500;

    /**
     * Initialize all event handlers
     * @returns {void}
     */
    const init = () => {
        // Remove existing handlers before adding new ones to prevent duplicates
        $(SELECTORS.UPLOAD_BTN).off('click').on('click', handleMediaUpload);
        $(SELECTORS.CLEAR_CACHE_BTN).off('click').on('click', handleClearCache);
    };

    /**
     * Handle WordPress media uploader for image selection
     * @param {Event} e - Click event object
     * @returns {void}
     */
    const handleMediaUpload = function(e) {
        e.preventDefault();
        const button = $(this);
        
        // Configure and open WordPress media frame
        const frame = wp.media({
            title: scg_admin.i18n.upload_title,
            button: { text: scg_admin.i18n.use_image },
            multiple: false, // Allow only single image selection
            library: { type: 'image' } // Show only images in media library
        });
        
        // Handle image selection
        frame.on('select', () => {
            const attachment = frame.state().get('selection').first().toJSON();
            updateImageField(button, attachment.url);
        });
        
        frame.open();
    };

    /**
     * Update image field with selected URL
     * @param {jQuery} button - Upload button element
     * @param {string} url - Selected image URL
     */
    const updateImageField = (button, url) => {
        button.siblings('input')
             .val(url)
             .trigger('change');
    };

    /**
     * Handle cache clearing functionality
     * @param {Event} e - Click event object
     * @returns {void}
     */
    const handleClearCache = function(e) {
        e.preventDefault();
        const button = $(this);
        
        if (!confirm(scg_admin.i18n.clear_confirm)) return;
        
        sendClearCacheRequest(button);
    };

    /**
     * Send AJAX request to clear cache
     * @param {jQuery} button - Clear cache button element
     */
    const sendClearCacheRequest = (button) => {
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'scg_clear_cache',
                nonce: scg_admin.nonce
            },
            beforeSend: () => {
                toggleButtonState(button, true, scg_admin.i18n.clearing);
            },
            success: (response) => {
                const type = response.success ? 'success' : 'error';
                showAdminNotice(type, response.data.message);
            },
            error: () => {
                showAdminNotice('error', scg_admin.i18n.clear_failed);
            },
            complete: () => {
                toggleButtonState(button, false, scg_admin.i18n.clear_cache);
            }
        });
    };

    /**
     * Toggle button state and text
     * @param {jQuery} button - Button element
     * @param {boolean} disabled - Whether to disable the button
     * @param {string} text - Button text
     */
    const toggleButtonState = (button, disabled, text) => {
        button.prop('disabled', disabled)
              .text(text);
    };

    /**
     * Display admin notice message
     * @param {string} type - Notice type ('success' or 'error')
     * @param {string} message - Notice message
     */
    const showAdminNotice = (type, message) => {
        // Remove existing notices
        $(SELECTORS.NOTICE).remove();
        
        // Create new notice
        const notice = $(`
            <div class="notice notice-${type} scg-notice is-dismissible">
                <p>${message}</p>
            </div>
        `);
        
        // Insert notice after settings title
        $(SELECTORS.SETTINGS_WRAP).after(notice);
        
        // Auto-remove notice after timeout
        setTimeout(() => {
            notice.fadeOut(FADE_DURATION, () => notice.remove());
        }, NOTICE_TIMEOUT);
    };

    // Initialize on page load
    init();
    
    // Re-initialize after AJAX completions
    $(document).ajaxComplete(() => init());
});