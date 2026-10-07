/* global jQuery, wp, ajaxurl, scg_admin */
(function ($) {
    'use strict';

    const SCGAdmin = {
        init: function () {
            if ($.fn.wpColorPicker) {
                $('.scg-color-picker').wpColorPicker();
            }
            $(document)
                .on('click', '.scg-upload-image', this.handleMediaUpload)
                .on('click', '#scg-clear-cache', this.handleClearCache);
        },

        // New wp.media frame per click so the "select" callback always targets the right input.
        handleMediaUpload: function (e) {
            e.preventDefault();
            if (typeof wp === 'undefined' || !wp.media) {
                return;
            }
            const $target = $(this).siblings('input[type="url"]');

            const frame = wp.media({
                title: scg_admin.i18n.upload_title,
                button: { text: scg_admin.i18n.use_image },
                multiple: false,
                library: { type: 'image' }
            });

            frame.on('select', function () {
                const att = frame.state().get('selection').first().toJSON();
                $target.val(att.url).trigger('change');
            });

            frame.open();
        },

        handleClearCache: function (e) {
            e.preventDefault();
            const $btn = $(this);
            if ($btn.prop('disabled') || !window.confirm(scg_admin.i18n.clear_confirm)) {
                return;
            }
            const original = $btn.text();
            const url = scg_admin.ajax_url || ajaxurl;

            $.ajax({
                url: url,
                method: 'POST',
                dataType: 'json',
                data: { action: 'scg_clear_cache', nonce: scg_admin.nonce },
                beforeSend: function () {
                    $btn.prop('disabled', true).attr('aria-busy', 'true').text(scg_admin.i18n.clearing);
                },
                success: function (response) {
                    const ok  = !!(response && response.success);
                    const msg = (response && response.data && response.data.message) ? response.data.message : scg_admin.i18n.clear_failed;
                    SCGAdmin.notice(ok ? 'success' : 'error', msg);
                },
                error: function () {
                    SCGAdmin.notice('error', scg_admin.i18n.clear_failed);
                },
                complete: function () {
                    $btn.prop('disabled', false).removeAttr('aria-busy').text(original);
                }
            });
        },

        notice: function (type, msg) {
            $('.notice.scg-notice').remove();
            const $n = $('<div>', {
                'class': 'notice notice-' + type + ' scg-notice is-dismissible',
                'role': type === 'error' ? 'alert' : 'status'
            }).append($('<p>').text(msg));
            $('.scg-settings-wrap h1').first().after($n);
            if (wp && wp.a11y && wp.a11y.speak) {
                wp.a11y.speak(msg);
            }
            setTimeout(function () {
                $n.fadeOut(500, function () { $(this).remove(); });
            }, 5000);
        }
    };

    $(SCGAdmin.init.bind(SCGAdmin));
})(jQuery);
