jQuery(document).ready(function($) {
    const init = () => {
        // Media Uploader
        $('.scg-upload-image').off('click').on('click', handleMediaUpload);
        
        // Clear Cache
        $('#scg-clear-cache').off('click').on('click', handleClearCache);
    };

    const handleMediaUpload = function(e) {
        e.preventDefault();
        const button = $(this);
        
        const frame = wp.media({
            title: scg_admin.i18n.upload_title,
            button: { text: scg_admin.i18n.use_image },
            multiple: false
        });
        
        frame.on('select', () => {
            const attachment = frame.state().get('selection').first().toJSON();
            button.siblings('input').val(attachment.url).trigger('change');
        });
        
        frame.open();
    };

    const handleClearCache = function(e) {
        e.preventDefault();
        const button = $(this);
        
        if(!confirm(scg_admin.i18n.clear_confirm)) return;
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'scg_clear_cache',
                nonce: scg_admin.nonce
            },
            beforeSend: () => {
                button.prop('disabled', true).text(scg_admin.i18n.clearing);
            },
            success: (response) => {
                showAdminNotice(response.success ? 'success' : 'error', response.data.message);
            },
            error: () => {
                showAdminNotice('error', scg_admin.i18n.clear_failed);
            },
            complete: () => {
                button.prop('disabled', false).text(scg_admin.i18n.clear_cache);
            }
        });
    };

    const showAdminNotice = (type, message) => {
        $('.notice.scg-notice').remove();
        
        const notice = $(
            `<div class="notice notice-${type} scg-notice is-dismissible">
                <p>${message}</p>
            </div>`
        );
        
        $('.scg-settings-wrap h1').after(notice);
        
        setTimeout(() => {
            notice.fadeOut(500, () => notice.remove());
        }, 5000);
    };

    // Initialization
    init();
    
    // Re-init after AJAX
    $(document).ajaxComplete(() => init());
});