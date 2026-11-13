jQuery(document).ready(function($) {
    var packageForm = $('#tp-package-form');
    var mediaFrame;
    var featuredFrame;
    var seoImageFrame;
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

        return {
            title: String($('#tp_title').val() || '').trim() || 'Paquete de viaje',
            subtitle: String($('#tp_short_desc').val() || '').trim(),
            dateFrom: date ? date.from : '',
            dateTo: date ? date.to : '',
            currency: promoCurrencySymbol(currencyCode),
            price: price,
            childPrice: $('#tp_price_child').val(),
            background: String($('#tp_featured_image_url').val() || $('#tp-featured-preview-main img').attr('src') || ''),
            site: ( window.TP_ADMIN && TP_ADMIN.site_name ) ? TP_ADMIN.site_name : ''
        };
    }

    function promoLoadImage(url, done) {
        if ( ! url ) { done(null); return; }
        var img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = function() { done(img); };
        img.onerror = function() { done(null); };
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

    function promoDimensions() {
        var format = $('#tp_promo_format').val();
        if ( format === 'story' ) {
            return { W: 1080, H: 1920 };
        }
        if ( format === 'custom' ) {
            var width = Number($('#tp_promo_width').val());
            var height = Number($('#tp_promo_height').val());
            if ( ! Number.isInteger(width) || ! Number.isInteger(height) || width < 320 || width > 4096 || height < 320 || height > 4096 ) {
                return null;
            }
            return { W: width, H: height };
        }
        return { W: 1080, H: 1080 };
    }

    function promoDraw(data, img) {
        var canvas = document.getElementById('tp-promo-canvas');
        if ( ! canvas ) { return; }

        var dims = promoDimensions();
        if ( ! dims ) { return; }
        var W = dims.W;
        var H = dims.H;
        var isStory = H > W;
        var fontScale = Math.min(W, H) / 1080;
        var pad = Math.round((isStory ? 84 : 72) * fontScale);
        var siteSize = Math.round((isStory ? 28 : 26) * fontScale);
        var titleSize = Math.round((isStory ? 72 : 62) * fontScale);
        var subtitleSize = Math.round((isStory ? 34 : 30) * fontScale);
        var dateSize = Math.round((isStory ? 28 : 25) * fontScale);
        var childSize = Math.round((isStory ? 30 : 27) * fontScale);
        var priceSize = Math.round((isStory ? 84 : 70) * fontScale);
        var titleLineHeight = Math.round(titleSize * 1.12);
        var subtitleLineHeight = Math.round(subtitleSize * 1.32);
        var datePillHeight = dateSize + Math.round(20 * fontScale);
        var gap = Math.round((isStory ? 24 : 18) * fontScale);
        var fontPair = promoFonts();
        var titleFont = promoFont(700, titleSize, fontPair.heading);
        var subtitleFont = promoFont(400, subtitleSize, fontPair.body);
        var dateFont = promoFont(600, dateSize, fontPair.body);
        var childFont = promoFont(600, childSize, fontPair.body);

        if ( canvas.width !== W ) { canvas.width = W; }
        if ( canvas.height !== H ) { canvas.height = H; }

        var ctx = canvas.getContext('2d');
        if ( 'imageSmoothingQuality' in ctx ) { ctx.imageSmoothingQuality = 'high'; }

        ctx.clearRect(0, 0, W, H);

        var bg = ctx.createLinearGradient(0, 0, W, H);
        bg.addColorStop(0, '#0f766e');
        bg.addColorStop(1, '#0f172a');
        ctx.fillStyle = bg;
        ctx.fillRect(0, 0, W, H);

        if ( img && img.width && img.height ) {
            var imageScale = Math.max(W / img.width, H / img.height);
            var dw = img.width * imageScale;
            var dh = img.height * imageScale;
            ctx.drawImage(img, (W - dw) / 2, (H - dh) / 2, dw, dh);
        }

        var shade = ctx.createLinearGradient(0, H * 0.2, 0, H);
        shade.addColorStop(0, 'rgba(8,15,30,0)');
        shade.addColorStop(0.55, 'rgba(8,15,30,0.45)');
        shade.addColorStop(1, 'rgba(8,15,30,0.94)');
        ctx.fillStyle = shade;
        ctx.fillRect(0, 0, W, H);

        var topShade = ctx.createLinearGradient(0, 0, 0, H * 0.3);
        topShade.addColorStop(0, 'rgba(8,15,30,0.7)');
        topShade.addColorStop(1, 'rgba(8,15,30,0)');
        ctx.fillStyle = topShade;
        ctx.fillRect(0, 0, W, H * 0.3);

        if ( data.site ) {
            ctx.font = promoFont(700, siteSize, fontPair.body);
            ctx.fillStyle = 'rgba(255,255,255,0.92)';
            ctx.textBaseline = 'top';
            ctx.fillText(String(data.site).toUpperCase(), pad, Math.round((isStory ? 80 : 56) * fontScale));
        }

        var maxTextWidth = W - pad * 2;

        var titleLines = promoWrapText(ctx, data.title, maxTextWidth, 3, titleFont);
        var subtitleLines = data.subtitle ? promoWrapText(ctx, data.subtitle, maxTextWidth, 3, subtitleFont) : [];

        var dateText = '';
        if ( data.dateFrom && data.dateTo ) {
            dateText = data.dateFrom + '  →  ' + data.dateTo;
        } else if ( data.dateFrom ) {
            dateText = 'Salida ' + data.dateFrom;
        }

        var priceText = data.price ? 'Desde ' + data.currency + ' ' + promoFormatNumber(data.price) : 'Consultar precio';
        var childText = data.childPrice ? 'Niños ' + data.currency + ' ' + promoFormatNumber(data.childPrice) : '';

        ctx.font = promoFont(800, priceSize, fontPair.heading);
        while ( ctx.measureText(priceText).width > maxTextWidth && priceSize > Math.max(12, Math.round(36 * fontScale)) ) {
            priceSize -= 2;
            ctx.font = promoFont(800, priceSize, fontPair.heading);
        }

        var dateHeight = dateText ? datePillHeight : 0;
        var titleHeight = titleLines.length * titleLineHeight;
        var subtitleHeight = subtitleLines.length * subtitleLineHeight;
        var priceHeight = priceSize + 12;
        var childHeight = childText ? Math.round(childSize * 1.35) : 0;
        var total = dateHeight + ( dateText ? gap : 0 ) + titleHeight +
            ( subtitleLines.length ? gap + subtitleHeight : 0 ) +
            gap + priceHeight + ( childText ? 8 + childHeight : 0 );
        var minY = Math.round((isStory ? 80 : 56) * fontScale) + siteSize + Math.round(48 * fontScale);
        var y = H - pad - total;
        if ( y < minY ) { y = minY; }

        if ( dateText ) {
            ctx.font = dateFont;
            var dateWidth = ctx.measureText(dateText).width;
            var datePadding = Math.round(20 * fontScale);
            var pillW = Math.min(dateWidth + datePadding * 2, maxTextWidth);
            ctx.fillStyle = 'rgba(255,255,255,0.18)';
            promoRoundRect(ctx, pad, y, pillW, datePillHeight, datePillHeight / 2);
            ctx.fill();
            ctx.fillStyle = '#fff';
            ctx.textBaseline = 'middle';
            ctx.fillText(dateText, pad + datePadding, y + datePillHeight / 2 + 1);
            y += dateHeight + gap;
        }

        ctx.textBaseline = 'top';
        ctx.fillStyle = '#fff';
        ctx.font = titleFont;
        for ( var i = 0; i < titleLines.length; i++ ) {
            ctx.fillText(titleLines[i], pad, y);
            y += titleLineHeight;
        }

        if ( subtitleLines.length ) {
            y += gap;
            ctx.fillStyle = 'rgba(255,255,255,0.82)';
            ctx.font = subtitleFont;
            for ( var j = 0; j < subtitleLines.length; j++ ) {
                ctx.fillText(subtitleLines[j], pad, y);
                y += subtitleLineHeight;
            }
        }

        y += gap;
        ctx.fillStyle = '#fff';
        ctx.font = promoFont(800, priceSize, fontPair.heading);
        ctx.fillText(priceText, pad, y);

        if ( childText ) {
            ctx.font = childFont;
            ctx.fillStyle = 'rgba(255,255,255,0.82)';
            ctx.fillText(childText, pad, y + priceHeight + 4);
        }
    }

    function resetPromoPreview() {
        var canvas = document.getElementById('tp-promo-canvas');
        if ( canvas ) {
            canvas.style.display = 'none';
            var ctx = canvas.getContext('2d');
            if ( ctx ) { ctx.clearRect(0, 0, canvas.width, canvas.height); }
        }
        $('#tp-download-promo').prop('disabled', true);
        $('#tp-promo-status').text('');
    }

    function generatePromoImage() {
        var data = promoData();
        var canvas = document.getElementById('tp-promo-canvas');
        if ( ! canvas ) { return; }

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
                promoDraw(data, img);
                canvas.style.display = 'block';
                $('#tp-download-promo').prop('disabled', false);

                var status = ! data.background
                    ? 'Imagen creada sin foto de fondo. Seleccioná una imagen principal para mejorar el resultado.'
                    : ( ! img ? 'No se pudo cargar la imagen principal. Se generó la imagen con fondo de color.' : 'Imagen lista. Podés descargarla como PNG.' );
                if ( ! fontsLoaded ) {
                    status += ' No se pudieron cargar las tipografías seleccionadas; se usó la fuente alternativa.';
                }
                $('#tp-promo-status').text(status);
            });
        });
    }

    function downloadPromoImage() {
        var canvas = document.getElementById('tp-promo-canvas');
        if ( ! canvas || canvas.style.display === 'none' || ! canvas.toBlob ) { return; }
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

    $(document).on('change', '#tp_promo_format', function() {
        $('#tp-promo-custom-dimensions').prop('hidden', $(this).val() !== 'custom');
        resetPromoPreview();
    });

    $(document).on('input change', '#tp_promo_width, #tp_promo_height', function() {
        resetPromoPreview();
    });

    $(document).on('click', '#tp-generate-promo', function(e) {
        e.preventDefault();
        generatePromoImage();
    });

    $(document).on('click', '#tp-download-promo', function(e) {
        e.preventDefault();
        downloadPromoImage();
    });

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
        resetPromoPreview();
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
        initOptionalSections();
    }

    function restoreFormData(data) {
        if ( ! data || ! packageForm.length ) {
            return;
        }

        var excludedNames = [
            'tp_price_item[]', 'tp_currency_item[]', 'tp_price_note_item[]', 'tp_price_highlighted[]',
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
