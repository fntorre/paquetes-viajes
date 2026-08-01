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

    if ( $cards.length === 0 ) return;

    function filterAndSort() {
        var query   = ($search.val() || '').toLowerCase().trim();
        var destSlug = $destino.val() || '';
        var order   = $orderby.val() || 'default';
        var dir     = $orderDir.val() || 'DESC';

        var visible = [];

        $cards.each(function() {
            var $c = $(this);
            var show = true;

            // Text search
            if ( query ) {
                var searchText = ($c.data('search') || '').toLowerCase();
                if ( searchText.indexOf(query) === -1 ) show = false;
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

    // Read URL params for pre-filtering (e.g. ?destino=caribe&tipologia=playa)
    var urlParams = new URLSearchParams(window.location.search);
    var urlDestino = urlParams.get('destino');
    var urlTipologia = urlParams.get('tipologia');
    var shouldFilter = $orderby.val() !== 'default';
    if ( urlDestino && $destino.length ) {
        $destino.val(urlDestino);
        shouldFilter = true;
    }
    if ( urlTipologia && $tipologia.length ) {
        $tipologia.val(urlTipologia);
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
    var $prev   = $wrap.find('.tp-carousel-prev');
    var $next   = $wrap.find('.tp-carousel-next');
    var $dots   = $wrap.find('.tp-carousel-dots');

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
   GALLERY LIGHTBOX (single paquete) — full UI
============================================ */
    (function() {
        var $links = $('.tp-single-gallery.tp-pinterest .tp-gallery-item-link');
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

});
