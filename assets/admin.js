(function($) {
    'use strict';
    
    const SCGAdmin = {
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            // Используем делегирование событий для лучшей производительности
            $(document)
                .off('click', '.scg-upload-image')
                .on('click', '.scg-upload-image', this.handleMediaUpload.bind(this))
                .off('click', '#scg-clear-cache')
                .on('click', '#scg-clear-cache', this.handleClearCache.bind(this));
        },
        
        handleMediaUpload: function(e) {
            e.preventDefault();
            const button = $(e.currentTarget);
            
            // Переиспользуем frame если он уже существует
            if (!this.mediaFrame) {
                this.mediaFrame = wp.media({
                    title: scg_admin.i18n.upload_title,
                    button: { text: scg_admin.i18n.use_image },
                    multiple: false,
                    library: { type: 'image' }
                });
                
                this.mediaFrame.on('select', function() {
                    const attachment = this.mediaFrame.state().get('selection').first().toJSON();
                    button.siblings('input[type="url"]').val(attachment.url).trigger('change');
                }.bind(this));
            }
            
            this.mediaFrame.open();
        },
        
        handleClearCache: function(e) {
            e.preventDefault();
            const button = $(e.currentTarget);
            
            if (!confirm(scg_admin.i18n.clear_confirm)) {
                return;
            }
            
            const originalText = button.text();
            
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
                    SCGAdmin.showAdminNotice(
                        response.success ? 'success' : 'error',
                        response.data ? response.data.message : scg_admin.i18n.clear_failed
                    );
                },
                error: function() {
                    SCGAdmin.showAdminNotice('error', scg_admin.i18n.clear_failed);
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        },
        
        showAdminNotice: function(type, message) {
            $('.notice.scg-notice').remove();
            
            const notice = $('<div>', {
                'class': 'notice notice-' + type + ' scg-notice is-dismissible',
                html: '<p>' + this.escapeHtml(message) + '</p>'
            });
            
            $('.scg-settings-wrap h1').first().after(notice);
            
            // Автоматическое скрытие через 5 секунд
            setTimeout(function() {
                notice.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    };
    
    $(document).ready(function() {
        SCGAdmin.init();
    });
    
    // Переинициализация после AJAX операций
    $(document).ajaxComplete(function() {
        SCGAdmin.bindEvents();
    });
    
})(jQuery);