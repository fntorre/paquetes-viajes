jQuery(document).ready(function($) {

    /* ============================================
       SLIDER
    ============================================ */
    $('.tp-slider-wrapper').each(function() {
        var $wrap     = $(this);
        var $track    = $wrap.find('.tp-slider-track');
        var $slides   = $wrap.find('.tp-slide');
        var $prev     = $wrap.find('.tp-slider-prev');
        var $next     = $wrap.find('.tp-slider-next');
        var $dotsWrap = $wrap.find('.tp-slider-dots');
        var total     = $slides.length;
        var current   = 0;
        
        // Read props from data-attributes
        var autoplay      = $wrap.data('autoplay') === 'true' || $wrap.data('autoplay') === true;
        var interval      = parseInt($wrap.data('interval')) || 5000;
        var speed         = parseInt($wrap.data('speed')) || 300;
        // HTML lowercases all attribute names, so read them in lowercase
        var pauseOnHoverVal = $wrap.data('pauseonhover');
        var showArrowsVal   = $wrap.data('showarrows');
        var showDotsVal     = $wrap.data('showdots');
        var pauseOnHover  = pauseOnHoverVal === undefined ? true  : (pauseOnHoverVal === 'true' || pauseOnHoverVal === true);
        var showArrows    = showArrowsVal   === undefined ? true  : (showArrowsVal   === 'true' || showArrowsVal   === true);
        var showDots      = showDotsVal     === undefined ? true  : (showDotsVal     === 'true' || showDotsVal     === true);
        var timer         = null;

        // Apply transition speed to track
        $track.css('transition', 'transform ' + speed + 'ms cubic-bezier(0.4, 0.0, 0.2, 1)');

        // Hide/show UI elements based on props
        if ( !showArrows || total <= 1 ) {
            $prev.hide();
            $next.hide();
        }
        if ( !showDots ) {
            $dotsWrap.hide();
        }

        // Build dots
        for ( var i = 0; i < total; i++ ) {
            $dotsWrap.append('<button class="tp-slider-dot" data-index="' + i + '" aria-label="Slide ' + (i+1) + '"></button>');
        }

        function goTo(index) {
            if ( index < 0 ) index = total - 1;
            if ( index >= total ) index = 0;

            $slides.eq(current).removeClass('is-active');
            current = index;
            $slides.eq(current).addClass('is-active');

            $track.css('transform', 'translateX(-' + (current * 100) + '%)');
            $dotsWrap.find('.tp-slider-dot').removeClass('is-active').eq(current).addClass('is-active');
        }

        // Init
        goTo(0);

        $prev.on('click', function() {
            resetTimer();
            goTo(current - 1);
        });

        $next.on('click', function() {
            resetTimer();
            goTo(current + 1);
        });

        $dotsWrap.on('click', '.tp-slider-dot', function() {
            resetTimer();
            goTo( parseInt($(this).data('index')) );
        });

        // Touch swipe
        var touchStartX = 0;
        $wrap[0].addEventListener('touchstart', function(e) {
            touchStartX = e.touches[0].clientX;
        }, { passive: true });
        $wrap[0].addEventListener('touchend', function(e) {
            var dx = touchStartX - e.changedTouches[0].clientX;
            if ( Math.abs(dx) > 50 ) {
                resetTimer();
                goTo( dx > 0 ? current + 1 : current - 1 );
            }
        }, { passive: true });

        // Autoplay
        function startTimer() {
            if ( !autoplay || total <= 1 ) return;
            timer = setInterval(function() { goTo(current + 1); }, interval);
        }
        function resetTimer() {
            clearInterval(timer);
            startTimer();
        }

        // Pause on hover (configurable)
        if ( pauseOnHover ) {
            $wrap.on('mouseenter', function() { clearInterval(timer); });
            $wrap.on('mouseleave', function() { startTimer(); });
        }

        startTimer();
});

/* ============================================
   SEARCH SUGGESTIONS (tp_buscador)
============================================ */
(function() {
    var cfg = window.TP_PUBLIC || {};
    var minChars = parseInt(cfg.min_chars, 10) || 2;

    $('.tp-buscador').each(function() {
        var $form  = $(this);
        var $field = $form.find('.tp-buscador-field').first();
        var $input = $form.find('[data-tp-search-input]').first();
        var $box   = $field.find('.tp-buscador-suggestions').first();
        if ( ! $input.length || ! $box.length ) return;

        var timer = null;
        var xhr = null;
        var activeIndex = -1;

        function hide() {
            $box.prop('hidden', true).empty();
            $input.attr('aria-expanded', 'false');
            activeIndex = -1;
        }

        function show() {
            $box.prop('hidden', false);
            $input.attr('aria-expanded', 'true');
        }

        function buildAllUrl(term) {
            var raw = $form.attr('action') || window.location.href;
            var url;
            try {
                url = new URL(raw, window.location.origin);
            } catch (err) {
                url = new URL(window.location.href);
            }

            $form.find('input[type="hidden"]').each(function() {
                if ( this.name ) url.searchParams.set(this.name, this.value);
            });

            if ( term ) url.searchParams.set('tp_search', term);

            var destino = $form.find('[name="destino"]').val();
            if ( destino ) url.searchParams.set('destino', destino);

            var tipo = $form.find('[name="tipo_viaje"]').val();
            if ( tipo ) url.searchParams.set('tipo_viaje', tipo);

            return url.toString();
        }

        function escapeHtml(str) {
            return String(str).replace(/[&<>"']/g, function(ch) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
            });
        }

        function render(data, term) {
            var items = data.items || [];
            activeIndex = -1;
            $box.empty();

            if ( ! items.length ) {
                $box.append('<div class="tp-suggestion-empty">' + (cfg.no_results || 'No encontramos paquetes para tu búsqueda.') + '</div>');
                $box.append('<a class="tp-suggestion-all" href="' + escapeHtml(buildAllUrl(term)) + '">Ver todos los resultados</a>');
                show();
                return;
            }

            items.forEach(function(item, index) {
                var meta = [];
                if ( item.destination ) meta.push(item.destination);
                if ( item.price ) meta.push(item.price);

                var thumb = item.thumb
                    ? '<img src="' + escapeHtml(item.thumb) + '" alt="" loading="lazy">'
                    : '<span class="dashicons dashicons-palmtree" aria-hidden="true"></span>';

                var html =
                    '<a class="tp-suggestion-item" role="option" href="' + escapeHtml(item.url) + '" data-index="' + index + '">' +
                        '<span class="tp-suggestion-thumb">' + thumb + '</span>' +
                        '<span class="tp-suggestion-info">' +
                            '<span class="tp-suggestion-title">' + escapeHtml(item.title) + '</span>' +
                            ( meta.length ? '<span class="tp-suggestion-meta">' + escapeHtml(meta.join(' · ')) + '</span>' : '' ) +
                        '</span>' +
                    '</a>';

                $box.append(html);
            });

            var total = parseInt(data.total, 10) || items.length;
            $box.append('<a class="tp-suggestion-all" href="' + escapeHtml(buildAllUrl(term)) + '">Ver todos los resultados (' + total + ')</a>');
            show();
        }

        function moveActive(delta) {
            var $items = $box.find('.tp-suggestion-item');
            if ( ! $items.length ) return;

            activeIndex += delta;
            if ( activeIndex < 0 ) activeIndex = $items.length - 1;
            if ( activeIndex >= $items.length ) activeIndex = 0;

            $items.removeClass('is-active').eq(activeIndex).addClass('is-active');

            var el = $items.get(activeIndex);
            if ( el && el.scrollIntoView ) {
                el.scrollIntoView({ block: 'nearest' });
            }
        }

        function fetchSuggestions(term) {
            if ( xhr && xhr.abort ) xhr.abort();
            if ( ! cfg.ajax_url ) return;

            $box.html('<div class="tp-suggestion-loading">' + (cfg.searching || 'Buscando paquetes…') + '</div>');
            show();

            xhr = $.ajax({
                url: cfg.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'tp_search_suggestions',
                    nonce: cfg.nonce,
                    term: term
                }
            }).done(function(res) {
                if ( ! res || ! res.success ) { hide(); return; }
                render(res.data || {}, term);
            }).fail(function(jqXHR, status) {
                if ( status !== 'abort' ) hide();
            });
        }

        $input.on('input', function() {
            var term = $.trim($input.val());
            clearTimeout(timer);

            if ( term.length < minChars ) {
                hide();
                return;
            }

            timer = setTimeout(function() {
                fetchSuggestions(term);
            }, 220);
        });

        $input.on('keydown', function(e) {
            if ( $box.prop('hidden') ) return;

            if ( e.key === 'ArrowDown' ) {
                e.preventDefault();
                moveActive(1);
            } else if ( e.key === 'ArrowUp' ) {
                e.preventDefault();
                moveActive(-1);
            } else if ( e.key === 'Enter' ) {
                var $active = $box.find('.tp-suggestion-item.is-active');
                if ( $active.length ) {
                    e.preventDefault();
                    window.location.href = $active.attr('href');
                }
            } else if ( e.key === 'Escape' ) {
                hide();
            }
        });

        $box.on('mousedown', '.tp-suggestion-item, .tp-suggestion-all', function(e) {
            e.preventDefault();
        });

        $box.on('click', '.tp-suggestion-item', function(e) {
            if ( e.metaKey || e.ctrlKey ) return;
            e.preventDefault();
            window.location.href = $(this).attr('href');
        });

        $box.on('mouseenter', '.tp-suggestion-item', function() {
            var index = parseInt($(this).data('index'), 10);
            if ( ! isNaN(index) ) {
                activeIndex = index;
                $box.find('.tp-suggestion-item').removeClass('is-active');
                $(this).addClass('is-active');
            }
        });

        $input.on('blur', function() {
            setTimeout(hide, 150);
        });

        $(document).on('click', function(e) {
            if ( ! $.contains($field[0], e.target) && e.target !== $field[0] ) {
                hide();
            }
        });
    });
})();

/* ============================================
   CATALOG FILTERS — client-side search, filter & sort
============================================ */
(function() {
    var $catalogo = $('.tp-catalogo');
    if ( $catalogo.length === 0 ) return;

    var $grid     = $catalogo.find('#tp-catalogo-grid');
    var $cards    = $grid.find('.tp-card');
    var $total    = $catalogo.find('#tp-catalogo-total');
    var $empty    = $catalogo.find('.tp-catalogo-empty');
    var $search   = $catalogo.find('#tp-catalogo-search');
    var $destino  = $catalogo.find('#tp-filter-destino');
    var $tipologia = $catalogo.find('#tp-filter-tipologia');
    var $orderby  = $catalogo.find('#tp-filter-orderby');
    var $orderDir = $catalogo.find('#tp-filter-order');
    var $tipoViajeToggle = $catalogo.find('#tp-filter-tipo-viaje');
    var tipoViajeVal = '';

    function normalizeText(value) {
        var text = (value || '').toString().toLowerCase();
        if ( text.normalize ) {
            text = text.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
        return text;
    }

    var serverSearch = normalizeText($catalogo.data('search-term') || '').trim();

    if ( $tipoViajeToggle.length ) {
        $tipoViajeToggle.find('.tp-toggle-btn').on('click', function() {
            var $btn = $(this);
            $tipoViajeToggle.find('.tp-toggle-btn').removeClass('is-active');
            $btn.addClass('is-active');
            tipoViajeVal = $btn.data('value') || '';
            filterAndSort();
        });
        $tipoViajeToggle.find('.tp-toggle-btn[data-value=""]').addClass('is-active');
    }

    if ( $cards.length === 0 ) return;

    function filterAndSort() {
        var query   = normalizeText($search.val() || '').trim();
        var destSlug = $destino.val() || '';
        var order   = $orderby.val() || 'default';
        var dir     = $orderDir.val() || 'DESC';

        var visible = [];

        $cards.each(function() {
            var $c = $(this);
            var show = true;

            // Text search: all words must appear (accent-insensitive)
            if ( query && query !== serverSearch ) {
                var searchText = normalizeText($c.data('search'));
                var tokens = query.split(/\s+/);
                for ( var t = 0; t < tokens.length; t++ ) {
                    if ( tokens[t] && searchText.indexOf(tokens[t]) === -1 ) {
                        show = false;
                        break;
                    }
                }
            }

            // Destination filter
            if ( show && destSlug ) {
                var destinos = ($c.data('destinos') || '').split(' ');
                if ( destinos.indexOf(destSlug) === -1 ) show = false;
            }

            // Tipologia filter
            var tipSlug = $tipologia.val() || '';
            if ( show && tipSlug ) {
                var tipologias = ($c.data('tipologia') || '').split(' ');
                if ( tipologias.indexOf(tipSlug) === -1 ) show = false;
            }

            // Tipo viaje filter
            if ( show && tipoViajeVal ) {
                var cardTipoViaje = $c.data('tipo-viaje') || '';
                if ( cardTipoViaje !== tipoViajeVal ) show = false;
            }

            $c.toggle(show);
            if ( show ) visible.push($c);
        });

        // Update count
        $total.text(visible.length);

        // Empty state
        if ( visible.length === 0 ) {
            $empty.show();
            $grid.hide();
        } else {
            $empty.hide();
            $grid.show();
        }

        // Sorting
        if ( order !== 'default' && visible.length > 1 ) {
            visible.sort(function(a, b) {
                var $a = $(a), $b = $(b);
                var valA, valB;

                switch ( order ) {
                    case 'title':
                        valA = ($a.data('title') || '').toLowerCase();
                        valB = ($b.data('title') || '').toLowerCase();
                        return dir === 'ASC' ? (valA > valB ? 1 : -1) : (valA < valB ? 1 : -1);
                    case 'price':
                        valA = parseFloat($a.data('price')) || 0;
                        valB = parseFloat($b.data('price')) || 0;
                        return dir === 'ASC' ? valA - valB : valB - valA;
                    case 'date':
                        valA = parseInt($a.data('date')) || 0;
                        valB = parseInt($b.data('date')) || 0;
                        return dir === 'ASC' ? valA - valB : valB - valA;
                }
            });

            $grid.append(visible);
        }
    }

    $search.on('input', filterAndSort);
    $destino.on('change', filterAndSort);
    if ( $tipologia.length ) $tipologia.on('change', filterAndSort);
    $orderby.on('change', filterAndSort);
    $orderDir.on('change', filterAndSort);

    // Read URL params for pre-filtering (e.g. ?tp_search=caribe&destino=caribe&tipologia=playa&tipo_viaje=nacional)
    var urlParams = new URLSearchParams(window.location.search);
    var urlSearch = urlParams.get('tp_search') || urlParams.get('s');
    var urlDestino = urlParams.get('destino');
    var urlTipologia = urlParams.get('tipologia');
    var urlTipoViaje = urlParams.get('tipo_viaje');
    var shouldFilter = $orderby.val() !== 'default';
    if ( urlSearch && $search.length ) {
        $search.val(urlSearch);
        shouldFilter = true;
    }
    if ( urlDestino && $destino.length ) {
        $destino.val(urlDestino);
        shouldFilter = true;
    }
    if ( urlTipologia && $tipologia.length ) {
        $tipologia.val(urlTipologia);
        shouldFilter = true;
    }
    if ( urlTipoViaje && $tipoViajeToggle.length ) {
        $tipoViajeToggle.find('.tp-toggle-btn').removeClass('is-active');
        $tipoViajeToggle.find('.tp-toggle-btn[data-value="' + urlTipoViaje + '"]').addClass('is-active');
        tipoViajeVal = urlTipoViaje;
        shouldFilter = true;
    }

    // Initial sort if non-default order is selected
    if ( shouldFilter ) filterAndSort();
})();

/* ============================================
   CAROUSEL — tp-grid carousel mode
============================================ */
$('.tp-grid-carousel').each(function() {
    var $wrap   = $(this);
    var $track  = $wrap.find('.tp-grid-carousel-track');
    var $prev     = $wrap.find('.tp-carousel-prev');
    var $next     = $wrap.find('.tp-carousel-next');
    var $dots     = $wrap.find('.tp-carousel-dots');
    var $controls = $wrap.find('.tp-carousel-controls');

    var $orig     = $track.find('.tp-grid-carousel-item');
    var count     = $orig.length;
    if ( count <= 0 ) return;

    var desktopPer = parseInt($wrap.data('desktop')) || 1;
    var mobilePer  = parseInt($wrap.data('movile'))  || 1;
    var mobileBreakpoint = 767;

    // Clone the slides before + after to enable seamless infinite scroll
    $track.prepend($orig.clone(true, true));
    $track.append($orig.clone(true, true));

    var $slides = $track.find('.tp-grid-carousel-item');
    var per        = 1;
    var pages      = 1;
    var currentSlide = count; // index of the first original slide within the track

    function perView() {
        return window.innerWidth <= mobileBreakpoint ? mobilePer : desktopPer;
    }

    function translateTo(slideIndex, animate) {
        $track.css('transition', animate ? '' : 'none');
        $track.css('transform', 'translateX(-' + (slideIndex * 100 / per) + '%)');
    }

    function activePage() {
        var offset = currentSlide - count;
        if ( offset < 0 ) offset += count;
        offset = offset % count;
        return Math.floor(offset / per);
    }

    function updateDots() {
        $dots.find('.tp-carousel-dot').removeClass('is-active').eq(activePage()).addClass('is-active');
    }

    function goTo(slideIndex, animate) {
        translateTo(slideIndex, animate);
        currentSlide = slideIndex;
        updateDots();
    }

    // After a transition ends, snap back to the original copy (invisible jump)
    function normalize() {
        var next = currentSlide;
        if ( next >= 2 * count ) next -= count;
        else if ( next < count ) next += count;
        if ( next !== currentSlide ) {
            currentSlide = next;
            translateTo(currentSlide, false);
            updateDots();
        }
    }

    function buildDots() {
        $dots.empty();
        for ( var i = 0; i < pages; i++ ) {
            $dots.append('<button class="tp-carousel-dot" data-index="' + i + '" aria-label="Page ' + (i+1) + '"></button>');
        }
    }

    function refresh() {
        per = perView();
        $slides.css('flex', '0 0 calc(100% / ' + per + ')');

        pages = Math.max(1, Math.ceil(count / per));

        // Snap current position to a valid page boundary inside the original copy
        var offset = currentSlide - count;
        if ( offset < 0 ) offset = 0;
        if ( offset > count - 1 ) offset = count - 1;
        currentSlide = count + Math.floor(offset / per) * per;

        buildDots();
        translateTo(currentSlide, false);
        updateDots();

        var hasPages = pages > 1 && count > per;
        $prev.toggle(hasPages);
        $next.toggle(hasPages);
        $dots.toggle(hasPages);
        $controls.toggle(hasPages);
    }

    $track.on('transitionend', function() {
        normalize();
    });

    refresh();

    $prev.on('click', function() { goTo(currentSlide - per, true); });
    $next.on('click', function() { goTo(currentSlide + per, true); });
    $dots.on('click', '.tp-carousel-dot', function() {
        goTo( count + parseInt($(this).data('index'), 10) * per, true );
    });

    // Touch swipe
    var touchStartX = 0;
    $wrap[0].addEventListener('touchstart', function(e) {
        touchStartX = e.touches[0].clientX;
    }, { passive: true });
    $wrap[0].addEventListener('touchend', function(e) {
        var dx = touchStartX - e.changedTouches[0].clientX;
        if ( Math.abs(dx) > 50 ) {
            goTo( dx > 0 ? currentSlide + per : currentSlide - per, true );
        }
    }, { passive: true });

    // Recompute on resize (breakpoint changes per-view count)
    var resizeTimer = null;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(refresh, 150);
    });
});

/* ============================================
   COPY SHARE LINK (single paquete)
============================================ */
$(document).on('click', '.tp-copy-link', function(e) {
    e.preventDefault();

    var $btn = $(this);
    var url = $btn.data('url') || window.location.href;
    var original = $btn.html();

    function done() {
        $btn.html('<span class="dashicons dashicons-yes"></span>Copiado');
        setTimeout(function() { $btn.html(original); }, 1800);
    }

    if ( navigator.clipboard && navigator.clipboard.writeText ) {
        navigator.clipboard.writeText(url).then(done).catch(function() {});
        return;
    }

    var $temp = $('<textarea>').val(url).css({ position: 'fixed', top: '-1000px', opacity: 0 }).appendTo('body');
    $temp[0].select();
    try { document.execCommand('copy'); done(); } catch (err) {}
    $temp.remove();
});

/* ============================================
   GALLERY LIGHTBOX (single paquete) — full UI
============================================ */
    (function() {
        var $links = $('.tp-single-gallery .tp-gallery-item-link');
        if ( $links.length === 0 ) return;

        var items = [];
        $links.each(function(i) {
            var $a = $(this);
            items.push({ full: $a.data('full') || $a.find('img').attr('src'), thumb: $a.find('img').attr('src'), el: $a });
            $a.attr('data-tp-index', i);
        });

        var $lb = $(
            '<div id="tp-lightbox" class="tp-lightbox" style="display:none;">' +
                '<div class="tp-lb-backdrop"></div>' +
                '<div class="tp-lb-inner" role="dialog" aria-modal="true">' +
                    '<button class="tp-lb-close" aria-label="Cerrar">✕</button>' +
                    '<button class="tp-lb-prev" aria-label="Anterior">‹</button>' +
                    '<div class="tp-lb-stage"><img src="" alt=""></div>' +
                    '<button class="tp-lb-next" aria-label="Siguiente">›</button>' +
                    '<div class="tp-lb-caption"><span class="tp-lb-count"></span></div>' +
                '</div>' +
            '</div>'
        );
        $('body').append($lb);

        var $stage = $lb.find('.tp-lb-stage img');
        var $count = $lb.find('.tp-lb-count');
        var current = 0;

        function openAt(index) {
            current = (index + items.length) % items.length;
            show(current);
            $lb.fadeIn(180);
            $('body').addClass('tp-lightbox-open');
        }

        function closeLB() {
            $lb.fadeOut(120);
            $('body').removeClass('tp-lightbox-open');
        }

        function show(i) {
            var it = items[(i + items.length) % items.length];
            // animate out
            $stage.removeClass('is-visible');
            // change src and fade/zoom in on load
            $stage.off('load').on('load', function() {
                // small delay to allow CSS transition
                setTimeout(function() { $stage.addClass('is-visible'); }, 20);
            });
            $stage.attr('src', it.full);
            $count.text( ( (i+items.length) % items.length ) + 1 + ' / ' + items.length );
            // preload next
            preload((i+1));
        }

        function preload(i) {
            var idx = (i + items.length) % items.length;
            var src = items[idx].full;
            var img = new Image(); img.src = src;
        }

        $links.on('click', function(e) {
            e.preventDefault();
            var idx = parseInt( $(this).attr('data-tp-index'), 10 );
            openAt(idx);
        });

        $lb.on('click', '.tp-lb-close, .tp-lb-backdrop', function(e) { e.stopPropagation(); closeLB(); });
        $lb.on('click', '.tp-lb-prev', function(e) { e.stopPropagation(); openAt(current - 1); });
        $lb.on('click', '.tp-lb-next', function(e) { e.stopPropagation(); openAt(current + 1); });

        $(document).on('keydown.tpLightbox', function(e) {
            if ( $lb.is(':visible') ) {
                if ( e.key === 'Escape' ) closeLB();
                if ( e.key === 'ArrowLeft' ) openAt(current - 1);
                if ( e.key === 'ArrowRight' ) openAt(current + 1);
            }
        });
    })();

    /* ============================================
       DATE CARDS CAROUSEL (single paquete)
    ============================================ */
    $('.tp-dates-block[data-dates-carousel="1"]').each(function() {
        var $block  = $(this);
        $block.addClass('is-carousel');
        var $track  = $block.find('.tp-single-dates-cards');
        var $cards  = $track.find('.tp-date-card');
        var $prev   = $block.find('.tp-dates-prev');
        var $next   = $block.find('.tp-dates-next');
        var $dots   = $block.find('.tp-dates-dots');
        var $controls = $block.find('.tp-dates-controls');
        var total   = $cards.length;
        var current = 0;

        if ( total < 2 ) return;

        function perView() {
            return window.innerWidth <= 768 ? 1 : 2;
        }

        function maxIndex() {
            return Math.max(0, total - perView());
        }

        function buildDots() {
            $dots.empty();
            for ( var i = 0; i <= maxIndex(); i++ ) {
                $dots.append('<button type="button" class="tp-dates-dot" data-index="' + i + '" aria-label="Salida ' + (i + 1) + '"></button>');
            }
        }

        function update() {
            var cardWidth = $cards.first().outerWidth();
            var gap = parseFloat($track.css('gap')) || 12;
            $track.css('transform', 'translateX(-' + (current * (cardWidth + gap)) + 'px)');
            $dots.find('.tp-dates-dot').removeClass('is-active').eq(current).addClass('is-active');
            $prev.prop('disabled', current <= 0);
            $next.prop('disabled', current >= maxIndex());
            $controls.toggleClass('has-pages', maxIndex() > 0);
        }

        function refresh() {
            current = Math.min(current, maxIndex());
            buildDots();
            update();
        }

        $prev.on('click', function() {
            if ( current > 0 ) {
                current--;
                update();
            }
        });

        $next.on('click', function() {
            if ( current < maxIndex() ) {
                current++;
                update();
            }
        });

        $dots.on('click', '.tp-dates-dot', function() {
            current = parseInt($(this).data('index'), 10) || 0;
            update();
        });

        var $viewport = $block.find('.tp-dates-viewport');
        if ( $viewport.length ) {
            var touchStartX = 0;
            $viewport[0].addEventListener('touchstart', function(e) {
                touchStartX = e.touches[0].clientX;
            }, { passive: true });
            $viewport[0].addEventListener('touchend', function(e) {
                var dx = touchStartX - e.changedTouches[0].clientX;
                if ( Math.abs(dx) > 40 ) {
                    current = Math.max(0, Math.min(maxIndex(), current + (dx > 0 ? 1 : -1)));
                    update();
                }
            }, { passive: true });
        }

        var resizeTimer = null;
        $(window).on('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(refresh, 150);
        });

        refresh();
    });

});
