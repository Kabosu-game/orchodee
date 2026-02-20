/* Annonces - version simple */
(function() {
    function init() {
        var el = document.querySelector('.annonces-simple');
        if (!el || typeof $ === 'undefined' || !$.fn.owlCarousel) return;
        $(el).owlCarousel({
        items: 1,
        loop: true,
        dots: true,
        nav: false,
        autoplay: true,
        autoplayTimeout: 4000
        });
        el.querySelectorAll('video').forEach(function(v) { v.play().catch(function(){}); });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
