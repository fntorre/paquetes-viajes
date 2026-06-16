jQuery(document).ready(function($) {
    var packageForm = $('#tp-package-form');
    var packageSearch = $('#tp-package-search');
    var mediaFrame;
    var featuredFrame;

    $(document).on('click', '#tp-new-package', function() {
        resetPackageForm();
        $('html, body').animate({ scrollTop: packageForm.offset().top - 20 }, 400);
    });

    $(document).on('click', '.tp-package-card', function() {
        var data = $(this).data('package');
        if ( typeof data === 'string' ) {
            try {
                data = JSON.parse( data );
            } catch ( e ) {
                data = {};
            }
        }
        fillPackageForm(data);
        $('html, body').animate({ scrollTop: packageForm.offset().top - 20 }, 400);
    });

    $(document).on('input', '#tp-package-search', function() {
        var query = $(this).val().toLowerCase();
        $('.tp-package-card').each(function() {
            var title = $(this).find('strong').text().toLowerCase();
            $(this).toggle( title.indexOf(query) !== -1 );
        });
    });

    $(document).on('click', '#tp-reset-package', function() {
        resetPackageForm();
    });

    // Validate date ranges before submitting custom package form
    $(document).on('submit', '#tp-package-form', function(e) {
        var valid = true;
        $('#tp-dates-list .tp-date-item').each(function() {
            var from = $(this).find('.tp-datepicker-from').val();
            var to = $(this).find('.tp-datepicker-to').val();
            if ( from && to ) {
                var f = parseDMY(from);
                var t = parseDMY(to);
                if ( f && t && t < f ) {
                    valid = false;
                    return false;
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

    $(document).on('click', '#tp-add-date', function() {
        addDateField('','');
    });

    $(document).on('click', '#tp-add-tech-field', function() {
        addTechField('', '');
    });

    $(document).on('click', '.tp-remove-date', function() {
        $(this).closest('.tp-date-item').remove();
    });

    $(document).on('click', '.tp-remove-field', function() {
        $(this).closest('.tp-tech-field').remove();
    });

    $(document).on('click', '.tp-gallery-remove', function() {
        var $item = $(this).closest('.tp-gallery-item');
        $item.remove();
        updateGalleryIds();
    });

    $(document).on('click', '#tp-add-gallery', function(e) {
        e.preventDefault();

        if ( mediaFrame ) {
            mediaFrame.open();
            return;
        }

        mediaFrame = wp.media({
            title: 'Seleccionar imágenes',
            button: { text: 'Agregar a la galería' },
            library: { type: 'image' },
            multiple: true
        });

        mediaFrame.on('select', function() {
            var selection = mediaFrame.state().get('selection');
            selection.each(function(attachment) {
                attachment = attachment.toJSON();
                addGalleryItem(attachment.id, attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url);
            });
            updateGalleryIds();
        });

        mediaFrame.open();
    });

    $(document).on('input', '#tp_short_desc', function() {
        $('#tp-short-count').text( $(this).val().length );
    });

    // Featured image for packages manager
    $(document).on('click', '#tp-set-featured-main', function(e) {
        e.preventDefault();
        if ( featuredFrame ) { featuredFrame.open(); return; }
        featuredFrame = wp.media({
            title: 'Seleccionar imagen principal',
            button: { text: 'Usar imagen' },
            library: { type: 'image' },
            multiple: false
        });
        featuredFrame.on('select', function() {
            var selection = featuredFrame.state().get('selection').first().toJSON();
            var id = selection.id;
            var thumb = selection.sizes && selection.sizes.medium ? selection.sizes.medium.url : selection.url;
            $('#tp-featured-preview-main').html('<img src="'+thumb+'" style="max-width:220px;border-radius:8px;display:block;">');
            $('#tp_featured_image').val(id);
        });
        featuredFrame.open();
    });

    $(document).on('click', '#tp-remove-featured-main', function(e) {
        e.preventDefault();
        $('#tp-featured-preview-main').empty();
        $('#tp_featured_image').val('');
    });

    function fillPackageForm(data) {
        $('#tp_package_id').val(data.ID || 0);
        $('#tp_title').val(data.post_title || '');
        $('#tp_short_desc').val(data.tp_short_desc || '').trigger('input');
        $('#tp_content').val(data.post_content || '');
        $('#tp_currency').val(data.tp_currency || 'USD');
        $('#tp_price').val(data.tp_price || '');
        $('#tp_price_note').val(data.tp_price_note || '');
        $('#tp_slider').prop('checked', data.tp_slider === '1');
        $('#tp_featured').prop('checked', data.tp_featured === '1');
        $('#tp_post_status').val(data.post_status || 'draft');
        $('#tp_destino').val((data.destinos && data.destinos[0]) || '');
        $('#tp_new_destino').val('');

        // Load featured image
        var featuredImageId = data.tp_featured_image || '';
        $('#tp_featured_image').val(featuredImageId);
        if ( featuredImageId ) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tp_get_image_thumb',
                    id: featuredImageId
                },
                success: function(response) {
                    if ( response.success ) {
                        $('#tp-featured-preview-main').html('<img src="' + response.data.url + '" alt="" style="max-width:220px;height:auto;border-radius:8px;display:block;">');
                    }
                }
            });
        } else {
            $('#tp-featured-preview-main').empty();
        }

        $('#tp-dates-list').empty();
        (data.tp_dates || []).forEach(function(date) {
            if ( typeof date === 'object' ) {
                addDateField(date.from || '', date.to || '');
            } else {
                addDateField(date || '', '');
            }
        });

        $('#tp-tech-fields').empty();
        (data.tp_tech_fields || []).forEach(function(item) {
            addTechField(item.key, item.val);
        });

        $('#tp-gallery-preview').empty();
        $('#tp_gallery_ids').val('');
        (data.tp_gallery || []).forEach(function(item) {
            addGalleryItem(item.id, item.url);
        });
        updateGalleryIds();
    }

    function resetPackageForm() {
        packageForm[0].reset();
        $('#tp_package_id').val('0');
        $('#tp-short-count').text('0');
        $('#tp-dates-list').empty();
        $('#tp-tech-fields').empty();
        $('#tp-gallery-preview').empty();
        $('#tp_gallery_ids').val('');
        $('#tp_featured_image').val('');
        $('#tp-featured-preview-main').empty();
        $('#tp_currency').val('USD');
    }

    function addDateField(from, to) {
        var field = '<div class="tp-date-item"><span class="dashicons dashicons-calendar-alt"></span>' +
            '<input type="text" name="tp_dates_from[]" class="tp-datepicker-from" value="' + (from || '') + '" placeholder="Salida dd/mm/aaaa" readonly>' +
            '<input type="text" name="tp_dates_to[]" class="tp-datepicker-to" value="' + (to || '') + '" placeholder="Regreso dd/mm/aaaa" readonly>' +
            '<button type="button" class="tp-remove-date tp-btn-icon">✕</button></div>';
        $('#tp-dates-list').append(field);
        initDatepicker($('#tp-dates-list .tp-date-item:last-child .tp-datepicker-from'));
        initDatepicker($('#tp-dates-list .tp-date-item:last-child .tp-datepicker-to'));
    }

    function addTechField(key, value) {
        var field = '<div class="tp-tech-field"><div class="tp-tech-row">' +
            '<input type="text" name="tp_tech_key[]" placeholder="Etiqueta (ej: Duración)" class="tp-tech-key" value="' + (key || '') + '">' +
            '<input type="text" name="tp_tech_val[]" placeholder="Valor (ej: 7 días / 6 noches)" class="tp-tech-val" value="' + (value || '') + '">' +
            '<button type="button" class="tp-remove-field tp-btn-icon" title="Eliminar campo">✕</button>' +
            '</div></div>';
        $('#tp-tech-fields').append(field);
    }

    function addGalleryItem(id, url) {
        if ( ! id || ! url ) {
            return;
        }
        var exists = $('#tp-gallery-preview .tp-gallery-item[data-id="' + id + '"]');
        if ( exists.length ) {
            return;
        }
        var item = '<div class="tp-gallery-item" data-id="' + id + '">' +
            '<img src="' + url + '">' +
            '<button type="button" class="tp-gallery-remove" title="Eliminar">✕</button>' +
            '</div>';
        $('#tp-gallery-preview').append(item);
    }

    function updateGalleryIds() {
        var ids = [];
        $('#tp-gallery-preview .tp-gallery-item').each(function() {
            ids.push( $(this).data('id') );
        });
        $('#tp_gallery_ids').val(ids.join(','));
    }

    function initDatepicker($input) {
        $input.datepicker({
            dateFormat: 'dd/mm/yy',
            minDate: 0,
            changeMonth: true,
            changeYear: true,
            numberOfMonths: 1
        });
    }

    $(document).trigger('tpPackagesReady');
});
