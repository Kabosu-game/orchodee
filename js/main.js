(function ($) {
    "use strict";

    // Spinner
    var spinner = function () {
        setTimeout(function () {
            if ($('#spinner').length > 0) {
                $('#spinner').removeClass('show');
            }
        }, 100);
    };
    
    // Attendre que le DOM soit prêt
    $(document).ready(function() {
        spinner();
    });
    
    // Aussi au chargement de la page
    $(window).on('load', function() {
        $('#spinner').removeClass('show');
    });
    
    
    try {
        if (typeof WOW !== 'undefined') {
            new WOW().init();
        }
    } catch (e) {}
    

    // Sticky Navbar
    $(window).scroll(function () {
        if ($(this).scrollTop() > 45) {
            $('.nav-bar').addClass('sticky-top shadow-sm').css('top', '0px');
        } else {
            $('.nav-bar').removeClass('sticky-top shadow-sm').css('top', '-100px');
        }
    });


    // Image carousel (about section - pas hero, pas annonces)
    $(document).ready(function() {
        try {
            if ($(".image-carousel").length > 0 && typeof $.fn.owlCarousel === 'function') {
                $(".image-carousel").owlCarousel({
                animateOut: 'fadeOut',
                items: 1,
                margin: 0,
                stagePadding: 0,
                autoplay: true,
                autoplayTimeout: 3000, // 3 secondes entre chaque image
                autoplayHoverPause: true,
                smartSpeed: 500,
                dots: true,
                loop: true,
                nav: true,
                navText: [
                    '<i class="bi bi-arrow-left"></i>',
                    '<i class="bi bi-arrow-right"></i>'
                ],
                responsive: {
                    0: {
                        items: 1
                    },
                    600: {
                        items: 1
                    },
                    1000: {
                        items: 1
                    }
                }
            });
            }
        } catch (e) {}
    });

    // Facts counter (only if counterUp plugin is loaded)
    try {
        if (typeof $.fn.counterUp === 'function' && $('[data-toggle="counter-up"]').length > 0) {
            $('[data-toggle="counter-up"]').counterUp({ delay: 5, time: 2000 });
        }
    } catch (e) {}

    // Index page hover effects (Why Choose Us, Services, Testimonials)
    $(document).ready(function() {
        $('.container-fluid.py-5[style*="f8f9fa"][style*="e9ecef"] .bg-white.rounded.shadow-lg').on('mouseenter', function() { $(this).css('transform', 'translateY(-10px)'); }).on('mouseleave', function() { $(this).css('transform', 'translateY(0)'); });
        $('.service-card').on('mouseenter', function() { $(this).css({'transform': 'translateY(-10px)', 'box-shadow': '0 15px 35px rgba(0, 0, 0, 0.15)'}); }).on('mouseleave', function() { $(this).css({'transform': 'translateY(0)', 'box-shadow': '0 0.5rem 1rem rgba(0, 0, 0, 0.15)'}); });
        $('.testimonials-section .bg-white.rounded.shadow-lg.p-4[style*="border-left"]').on('mouseenter', function() { $(this).css({'transform': 'translateY(-5px)', 'box-shadow': '0 10px 25px rgba(0, 0, 0, 0.1)'}); }).on('mouseleave', function() { $(this).css({'transform': 'translateY(0)', 'box-shadow': '0 0.5rem 1rem rgba(0, 0, 0, 0.15)'}); });

        // Rating stars hover
        $('.rating-input label.star-label').on('mouseenter', function() {
            var val = parseInt($(this).attr('for').replace('rating', ''));
            $('.rating-input label.star-label').each(function() {
                var lVal = 5 - $('.rating-input label.star-label').index($(this));
                $(this).css('color', lVal <= val ? '#ffc107' : '#ddd');
            });
        });
        $('.rating-input').on('mouseleave', function() {
            var checked = $('.rating-input input:checked').val();
            $('.rating-input label.star-label').each(function() {
                var lVal = 5 - $('.rating-input label.star-label').index($(this));
                $(this).css('color', checked && lVal <= parseInt(checked) ? '#ffc107' : '#ddd');
            });
        });
    });


   // Back to top button
   $(window).scroll(function () {
    if ($(this).scrollTop() > 300) {
        $('.back-to-top').fadeIn('slow');
    } else {
        $('.back-to-top').fadeOut('slow');
    }
    });
    $('.back-to-top').click(function () {
        $('html, body').animate({scrollTop: 0}, 1500, 'easeInOutExpo');
        return false;
    });


})(jQuery);

