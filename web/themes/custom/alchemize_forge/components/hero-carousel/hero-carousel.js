/**
 * @file
 * Hero Carousel behavior.
 *
 * Manages crossfade transitions, auto-rotation, and navigation for the
 * hero carousel component. Discovers child carousel-slide components via
 * DOM queries and reads their data attributes for the info bar.
 */

(function (Drupal) {
  'use strict';

  Drupal.behaviors.heroCarousel = {
    attach: function (context) {
      var carousels = context.querySelectorAll('.hero-carousel');

      carousels.forEach(function (carousel) {
        // Prevent duplicate processing on AJAX / partial re-renders.
        if (carousel.dataset.heroCarouselProcessed) {
          return;
        }
        carousel.dataset.heroCarouselProcessed = 'true';

        var slides = carousel.querySelectorAll('.carousel-slide');
        if (slides.length === 0) {
          return;
        }

        var currentIndex = 0;
        var autoRotateInterval = parseInt(carousel.dataset.autoRotate, 10) || 5000;
        var timer = null;

        // DOM references for the info bar.
        var titleEl = carousel.querySelector('.hero-carousel__slide-title');
        var statEl = carousel.querySelector('.hero-carousel__slide-stat');
        var currentEl = carousel.querySelector('.hero-carousel__current');
        var totalEl = carousel.querySelector('.hero-carousel__total');
        var prevBtn = carousel.querySelector('.hero-carousel__prev');
        var nextBtn = carousel.querySelector('.hero-carousel__next');

        // Set total count.
        if (totalEl) {
          totalEl.textContent = slides.length;
        }

        /**
         * Transition to a specific slide index.
         */
        function goToSlide(index) {
          // Wrap around.
          if (index < 0) {
            index = slides.length - 1;
          } else if (index >= slides.length) {
            index = 0;
          }

          // Remove active class from current slide.
          slides[currentIndex].classList.remove('carousel-slide--active');

          // Set new slide as active.
          currentIndex = index;
          slides[currentIndex].classList.add('carousel-slide--active');

          // Update info bar.
          var slide = slides[currentIndex];
          if (titleEl) {
            titleEl.textContent = slide.dataset.slideTitle || '';
          }
          if (statEl) {
            statEl.textContent = slide.dataset.slideStat || '';
            statEl.style.display = slide.dataset.slideStat ? '' : 'none';
          }
          if (currentEl) {
            currentEl.textContent = currentIndex + 1;
          }
        }

        /**
         * Start auto-rotation timer.
         */
        function startAutoRotate() {
          if (autoRotateInterval > 0) {
            timer = setInterval(function () {
              goToSlide(currentIndex + 1);
            }, autoRotateInterval);
          }
        }

        /**
         * Stop auto-rotation timer.
         */
        function stopAutoRotate() {
          if (timer) {
            clearInterval(timer);
            timer = null;
          }
        }

        // Navigation buttons.
        if (prevBtn) {
          prevBtn.addEventListener('click', function () {
            stopAutoRotate();
            goToSlide(currentIndex - 1);
            startAutoRotate();
          });
        }
        if (nextBtn) {
          nextBtn.addEventListener('click', function () {
            stopAutoRotate();
            goToSlide(currentIndex + 1);
            startAutoRotate();
          });
        }

        // Pause on hover.
        carousel.addEventListener('mouseenter', stopAutoRotate);
        carousel.addEventListener('mouseleave', startAutoRotate);

        // Initialize: activate first slide.
        goToSlide(0);
        startAutoRotate();
      });
    }
  };

})(Drupal);
