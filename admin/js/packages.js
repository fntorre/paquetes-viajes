jQuery(document).ready(function($) {
    var packageForm = $('#tp-package-form');
    var packageSearch = $('#tp-package-search');
    var mediaFrame;
    var featuredFrame;
    var reservationsEnabled = !!( window.TP_ADMIN && TP_ADMIN.reservations_enabled === '1' );

    $(document).on('click', '#tp-new-package, #tp-new-package-empty', function() {
        resetPackageForm();
        $('html, body').animate({ scrollTop: packageForm.offset().top - 20 }, 400);
    });

    $(document).on('click', '#tp-similar-clear', function() {
        $('#tp_similar_destinos input[type="checkbox"]').prop('checked', false);
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
        var visible = 0;
        var total = $('.tp-package-card').length;

        $('.tp-package-card').each(function() {
            var title = $(this).find('strong').text().toLowerCase();
            var match = title.indexOf(query) !== -1;
            $(this).toggle(match);
            if ( match ) {
                visible++;
            }
        });

        $('#tp-package-no-results').toggle( total > 0 && visible === 0 );
    });

    $(document).on('click', '#tp-reset-package', function() {
        resetPackageForm();
    });

    // Validate date ranges before submitting custom package form
    $(document).on('submit', '#tp-package-form', function(e) {
        if ( window.tinymce ) {
            window.tinymce.triggerSave();
        }
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
        var cfOrder = [];
        $('#tp-custom-fields-wrap .tp-cf-field').each(function() {
            var k = $(this).data('key');
            if (k) cfOrder.push(k);
        });
        $('#tp_cf_order').val(cfOrder.join(','));
    });

    function setPackageContent(html) {
        html = html || '';
        $('#tp_content').val(html);
        if ( ! window.tinymce ) {
            return;
        }
        var editor = window.tinymce.get('tp_content');
        if ( editor ) {
            editor.setContent(html);
            return;
        }
        setTimeout(function() {
            var delayed = window.tinymce.get('tp_content');
            if ( delayed ) {
                delayed.setContent(html);
            }
        }, 200);
    }

    function parseDMY(str) {
        if ( ! str ) return null;
        var parts = str.split('/');
        if ( parts.length !== 3 ) return null;
        var d = parseInt(parts[0],10), m = parseInt(parts[1],10)-1, y = parseInt(parts[2],10);
        return new Date(y,m,d).getTime();
    }

    $(document).on('click', '#tp-add-date', function() {
        addDateField('', '', {}, true);
    });

    $(document).on('click', '#tp-add-price', function(e) {
        e.preventDefault();
        e.stopPropagation();
        addPriceField('', '', 'USD', false);
    });

    $(document).on('click', '.tp-remove-price', function(e) {
        e.preventDefault();
        $(this).closest('.tp-price-item').remove();
        reindexPriceHighlightCheckboxes();
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

    $(document).on('click', '#tp-add-itinerary', function() {
        addItineraryRow('', '', '');
    });

    $(document).on('click', '.tp-remove-itinerary', function() {
        $(this).closest('.tp-itinerary-item').remove();
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

    function markSelectedPackage(id) {
        var target = String(id || 0);

        $('.tp-package-card').each(function() {
            var cardData = $(this).data('package');
            if ( typeof cardData === 'string' ) {
                try {
                    cardData = JSON.parse(cardData);
                } catch (e) {
                    cardData = {};
                }
            }
            var cardId = cardData && cardData.ID ? String(cardData.ID) : '';
            $(this).toggleClass('is-selected', target !== '0' && cardId === target);
        });
    }

    function fillPackageForm(data) {
        $('#tp_package_id').val(data.ID || 0);
        markSelectedPackage(data.ID);
        $('#tp-delete-package').toggle(!!data.ID);
        $('#tp-form-title').text(data.ID ? 'Editar paquete' : 'Nuevo paquete');
        $('#tp-view-package')
            .attr('href', data.view_url || '#')
            .toggle(!!(data.ID && data.view_url));
        $('#tp_title').val(data.post_title || '');
        $('#tp_short_desc').val(data.tp_short_desc || '').trigger('input');
        $('#tp_video_url').val(data.tp_video_url || '');
        $('#tp_map_location').val(data.tp_map_location || '');
        $('#tp_whatsapp_number').val(data.tp_whatsapp || '');
        setPackageContent(data.post_content || '');
        $('#tp_slider').prop('checked', data.tp_slider === '1');
        $('#tp_featured').prop('checked', data.tp_featured === '1');
        $('#tp_post_status').val(data.post_status || 'draft');
        $('#tp_destino').val((data.destinos && data.destinos[0]) || '');
        $('#tp_new_destino').val('');
        $('#tp_tipologia').val((data.tipologias && data.tipologias[0]) || '');
        $('#tp_new_tipologia').val('');
        var similarDestinos = (data.tp_similar_destinos || []).map(String);
        $('#tp_similar_destinos input[type="checkbox"]').each(function() {
            $(this).prop('checked', similarDestinos.indexOf(String($(this).val())) !== -1);
        });
        $('input[name="tp_tipo_viaje"]').prop('checked', false);
        if ( data.tp_tipo_viaje ) {
            $('input[name="tp_tipo_viaje"][value="' + data.tp_tipo_viaje + '"]').prop('checked', true);
        } else {
            $('input[name="tp_tipo_viaje"][value=""]').prop('checked', true);
        }

        // Load featured image
        var featuredImageId = data.tp_featured_image || '';
        $('#tp_featured_image').val(featuredImageId);
        if ( featuredImageId ) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tp_get_image_thumb',
                    id: featuredImageId,
                    nonce: TP_ADMIN.nonce
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

        $('#tp-prices-list').empty();
        (data.tp_prices || []).forEach(function(p) {
            addPriceField(p.price || '', p.note || '', p.currency || 'USD', !!p.highlighted);
        });

        var pricing = data.tp_pricing || {};
        $('#tp_currency_base').val(pricing.currency || 'USD');
        $('#tp_price_adult').val(pricing.price_adult || '');
        $('#tp_price_child').val(pricing.price_child || '');
        $('#tp_single_supplement').val(pricing.single_supplement || '');
        $('#tp_deposit_type').val(pricing.deposit_type || 'none');
        $('#tp_deposit_value').val(pricing.deposit_value || '');
        $('#tp_deposit_note').val(pricing.deposit_note || '');

        $('#tp-dates-list').empty();
        (data.tp_dates || []).forEach(function(date) {
            if ( typeof date === 'object' && date !== null ) {
                addDateField(date.from || '', date.to || '', date);
            } else {
                addDateField(date || '', '', {});
            }
        });

        $('#tp-tech-fields').empty();
        (data.tp_tech_fields || []).forEach(function(item) {
            addTechField(item.key, item.val);
        });

        $('#tp-itinerary-list').empty();
        (data.tp_itinerary || []).forEach(function(item) {
            addItineraryRow(item.day || '', item.title || '', item.desc || '');
        });

        // Populate custom fields
        var cfData = data.tp_custom_fields || {};
        $('.tp-cf-field').each(function() {
            var key = $(this).data('key');
            if (typeof cfData[key] === 'undefined') return;
            var val = cfData[key];
            var $input = $(this).find('input, textarea, select');
            if ($input.attr('type') === 'checkbox') {
                $input.prop('checked', val === '1');
            } else {
                $input.val(val);
            }
        });

        // Restore custom fields order
        var cfOrder = data.tp_cf_order || '';
        if (cfOrder && typeof cfOrder === 'string') {
            var keys = cfOrder.split(',');
            var $wrap = $('#tp-custom-fields-wrap');
            keys.forEach(function(k) {
                $wrap.find('.tp-cf-field[data-key="' + k + '"]').appendTo($wrap);
            });
        }

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
        markSelectedPackage(0);
        $('#tp-form-title').text('Nuevo paquete');
        $('#tp-view-package').hide().attr('href', '#');
        setPackageContent('');
        $('#tp-short-count').text('0');
        $('#tp_video_url').val('');
        $('#tp_map_location').val('');
        $('#tp_whatsapp_number').val('');
        $('#tp-prices-list').empty();
        $('#tp-dates-list').empty();
        $('#tp-tech-fields').empty();
        $('#tp-itinerary-list').empty();
        $('#tp-gallery-preview').empty();
        $('#tp_gallery_ids').val('');
        $('#tp_cf_order').val('');
        $('#tp_currency_base').val('USD');
        $('#tp_price_adult').val('');
        $('#tp_price_child').val('');
        $('#tp_single_supplement').val('');
        $('#tp_deposit_type').val('none');
        $('#tp_deposit_value').val('');
        $('#tp_deposit_note').val('');
        $('#tp_featured_image').val('');
        $('#tp-featured-preview-main').empty();
        $('#tp_tipologia').val('');
        $('#tp_new_tipologia').val('');
        $('#tp_similar_destinos input[type="checkbox"]').prop('checked', false);
        $('input[name="tp_tipo_viaje"][value=""]').prop('checked', true);
        $('#tp-delete-package').hide();
        // Reset custom fields
        $('.tp-cf-field').each(function() {
            var $input = $(this).find('input, textarea, select');
            if ($input.attr('type') === 'checkbox') {
                $input.prop('checked', false);
            } else {
                $input.val('');
            }
        });
    }

    function addPriceField(price, note, currency, highlighted) {
        var idx = $('#tp-prices-list .tp-price-item').length;
        var checked = highlighted ? ' checked' : '';
        var symbols = { USD: 'US$', ARS: '$', EUR: '€', BRL: 'R$' };
        var field = '<div class="tp-price-item">' +
            '<select name="tp_currency_item[]" class="tp-price-currency">' +
                '<option value="USD"' + (currency === 'USD' ? ' selected' : '') + '>' + symbols.USD + '</option>' +
                '<option value="ARS"' + (currency === 'ARS' ? ' selected' : '') + '>' + symbols.ARS + '</option>' +
                '<option value="EUR"' + (currency === 'EUR' ? ' selected' : '') + '>' + symbols.EUR + '</option>' +
                '<option value="BRL"' + (currency === 'BRL' ? ' selected' : '') + '>' + symbols.BRL + '</option>' +
            '</select>' +
            '<input type="number" name="tp_price_item[]" class="tp-price-value" step="0.01" min="0" placeholder="Precio" value="' + (price || '') + '">' +
            '<input type="text" name="tp_price_note_item[]" class="tp-price-note" placeholder="Nota (ej: por persona en doble)" value="' + (note || '') + '">' +
            '<label class="tp-price-highlight-label"><input type="checkbox" name="tp_price_highlighted[]" value="' + idx + '"' + checked + '> Destacar</label>' +
            '<button type="button" class="tp-remove-price tp-btn-icon" title="Eliminar precio">✕</button>' +
            '</div>';
        $('#tp-prices-list').append(field);
    }
    window.tpAddPriceField = addPriceField;

    function reindexPriceHighlightCheckboxes() {
        $('#tp-prices-list .tp-price-item').each(function(i) {
            $(this).find('input[name="tp_price_highlighted[]"]').val(i);
        });
    }

    function escAttr(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function escHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function addItineraryRow(day, title, desc) {
        var index = $('#tp-itinerary-list .tp-itinerary-item').length + 1;
        if ( ! day ) {
            day = 'Día ' + index;
        }
        var row = '<div class="tp-itinerary-item">' +
            '<div class="tp-itinerary-head">' +
                '<input type="text" name="tp_itinerary_day[]" class="tp-itinerary-day" value="' + escAttr(day) + '" placeholder="Día 1">' +
                '<input type="text" name="tp_itinerary_title[]" class="tp-itinerary-title" value="' + escAttr(title) + '" placeholder="Título del día">' +
                '<button type="button" class="tp-remove-itinerary tp-btn-icon" title="Eliminar día">✕</button>' +
            '</div>' +
            '<textarea name="tp_itinerary_desc[]" rows="3" placeholder="Descripción, actividades, comidas, hotel...">' + escHtml(desc) + '</textarea>' +
            '</div>';
        $('#tp-itinerary-list').append(row);
    }

    function departureNumberField(label, name, value, placeholder) {
        return '<label class="tp-dep-field"><span>' + label + '</span>' +
            '<input type="number" min="0" step="1" name="' + name + '" value="' + escAttr(value) + '" placeholder="' + placeholder + '"></label>';
    }

    function departureStatusOptions(current) {
        var options = {
            auto: 'Automático',
            open: 'Abierta',
            soldout: 'Agotada',
            closed: 'Cerrada'
        };
        var html = '';
        for (var key in options) {
            html += '<option value="' + key + '"' + (current === key ? ' selected' : '') + '>' + options[key] + '</option>';
        }
        return html;
    }

    function departureCurrencyOptions(current) {
        var options = { '': 'Según paquete', USD: 'US$', ARS: '$', EUR: '€', BRL: 'R$' };
        var html = '';
        for (var key in options) {
            html += '<option value="' + key + '"' + (current === key ? ' selected' : '') + '>' + options[key] + '</option>';
        }
        return html;
    }

    function addDateField(from, to, data, open) {
        data = data || {};
        var bookedLabel = reservationsEnabled ? 'Reservados externos' : 'Reservados';
        var moduleBooked = parseInt(data.reservations_booked, 10) || 0;
        var field = '<div class="tp-date-item tp-departure">' +
            '<input type="hidden" name="tp_dates_uid[]" value="' + escAttr(data.uid) + '">' +
            '<div class="tp-departure-head">' +
                '<span class="dashicons dashicons-calendar-alt"></span>' +
                '<input type="text" name="tp_dates_from[]" class="tp-datepicker-from" value="' + escAttr(from || data.from || '') + '" placeholder="Salida dd/mm/aaaa" readonly>' +
                '<span class="tp-departure-arrow">→</span>' +
                '<input type="text" name="tp_dates_to[]" class="tp-datepicker-to" value="' + escAttr(to || data.to || '') + '" placeholder="Regreso dd/mm/aaaa" readonly>' +
                '<label class="tp-departure-seats"><span>Cupos</span>' +
                    '<input type="number" min="0" step="1" name="tp_dates_seats[]" value="' + escAttr(data.seats) + '" placeholder="0"></label>' +
                '<span class="tp-departure-summary" style="display:none;"></span>' +
                '<button type="button" class="tp-departure-toggle button" aria-expanded="false">Más detalles</button>' +
                '<button type="button" class="tp-remove-date tp-btn-icon" title="Eliminar salida">✕</button>' +
            '</div>' +
            '<div class="tp-departure-details" style="display:none;">' +
                '<div class="tp-departure-grid">' +
                    departureNumberField(bookedLabel, 'tp_dates_seats_booked[]', data.seats_booked, '0') +
                    departureNumberField('Mín. pax', 'tp_dates_min_pax[]', data.min_pax, 'Ej: 2') +
                    departureNumberField('Máx. pax', 'tp_dates_max_pax[]', data.max_pax, 'Ej: 20') +
                    '<label class="tp-dep-field"><span>Cierre de venta</span>' +
                        '<input type="text" name="tp_dates_cutoff[]" class="tp-datepicker-cutoff" value="' + escAttr(data.cutoff) + '" placeholder="dd/mm/aaaa" readonly></label>' +
                    '<label class="tp-dep-field"><span>Estado</span>' +
                        '<select name="tp_dates_status[]">' + departureStatusOptions(data.status || 'auto') + '</select></label>' +
                    '<label class="tp-dep-field"><span>Precio adulto</span>' +
                        '<input type="number" step="0.01" min="0" name="tp_dates_price_adult[]" value="' + escAttr(data.price_adult) + '" placeholder="Según paquete"></label>' +
                    '<label class="tp-dep-field"><span>Precio niño</span>' +
                        '<input type="number" step="0.01" min="0" name="tp_dates_price_child[]" value="' + escAttr(data.price_child) + '" placeholder="Según paquete"></label>' +
                    '<label class="tp-dep-field"><span>Moneda</span>' +
                        '<select name="tp_dates_currency[]">' + departureCurrencyOptions(data.currency || '') + '</select></label>' +
                    ( moduleBooked > 0 ? '<p class="tp-dep-res-note">Reservas del módulo: <strong>' + moduleBooked + '</strong> (se suman automáticamente).</p>' : '' ) +
                '</div>' +
            '</div>' +
        '</div>';
        $('#tp-dates-list').append(field);

        var $last = $('#tp-dates-list .tp-departure:last-child');
        initDatepicker($last.find('.tp-datepicker-from'));
        initDatepicker($last.find('.tp-datepicker-to'));
        initDatepicker($last.find('.tp-datepicker-cutoff'));

        if ( open ) {
            $last.addClass('is-open');
            $last.find('.tp-departure-details').show();
            $last.find('.tp-departure-toggle').attr('aria-expanded', 'true').text('Ocultar');
        }

        refreshDepartureSummary($last);
    }

    function refreshDepartureSummary($dep) {
        var $summary = $dep.find('.tp-departure-summary');
        if ( ! $summary.length ) {
            return;
        }

        var reserved = $dep.find('input[name="tp_dates_seats_booked[]"]').val();
        var status   = $dep.find('select[name="tp_dates_status[]"]').val();
        var labels   = { auto: 'Automático', open: 'Abierta', soldout: 'Agotada', closed: 'Cerrada' };
        var parts    = [];

        if ( reserved !== '' && reserved !== undefined && reserved !== null && Number(reserved) > 0 ) {
            parts.push(reserved + ' reservados');
        }
        if ( status && status !== 'auto' ) {
            parts.push(labels[status] || status);
        }

        if ( parts.length ) {
            $summary.text(parts.join(' · ')).show();
        } else {
            $summary.text('').hide();
        }
    }

    $(document).on('input change', '.tp-departure', function() {
        refreshDepartureSummary($(this));
    });

    $(document).on('click', '.tp-departure-toggle', function(e) {
        e.preventDefault();
        var $dep = $(this).closest('.tp-departure');
        var $details = $dep.find('.tp-departure-details');
        var isOpen = $dep.hasClass('is-open');

        if ( isOpen ) {
            $dep.removeClass('is-open');
            $details.slideUp(160);
        } else {
            $dep.addClass('is-open');
            $details.slideDown(160);
        }

        $(this).attr('aria-expanded', isOpen ? 'false' : 'true');
        $(this).text(isOpen ? 'Más detalles' : 'Ocultar');
    });

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

    // Toggle filters form
    $(document).on('click', '#tp-toggle-filters', function(e) {
        e.preventDefault();
        $('.tp-filters-form').slideToggle(180);
    });

    // Toggle import form
    $(document).on('click', '#tp-import-btn', function(e) {
        e.preventDefault();
        $('#tp-import-form').toggle();
    });

    // Delete package confirmation
    $(document).on('click', '#tp-delete-package', function(e) {
        e.preventDefault();
        if ( ! confirm('¿Estás seguro de eliminar este paquete? Esta acción no se puede deshacer.') ) return;
        var pid = $('#tp_package_id').val();
        if ( ! pid || pid === '0' ) return;
        window.location.href = 'admin.php?page=tp-packages&tp_action=delete_package&tp_package_id=' + pid + '&tp_delete_nonce=' + TP_ADMIN.delete_nonce;
    });

    // Init custom field datepickers
    $('.tp-cf-field .tp-datepicker').each(function() {
        initDatepicker($(this));
    });

    /* ---- Drag & Drop Gallery Reorder ---- */
    var dragSrc = null;
    $(document).on('dragstart', '#tp-gallery-preview .tp-gallery-item', function(e) {
        dragSrc = this;
        $(this).addClass('tp-dragging');
        e.originalEvent.dataTransfer.effectAllowed = 'move';
    });
    $(document).on('dragend', '#tp-gallery-preview .tp-gallery-item', function() {
        $(this).removeClass('tp-dragging');
        $('#tp-gallery-preview .tp-gallery-item').removeClass('tp-drag-over');
        updateGalleryIds();
    });
    $(document).on('dragover', '#tp-gallery-preview .tp-gallery-item', function(e) {
        e.preventDefault();
        e.originalEvent.dataTransfer.dropEffect = 'move';
        $(this).addClass('tp-drag-over');
    });
    $(document).on('dragleave', '#tp-gallery-preview .tp-gallery-item', function() {
        $(this).removeClass('tp-drag-over');
    });
    $(document).on('drop', '#tp-gallery-preview .tp-gallery-item', function(e) {
        e.preventDefault();
        if ( dragSrc !== this ) {
            $(dragSrc).insertBefore($(this));
        }
        $(this).removeClass('tp-drag-over');
    });
    $(document).on('DOMNodeInserted', '#tp-gallery-preview .tp-gallery-item', function() {
        $(this).prop('draggable', true);
    });

    /* ---- Drag & Drop Custom Fields Reorder ---- */
    var cfDragSrc = null;
    $(document).on('dragstart', '#tp-custom-fields-wrap .tp-cf-field', function(e) {
        cfDragSrc = this;
        $(this).addClass('tp-cf-dragging');
        e.originalEvent.dataTransfer.effectAllowed = 'move';
    });
    $(document).on('dragend', '#tp-custom-fields-wrap .tp-cf-field', function() {
        $(this).removeClass('tp-cf-dragging');
        $('#tp-custom-fields-wrap .tp-cf-field').removeClass('tp-cf-drag-over');
    });
    $(document).on('dragover', '#tp-custom-fields-wrap .tp-cf-field', function(e) {
        e.preventDefault();
        e.originalEvent.dataTransfer.dropEffect = 'move';
        $(this).addClass('tp-cf-drag-over');
    });
    $(document).on('dragleave', '#tp-custom-fields-wrap .tp-cf-field', function() {
        $(this).removeClass('tp-cf-drag-over');
    });
    $(document).on('drop', '#tp-custom-fields-wrap .tp-cf-field', function(e) {
        e.preventDefault();
        if ( cfDragSrc !== this ) {
            $(cfDragSrc).insertBefore($(this));
        }
        $(this).removeClass('tp-cf-drag-over');
    });

    $(document).trigger('tpPackagesReady');

    /* ---- Form section navigation ---- */
    (function initFormNav() {
        var $nav = $('.tp-form-nav');
        if ( ! $nav.length ) {
            return;
        }

        var $links = $nav.find('a');

        $links.on('click', function(e) {
            var target = $(this).attr('href');
            var $target = target ? $(target) : $();
            if ( ! $target.length ) {
                return;
            }
            e.preventDefault();
            $target[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
            $links.removeClass('is-active');
            $(this).addClass('is-active');
        });

        var $sections = $('.tp-panel[id]');
        if ( 'IntersectionObserver' in window && $sections.length ) {
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if ( ! entry.isIntersecting ) {
                        return;
                    }
                    var href = '#' + entry.target.id;
                    $links.removeClass('is-active');
                    $links.filter('[href="' + href + '"]').addClass('is-active');
                });
            }, { rootMargin: '-90px 0px -60% 0px', threshold: 0 });

            $sections.each(function() {
                observer.observe(this);
            });
        }
    })();

    // Prefill form from server-side selected package (e.g. ?package_id=X)
    var $selectedData = $('#tp-selected-package-data');
    if ( $selectedData.length && $selectedData.val() && $selectedData.val() !== '[]' && $selectedData.val() !== '{}' ) {
        try {
            var selected = JSON.parse( $selectedData.val() );
            if ( selected && selected.ID ) {
                fillPackageForm(selected);
            }
        } catch ( e ) {
            // ignore malformed JSON
        }
    }
});
