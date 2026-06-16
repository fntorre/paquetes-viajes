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
