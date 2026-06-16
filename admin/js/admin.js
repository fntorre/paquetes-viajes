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

});
