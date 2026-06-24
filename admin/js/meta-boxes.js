jQuery(document).ready(function($) {

    /* ============================================
       CHAR COUNTER for Short Description
    ============================================ */
    $('#tp_short_desc').on('input', function() {
        $('#tp-short-count').text( $(this).val().length );
    });

    /* ============================================
       GALLERY
    ============================================ */
    var galleryFrame;

    $('#tp-add-gallery').on('click', function(e) {
        e.preventDefault();

        if ( galleryFrame ) {
            galleryFrame.open();
            return;
        }

        galleryFrame = wp.media({
            title:    TP_META.select_images,
            button:   { text: TP_META.add_images },
            library:  { type: 'image' },
            multiple: true
        });

        galleryFrame.on('select', function() {
            var selection = galleryFrame.state().get('selection');
            var ids = [];

            // Get existing IDs
            var existing = $('#tp_gallery_ids').val();
            if (existing) ids = existing.split(',').map(Number).filter(Boolean);

            selection.each(function(attachment) {
                var a = attachment.toJSON();
                if ( ids.indexOf(a.id) === -1 ) {
                    ids.push(a.id);
                    var thumb = a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url;
                    $('#tp-gallery-preview').append(
                        '<div class="tp-gallery-item" data-id="' + a.id + '">' +
                        '<img src="' + thumb + '">' +
                        '<button type="button" class="tp-gallery-remove" title="Eliminar">✕</button>' +
                        '</div>'
                    );
                }
            });

            $('#tp_gallery_ids').val(ids.join(','));
        });

        galleryFrame.open();
    });

    // Remove gallery item
    $(document).on('click', '.tp-gallery-remove', function() {
        var $item = $(this).closest('.tp-gallery-item');
        var id    = $item.data('id');
        var ids   = $('#tp_gallery_ids').val().split(',').filter(function(v) { return parseInt(v) !== id; });
        $('#tp_gallery_ids').val(ids.join(','));
        $item.remove();
    });

    // Drag & drop reorder gallery
    var dragSrc = null;
    $(document).on('dragstart', '.tp-gallery-item', function(e) {
        dragSrc = this;
        $(this).addClass('tp-dragging');
        e.originalEvent.dataTransfer.effectAllowed = 'move';
    });
    $(document).on('dragend', '.tp-gallery-item', function() {
        $(this).removeClass('tp-dragging');
        $('.tp-gallery-item').removeClass('tp-drag-over');
        updateGalleryOrder();
    });
    $(document).on('dragover', '.tp-gallery-item', function(e) {
        e.preventDefault();
        e.originalEvent.dataTransfer.dropEffect = 'move';
        $(this).addClass('tp-drag-over');
    });
    $(document).on('dragleave', '.tp-gallery-item', function() {
        $(this).removeClass('tp-drag-over');
    });
    $(document).on('drop', '.tp-gallery-item', function(e) {
        e.preventDefault();
        if ( dragSrc !== this ) {
            $(dragSrc).insertBefore($(this));
        }
        $(this).removeClass('tp-drag-over');
    });
    $('.tp-gallery-item').prop('draggable', true);
    $(document).on('DOMNodeInserted', '.tp-gallery-item', function() {
        $(this).prop('draggable', true);
    });

    function updateGalleryOrder() {
        var ids = [];
        $('.tp-gallery-item').each(function() {
            ids.push( $(this).data('id') );
        });
        $('#tp_gallery_ids').val(ids.join(','));
    }

    /* ============================================
       FEATURED IMAGE (meta box)
    ============================================ */
    var featuredMetaFrame;
    $(document).on('click', '#tp-set-featured', function(e) {
        e.preventDefault();
        if ( featuredMetaFrame ) { featuredMetaFrame.open(); return; }
        featuredMetaFrame = wp.media({
            title: TP_META.select_image,
            button: { text: TP_META.use_image },
            library: { type: 'image' },
            multiple: false
        });
        featuredMetaFrame.on('select', function() {
            var sel = featuredMetaFrame.state().get('selection').first().toJSON();
            var thumb = sel.sizes && sel.sizes.medium ? sel.sizes.medium.url : sel.url;
            $('#tp-featured-preview').html('<img src="'+thumb+'" style="max-width:220px;border-radius:8px;display:block;">');
            $('#tp_featured_image').val(sel.id);
        });
        featuredMetaFrame.open();
    });

    $(document).on('click', '#tp-remove-featured', function(e) {
        e.preventDefault();
        $('#tp-featured-preview').empty();
        $('#tp_featured_image').val('');
    });

    /* ============================================
       TECH FIELDS
    ============================================ */
    var techIndex = $('.tp-tech-field').length;

    $('#tp-add-tech-field').on('click', function() {
        var html = '<div class="tp-tech-field" data-index="' + techIndex + '">' +
            '<div class="tp-tech-row">' +
            '<input type="text" name="tp_tech_key[]" placeholder="Etiqueta (ej: Duración)" class="tp-tech-key">' +
            '<input type="text" name="tp_tech_val[]" placeholder="Valor (ej: 7 días / 6 noches)" class="tp-tech-val">' +
            '<button type="button" class="tp-remove-field tp-btn-icon" title="Eliminar campo">✕</button>' +
            '</div>' +
            '</div>';
        $('#tp-tech-fields').append(html);
        techIndex++;
        // Focus new key input
        $('#tp-tech-fields .tp-tech-field:last-child .tp-tech-key').focus();
    });

    // Remove tech field
    $(document).on('click', '.tp-remove-field', function() {
        if ( confirm(TP_META.confirm_field) ) {
            $(this).closest('.tp-tech-field').remove();
        }
    });

    // Quick suggestions
    $(document).on('click', '.tp-suggestion', function() {
        var label = $(this).data('label');
        var $last = $('#tp-tech-fields .tp-tech-field:last-child');
        // If last field key is empty, use it; otherwise add new
        if ( $last.length && $last.find('.tp-tech-key').val() === '' ) {
            $last.find('.tp-tech-key').val(label);
            $last.find('.tp-tech-val').focus();
        } else {
            $('#tp-add-tech-field').trigger('click');
            setTimeout(function() {
                $('#tp-tech-fields .tp-tech-field:last-child .tp-tech-key').val(label);
                $('#tp-tech-fields .tp-tech-field:last-child .tp-tech-val').focus();
            }, 50);
        }
    });

    /* ============================================
       DATES
    ============================================ */
    function initDatepicker($input) {
        $input.datepicker({
            dateFormat:  'dd/mm/yy',
            minDate:     0,
            showAnim:    'slideDown',
            changeMonth: true,
            changeYear:  true,
            numberOfMonths: 2,
        });
    }

    // Init existing datepickers (from/to)
    $('.tp-datepicker-from, .tp-datepicker-to').each(function() {
        initDatepicker($(this));
    });

    // Init custom field datepickers
    $('.tp-cf-field .tp-datepicker').each(function() {
        initDatepicker($(this));
    });

    // Validate date ranges before saving post
    $('#post').on('submit', function(e) {
        var valid = true;
        $('#tp-dates-list .tp-date-item').each(function() {
            var from = $(this).find('.tp-datepicker-from').val();
            var to = $(this).find('.tp-datepicker-to').val();
            if ( from && to ) {
                var f = parseDMY(from);
                var t = parseDMY(to);
                if ( f && t && t < f ) {
                    valid = false;
                    return false; // break
                }
            }
        });
        if ( ! valid ) {
            alert('La fecha de regreso no puede ser anterior a la fecha de salida.');
            e.preventDefault();
            return false;
        }
    });

    function parseDMY(str) {
        if ( ! str ) return null;
        var parts = str.split('/');
        if ( parts.length !== 3 ) return null;
        var d = parseInt(parts[0],10), m = parseInt(parts[1],10)-1, y = parseInt(parts[2],10);
        return new Date(y,m,d).getTime();
    }

    $('#tp-add-date').on('click', function() {
        var html = '<div class="tp-date-item">' +
            '<span class="dashicons dashicons-calendar-alt"></span>' +
            '<input type="text" name="tp_dates_from[]" class="tp-datepicker-from" placeholder="Salida dd/mm/aaaa" readonly>' +
            '<input type="text" name="tp_dates_to[]" class="tp-datepicker-to" placeholder="Regreso dd/mm/aaaa" readonly>' +
            '<button type="button" class="tp-remove-date tp-btn-icon">✕</button>' +
            '</div>';
        $('#tp-dates-list').append(html);
        var $lastFrom = $('#tp-dates-list .tp-date-item:last-child .tp-datepicker-from');
        var $lastTo   = $('#tp-dates-list .tp-date-item:last-child .tp-datepicker-to');
        initDatepicker( $lastFrom );
        initDatepicker( $lastTo );
        $lastFrom.datepicker('show');
    });

    $(document).on('click', '.tp-remove-date', function() {
        $(this).closest('.tp-date-item').remove();
    });

    /* ============================================
       PRICE CURRENCY SYMBOL
    ============================================ */
    $('[name="tp_currency"]').on('change', function() {
        $('#tp-currency-symbol').text( $(this).val() );
    });

});
