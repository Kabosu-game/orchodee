/**
 * Hero carousel - script isolé
 * Uniquement .header-carousel dans .hero-section - pas de conflit avec annonces ou image-carousel
 */
(function () {
    "use strict";

    function initHeroCarousel() {
        if (typeof $ === 'undefined' || !$.fn.owlCarousel) return;
        var $hero = $('.hero-section .header-carousel');
        if ($hero.length === 0) return;

        $hero.owlCarousel({
            animateOut: 'fadeOut',
            items: 1,
            margin: 0,
            stagePadding: 0,
            autoplay: true,
            autoplayTimeout: 8000,
            autoplayHoverPause: true,
            smartSpeed: 500,
            dots: true,
            loop: true,
            nav: true,
            navText: [
                '<i class="bi bi-arrow-left"></i>',
                '<i class="bi bi-arrow-right"></i>'
            ]
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initHeroCarousel);
    } else {
        initHeroCarousel();
    }
})();
