jQuery(document).ready(function($) {
    var packageForm = $('#tp-package-form');
    var mediaFrame;
    var featuredFrame;
    var seoImageFrame;
    var promoLogoFrame;
    var promoLogoUrl = '';
    var promoSecondaryFrame;
    var promoSecondaryUrl = '';
    var promoImageCache = {};
    var promoHitAreas = [];
    var promoDrag = null;
    var promoPositions = {};
    var promoDrawingFormatKey = '';
    var promoPreviewTimer = null;
    var promoPreviewVersion = 0;
    var promoLastImages = { background: null, logo: null, secondary: null };
    var promoTemplates = window.TP_ADMIN && Array.isArray(TP_ADMIN.promo_templates) ? TP_ADMIN.promo_templates : [];
    var reservationsEnabled = !!( window.TP_ADMIN && TP_ADMIN.reservations_enabled === '1' );

    $(document).on('click', '#tp-similar-clear', function() {
        $('#tp_similar_destinos input[type="checkbox"]').prop('checked', false);
    });

    $('#tp_title').on('invalid', function() {
        $('#tp-title-error').show();
        $(this).attr('aria-invalid', 'true');
    });

    $('#tp_title').on('input', function() {
        if ( $.trim($(this).val()) ) {
            $('#tp-title-error').hide();
            $(this).removeAttr('aria-invalid');
        }
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
        $('#tp-dates-list .tp-datepicker-from, #tp-dates-list .tp-datepicker-to').removeAttr('aria-invalid');
        $('#tp-dates-list .tp-date-error').remove();
        $('#tp-dates-list .tp-date-item').each(function() {
            var from = $(this).find('.tp-datepicker-from').val();
            var to = $(this).find('.tp-datepicker-to').val();
            if ( from && to ) {
                var f = parseDMY(from);
                var t = parseDMY(to);
                if ( f && t && t < f ) {
                    valid = false;
                    var $returnDate = $(this).find('.tp-datepicker-to');
                    $returnDate.attr('aria-invalid', 'true');
                    $(this).find('.tp-departure-head').after('<span class="tp-inline-error tp-date-error" role="alert">La fecha de regreso no puede ser anterior a la fecha de salida.</span>');
                    $returnDate.trigger('focus');
                    return false;
                }
            }
        });
        if ( ! valid ) {
            e.preventDefault();
            return false;
        }
        var cfOrder = [];
        $('#tp-custom-fields-wrap .tp-cf-field').each(function() {
            var k = $(this).data('key');
            if (k) cfOrder.push(k);
        });
        $('#tp_cf_order').val(cfOrder.join(','));
        $('#tp_promo_config').val(JSON.stringify(promoConfig()));
    });

    $(document).on('change', '#tp-dates-list .tp-datepicker-from, #tp-dates-list .tp-datepicker-to', function() {
        $(this).closest('.tp-date-item').find('.tp-date-error').remove();
        $(this).removeAttr('aria-invalid');
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

    $(document).on('click', '#tp-add-financing', function() {
        addFinancingRow('', $('#tp-financing-list .tp-financing-item').length === 0);
    });

    $(document).on('click', '.tp-remove-financing', function() {
        $(this).closest('.tp-financing-item').remove();
        reindexFinancingRows();
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

    $(document).on('input', '#tp_seo_title', function() {
        $('#tp-seo-title-count').text( $(this).val().length );
    });

    $(document).on('input', '#tp_seo_description', function() {
        $('#tp-seo-description-count').text( $(this).val().length );
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
            var full = selection.sizes && selection.sizes.full ? selection.sizes.full.url : ( selection.sizes && selection.sizes.large ? selection.sizes.large.url : selection.url );
            $('#tp-featured-preview-main').html('<img src="' + escAttr(thumb) + '" style="max-width:220px;border-radius:8px;display:block;">');
            $('#tp_featured_image').val(id);
            $('#tp_featured_image_url').val(full);
            resetPromoPreview();
        });
        featuredFrame.open();
    });

    $(document).on('click', '#tp-remove-featured-main', function(e) {
        e.preventDefault();
        $('#tp-featured-preview-main').empty();
        $('#tp_featured_image').val('');
        $('#tp_featured_image_url').val('');
        resetPromoPreview();
    });

    // Imagen para redes sociales (Open Graph)
    $(document).on('click', '#tp-set-seo-image', function(e) {
        e.preventDefault();
        if ( seoImageFrame ) { seoImageFrame.open(); return; }
        seoImageFrame = wp.media({
            title: 'Seleccionar imagen SEO',
            button: { text: 'Usar imagen' },
            library: { type: 'image' },
            multiple: false
        });
        seoImageFrame.on('select', function() {
            var selection = seoImageFrame.state().get('selection').first().toJSON();
            var thumb = selection.sizes && selection.sizes.medium ? selection.sizes.medium.url : selection.url;
            $('#tp-seo-image-preview').html('<img src="' + escAttr(thumb) + '" style="max-width:220px;border-radius:8px;display:block;">');
            $('#tp_seo_image').val(selection.id);
        });
        seoImageFrame.open();
    });

    $(document).on('click', '#tp-remove-seo-image', function(e) {
        e.preventDefault();
        $('#tp-seo-image-preview').empty();
        $('#tp_seo_image').val('');
    });

    // Imagen promocional generada con Canvas
    function promoSlug(text) {
        return String(text || '')
            .toLowerCase()
            .replace(/[áàäâã]/g, 'a')
            .replace(/[éèëê]/g, 'e')
            .replace(/[íìïî]/g, 'i')
            .replace(/[óòöôõ]/g, 'o')
            .replace(/[úùüû]/g, 'u')
            .replace(/ñ/g, 'n')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 60) || 'paquete';
    }

    function promoCurrencySymbol(code) {
        var symbols = { USD: 'US$', ARS: '$', EUR: '€', BRL: 'R$' };
        return symbols[code] || code || '';
    }

    function promoFormatNumber(value) {
        var num = parseFloat(value);
        if ( isNaN(num) ) { return ''; }
        try {
            return num.toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
        } catch (e) {
            return String(num);
        }
    }

    function promoFonts() {
        var fonts = window.TP_ADMIN && TP_ADMIN.promo_fonts ? TP_ADMIN.promo_fonts : {};
        return {
            heading: fonts.heading || 'Arial, Helvetica, sans-serif',
            body: fonts.body || 'Arial, Helvetica, sans-serif'
        };
    }

    function promoFont(weight, size, family) {
        return weight + ' ' + size + 'px ' + family;
    }

    function promoEnsureFonts() {
        var fonts = promoFonts();
        if ( ! document.fonts || ! document.fonts.load ) {
            return Promise.resolve(true);
        }

        return Promise.all([
            document.fonts.load(promoFont(700, 32, fonts.heading)),
            document.fonts.load(promoFont(400, 24, fonts.body))
        ]).then(function() {
            return true;
        }, function() {
            return false;
        });
    }

    function promoSelectedDate() {
        var chosen = null;
        var next = null;
        var today = new Date();
        today.setHours(0, 0, 0, 0);

        $('#tp-dates-list .tp-departure').each(function() {
            var from = String($(this).find('.tp-datepicker-from').val() || '').trim();
            var to = String($(this).find('.tp-datepicker-to').val() || '').trim();
            if ( ! from ) { return; }
            var parts = from.split('/');
            if ( parts.length !== 3 ) { return; }
            var ts = new Date(parseInt(parts[2], 10), parseInt(parts[1], 10) - 1, parseInt(parts[0], 10)).getTime();
            if ( isNaN(ts) ) { return; }
            var item = { ts: ts, from: from, to: to };
            if ( ! chosen || ts < chosen.ts ) { chosen = item; }
            if ( ts >= today.getTime() && ( ! next || ts < next.ts ) ) { next = item; }
        });

        return next || chosen;
    }

    function updatePromoExtraOptions() {
        var $container = $('#tp-promo-extra-options');
        if ( ! $container.length ) { return; }
        var selected = {};
        $container.find('.tp-promo-extra-include:checked').each(function() {
            selected[String($(this).data('source'))] = true;
        });

        var options = [];
        $('#tp-tech-fields .tp-tech-field').each(function(index) {
            var $field = $(this);
            var label = $.trim($field.find('.tp-tech-key').val() || '');
            var value = $.trim($field.find('.tp-tech-val').val() || '');
            if ( label && value ) {
                options.push({ source: 'tech:' + index, label: label, checked: !!selected['tech:' + index] });
            }
        });
        $('#tp-custom-fields-wrap .tp-cf-field').each(function() {
            var $field = $(this);
            var label = $.trim($field.find('> .tp-label').text().replace(/\s*\*$/, ''));
            var $control = $field.find('input:not([type="hidden"]), select, textarea').first();
            var value = $control.is(':checkbox') ? ($control.is(':checked') ? 'Sí' : '') : $.trim($control.val() || '');
            if ( label && value && value !== '0' ) {
                var source = 'custom:' + String($field.data('key') || index);
                options.push({ source: source, label: label, checked: !!selected[source] });
            }
        });

        $container.empty();
        options.forEach(function(option, index) {
            var id = 'tp-promo-extra-' + index;
            var checked = option.checked ? ' checked' : '';
            $container.append(
                '<label for="' + id + '"><input type="checkbox" id="' + id + '" class="tp-promo-extra-include" data-source="' + escAttr(option.source) + '"' + checked + '> ' +
                escHtml(option.label) + '</label>'
            );
        });
        if ( ! options.length ) {
            $container.append('<span class="tp-promo-no-extra">Completá la ficha técnica o los campos personalizados para ver opciones.</span>');
        }
    }

    function promoData() {
        var price = $('#tp_price_adult').val();
        var currencyCode = $('#tp_currency_base').val() || 'USD';

        if ( ! price ) {
            var $first = $('#tp-prices-list .tp-price-item').filter(function() {
                return String($(this).find('.tp-price-value').val() || '') !== '';
            }).first();
            if ( $first.length ) {
                price = $first.find('.tp-price-value').val();
                currencyCode = $first.find('.tp-price-currency').val() || currencyCode;
            }
        }

        var date = promoSelectedDate();
        var include = {};
        $('.tp-promo-include').each(function() {
            include[String($(this).data('promo-key'))] = $(this).is(':checked');
        });
        var extras = [];
        $('#tp-promo-extra-options .tp-promo-extra-include:checked').each(function() {
            var source = String($(this).data('source') || '').split(':');
            var $field;
            var label = '';
            var value = '';
            if ( source[0] === 'tech' ) {
                $field = $('#tp-tech-fields .tp-tech-field').eq(parseInt(source[1], 10));
                label = $.trim($field.find('.tp-tech-key').val() || '');
                value = $.trim($field.find('.tp-tech-val').val() || '');
            } else if ( source[0] === 'custom' ) {
                var customKey = source.slice(1).join(':');
                $field = $('#tp-custom-fields-wrap .tp-cf-field').filter(function() {
                    return String($(this).data('key')) === customKey;
                }).first();
                label = $.trim($field.find('> .tp-label').text().replace(/\s*\*$/, ''));
                var $control = $field.find('input:not([type="hidden"]), select, textarea').first();
                value = $control.is(':checkbox') ? ($control.is(':checked') ? 'Sí' : '') : $.trim($control.val() || '');
            }
            if ( label && value && value !== '0' ) {
                extras.push(label + ': ' + value);
            }
        });

        var brand = $('#tp_promo_brand').val() || 'site_name';
        var logo = brand === 'custom_logo'
            ? promoLogoUrl
            : (brand === 'site_logo' && window.TP_ADMIN ? TP_ADMIN.site_logo : '');
        var $featuredFinancing = $('#tp-financing-list .tp-financing-featured:checked').closest('.tp-financing-item');
        var contact = window.TP_ADMIN && TP_ADMIN.promo_contact ? TP_ADMIN.promo_contact : {};
        var contactLines = [];
        if ( include.phone && contact.phone ) { contactLines.push('Tel: ' + contact.phone); }
        if ( include.whatsapp && contact.whatsapp ) { contactLines.push('Consultanos por WhatsApp: ' + contact.whatsapp); }
        if ( include.email && contact.email ) { contactLines.push('Email: ' + contact.email); }
        if ( include.address && contact.address ) { contactLines.push(contact.address); }
        if ( include.website && contact.website ) { contactLines.push(String(contact.website).replace(/^https?:\/\//, '').replace(/\/$/, '')); }

        return {
            title: String($('#tp_title').val() || '').trim() || 'Paquete de viaje',
            subtitle: include.subtitle ? String($('#tp_short_desc').val() || '').trim() : '',
            dateFrom: date ? date.from : '',
            dateTo: date ? date.to : '',
            currency: promoCurrencySymbol(currencyCode),
            price: include.price ? price : '',
            includePrice: include.price,
            childPrice: include.child_price ? $('#tp_price_child').val() : '',
            destination: include.destination
                ? ($.trim($('#tp_new_destino').val() || '') || ($('#tp_destino').val() ? $.trim($('#tp_destino option:selected').text() || '') : ''))
                : '',
            financing: include.financing ? $.trim($featuredFinancing.find('.tp-financing-label').val() || '') : '',
            extras: extras,
            contacts: contactLines,
            includeDate: include.date,
            background: String($('#tp_featured_image_url').val() || $('#tp-featured-preview-main img').attr('src') || ''),
            brand: brand,
            logo: logo || '',
            logoSize: Number($('#tp_promo_logo_size').val()) || 30,
            secondaryImage: promoSecondaryUrl,
            secondarySize: Number($('#tp_promo_secondary_size').val()) || 24,
            secondaryPosition: $('#tp_promo_secondary_position').val() || 'top-right',
            site: brand === 'site_name' && window.TP_ADMIN ? TP_ADMIN.site_name : '',
            template: $('#tp_promo_template').val() || 'premium',
            cropX: Number($('#tp_promo_crop_x').val()) || 50,
            cropY: Number($('#tp_promo_crop_y').val()) || 50,
            bgEffect: $('#tp_promo_bg_effect').val() || 'none',
            bgIntensity: Number($('#tp_promo_bg_intensity').val() || 60)
        };
    }

    function promoConfig() {
        var options = {};
        $('.tp-promo-include').each(function() {
            options[String($(this).data('promo-key'))] = $(this).is(':checked');
        });
        var extras = [];
        $('#tp-promo-extra-options .tp-promo-extra-include:checked').each(function() {
            extras.push(String($(this).data('source')));
        });
        var formats = [];
        $('.tp-promo-format-check:checked').each(function() {
            formats.push(String(this.value));
        });
        return {
            template: $('#tp_promo_template').val() || 'premium',
            brand: $('#tp_promo_brand').val() || 'site_name',
            logo_url: promoLogoUrl,
            logo_size: Number($('#tp_promo_logo_size').val()) || 30,
            overlay_url: promoSecondaryUrl,
            overlay_size: Number($('#tp_promo_secondary_size').val()) || 24,
            overlay_position: $('#tp_promo_secondary_position').val() || 'top-right',
            positions: promoPositions,
            options: options,
            extras: extras,
            crop_x: Number($('#tp_promo_crop_x').val()) || 50,
            crop_y: Number($('#tp_promo_crop_y').val()) || 50,
            bg_effect: $('#tp_promo_bg_effect').val() || 'none',
            bg_intensity: Number($('#tp_promo_bg_intensity').val() || 60),
            format: $('#tp_promo_format').val() || '',
            width: parseInt($('#tp_promo_width').val(), 10) || 1200,
            height: parseInt($('#tp_promo_height').val(), 10) || 628,
            formats: formats
        };
    }

    function applyPromoConfig(config) {
        if ( ! config || typeof config !== 'object' ) { return; }
        $('#tp_promo_template').val(config.template || 'premium');
        var format = config.format || '';
        if ( format && $('#tp_promo_format option[value="' + format + '"]').length ) {
            $('#tp_promo_format').val(format);
        } else {
            $('#tp_promo_format').val('');
        }
        var savedWidth = parseInt(config.width, 10);
        var savedHeight = parseInt(config.height, 10);
        $('#tp_promo_width').val(Number.isFinite(savedWidth) ? Math.max(320, Math.min(4096, savedWidth)) : 1200);
        $('#tp_promo_height').val(Number.isFinite(savedHeight) ? Math.max(320, Math.min(4096, savedHeight)) : 628);
        $('#tp-promo-custom-dimensions').prop('hidden', $('#tp_promo_format').val() !== 'custom');
        var brand = config.brand || 'site_name';
        if ( $('#tp_promo_brand option[value="' + brand + '"]').length ) {
            $('#tp_promo_brand').val(brand);
        }
        promoLogoUrl = config.logo_url || '';
        promoSecondaryUrl = config.overlay_url || '';
        promoPositions = config.positions && typeof config.positions === 'object' ? config.positions : {};
        $('#tp_promo_logo_size').val(config.logo_size || 30);
        $('#tp-promo-logo-size-value').text($('#tp_promo_logo_size').val() + '%');
        $('#tp_promo_secondary_size').val(config.overlay_size || 24);
        $('#tp-promo-secondary-size-value').text($('#tp_promo_secondary_size').val() + '%');
        $('#tp_promo_secondary_position').val(config.overlay_position || 'top-right');
        if ( promoLogoUrl ) {
            $('#tp-promo-logo-preview').html('<img src="' + escAttr(promoLogoUrl) + '" alt="Vista previa del logo">');
            $('#tp-promo-remove-logo').prop('hidden', false);
        } else {
            $('#tp-promo-logo-preview').empty();
            $('#tp-promo-remove-logo').prop('hidden', true);
        }
        $('#tp-promo-custom-logo').prop('hidden', $('#tp_promo_brand').val() !== 'custom_logo');
        $('#tp-promo-logo-size-control').prop('hidden', ['site_logo', 'custom_logo'].indexOf($('#tp_promo_brand').val()) === -1);
        if ( promoSecondaryUrl ) {
            $('#tp-promo-secondary-preview').html('<img src="' + escAttr(promoSecondaryUrl) + '" alt="Vista previa de la imagen adicional">');
            $('#tp-promo-remove-secondary').prop('hidden', false);
            $('#tp-promo-secondary-options').prop('hidden', false);
        } else {
            $('#tp-promo-secondary-preview').empty();
            $('#tp-promo-remove-secondary').prop('hidden', true);
            $('#tp-promo-secondary-options').prop('hidden', true);
        }
        $('.tp-promo-include').each(function() {
            var key = String($(this).data('promo-key'));
            if ( config.options && Object.prototype.hasOwnProperty.call(config.options, key) ) {
                $(this).prop('checked', !!config.options[key]);
            }
        });
        $('#tp_promo_crop_x').val(config.crop_x === undefined ? 50 : config.crop_x);
        $('#tp_promo_crop_y').val(config.crop_y === undefined ? 50 : config.crop_y);
        $('#tp-promo-crop-value').text($('#tp_promo_crop_x').val() + '% · ' + $('#tp_promo_crop_y').val() + '%');
        var bgEffect = config.bg_effect || 'none';
        if ( $('#tp_promo_bg_effect option[value="' + bgEffect + '"]').length ) {
            $('#tp_promo_bg_effect').val(bgEffect);
        } else {
            $('#tp_promo_bg_effect').val('none');
        }
        var bgIntensity = parseInt(config.bg_intensity, 10);
        $('#tp_promo_bg_intensity').val(Number.isFinite(bgIntensity) ? Math.max(0, Math.min(100, bgIntensity)) : 60);
        updatePromoBackgroundEffectUI();
        $('.tp-promo-format-check').each(function() {
            $(this).prop('checked', Array.isArray(config.formats) ? config.formats.indexOf(String(this.value)) !== -1 : true);
        });
        var selectedExtras = Array.isArray(config.extras) ? config.extras : [];
        updatePromoExtraOptions();
        $('#tp-promo-extra-options .tp-promo-extra-include').each(function() {
            $(this).prop('checked', selectedExtras.indexOf(String($(this).data('source'))) !== -1);
        });
        resetPromoPreview();
    }

    function promoLoadImage(url, done) {
        if ( ! url ) { done(null); return; }
        if ( promoImageCache[url] ) {
            if ( promoImageCache[url].loaded ) {
                done(promoImageCache[url].image);
            } else {
                promoImageCache[url].callbacks.push(done);
            }
            return;
        }
        var img = new Image();
        var imageState = { loaded: false, image: null, callbacks: [done] };
        promoImageCache[url] = imageState;
        img.crossOrigin = 'anonymous';
        img.onload = function() {
            imageState.loaded = true;
            imageState.image = img;
            imageState.callbacks.forEach(function(callback) { callback(img); });
            imageState.callbacks = [];
        };
        img.onerror = function() {
            imageState.loaded = true;
            imageState.callbacks.forEach(function(callback) { callback(null); });
            imageState.callbacks = [];
        };
        img.src = url;
    }

    function promoWrapText(ctx, text, maxWidth, maxLines, font) {
        ctx.font = font;
        var words = String(text || '').replace(/\s+/g, ' ').trim().split(' ');
        var lines = [];
        var line = '';
        var i = 0;

        for ( i = 0; i < words.length; i++ ) {
            var test = line ? line + ' ' + words[i] : words[i];
            if ( ctx.measureText(test).width <= maxWidth || ! line ) {
                line = test;
            } else {
                lines.push(line);
                line = words[i];
                if ( lines.length === maxLines ) { break; }
            }
        }

        if ( line && lines.length < maxLines ) { lines.push(line); }

        if ( lines.length === maxLines && i < words.length - 1 ) {
            var last = lines[maxLines - 1];
            while ( last.length > 0 && ctx.measureText(last + '…').width > maxWidth ) {
                last = last.slice(0, -1);
            }
            lines[maxLines - 1] = last + '…';
        }

        return lines;
    }

    function promoRoundRect(ctx, x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }

    function promoDimensions(override) {
        if ( override ) { return override; }
        var format = $('#tp_promo_format').val();
        if ( ! format ) { return null; }
        if ( format === 'story' ) {
            return { W: 1080, H: 1920 };
        }
        if ( format === 'landscape' ) {
            return { W: 1200, H: 628 };
        }
        if ( format === 'custom' ) {
            var width = parseInt($('#tp_promo_width').val(), 10);
            var height = parseInt($('#tp_promo_height').val(), 10);
            if ( ! Number.isFinite(width) || ! Number.isFinite(height) || width < 320 || width > 4096 || height < 320 || height > 4096 ) {
                return null;
            }
            return { W: width, H: height };
        }
        return { W: 1080, H: 1080 };
    }

    function promoFormatKey(dimensions) {
        var dims = dimensions || promoDimensions();
        if ( ! dims ) { return ''; }
        if ( dims.W === 1080 && dims.H === 1080 ) { return 'square'; }
        if ( dims.W === 1080 && dims.H === 1920 ) { return 'story'; }
        if ( dims.W === 1200 && dims.H === 628 ) { return 'landscape'; }
        return 'custom-' + dims.W + 'x' + dims.H;
    }

    function promoScopedPositionKey(key) {
        return (promoDrawingFormatKey || promoFormatKey()) + ':' + key;
    }

    function promoCustomPosition(key) {
        var scoped = promoPositions[promoScopedPositionKey(key)];
        var saved = scoped || promoPositions[key];
        return saved && Number.isFinite(Number(saved.x)) && Number.isFinite(Number(saved.y))
            ? saved
            : null;
    }

    function promoDeletePosition(key) {
        Object.keys(promoPositions).forEach(function(storedKey) {
            if ( storedKey === key || storedKey.slice(-(key.length + 1)) === ':' + key ) {
                delete promoPositions[storedKey];
            }
        });
    }

    function promoPosition(key, defaultX, defaultY, width, height) {
        var saved = promoCustomPosition(key);
        return saved
            ? {
                x: Math.max(0, Math.min(100, Number(saved.x))) * width / 100,
                y: Math.max(0, Math.min(100, Number(saved.y))) * height / 100
            }
            : { x: defaultX, y: defaultY };
    }

    function promoAddHitArea(key, x, y, width, height, canvasWidth, canvasHeight) {
        if ( width <= 0 || height <= 0 ) { return; }
        promoHitAreas.push({
            key: promoScopedPositionKey(key),
            x: x,
            y: y,
            width: width,
            height: height,
            position: {
                x: Math.max(0, Math.min(100, x / canvasWidth * 100)),
                y: Math.max(0, Math.min(100, y / canvasHeight * 100))
            }
        });
    }

    function promoBackgroundFilter(effect, intensity, scale) {
        var amount = Number(intensity);
        if ( ! isFinite(amount) ) { amount = 60; }
        amount = Math.max(0, Math.min(100, amount)) / 100;
        if ( effect === 'none' || amount <= 0 ) { return 'none'; }
        var blurScale = Math.max(0.65, Math.min(2.5, Number(scale) || 1));
        switch ( effect ) {
            case 'soft':
                return 'blur(' + ((1.5 + amount * 3.5) * blurScale).toFixed(1) + 'px) brightness(' + (1 + amount * 0.06).toFixed(2) + ') saturate(' + (1 + amount * 0.08).toFixed(2) + ')';
            case 'blur':
                return 'blur(' + ((2 + amount * 10) * blurScale).toFixed(1) + 'px) brightness(' + (1 + amount * 0.03).toFixed(2) + ')';
            case 'bw':
                return 'grayscale(' + amount.toFixed(2) + ') contrast(' + (1 + amount * 0.12).toFixed(2) + ')';
            case 'warm':
                return 'sepia(' + (amount * 0.65).toFixed(2) + ') saturate(' + (1 + amount * 0.25).toFixed(2) + ') brightness(' + (1 + amount * 0.04).toFixed(2) + ')';
            case 'cool':
                return 'saturate(' + (1 + amount * 0.18).toFixed(2) + ') hue-rotate(' + (-amount * 12).toFixed(1) + 'deg) brightness(' + (1 + amount * 0.02).toFixed(2) + ')';
            case 'dramatic':
                return 'contrast(' + (1 + amount * 0.3).toFixed(2) + ') saturate(' + (1 + amount * 0.25).toFixed(2) + ') brightness(' + (1 - amount * 0.08).toFixed(2) + ')';
            default:
                return 'none';
        }
    }

    function updatePromoBackgroundEffectUI() {
        var effect = $('#tp_promo_bg_effect').val() || 'none';
        var intensity = $('#tp_promo_bg_intensity').val() || 60;
        $('#tp-promo-bg-intensity-value').text(intensity + '%');
        $('#tp_promo_bg_intensity').prop('disabled', effect === 'none');
    }

    function promoDraw(data, img, dimensions) {
        var canvas = document.getElementById('tp-promo-canvas');
        if ( ! canvas ) { return; }

        var dims = promoDimensions(dimensions);
        if ( ! dims ) { return; }
        promoDrawingFormatKey = promoFormatKey(dims);
        var W = dims.W;
        var H = dims.H;
        var isStory = H > W;
        var fontScale = Math.min(W, H) / 1080;
        var pad = Math.round((isStory ? 84 : 72) * fontScale);
        var fontPair = promoFonts();
        var template = data.template || 'premium';
        var palette = window.TP_ADMIN && TP_ADMIN.promo_palette ? TP_ADMIN.promo_palette : {};
        var primary = palette.primary || '#0f766e';
        var accent = palette.accent || '#0f172a';
        var highlight = palette.highlight || '#b8fff0';
        var bgEffect = data.bgEffect || 'none';
        var bgIntensity = Number(data.bgIntensity);
        if ( ! isFinite(bgIntensity) ) { bgIntensity = 60; }
        var bgFilter = promoBackgroundFilter(bgEffect, bgIntensity, fontScale);

        if ( canvas.width !== W ) { canvas.width = W; }
        if ( canvas.height !== H ) { canvas.height = H; }

        var ctx = canvas.getContext('2d');
        promoHitAreas = [];
        if ( 'imageSmoothingQuality' in ctx ) { ctx.imageSmoothingQuality = 'high'; }

        ctx.clearRect(0, 0, W, H);

        var bg = ctx.createLinearGradient(0, 0, W, H);
        bg.addColorStop(0, template === 'adventure' ? accent : primary);
        bg.addColorStop(1, accent);
        ctx.fillStyle = bg;
        ctx.fillRect(0, 0, W, H);

        if ( img && img.width && img.height ) {
            var imageScale = Math.max(W / img.width, H / img.height);
            var dw = img.width * imageScale;
            var dh = img.height * imageScale;
            var cropX = Math.max(0, Math.min(100, Number(data.cropX) || 50)) / 100;
            var cropY = Math.max(0, Math.min(100, Number(data.cropY) || 50)) / 100;
            if ( bgFilter !== 'none' && 'filter' in ctx ) {
                ctx.filter = bgFilter;
            }
            ctx.drawImage(img, (W - dw) * cropX, (H - dh) * cropY, dw, dh);
            if ( 'filter' in ctx ) {
                ctx.filter = 'none';
            }
        }

        var shade = ctx.createLinearGradient(0, H * 0.2, 0, H);
        shade.addColorStop(0, template === 'minimal' ? 'rgba(8,15,30,0.12)' : 'rgba(8,15,30,0)');
        shade.addColorStop(0.55, template === 'minimal' ? 'rgba(8,15,30,0.52)' : 'rgba(8,15,30,0.45)');
        shade.addColorStop(1, template === 'minimal' ? 'rgba(8,15,30,0.86)' : 'rgba(8,15,30,0.94)');
        ctx.fillStyle = shade;
        ctx.fillRect(0, 0, W, H);

        var topShade = ctx.createLinearGradient(0, 0, 0, H * 0.3);
        topShade.addColorStop(0, 'rgba(8,15,30,0.7)');
        topShade.addColorStop(1, 'rgba(8,15,30,0)');
        ctx.fillStyle = topShade;
        ctx.fillRect(0, 0, W, H * 0.3);

        if ( template === 'premium' ) {
            ctx.strokeStyle = 'rgba(255,255,255,0.54)';
            ctx.lineWidth = Math.max(2, Math.round(2 * fontScale));
            ctx.strokeRect(Math.round(26 * fontScale), Math.round(26 * fontScale), W - Math.round(52 * fontScale), H - Math.round(52 * fontScale));
        } else if ( template === 'adventure' ) {
            ctx.fillStyle = primary;
            ctx.beginPath();
            ctx.moveTo(W, 0);
            ctx.lineTo(W, Math.round(H * 0.28));
            ctx.lineTo(Math.round(W * 0.68), 0);
            ctx.closePath();
            ctx.fill();
        }

        var top = Math.round((isStory ? 80 : 56) * fontScale);
        var headerGap = Math.round(20 * fontScale);
        var blockGap = Math.round(24 * fontScale);
        var secondaryPosition = data.secondaryPosition || 'top-right';
        var secondaryIsTop = secondaryPosition.indexOf('bottom') === -1;
        var secondaryIsLeft = secondaryPosition.indexOf('left') !== -1;

        var secondaryMetrics = null;
        if ( data.secondaryImg && data.secondaryImg.width && data.secondaryImg.height ) {
            var secondaryMaxWidth = Math.min(W * Math.max(10, Math.min(50, Number(data.secondarySize) || 24)) / 100, W - pad * 2);
            var secondaryMaxHeight = H * (isStory ? 0.18 : 0.22);
            var secondaryScale = Math.min(secondaryMaxWidth / data.secondaryImg.width, secondaryMaxHeight / data.secondaryImg.height);
            var secondaryWidth = data.secondaryImg.width * secondaryScale;
            var secondaryHeight = data.secondaryImg.height * secondaryScale;
            var secondaryPadding = Math.round(10 * fontScale);
            secondaryMetrics = {
                imgWidth: secondaryWidth,
                imgHeight: secondaryHeight,
                padding: secondaryPadding,
                width: secondaryWidth + secondaryPadding * 2,
                height: secondaryHeight + secondaryPadding * 2
            };
        }

        var brandMaxWidth = W - pad * 2;
        var brandDefaultX = pad;
        if ( secondaryMetrics && secondaryIsTop ) {
            brandMaxWidth = Math.max(80, brandMaxWidth - secondaryMetrics.width - headerGap);
            if ( secondaryIsLeft ) {
                brandDefaultX = pad + secondaryMetrics.width + headerGap;
                brandMaxWidth = Math.max(80, W - brandDefaultX - pad);
            }
        }

        var brandBottom = top;
        if ( data.brand === 'site_name' && data.site ) {
            var siteSize = Math.round((isStory ? 28 : 26) * fontScale);
            var siteText = String(data.site).toUpperCase();
            ctx.font = promoFont(700, siteSize, fontPair.body);
            while ( siteSize > 12 && ctx.measureText(siteText).width > brandMaxWidth ) {
                siteSize -= Math.max(1, Math.round(fontScale));
                ctx.font = promoFont(700, siteSize, fontPair.body);
            }
            ctx.fillStyle = 'rgba(255,255,255,0.92)';
            ctx.textBaseline = 'top';
            var brandPosition = promoPosition('brand', brandDefaultX, top, W, H);
            brandPosition.x = Math.max(0, Math.min(brandPosition.x, W - pad - 40));
            brandPosition.y = Math.max(0, Math.min(brandPosition.y, H - siteSize - 8));
            ctx.fillText(siteText, brandPosition.x, brandPosition.y, brandMaxWidth);
            promoAddHitArea('brand', brandPosition.x, brandPosition.y, Math.min(ctx.measureText(siteText).width, brandMaxWidth), siteSize, W, H);
            brandBottom = brandPosition.y + siteSize;
        } else if ( data.logoImg && data.logoImg.width && data.logoImg.height ) {
            var logoSize = Math.max(10, Math.min(50, Number(data.logoSize) || 30)) / 100;
            var logoMaxWidth = Math.min(W * logoSize, brandMaxWidth);
            var logoMaxHeight = Math.min(H * logoSize, H * (isStory ? 0.18 : 0.22));
            var logoScale = Math.min(logoMaxWidth / data.logoImg.width, logoMaxHeight / data.logoImg.height);
            var logoWidth = data.logoImg.width * logoScale;
            var logoHeight = data.logoImg.height * logoScale;
            var logoPadding = Math.round(12 * fontScale);
            var badgeWidth = logoWidth + logoPadding * 2;
            var badgeHeight = logoHeight + logoPadding * 2;
            var logoPosition = promoPosition('logo', brandDefaultX, top, W, H);
            logoPosition.x = Math.max(0, Math.min(logoPosition.x, W - badgeWidth));
            logoPosition.y = Math.max(0, Math.min(logoPosition.y, H - badgeHeight));
            ctx.fillStyle = 'rgba(255,255,255,0.94)';
            promoRoundRect(ctx, logoPosition.x, logoPosition.y, badgeWidth, badgeHeight, Math.round(10 * fontScale));
            ctx.fill();
            ctx.drawImage(data.logoImg, logoPosition.x + logoPadding, logoPosition.y + logoPadding, logoWidth, logoHeight);
            promoAddHitArea('logo', logoPosition.x, logoPosition.y, badgeWidth, badgeHeight, W, H);
            brandBottom = logoPosition.y + badgeHeight;
        }

        var secondaryBox = null;
        if ( secondaryMetrics ) {
            var defaultSecondaryX = secondaryIsLeft ? pad : W - pad - secondaryMetrics.width;
            var defaultSecondaryY = secondaryIsTop ? top : H - pad - secondaryMetrics.height;
            var secondaryCoords = promoPosition('secondary', defaultSecondaryX, defaultSecondaryY, W, H);
            secondaryCoords.x = Math.max(0, Math.min(W - secondaryMetrics.width, secondaryCoords.x));
            secondaryCoords.y = Math.max(0, Math.min(H - secondaryMetrics.height, secondaryCoords.y));
            ctx.fillStyle = 'rgba(255,255,255,0.94)';
            promoRoundRect(ctx, secondaryCoords.x, secondaryCoords.y, secondaryMetrics.width, secondaryMetrics.height, Math.round(10 * fontScale));
            ctx.fill();
            ctx.drawImage(data.secondaryImg, secondaryCoords.x + secondaryMetrics.padding, secondaryCoords.y + secondaryMetrics.padding, secondaryMetrics.imgWidth, secondaryMetrics.imgHeight);
            promoAddHitArea('secondary', secondaryCoords.x, secondaryCoords.y, secondaryMetrics.width, secondaryMetrics.height, W, H);
            secondaryBox = { x: secondaryCoords.x, y: secondaryCoords.y, width: secondaryMetrics.width, height: secondaryMetrics.height };
        }

        var headerBottom = Math.max(top, brandBottom);
        if ( secondaryBox && secondaryIsTop ) {
            headerBottom = Math.max(headerBottom, secondaryBox.y + secondaryBox.height);
        }
        headerBottom = Math.min(headerBottom, Math.round(H * 0.45));

        var entries = [];
        var dateText = data.dateFrom
            ? ('Salida: ' + data.dateFrom + (data.dateTo ? '  →  ' + data.dateTo : ''))
            : '';
        if ( data.destination ) {
            entries.push({ key: 'destination', text: 'Destino: ' + data.destination, size: 27, weight: 700, family: fontPair.body, color: highlight, lines: 2, gap: 7 });
        }
        if ( data.includeDate && dateText ) {
            entries.push({ key: 'date', text: dateText, size: 26, weight: 600, family: fontPair.body, color: '#fff', lines: 2, gap: 12, pill: true });
        }
        entries.push({ key: 'title', text: data.title, size: isStory ? 72 : 62, weight: 700, family: fontPair.heading, color: '#fff', lines: 3, gap: 16 });
        if ( data.subtitle ) {
            entries.push({ key: 'subtitle', text: data.subtitle, size: isStory ? 34 : 30, weight: 400, family: fontPair.body, color: 'rgba(255,255,255,0.86)', lines: 2, gap: 12 });
        }
        (data.extras || []).forEach(function(text, index) {
            entries.push({ key: 'extra_' + index, text: text, size: 25, weight: 600, family: fontPair.body, color: 'rgba(255,255,255,0.9)', lines: 2, gap: 7 });
        });
        if ( data.financing ) {
            entries.push({ key: 'financing', text: data.financing, size: 25, weight: 700, family: fontPair.body, color: highlight, lines: 2, gap: 12 });
        }
        var priceText = data.price ? 'Desde ' + data.currency + ' ' + promoFormatNumber(data.price) : 'Consultar precio';
        var childText = data.childPrice ? 'Niños ' + data.currency + ' ' + promoFormatNumber(data.childPrice) : '';
        if ( data.price !== '' || data.includePrice ) {
            entries.push({ key: 'price', text: priceText, size: isStory ? 84 : 70, weight: 800, family: fontPair.heading, color: '#fff', lines: 2, gap: childText ? 5 : 0 });
        }
        if ( childText ) {
            entries.push({ key: 'child_price', text: childText, size: isStory ? 30 : 27, weight: 600, family: fontPair.body, color: 'rgba(255,255,255,0.86)', lines: 1, gap: 0 });
        }

        var contactEntries = [];
        (data.contacts || []).forEach(function(text, index) {
            var isWhatsapp = text.indexOf('WhatsApp:') !== -1;
            contactEntries.push({
                key: 'contact_' + index,
                text: text,
                size: isWhatsapp ? 23 : 21,
                weight: isWhatsapp ? 700 : 600,
                family: fontPair.body,
                color: isWhatsapp ? highlight : 'rgba(255,255,255,0.88)',
                lines: 2,
                gap: 6,
                pill: isWhatsapp
            });
        });

        function measureEntryList(list, factor, baseWidth) {
            var totalHeight = 0;
            var measured = [];
            list.forEach(function(entry, index) {
                var size = Math.max(12, Math.round(entry.size * fontScale * factor));
                var font = promoFont(entry.weight, size, entry.family);
                var saved = promoCustomPosition(entry.key);
                var customX = saved ? Math.max(0, Math.min(100, Number(saved.x))) * W / 100 : null;
                var pillPadX = entry.pill ? Math.round(12 * fontScale * factor) : 0;
                var pillPadY = entry.pill ? Math.round(8 * fontScale * factor) : 0;
                var availableWidth = Math.max(40, customX !== null ? W - customX - pad : baseWidth);
                var textWidth = Math.max(40, availableWidth - pillPadX * 2);
                var lines = promoWrapText(ctx, entry.text, textWidth, entry.lines, font);
                var lineHeight = Math.round(size * (entry.size > 50 ? 1.12 : 1.32));
                var offerPadding = template === 'offer' && entry.key === 'price' ? Math.round(18 * fontScale * factor) : 0;
                var glyphHeight = lines.length ? (lines.length - 1) * lineHeight + size : 0;
                var boxHeight = entry.pill ? glyphHeight + pillPadY * 2 : lines.length * lineHeight;
                boxHeight += offerPadding;
                var gapAfter = index < list.length - 1 ? Math.round(entry.gap * fontScale * factor) : 0;
                measured.push({
                    entry: entry,
                    size: size,
                    font: font,
                    lines: lines,
                    lineHeight: lineHeight,
                    textWidth: textWidth,
                    pillPadX: pillPadX,
                    pillPadY: pillPadY,
                    boxHeight: boxHeight,
                    gapAfter: gapAfter
                });
                totalHeight += boxHeight + gapAfter;
            });
            return { items: measured, height: totalHeight };
        }

        function drawEntry(item, x, y, factor) {
            ctx.font = item.font;
            ctx.fillStyle = item.entry.color;
            var textX = Math.max(0, Math.min(x, W - pad - 40));
            var textY = Math.max(0, Math.min(y, H - item.boxHeight - 8));
            var availableWidth = Math.max(40, W - textX - pad);
            var textWidth = item.entry.pill
                ? Math.max(40, Math.min(item.textWidth, availableWidth - item.pillPadX * 2))
                : Math.max(40, Math.min(item.textWidth, availableWidth));
            var baseX = textX;
            var baseY = textY;
            if ( template === 'offer' && item.entry.key === 'price' ) {
                var pricePadding = Math.round(18 * fontScale * factor);
                var priceTextWidth = item.lines.reduce(function(max, line) { return Math.max(max, ctx.measureText(line).width); }, 0);
                var priceHeight = item.lines.length * item.lineHeight + pricePadding;
                var priceWidth = Math.min(priceTextWidth + pricePadding * 2, textWidth);
                ctx.fillStyle = primary;
                promoRoundRect(ctx, textX - pricePadding, textY - Math.round(6 * fontScale * factor), priceWidth, priceHeight, Math.round(14 * fontScale * factor));
                ctx.fill();
                ctx.fillStyle = '#fff';
                textY += Math.round((pricePadding / 2) - (6 * fontScale * factor));
            }
            if ( item.entry.pill && item.lines.length ) {
                var pillTextWidth = item.lines.reduce(function(max, line) { return Math.max(max, ctx.measureText(line).width); }, 0);
                var pillWidth = Math.min(pillTextWidth + item.pillPadX * 2, textWidth + item.pillPadX * 2);
                var pillHeight = item.boxHeight;
                ctx.fillStyle = 'rgba(255,255,255,0.18)';
                promoRoundRect(ctx, textX, textY, pillWidth, pillHeight, pillHeight / 2);
                ctx.fill();
                ctx.fillStyle = item.entry.color;
                textX += item.pillPadX;
                textY += item.pillPadY + Math.round(item.size * 0.05);
            }
            var measuredWidth = 0;
            item.lines.forEach(function(line) {
                var lineWidth = ctx.measureText(line).width;
                measuredWidth = Math.max(measuredWidth, lineWidth);
                ctx.fillText(line, textX, textY, textWidth);
                textY += item.lineHeight;
            });
            var hitWidth = item.entry.pill
                ? Math.min(textWidth + item.pillPadX * 2, measuredWidth + item.pillPadX * 2)
                : Math.min(textWidth, measuredWidth);
            promoAddHitArea(item.entry.key, baseX, baseY, hitWidth, item.boxHeight, W, H);
        }

        var footerX = pad;
        var footerWidth = W - pad * 2;
        if ( secondaryBox && ! secondaryIsTop ) {
            footerWidth = Math.max(80, footerWidth - secondaryBox.width - headerGap);
            if ( secondaryIsLeft ) {
                footerX = W - pad - footerWidth;
            }
        }

        var contactLayout = contactEntries.length ? measureEntryList(contactEntries, 1, footerWidth) : null;
        var footerHeight = contactLayout ? contactLayout.height : 0;
        if ( secondaryBox && ! secondaryIsTop ) {
            footerHeight = Math.max(footerHeight, secondaryBox.height);
        }
        var footerTop = H - pad - footerHeight;
        var footerGap = footerHeight > 0 ? blockGap : 0;
        var mainTop = headerBottom + blockGap;
        var mainBottom = footerTop - footerGap;
        var mainAvailable = Math.max(80 * fontScale, mainBottom - mainTop);

        var scale = 1;
        var layout = measureEntryList(entries, scale, W - pad * 2);
        var guard = 0;
        while ( layout.height > mainAvailable && scale > 0.25 && guard < 12 ) {
            scale = Math.max(0.25, scale * (mainAvailable / layout.height) * 0.98);
            layout = measureEntryList(entries, scale, W - pad * 2);
            guard++;
        }

        ctx.textBaseline = 'top';
        var y = Math.max(mainTop, mainBottom - layout.height);
        layout.items.forEach(function(item) {
            var defaultY = y;
            var textPosition = promoPosition(item.entry.key, pad, defaultY, W, H);
            drawEntry(item, textPosition.x, textPosition.y, scale);
            y += item.boxHeight + item.gapAfter;
        });

        if ( contactLayout && contactLayout.items.length ) {
            ctx.strokeStyle = 'rgba(255,255,255,0.22)';
            ctx.lineWidth = Math.max(1, Math.round(fontScale));
            var lineY = footerTop - Math.round(14 * fontScale);
            var lineStart = pad;
            var lineEnd = W - pad;
            if ( secondaryBox && ! secondaryIsTop ) {
                if ( secondaryIsLeft ) {
                    lineStart = secondaryBox.x + secondaryBox.width + headerGap;
                } else {
                    lineEnd = secondaryBox.x - headerGap;
                }
            }
            if ( lineEnd > lineStart ) {
                ctx.beginPath();
                ctx.moveTo(lineStart, lineY);
                ctx.lineTo(lineEnd, lineY);
                ctx.stroke();
            }
            var contactY = H - pad - contactLayout.height;
            contactLayout.items.forEach(function(item) {
                var defaultY = contactY;
                var textPosition = promoPosition(item.entry.key, footerX, defaultY, W, H);
                drawEntry(item, textPosition.x, textPosition.y, 1);
                contactY += item.boxHeight + item.gapAfter;
            });
        }
    }

    function resetPromoPreview() {
        var canvas = document.getElementById('tp-promo-canvas');
        if ( ! canvas ) { return; }
        window.clearTimeout(promoPreviewTimer);
        var version = ++promoPreviewVersion;
        var format = $('#tp_promo_format').val();
        if ( ! format ) {
            canvas.style.display = 'none';
            $('#tp-download-promo').prop('disabled', true);
            $('#tp-promo-status').text('Elegí un formato para iniciar el preview.');
            return;
        }
        promoPreviewTimer = window.setTimeout(function() {
            var data = promoData();
            var dimensions = promoDimensions();
            if ( ! dimensions ) {
                canvas.style.display = 'none';
                $('#tp-download-promo').prop('disabled', true);
                $('#tp-promo-status').text(format === 'custom'
                    ? 'Ingresá un ancho y alto entre 320 y 4096 px para actualizar el preview.'
                    : 'No se pudo calcular el formato seleccionado.');
                return;
            }
            $('#tp-promo-status').text('Actualizando preview…');
            promoEnsureFonts().then(function() {
                promoLoadImage(data.background, function(background) {
                    promoLoadImage(data.logo, function(logoImg) {
                        promoLoadImage(data.secondaryImage, function(secondaryImg) {
                            if ( version !== promoPreviewVersion ) { return; }
                            data.logoImg = logoImg;
                            data.secondaryImg = secondaryImg;
                            promoLastImages = { background: background, logo: logoImg, secondary: secondaryImg };
                            promoDraw(data, background, dimensions);
                            canvas.style.display = 'block';
                            canvas.removeAttribute('hidden');
                            $('#tp-download-promo').prop('disabled', false);
                            $('#tp-promo-status').text('Preview en tiempo real · arrastrá los elementos para ubicarlos.');
                        });
                    });
                });
            }).catch(function() {
                if ( version === promoPreviewVersion ) {
                    $('#tp-promo-status').text('No se pudo actualizar el preview.');
                }
            });
        }, 140);
    }

    function redrawPromoPreview() {
        var canvas = document.getElementById('tp-promo-canvas');
        if ( ! canvas ) { return; }
        var format = $('#tp_promo_format').val();
        var dimensions = promoDimensions();
        if ( ! format || ! dimensions || canvas.style.display === 'none' ) {
            resetPromoPreview();
            return;
        }
        var data = promoData();
        data.logoImg = promoLastImages.logo;
        data.secondaryImg = promoLastImages.secondary;
        promoDraw(data, promoLastImages.background, dimensions);
        canvas.style.display = 'block';
        canvas.removeAttribute('hidden');
    }

    function promoCanvasPoint(event, canvas) {
        var rect = canvas.getBoundingClientRect();
        return {
            x: (event.clientX - rect.left) * canvas.width / rect.width,
            y: (event.clientY - rect.top) * canvas.height / rect.height
        };
    }

    $(document).on('pointerdown', '#tp-promo-canvas', function(event) {
        if ( ! promoHitAreas.length ) { return; }
        var canvas = this;
        var point = promoCanvasPoint(event.originalEvent, canvas);
        var hit = null;
        for ( var i = promoHitAreas.length - 1; i >= 0; i-- ) {
            var area = promoHitAreas[i];
            if ( point.x >= area.x && point.x <= area.x + area.width && point.y >= area.y && point.y <= area.y + area.height ) {
                hit = area;
                break;
            }
        }
        if ( ! hit ) { return; }
        event.preventDefault();
        promoDrag = {
            key: hit.key,
            x: hit.x,
            y: hit.y,
            pointerX: point.x,
            pointerY: point.y,
            width: canvas.width,
            height: canvas.height,
            areaWidth: hit.width,
            areaHeight: hit.height
        };
        canvas.setPointerCapture(event.originalEvent.pointerId);
        canvas.style.cursor = 'grabbing';
    });

    $(document).on('pointermove', '#tp-promo-canvas', function(event) {
        var canvas = this;
        if ( ! promoDrag ) {
            var point = promoCanvasPoint(event.originalEvent, canvas);
            var isOverElement = promoHitAreas.some(function(area) {
                return point.x >= area.x && point.x <= area.x + area.width && point.y >= area.y && point.y <= area.y + area.height;
            });
            canvas.style.cursor = isOverElement ? 'grab' : 'default';
            return;
        }
        var point = promoCanvasPoint(event.originalEvent, canvas);
        var maxX = Math.max(0, promoDrag.width - promoDrag.areaWidth);
        var maxY = Math.max(0, promoDrag.height - promoDrag.areaHeight);
        var x = Math.max(0, Math.min(maxX, promoDrag.x + point.x - promoDrag.pointerX) / promoDrag.width * 100);
        var y = Math.max(0, Math.min(maxY, promoDrag.y + point.y - promoDrag.pointerY) / promoDrag.height * 100);
        promoPositions[promoDrag.key] = { x: x, y: y };
        redrawPromoPreview();
    });

    $(document).on('pointerup pointercancel lostpointercapture', '#tp-promo-canvas', function() {
        if ( ! promoDrag ) { return; }
        promoDrag = null;
        this.style.cursor = 'grab';
        $('#tp_promo_config').val(JSON.stringify(promoConfig()));
    });

    $(document).on('click', '#tp-promo-reset-positions', function() {
        promoPositions = {};
        $('#tp_promo_config').val(JSON.stringify(promoConfig()));
        resetPromoPreview();
    });

    function generatePromoImage() {
        var data = promoData();
        var canvas = document.getElementById('tp-promo-canvas');
        if ( ! canvas ) { return; }

        if ( ! $('#tp_promo_format').val() ) {
            $('#tp-promo-status').text('Elegí un formato para crear la imagen.');
            $('#tp_promo_format').trigger('focus');
            return;
        }

        if ( data.brand === 'custom_logo' && ! data.logo ) {
            $('#tp-promo-status').text('Seleccioná un logo o elegí otra opción de marca.');
            $('#tp-promo-select-logo').trigger('focus');
            return;
        }

        var dims = promoDimensions();
        if ( ! dims ) {
            $('#tp-promo-status').text('Ingresá un ancho y alto entre 320 y 4096 px.');
            $('#tp_promo_width').trigger('focus');
            return;
        }

        $('#tp-promo-status').text('Generando imagen…');
        $('#tp-download-promo').prop('disabled', true);

        promoEnsureFonts().then(function(fontsLoaded) {
            promoLoadImage(data.background, function(img) {
                promoLoadImage(data.logo, function(logoImg) {
                    data.logoImg = logoImg;
                    promoLoadImage(data.secondaryImage, function(secondaryImg) {
                        data.secondaryImg = secondaryImg;
                        promoLastImages = { background: img, logo: logoImg, secondary: secondaryImg };
                        promoDraw(data, img);
                        canvas.style.display = 'block';
                        canvas.removeAttribute('hidden');
                        $('#tp-download-promo').prop('disabled', false);

                        var status = ! data.background
                            ? 'Imagen creada sin foto de fondo. Seleccioná una imagen principal para mejorar el resultado.'
                            : ( ! img ? 'No se pudo cargar la imagen principal. Se generó la imagen con fondo de color.' : 'Imagen lista. Podés descargarla como PNG.' );
                        if ( data.logo && ! logoImg ) {
                            status += ' No se pudo cargar el logo seleccionado.';
                        }
                        if ( data.secondaryImage && ! secondaryImg ) {
                            status += ' No se pudo cargar la imagen adicional seleccionada.';
                        }
                        if ( ! fontsLoaded ) {
                            status += ' No se pudieron cargar las tipografías seleccionadas; se usó la fuente alternativa.';
                        }
                        $('#tp-promo-status').text(status);
                    });
                });
            });
        });
    }

    function downloadPromoImage() {
        var canvas = document.getElementById('tp-promo-canvas');
        if ( ! canvas || canvas.style.display === 'none' || ! canvas.toBlob ) { return; }
        if ( ! $('#tp_promo_format').val() ) {
            $('#tp-promo-status').text('Elegí un formato para descargar la imagen.');
            $('#tp-download-promo').prop('disabled', true);
            return;
        }
        var dims = promoDimensions();
        if ( ! dims ) {
            $('#tp-promo-status').text('Ingresá un ancho y alto entre 320 y 4096 px.');
            $('#tp-download-promo').prop('disabled', true);
            return;
        }
        var filename = 'paquete-' + promoSlug($('#tp_title').val()) + '-' + dims.W + 'x' + dims.H + '.png';

        try {
            canvas.toBlob(function(blob) {
                if ( ! blob ) {
                    $('#tp-promo-status').text('No se pudo exportar la imagen.');
                    return;
                }
                var url = URL.createObjectURL(blob);
                var link = document.createElement('a');
                link.href = url;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                link.remove();
                setTimeout(function() { URL.revokeObjectURL(url); }, 1000);
            }, 'image/png');
        } catch (err) {
            $('#tp-promo-status').text('No se pudo exportar la imagen. Verificá que la imagen principal sea del mismo dominio.');
        }
    }

    function promoCrc32(bytes) {
        var crc = -1;
        for ( var i = 0; i < bytes.length; i++ ) {
            crc ^= bytes[i];
            for ( var bit = 0; bit < 8; bit++ ) {
                crc = (crc >>> 1) ^ ((crc & 1) ? 0xedb88320 : 0);
            }
        }
        return (crc ^ -1) >>> 0;
    }

    function promoZip(files) {
        var encoder = new TextEncoder();
        var localParts = [];
        var centralParts = [];
        var offset = 0;
        var dosTime = 0;
        var now = new Date();
        var dosDate = ((now.getFullYear() - 1980) << 9) | ((now.getMonth() + 1) << 5) | now.getDate();
        files.forEach(function(file) {
            var name = encoder.encode(file.name);
            var data = new Uint8Array(file.bytes);
            var crc = promoCrc32(data);
            var localHeader = new Uint8Array(30 + name.length);
            var localView = new DataView(localHeader.buffer);
            localView.setUint32(0, 0x04034b50, true);
            localView.setUint16(4, 20, true);
            localView.setUint16(6, 0, true);
            localView.setUint16(8, 0, true);
            localView.setUint16(10, dosTime, true);
            localView.setUint16(12, dosDate, true);
            localView.setUint32(14, crc, true);
            localView.setUint32(18, data.length, true);
            localView.setUint32(22, data.length, true);
            localView.setUint16(26, name.length, true);
            localView.setUint16(28, 0, true);
            localHeader.set(name, 30);
            localParts.push(localHeader, data);

            var centralHeader = new Uint8Array(46 + name.length);
            var centralView = new DataView(centralHeader.buffer);
            centralView.setUint32(0, 0x02014b50, true);
            centralView.setUint16(4, 20, true);
            centralView.setUint16(6, 20, true);
            centralView.setUint16(8, 0, true);
            centralView.setUint16(10, 0, true);
            centralView.setUint16(12, dosTime, true);
            centralView.setUint16(14, dosDate, true);
            centralView.setUint32(16, crc, true);
            centralView.setUint32(20, data.length, true);
            centralView.setUint32(24, data.length, true);
            centralView.setUint16(28, name.length, true);
            centralView.setUint16(30, 0, true);
            centralView.setUint16(32, 0, true);
            centralView.setUint16(34, 0, true);
            centralView.setUint16(36, 0, true);
            centralView.setUint32(38, 0, true);
            centralView.setUint32(42, offset, true);
            centralHeader.set(name, 46);
            centralParts.push(centralHeader);
            offset += localHeader.length + data.length;
        });

        var centralSize = centralParts.reduce(function(total, part) { return total + part.length; }, 0);
        var end = new Uint8Array(22);
        var endView = new DataView(end.buffer);
        endView.setUint32(0, 0x06054b50, true);
        endView.setUint16(4, 0, true);
        endView.setUint16(6, 0, true);
        endView.setUint16(8, files.length, true);
        endView.setUint16(10, files.length, true);
        endView.setUint32(12, centralSize, true);
        endView.setUint32(16, offset, true);
        endView.setUint16(20, 0, true);
        return new Blob(localParts.concat(centralParts, [end]), { type: 'application/zip' });
    }

    function downloadPromoBatch() {
        var formats = {
            square: { name: 'instagram-whatsapp-cuadrado', dimensions: { W: 1080, H: 1080 } },
            story: { name: 'stories-reels-vertical', dimensions: { W: 1080, H: 1920 } },
            landscape: { name: 'facebook-horizontal', dimensions: { W: 1200, H: 628 } }
        };
        var selected = [];
        $('.tp-promo-format-check:checked').each(function() {
            if ( formats[this.value] ) { selected.push(formats[this.value]); }
        });
        if ( ! selected.length ) {
            $('#tp-promo-status').text('Seleccioná al menos un formato para exportar.');
            return;
        }

        var data = promoData();
        if ( data.brand === 'custom_logo' && ! data.logo ) {
            $('#tp-promo-status').text('Seleccioná un logo o elegí otra opción de marca.');
            return;
        }
        $('#tp-promo-status').text('Preparando las imágenes…');
        $('#tp-download-promo-batch').prop('disabled', true);
        promoEnsureFonts().then(function() {
            promoLoadImage(data.background, function(background) {
                promoLoadImage(data.logo, function(logoImg) {
                    data.logoImg = logoImg;
                    promoLoadImage(data.secondaryImage, function(secondaryImg) {
                        data.secondaryImg = secondaryImg;
                        promoLastImages = { background: background, logo: logoImg, secondary: secondaryImg };
                        var canvas = document.getElementById('tp-promo-canvas');
                        var files = [];
                        var chain = Promise.resolve();
                        selected.forEach(function(format) {
                            chain = chain.then(function() {
                                promoDraw(data, background, format.dimensions);
                                return new Promise(function(resolve, reject) {
                                    canvas.toBlob(function(blob) {
                                        if ( ! blob ) {
                                            reject(new Error('No se pudo crear el PNG para ' + format.name + '.'));
                                            return;
                                        }
                                        blob.arrayBuffer().then(function(buffer) {
                                            files.push({
                                                name: 'paquete-' + promoSlug($('#tp_title').val()) + '-' + format.name + '.png',
                                                bytes: buffer
                                            });
                                            resolve();
                                        }, reject);
                                    }, 'image/png');
                                });
                            });
                        });
                        chain.then(function() {
                            var zipUrl = URL.createObjectURL(promoZip(files));
                            var link = document.createElement('a');
                            link.href = zipUrl;
                            link.download = 'paquete-' + promoSlug($('#tp_title').val()) + '-redes.zip';
                            document.body.appendChild(link);
                            link.click();
                            link.remove();
                            setTimeout(function() { URL.revokeObjectURL(zipUrl); }, 2000);
                            if ( $('#tp_promo_format').val() ) {
                                promoDraw(data, background);
                                canvas.style.display = 'block';
                                canvas.removeAttribute('hidden');
                            } else {
                                canvas.style.display = 'none';
                            }
                            $('#tp-promo-status').text('Listo. Se descargaron ' + files.length + ' formatos en un ZIP.');
                        }).catch(function(error) {
                            $('#tp-promo-status').text(error.message || 'No se pudieron exportar todos los formatos.');
                        }).then(function() {
                            $('#tp-download-promo-batch').prop('disabled', false);
                        });
                    });
                });
            });
        });
    }

    function savePromoConfig() {
        var postId = parseInt($('#tp_package_id').val(), 10) || 0;
        if ( ! postId ) {
            $('#tp-promo-status').text('Guardá el paquete primero; después vas a poder guardar el diseño para volver a usarlo.');
            return;
        }
        $('#tp-promo-status').text('Guardando diseño…');
        $.post(TP_ADMIN.ajax_url, {
            action: 'tp_save_promo_config',
            nonce: TP_ADMIN.nonce,
            post_id: postId,
            config: JSON.stringify(promoConfig())
        }).done(function(response) {
            if ( response && response.success ) {
                $('#tp-promo-status').text(response.data.message || 'Diseño guardado para este paquete.');
            } else {
                $('#tp-promo-status').text(response && response.data && response.data.message ? response.data.message : 'No se pudo guardar el diseño.');
            }
        }).fail(function(xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
            $('#tp-promo-status').text(message || 'Error al guardar el diseño. Verificá tus permisos e intentá nuevamente.');
        });
    }

    function setPromoTemplateStatus(message) {
        $('#tp-promo-template-status').text(message || '');
    }

    function findPromoTemplate(id) {
        var found = null;
        (promoTemplates || []).forEach(function(template) {
            if ( String(template.id) === String(id) ) { found = template; }
        });
        return found;
    }

    function renderPromoTemplates(selectedId) {
        var $select = $('#tp_promo_saved_template');
        if ( ! $select.length ) { return; }
        var current = selectedId || $select.val() || '';
        $select.empty().append($('<option>').val('').text('Elegí una plantilla guardada…'));
        (promoTemplates || []).forEach(function(template) {
            $select.append($('<option>').val(template.id).text(template.name));
        });
        if ( current && $select.find('option').filter(function() { return String(this.value) === String(current); }).length ) {
            $select.val(current);
        }
        $('#tp-promo-apply-template, #tp-promo-delete-template').prop('disabled', ! $select.val());
    }

    function promoTemplateConfig() {
        var config = promoConfig();
        config.extras = (config.extras || []).filter(function(source) {
            return String(source).indexOf('custom:') === 0;
        });
        var positions = {};
        Object.keys(config.positions || {}).forEach(function(key) {
            if ( /(?:^|:)extra_\d+$/.test(key) ) { return; }
            positions[key] = config.positions[key];
        });
        config.positions = positions;
        return config;
    }

    function savePromoTemplate() {
        var name = $.trim(String($('#tp_promo_template_name').val() || ''));
        var config = promoTemplateConfig();
        if ( ! config.format ) {
            setPromoTemplateStatus('Elegí un formato antes de guardar la plantilla.');
            return;
        }
        if ( ! name ) {
            setPromoTemplateStatus('Ingresá un nombre para la plantilla.');
            $('#tp_promo_template_name').trigger('focus');
            return;
        }
        setPromoTemplateStatus('Guardando plantilla…');
        $.post(TP_ADMIN.ajax_url, {
            action: 'tp_save_promo_template',
            nonce: TP_ADMIN.nonce,
            name: name,
            config: JSON.stringify(config)
        }).done(function(response) {
            if ( response && response.success ) {
                promoTemplates = Array.isArray(response.data.templates) ? response.data.templates : promoTemplates;
                renderPromoTemplates(response.data.id);
                setPromoTemplateStatus(response.data.message || 'Plantilla guardada.');
            } else {
                setPromoTemplateStatus(response && response.data && response.data.message ? response.data.message : 'No se pudo guardar la plantilla.');
            }
        }).fail(function(xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
            setPromoTemplateStatus(message || 'Error al guardar la plantilla. Verificá tus permisos e intentá nuevamente.');
        });
    }

    function deletePromoTemplate() {
        var id = $('#tp_promo_saved_template').val();
        if ( ! id ) {
            setPromoTemplateStatus('Elegí una plantilla para eliminar.');
            return;
        }
        var template = findPromoTemplate(id);
        var label = template ? template.name : 'esta plantilla';
        if ( ! window.confirm('¿Eliminar la plantilla "' + label + '"?') ) { return; }
        setPromoTemplateStatus('Eliminando plantilla…');
        $.post(TP_ADMIN.ajax_url, {
            action: 'tp_delete_promo_template',
            nonce: TP_ADMIN.nonce,
            template_id: id
        }).done(function(response) {
            if ( response && response.success ) {
                promoTemplates = Array.isArray(response.data.templates) ? response.data.templates : [];
    renderPromoTemplates();
    updatePromoBackgroundEffectUI();

    $(document).on('change', '#tp_promo_bg_effect', function() {
        updatePromoBackgroundEffectUI();
        resetPromoPreview();
    });

    $(document).on('input change', '#tp_promo_bg_intensity', function() {
        updatePromoBackgroundEffectUI();
        resetPromoPreview();
    });

                setPromoTemplateStatus(response.data.message || 'Plantilla eliminada.');
            } else {
                setPromoTemplateStatus(response && response.data && response.data.message ? response.data.message : 'No se pudo eliminar la plantilla.');
            }
        }).fail(function(xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
            setPromoTemplateStatus(message || 'Error al eliminar la plantilla. Verificá tus permisos e intentá nuevamente.');
        });
    }

    $(document).on('change', '#tp_promo_saved_template', function() {
        $('#tp-promo-apply-template, #tp-promo-delete-template').prop('disabled', ! $(this).val());
    });

    $(document).on('click', '#tp-promo-apply-template', function(e) {
        e.preventDefault();
        var template = findPromoTemplate($('#tp_promo_saved_template').val());
        if ( ! template || ! template.config ) {
            setPromoTemplateStatus('Elegí una plantilla guardada para aplicarla.');
            return;
        }
        applyPromoConfig(template.config);
        $('#tp_promo_template_name').val(template.name || '');
        setPromoTemplateStatus('Plantilla "' + template.name + '" aplicada. Guardá el diseño del paquete para conservar los cambios.');
    });

    $(document).on('click', '#tp-promo-save-template', function(e) {
        e.preventDefault();
        savePromoTemplate();
    });

    $(document).on('click', '#tp-promo-delete-template', function(e) {
        e.preventDefault();
        deletePromoTemplate();
    });

    renderPromoTemplates();

    $(document).on('change', '#tp_promo_format', function() {
        var isCustom = $(this).val() === 'custom';
        $('#tp-promo-custom-dimensions').prop('hidden', ! isCustom);
        if ( isCustom ) {
            if ( ! String($('#tp_promo_width').val() || '').trim() ) {
                $('#tp_promo_width').val(1200);
            }
            if ( ! String($('#tp_promo_height').val() || '').trim() ) {
                $('#tp_promo_height').val(628);
            }
        }
        resetPromoPreview();
    });

    $(document).on('input change', '#tp_promo_width, #tp_promo_height', function() {
        resetPromoPreview();
    });

    $(document).on('input change', '#tp_promo_template, #tp_promo_crop_x, #tp_promo_crop_y, .tp-promo-format-check', function() {
        $('#tp-promo-crop-value').text($('#tp_promo_crop_x').val() + '% · ' + $('#tp_promo_crop_y').val() + '%');
        resetPromoPreview();
    });

    $(document).on('change', '#tp_promo_brand', function() {
        $('#tp-promo-custom-logo').prop('hidden', $(this).val() !== 'custom_logo');
        $('#tp-promo-logo-size-control').prop('hidden', ['site_logo', 'custom_logo'].indexOf($(this).val()) === -1);
        resetPromoPreview();
    });

    $(document).on('input change', '#tp_promo_logo_size', function() {
        $('#tp-promo-logo-size-value').text($(this).val() + '%');
        resetPromoPreview();
    });

    $(document).on('input change', '#tp_promo_secondary_size, #tp_promo_secondary_position', function() {
        $('#tp-promo-secondary-size-value').text($('#tp_promo_secondary_size').val() + '%');
        resetPromoPreview();
    });

    $(document).on('change', '#tp_promo_secondary_position', function() {
        promoDeletePosition('secondary');
        resetPromoPreview();
    });

    $(document).on('click', '#tp-promo-select-logo', function(e) {
        e.preventDefault();
        if ( ! window.wp || ! wp.media ) {
            $('#tp-promo-status').text('No se pudo abrir la biblioteca de medios. Recargá el editor e intentá nuevamente.');
            return;
        }
        if ( ! promoLogoFrame ) {
            promoLogoFrame = wp.media({
                title: 'Seleccionar logo para la imagen',
                button: { text: 'Usar este logo' },
                library: { type: 'image' },
                multiple: false
            });
            promoLogoFrame.on('select', function() {
                var attachment = promoLogoFrame.state().get('selection').first().toJSON();
                if ( ! attachment || ! attachment.url ) {
                    $('#tp-promo-status').text('No se pudo obtener la imagen seleccionada.');
                    return;
                }
                promoLogoUrl = attachment.url;
                $('#tp-promo-logo-preview').html('<img src="' + escAttr(promoLogoUrl) + '" alt="Vista previa del logo">');
                $('#tp-promo-remove-logo').prop('hidden', false);
                resetPromoPreview();
            });
        }
        promoLogoFrame.open();
    });

    $(document).on('click', '#tp-promo-remove-logo', function(e) {
        e.preventDefault();
        promoLogoUrl = '';
        $('#tp-promo-logo-preview').empty();
        $(this).prop('hidden', true);
        resetPromoPreview();
    });

    $(document).on('click', '#tp-promo-select-secondary', function(e) {
        e.preventDefault();
        if ( ! window.wp || ! wp.media ) {
            $('#tp-promo-status').text('No se pudo abrir la biblioteca de medios. Recargá el editor e intentá nuevamente.');
            return;
        }
        if ( ! promoSecondaryFrame ) {
            promoSecondaryFrame = wp.media({
                title: 'Seleccionar imagen adicional',
                button: { text: 'Usar esta imagen' },
                library: { type: 'image' },
                multiple: false
            });
            promoSecondaryFrame.on('select', function() {
                var attachment = promoSecondaryFrame.state().get('selection').first().toJSON();
                if ( ! attachment || ! attachment.url ) {
                    $('#tp-promo-status').text('No se pudo obtener la imagen seleccionada.');
                    return;
                }
                promoSecondaryUrl = attachment.url;
                $('#tp-promo-secondary-preview').html('<img src="' + escAttr(promoSecondaryUrl) + '" alt="Vista previa de la imagen adicional">');
                $('#tp-promo-remove-secondary').prop('hidden', false);
                $('#tp-promo-secondary-options').prop('hidden', false);
                resetPromoPreview();
            });
        }
        promoSecondaryFrame.open();
    });

    $(document).on('click', '#tp-promo-remove-secondary', function(e) {
        e.preventDefault();
        promoSecondaryUrl = '';
        promoDeletePosition('secondary');
        $('#tp-promo-secondary-preview').empty();
        $('#tp-promo-secondary-options').prop('hidden', true);
        $(this).prop('hidden', true);
        resetPromoPreview();
    });

    $(document).on('input change', '#tp-tech-fields input, #tp-custom-fields-wrap input, #tp-custom-fields-wrap select, #tp-custom-fields-wrap textarea', function() {
        updatePromoExtraOptions();
        resetPromoPreview();
    });

    $(document).on('click', '#tp-add-tech-field, .tp-remove-field, #tp-add-financing, .tp-remove-financing', function() {
        window.setTimeout(function() {
            updatePromoExtraOptions();
            resetPromoPreview();
        }, 0);
    });

    $(document).on('input change', '#tp_title, #tp_short_desc, #tp_price_adult, #tp_price_child, #tp_currency_base, #tp-prices-list input, #tp-dates-list input, #tp_destino, #tp_new_destino, #tp-financing-list input, .tp-promo-include', resetPromoPreview);

    $(document).on('click', '#tp-generate-promo', function(e) {
        e.preventDefault();
        generatePromoImage();
    });

    $(document).on('click', '#tp-download-promo', function(e) {
        e.preventDefault();
        downloadPromoImage();
    });

    $(document).on('click', '#tp-download-promo-batch', function(e) {
        e.preventDefault();
        downloadPromoBatch();
    });

    $(document).on('click', '#tp-save-promo-config', function(e) {
        e.preventDefault();
        savePromoConfig();
    });

    $('#tp_promo_brand').trigger('change');
    updatePromoExtraOptions();

    function fillPackageForm(data) {
        $('#tp_package_id').val(data.ID || 0);
        $('#tp-delete-package').toggle(!!data.ID);
        $('#tp-form-title').text(data.ID ? 'Editar paquete' : 'Nuevo paquete');
        $('#tp-view-package')
            .attr('href', data.view_url || '#')
            .toggle(!!(data.ID && data.view_url));
        $('.tp-sticky-view-package')
            .attr('href', data.view_url || '#')
            .toggle(!!(data.ID && data.view_url));
        $('#tp_title').val(data.post_title || '');
        $('#tp_short_desc').val(data.tp_short_desc || '').trigger('input');
        $('#tp_video_url').val(data.tp_video_url || '');
        $('#tp_map_location').val(data.tp_map_location || '');
        $('#tp_whatsapp_number').val(data.tp_whatsapp || '');
        $('#tp_seo_title').val(data.tp_seo_title || '').trigger('input');
        $('#tp_seo_description').val(data.tp_seo_description || '').trigger('input');
        $('#tp_seo_noindex').prop('checked', data.tp_seo_noindex === '1');
        $('#tp_seo_image').val(data.tp_seo_image || '');
        if ( data.tp_seo_image_url ) {
            $('#tp-seo-image-preview').html('<img src="' + escAttr(data.tp_seo_image_url) + '" alt="" style="max-width:220px;height:auto;border-radius:8px;display:block;">');
        } else {
            $('#tp-seo-image-preview').empty();
        }
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
        if ( data.tp_tipo_viaje === 'nacional' || data.tp_tipo_viaje === 'internacional' ) {
            $('input[name="tp_tipo_viaje"][value="' + data.tp_tipo_viaje + '"]').prop('checked', true);
        } else {
            $('input[name="tp_tipo_viaje"][value=""]').prop('checked', true);
        }

        // Load featured image
        var featuredImageId = data.tp_featured_image || '';
        $('#tp_featured_image').val(featuredImageId);
        $('#tp_featured_image_url').val(data.tp_featured_image_url || '');
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
                    if ( response.success && response.data && response.data.url ) {
                        $('#tp-featured-preview-main').html('<img src="' + escAttr(response.data.url) + '" alt="" style="max-width:220px;height:auto;border-radius:8px;display:block;">');
                        if ( ! $('#tp_featured_image_url').val() ) {
                            $('#tp_featured_image_url').val(response.data.url);
                        }
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

        $('#tp-financing-list').empty();
        (Array.isArray(data.tp_financing) ? data.tp_financing : []).forEach(function(option) {
            addFinancingRow(option.label || '', !!option.featured);
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
                $wrap.find('.tp-cf-field').filter(function() {
                    return String($(this).data('key')) === String(k);
                }).appendTo($wrap);
            });
        }

        $('#tp-gallery-preview').empty();
        $('#tp_gallery_ids').val('');
        (data.tp_gallery || []).forEach(function(item) {
            addGalleryItem(item.id, item.url);
        });
        updateGalleryIds();
        updatePromoExtraOptions();
        applyPromoConfig(data.tp_promo_config || {});
    }

    function resetPackageForm() {
        packageForm[0].reset();
        $('#tp-title-error').hide();
        $('#tp_title').removeAttr('aria-invalid');
        $('#tp-dates-list .tp-date-error').remove();
        $('#tp-dates-list [aria-invalid="true"]').removeAttr('aria-invalid');
        $('#tp_package_id').val('0');
        $('#tp-form-title').text('Nuevo paquete');
        $('#tp-view-package').hide().attr('href', '#');
        $('.tp-sticky-view-package').hide().attr('href', '#');
        setPackageContent('');
        $('#tp-short-count').text('0');
        $('#tp_video_url').val('');
        $('#tp_map_location').val('');
        $('#tp_whatsapp_number').val('');
        $('#tp_seo_title').val('').trigger('input');
        $('#tp_seo_description').val('').trigger('input');
        $('#tp_seo_noindex').prop('checked', false);
        $('#tp_seo_image').val('');
        $('#tp-seo-image-preview').empty();
        $('#tp-prices-list').empty();
        $('#tp-financing-list').empty();
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
        $('#tp_featured_image_url').val('');
        $('#tp-featured-preview-main').empty();
        promoLogoUrl = '';
        promoPositions = {};
        $('#tp-promo-logo-preview').empty();
        $('#tp-promo-remove-logo').prop('hidden', true);
        promoSecondaryUrl = '';
        $('#tp-promo-secondary-preview').empty();
        $('#tp-promo-remove-secondary').prop('hidden', true);
        $('#tp-promo-secondary-options').prop('hidden', true);
        $('#tp_promo_logo_size').val(30);
        $('#tp-promo-logo-size-value').text('30%');
        $('#tp_promo_secondary_size').val(24);
        $('#tp-promo-secondary-size-value').text('24%');
        $('#tp_promo_secondary_position').val('top-right');
        $('#tp_promo_format').val('');
        $('#tp_promo_width').val(1200);
        $('#tp_promo_height').val(628);
        $('#tp_promo_bg_effect').val('none');
        $('#tp_promo_bg_intensity').val(60);
        updatePromoBackgroundEffectUI();
        $('#tp-promo-custom-dimensions').prop('hidden', true);
        $('#tp_promo_brand').val('site_name').trigger('change');
        $('#tp_promo_saved_template').val('').trigger('change');
        $('#tp_promo_template_name').val('');
        setPromoTemplateStatus('');
        resetPromoPreview();
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
        $('#tp_promo_config').val('');
        updatePromoExtraOptions();
        initOptionalSections();
    }

    function restoreFormData(data) {
        if ( ! data || ! packageForm.length ) {
            return;
        }

        var excludedNames = [
            'tp_price_item[]', 'tp_currency_item[]', 'tp_price_note_item[]', 'tp_price_highlighted[]',
            'tp_financing_option[]', 'tp_financing_featured',
            'tp_dates_uid[]', 'tp_dates_from[]', 'tp_dates_to[]', 'tp_dates_seats[]',
            'tp_dates_seats_booked[]', 'tp_dates_min_pax[]', 'tp_dates_max_pax[]',
            'tp_dates_cutoff[]', 'tp_dates_status[]', 'tp_dates_price_adult[]',
            'tp_dates_price_child[]', 'tp_dates_currency[]', 'tp_tech_key[]', 'tp_tech_val[]',
            'tp_itinerary_day[]', 'tp_itinerary_title[]', 'tp_itinerary_desc[]'
        ];

        packageForm.find('input[name], textarea[name], select[name]').each(function() {
            var name = this.name;
            if ( excludedNames.indexOf(name) !== -1 || name === 'action' || name === 'tp_package_nonce' ) {
                return;
            }

            var match = name.match(/^tp_cf\[([^\]]+)\]$/);
            var dataName = name.replace(/\[\]$/, '');
            var value = match
                ? (data.tp_cf || {})[match[1]]
                : (typeof data[dataName] !== 'undefined' ? data[dataName] : data[name]);

            if ( this.type === 'checkbox' ) {
                if ( /\[\]$/.test(name) ) {
                    this.checked = Array.isArray(value) && value.map(String).indexOf(String(this.value)) !== -1;
                } else {
                    this.checked = value === '1' || value === 1 || value === true;
                }
            } else if ( this.type === 'radio' ) {
                this.checked = value !== undefined && String(value) === String(this.value);
            } else if ( value !== undefined && !Array.isArray(value) ) {
                $(this).val(value);
            }
        });

        setPackageContent(data.tp_content || '');

        var prices = Array.isArray(data.tp_price_item) ? data.tp_price_item : [];
        var currencies = Array.isArray(data.tp_currency_item) ? data.tp_currency_item : [];
        var notes = Array.isArray(data.tp_price_note_item) ? data.tp_price_note_item : [];
        var highlighted = Array.isArray(data.tp_price_highlighted) ? data.tp_price_highlighted.map(String) : [];
        $('#tp-prices-list').empty();
        prices.forEach(function(price, index) {
            addPriceField(price, notes[index] || '', currencies[index] || 'USD', highlighted.indexOf(String(index)) !== -1);
        });

        $('#tp-financing-list').empty();
        (Array.isArray(data.tp_financing) ? data.tp_financing : []).forEach(function(option) {
            addFinancingRow(option.label || '', !!option.featured);
        });

        var dateNames = [
            'tp_dates_uid', 'tp_dates_from', 'tp_dates_to', 'tp_dates_seats',
            'tp_dates_seats_booked', 'tp_dates_min_pax', 'tp_dates_max_pax',
            'tp_dates_cutoff', 'tp_dates_status', 'tp_dates_price_adult',
            'tp_dates_price_child', 'tp_dates_currency'
        ];
        var dateValues = {};
        var dateCount = 0;
        dateNames.forEach(function(name) {
            dateValues[name] = Array.isArray(data[name]) ? data[name] : [];
            dateCount = Math.max(dateCount, dateValues[name].length);
        });
        $('#tp-dates-list').empty();
        for ( var dateIndex = 0; dateIndex < dateCount; dateIndex++ ) {
            var date = {
                uid: dateValues.tp_dates_uid[dateIndex],
                seats: dateValues.tp_dates_seats[dateIndex],
                seats_booked: dateValues.tp_dates_seats_booked[dateIndex],
                min_pax: dateValues.tp_dates_min_pax[dateIndex],
                max_pax: dateValues.tp_dates_max_pax[dateIndex],
                cutoff: dateValues.tp_dates_cutoff[dateIndex],
                status: dateValues.tp_dates_status[dateIndex],
                price_adult: dateValues.tp_dates_price_adult[dateIndex],
                price_child: dateValues.tp_dates_price_child[dateIndex],
                currency: dateValues.tp_dates_currency[dateIndex]
            };
            addDateField(dateValues.tp_dates_from[dateIndex], dateValues.tp_dates_to[dateIndex], date);
        }

        var techKeys = Array.isArray(data.tp_tech_key) ? data.tp_tech_key : [];
        var techValues = Array.isArray(data.tp_tech_val) ? data.tp_tech_val : [];
        $('#tp-tech-fields').empty();
        techKeys.forEach(function(key, index) {
            addTechField(key, techValues[index] || '');
        });

        var itineraryDays = Array.isArray(data.tp_itinerary_day) ? data.tp_itinerary_day : [];
        var itineraryTitles = Array.isArray(data.tp_itinerary_title) ? data.tp_itinerary_title : [];
        var itineraryDescs = Array.isArray(data.tp_itinerary_desc) ? data.tp_itinerary_desc : [];
        $('#tp-itinerary-list').empty();
        itineraryDays.forEach(function(day, index) {
            addItineraryRow(day, itineraryTitles[index] || '', itineraryDescs[index] || '');
        });

        $('#tp_gallery_ids').val(data.tp_gallery || '');
        loadRestoredGallery(data.tp_gallery || '');
        loadRestoredImage(data.tp_featured_image, '#tp-featured-preview-main', '#tp_featured_image_url');
        loadRestoredImage(data.tp_seo_image, '#tp-seo-image-preview');
        updatePromoExtraOptions();
        if ( data.tp_promo_config ) {
            try {
                applyPromoConfig(typeof data.tp_promo_config === 'string' ? JSON.parse(data.tp_promo_config) : data.tp_promo_config);
            } catch (error) {
                $('#tp-promo-status').text('No se pudieron restaurar las opciones del diseño promocional.');
            }
        } else {
            resetPromoPreview();
        }
    }

    function loadRestoredImage(id, target, urlInput) {
        if ( ! id ) {
            $(target).empty();
            if ( urlInput ) {
                $(urlInput).val('');
            }
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'tp_get_image_thumb', id: id, nonce: TP_ADMIN.nonce }
        }).done(function(response) {
            if ( response.success && response.data && response.data.url ) {
                $(target).html('<img src="' + escAttr(response.data.url) + '" alt="" style="max-width:220px;height:auto;border-radius:8px;display:block;">');
                if ( urlInput ) {
                    $(urlInput).val(response.data.url);
                }
            } else {
                console.error('No se pudo recuperar la vista previa de la imagen del paquete.');
            }
        }).fail(function() {
            console.error('Error al recuperar la vista previa de la imagen del paquete.');
        });
    }

    function loadRestoredGallery(rawIds) {
        var ids = String(rawIds || '').split(',').filter(function(id) {
            return /^\d+$/.test(id) && parseInt(id, 10) > 0;
        });
        $('#tp-gallery-preview').empty();
        if ( ! ids.length ) {
            return;
        }

        var loaded = 0;
        var failed = false;
        ids.forEach(function(id) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: { action: 'tp_get_image_thumb', id: id, nonce: TP_ADMIN.nonce }
            }).done(function(response) {
                if ( response.success && response.data && response.data.url ) {
                    addGalleryItem(id, response.data.url);
                } else {
                    failed = true;
                }
            }).fail(function() {
                failed = true;
            }).always(function() {
                loaded++;
                if ( loaded === ids.length && ! failed ) {
                    var $items = $('#tp-gallery-preview .tp-gallery-item');
                    var byId = {};
                    $items.each(function() {
                        byId[String($(this).data('id'))] = $(this);
                    });
                    ids.forEach(function(itemId) {
                        if ( byId[itemId] ) {
                            $('#tp-gallery-preview').append(byId[itemId]);
                        }
                    });
                    updateGalleryIds();
                }
            });
        });
    }

    function initOptionalSections() {
        var optionalIds = [
            'tp-section-seo', 'tp-section-departures', 'tp-section-itinerary',
            'tp-section-extra', 'tp-section-promo'
        ];
        optionalIds.forEach(function(id) {
            var $panel = $('#' + id);
            if ( ! $panel.length ) {
                return;
            }

            var $toggle = $panel.children('.tp-panel-toggle');
            if ( ! $toggle.length ) {
                $toggle = $('<button type="button" class="tp-panel-toggle"></button>')
                    .insertAfter($panel.children('h2').first());
            }

            var hasValue = $panel.find('input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), textarea, select').filter(function() {
                return $.trim($(this).val() || '') !== '';
            }).length > 0 || $panel.find('input[type="checkbox"]:checked').length > 0;

            if ( $panel.find('#tp-dates-list .tp-date-item, #tp-itinerary-list .tp-itinerary-item, #tp-tech-fields .tp-tech-field').length ) {
                hasValue = true;
            }
            if ( id === 'tp-section-promo' ) {
                hasValue = false;
            }

            $panel.toggleClass('is-collapsed', ! hasValue);
            $toggle
                .text(hasValue ? 'Ocultar sección' : 'Mostrar sección')
                .attr('aria-expanded', hasValue ? 'true' : 'false');
        });
    }

    $(document).on('click', '.tp-panel-toggle', function() {
        var $panel = $(this).closest('.tp-panel');
        var expanded = $panel.hasClass('is-collapsed');
        $panel.toggleClass('is-collapsed', ! expanded);
        $(this).text(expanded ? 'Ocultar sección' : 'Mostrar sección').attr('aria-expanded', expanded ? 'true' : 'false');
    });

    function addPriceField(price, note, currency, highlighted) {
        var idx = $('#tp-prices-list .tp-price-item').length;
        var checked = highlighted ? ' checked' : '';
        var symbols = { USD: 'US$', ARS: '$', EUR: '€', BRL: 'R$' };
        currency = (currency === 'ARS' || currency === 'EUR' || currency === 'BRL') ? currency : 'USD';
        var field = '<div class="tp-price-item">' +
            '<select name="tp_currency_item[]" class="tp-price-currency">' +
                '<option value="USD"' + (currency === 'USD' ? ' selected' : '') + '>' + symbols.USD + '</option>' +
                '<option value="ARS"' + (currency === 'ARS' ? ' selected' : '') + '>' + symbols.ARS + '</option>' +
                '<option value="EUR"' + (currency === 'EUR' ? ' selected' : '') + '>' + symbols.EUR + '</option>' +
                '<option value="BRL"' + (currency === 'BRL' ? ' selected' : '') + '>' + symbols.BRL + '</option>' +
            '</select>' +
            '<input type="number" name="tp_price_item[]" class="tp-price-value" step="0.01" min="0" placeholder="Precio" value="' + escAttr(price) + '">' +
            '<input type="text" name="tp_price_note_item[]" class="tp-price-note" placeholder="Nota (ej: por persona en doble)" value="' + escAttr(note) + '">' +
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

    function addFinancingRow(label, featured) {
        var index = $('#tp-financing-list .tp-financing-item').length;
        var checked = featured ? ' checked' : '';
        var row = '<div class="tp-financing-item">' +
            '<input type="text" name="tp_financing_option[]" class="tp-financing-label" maxlength="180" placeholder="Ej: Hasta 12 cuotas sin interés con tarjeta Visa Santander" value="' + escAttr(label) + '">' +
            '<label class="tp-financing-highlight-label"><input type="radio" class="tp-financing-featured" name="tp_financing_featured" value="' + index + '"' + checked + '> Destacar junto al precio</label>' +
            '<button type="button" class="tp-remove-financing tp-btn-icon" title="Eliminar financiación" aria-label="Eliminar financiación">✕</button>' +
            '</div>';
        $('#tp-financing-list').append(row);
    }

    function reindexFinancingRows() {
        $('#tp-financing-list .tp-financing-item').each(function(index) {
            $(this).find('.tp-financing-featured').val(index);
        });
        if ( $('#tp-financing-list .tp-financing-featured:checked').length === 0 ) {
            $('#tp-financing-list .tp-financing-featured').first().prop('checked', true);
        }
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
        var hasDescription = !!$.trim(desc || '');
        var row = '<div class="tp-itinerary-item' + (hasDescription ? '' : ' is-collapsed') + '">' +
            '<div class="tp-itinerary-head">' +
                '<input type="text" name="tp_itinerary_day[]" class="tp-itinerary-day" value="' + escAttr(day) + '" placeholder="Día 1">' +
                '<input type="text" name="tp_itinerary_title[]" class="tp-itinerary-title" value="' + escAttr(title) + '" placeholder="Título del día">' +
                '<button type="button" class="tp-itinerary-toggle" aria-expanded="' + (hasDescription ? 'true' : 'false') + '">' + (hasDescription ? 'Ocultar descripción' : 'Agregar descripción') + '</button>' +
                '<button type="button" class="tp-remove-itinerary tp-btn-icon" title="Eliminar día">✕</button>' +
            '</div>' +
            '<div class="tp-itinerary-body">' +
                '<textarea name="tp_itinerary_desc[]" rows="3" placeholder="Descripción, actividades, comidas, hotel...">' + escHtml(desc) + '</textarea>' +
            '</div>' +
            '</div>';
        $('#tp-itinerary-list').append(row);
    }

    $(document).on('click', '.tp-itinerary-toggle', function() {
        var $item = $(this).closest('.tp-itinerary-item');
        var expanded = $item.hasClass('is-collapsed');
        $item.toggleClass('is-collapsed', !expanded);
        $(this)
            .attr('aria-expanded', expanded ? 'true' : 'false')
            .text(expanded ? 'Ocultar descripción' : 'Agregar descripción');
    });

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
            '<input type="text" name="tp_tech_key[]" placeholder="Etiqueta (ej: Duración)" class="tp-tech-key" value="' + escAttr(key) + '">' +
            '<input type="text" name="tp_tech_val[]" placeholder="Valor (ej: 7 días / 6 noches)" class="tp-tech-val" value="' + escAttr(value) + '">' +
            '<button type="button" class="tp-remove-field tp-btn-icon" title="Eliminar campo">✕</button>' +
            '</div></div>';
        $('#tp-tech-fields').append(field);
    }

    function addGalleryItem(id, url) {
        if ( ! id || ! url ) {
            return;
        }
        var safeId = parseInt(id, 10) || 0;
        if ( ! safeId ) {
            return;
        }
        var exists = $('#tp-gallery-preview .tp-gallery-item[data-id="' + safeId + '"]');
        if ( exists.length ) {
            return;
        }
        var item = '<div class="tp-gallery-item" data-id="' + safeId + '">' +
            '<img src="' + escAttr(url) + '">' +
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
        var $button = $(this);
        var expanded = $button.attr('aria-expanded') === 'true';
        $('.tp-filters-form').stop(true, true).slideToggle(180);
        $button.attr('aria-expanded', expanded ? 'false' : 'true');
    });

    $(document).on('submit', '#tp-packages-filter-form', function(e) {
        var min = parseFloat($('#tp-filter-price-min').val());
        var max = parseFloat($('#tp-filter-price-max').val());
        var invalidRange = !isNaN(min) && !isNaN(max) && max > 0 && max < min;

        $('#tp-price-range-error').toggle(invalidRange);
        $('#tp-filter-price-min, #tp-filter-price-max').attr('aria-invalid', invalidRange ? 'true' : 'false');
        if ( invalidRange ) {
            e.preventDefault();
            $('#tp-filter-price-max').trigger('focus');
        }
    });

    $(document).on('input', '#tp-filter-price-min, #tp-filter-price-max', function() {
        $('#tp-price-range-error').hide();
        $('#tp-filter-price-min, #tp-filter-price-max').removeAttr('aria-invalid');
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
            if ( $target.hasClass('is-collapsed') ) {
                $target.children('.tp-panel-toggle').trigger('click');
            }
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

    // Prefill the editor from the selected package, then restore rejected form input.
    var $selectedData = $('#tp-selected-package-data');
    var selected = {};
    if ( $selectedData.length && $selectedData.val() && $selectedData.val() !== '[]' && $selectedData.val() !== '{}' ) {
        try {
            selected = JSON.parse( $selectedData.val() );
            if ( selected && selected.ID ) {
                fillPackageForm(selected);
            }
        } catch ( e ) {
            console.error('No se pudieron cargar los datos del paquete para editar.', e);
        }
    }

    var $invalidData = $('#tp-invalid-form-data');
    if ( $invalidData.length && $invalidData.val() && $invalidData.val() !== '[]' && $invalidData.val() !== '{}' ) {
        try {
            var restored = JSON.parse($invalidData.val());
            if ( restored && typeof restored === 'object' && !Array.isArray(restored) ) {
                restoreFormData(restored);
            }
        } catch ( e ) {
            console.error('No se pudieron restaurar los datos enviados del formulario.', e);
        }
    }

    initOptionalSections();
    if ( $('#tp-title-error').is(':visible') && !$.trim($('#tp_title').val()) ) {
        $('#tp_title').attr('aria-invalid', 'true').trigger('focus');
    }
});
