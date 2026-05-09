(function($) {
    'use strict';

    const SCGAdmin = {
        init: function() {
            // FIX #13: event delegation only — removed ajaxComplete rebind loop
            $(document)
                .on('click', '.scg-upload-image', this.handleMediaUpload)
                .on('click', '#scg-clear-cache', this.handleClearCache);
        },

        // FIX #12: new wp.media frame per click — prevents closure capturing wrong input
        handleMediaUpload: function(e) {
            e.preventDefault();
            const $button = $(this);
            const $target = $button.siblings('input[type="url"]');

            const frame = wp.media({
                title: scg_admin.i18n.upload_title,
                button: { text: scg_admin.i18n.use_image },
                multiple: false,
                library: { type: 'image' }
            });

            frame.on('select', function() {
                const att = frame.state().get('selection').first().toJSON();
                $target.val(att.url).trigger('change');
            });

            frame.open();
        },

        handleClearCache: function(e) {
            e.preventDefault();
            const $btn = $(this);

            if (!confirm(scg_admin.i18n.clear_confirm)) {
                return;
            }

            const original = $btn.text();

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'scg_clear_cache',
                    nonce: scg_admin.nonce
                },
                beforeSend: function() {
                    $btn.prop('disabled', true).text(scg_admin.i18n.clearing);
                },
                success: function(response) {
                    SCGAdmin.notice(
                        response.success ? 'success' : 'error',
                        (response.data && response.data.message) ? response.data.message : scg_admin.i18n.clear_failed
                    );
                },
                error: function() {
                    SCGAdmin.notice('error', scg_admin.i18n.clear_failed);
                },
                complete: function() {
                    $btn.prop('disabled', false).text(original);
                }
            });
        },

        notice: function(type, msg) {
            $('.notice.scg-notice').remove();
            const $n = $('<div>', {
                'class': 'notice notice-' + type + ' scg-notice is-dismissible'
            }).append($('<p>').text(msg));
            $('.scg-settings-wrap h1').first().after($n);
            setTimeout(function() {
                $n.fadeOut(500, function() { $(this).remove(); });
            }, 5000);
        }
    };

    $(document).ready(function() {
        SCGAdmin.init();
    });

})(jQuery);
