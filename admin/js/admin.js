jQuery(document).ready(function($) {

    /* ---- Quick Toggle (Dashboard) ---- */
    $(document).on('click', '.tp-quick-toggle', function() {
        var $btn    = $(this);
        var postId  = $btn.data('post');
        var action  = $btn.data('action');

        $btn.prop('disabled', true).css('opacity', .5);

        $.post(TP_ADMIN.ajax_url, {
            action: action,
            post_id: postId,
            nonce: TP_ADMIN.nonce
        }, function(res) {
            if ( res.success ) {
                var isActive = res.data.value === '1';
                $btn.toggleClass('active', isActive);
                if ( action === 'tp_toggle_slider' ) {
                    $btn.text( isActive ? '✓' : '○' );
                } else {
                    $btn.text( isActive ? '⭐' : '☆' );
                }
            }
        }).always(function() {
            $btn.prop('disabled', false).css('opacity', 1);
        });
    });

    /* ---- Delete confirmation (Dashboard table) ---- */
    $(document).on('click', '.tp-table-delete', function(e) {
        var msg = $(this).data('confirm') || '¿Estás seguro?';
        if ( ! confirm(msg) ) {
            e.preventDefault();
        }
    });

    /* ---- Copy shortcodes to clipboard ---- */
    function tpCopyText(text) {
        if ( navigator.clipboard && navigator.clipboard.writeText ) {
            try {
                return navigator.clipboard.writeText(text);
            } catch (err) {
                // Fall through to the legacy copy path.
            }
        }

        return new Promise(function(resolve, reject) {
            var $temp = $('<textarea>').val(text).css({
                position: 'fixed',
                top: '-1000px',
                opacity: 0
            }).appendTo('body');

            $temp[0].select();

            try {
                document.execCommand('copy');
                resolve();
            } catch (err) {
                reject(err);
            }

            $temp.remove();
        });
    }

    function tpAttachCopyButton($container, text, small) {
        if ( ! text || $container.find('.tp-copy-code').length ) {
            return;
        }

        var $btn = $('<button type="button" class="tp-copy-code' + (small ? ' tp-copy-code-sm' : '') + '" aria-label="Copiar shortcode">Copiar</button>');

        $btn.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            tpCopyText(text).then(function() {
                $btn.addClass('is-copied').text('¡Copiado!');
                setTimeout(function() {
                    $btn.removeClass('is-copied').text('Copiar');
                }, 1600);
            }).catch(function() {
                $btn.text('Error');
                setTimeout(function() {
                    $btn.text('Copiar');
                }, 1600);
            });
        });

        $container.append($btn);
    }

    $('.tp-code-block').each(function() {
        tpAttachCopyButton($(this), $(this).find('code').first().text(), false);
    });

    $('.tp-shortcode-item').each(function() {
        tpAttachCopyButton($(this), $(this).find('code').first().text(), true);
    });

});
