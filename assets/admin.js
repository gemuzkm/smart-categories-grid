jQuery(document).ready(function($) {
    // Media Uploader
    $('.scg-upload-image').on('click', function(e) {
        e.preventDefault();
        const button = $(this);
        
        const frame = wp.media({
            title: scg_admin.i18n.upload_title,
            button: { text: scg_admin.i18n.use_image },
            multiple: false
        });
        
        frame.on('select', function() {
            const attachment = frame.state().get('selection').first().toJSON();
            button.siblings('input').val(attachment.url).trigger('change');
        });
        
        frame.open();
    });

    // Clear Cache
    $('#scg-clear-cache').on('click', function(e) {
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
            beforeSend: function() {
                button.prop('disabled', true).text(scg_admin.i18n.clearing);
            },
            success: function(response) {
                showAdminNotice(response.success ? 'success' : 'error', response.data.message);
            },
            error: function() {
                showAdminNotice('error', scg_admin.i18n.error);
            },
            complete: function() {
                button.prop('disabled', false).text(scg_admin.i18n.clear_cache);
            }
        });
    });

    // Show Admin Notices
    function showAdminNotice(type, message) {
        const notice = $(
            '<div class="notice notice-' + type + ' is-dismissible">' +
                '<p>' + message + '</p>' +
            '</div>'
        );
        
        $('.scg-settings-wrap h1').after(notice);
        
        setTimeout(() => {
            notice.fadeOut(500, () => notice.remove());
        }, 5000);
    }
});