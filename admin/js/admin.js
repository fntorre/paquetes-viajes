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

    /* ---- Bulk package actions ---- */
    $(document).on('change', '#tp-select-all-packages', function() {
        $('.tp-package-select-checkbox').prop('checked', this.checked);
        $('#tp-bulk-packages-form').toggleClass('has-selection', this.checked);
        $('#tp-bulk-selected-count').text(this.checked ? $('.tp-package-select-checkbox').length + ' seleccionados' : '0 seleccionados');
    });
    $(document).on('change', '.tp-package-select-checkbox', function() {
        var total = $('.tp-package-select-checkbox').length;
        var checked = $('.tp-package-select-checkbox:checked').length;
        $('#tp-select-all-packages').prop('checked', total > 0 && total === checked);
        $('#tp-bulk-packages-form').toggleClass('has-selection', checked > 0);
        $('#tp-bulk-selected-count').text(checked + ' seleccionados');
    });
    $(document).on('submit', '#tp-bulk-packages-form', function(e) {
        var count = $('.tp-package-select-checkbox:checked').length;
        var action = $('#tp-bulk-action').val();
        if ( ! count || ! action ) {
            e.preventDefault();
            window.alert('Seleccioná al menos un paquete y una acción.');
            return false;
        }
        if ( ! window.confirm('¿Aplicar esta acción a ' + count + ' paquete(s)?') ) {
            e.preventDefault();
            return false;
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
