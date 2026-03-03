/**
 * @file
 * Scroll-reactive header with shrink/grow and color shift.
 *
 * Three visual states driven by two CSS classes:
 *
 *   1. FULL (top of page, within hero zone)
 *     - Transparent/dark background, white text, large logo.
 *     - No modifier classes.
 *
 *   2. SCROLLED + SHRUNK (scrolling down, past hero zone)
 *     - Solid white background, dark text, compact logo.
 *     - Classes: site-header--scrolled  site-header--shrunk
 *
 *   3. SCROLLED + EXPANDED (scrolling up, past hero zone)
 *     - Solid white background, dark text, large logo.
 *     - Class: site-header--scrolled  (no --shrunk)
 *
 * The hero zone (~500px) keeps the header full-size regardless of direction.
 * Past the hero zone, scroll direction controls shrink/expand.
 * Color shift and shrink never fire on the same frame.
 */
(function (Drupal) {

  'use strict';

  Drupal.behaviors.headerScroll = {
    attach: function (context) {
      var header = context.querySelector('.site-header');
      if (!header || header.dataset.headerScrollProcessed) {
        return;
      }
      header.dataset.headerScrollProcessed = 'true';

      var SCROLLED_CLASS = 'site-header--scrolled';
      var SHRUNK_CLASS = 'site-header--shrunk';

      // Hero zone: header stays transparent + full-size within this range.
      var HERO_ZONE = 500;
      // Minimum scroll distance before toggling shrink (prevents jitter).
      var DIRECTION_THRESHOLD = 5;

      var lastScrollY = window.scrollY;
      var isScrolled = false;
      var isShrunk = false;
      var ticking = false;

      function update() {
        var currentY = window.scrollY;
        var delta = currentY - lastScrollY;
        var colorShifted = false;

        // --- Color shift: transparent ↔ solid ---
        if (currentY > HERO_ZONE && !isScrolled) {
          isScrolled = true;
          header.classList.add(SCROLLED_CLASS);
          colorShifted = true;
        } else if (currentY <= HERO_ZONE && isScrolled) {
          isScrolled = false;
          header.classList.remove(SCROLLED_CLASS);
          // Also un-shrink when returning to hero zone.
          if (isShrunk) {
            isShrunk = false;
            header.classList.remove(SHRUNK_CLASS);
          }
        }

        // --- Shrink/expand (only outside hero zone) ---
        // Skip shrink on the same frame as color shift so they don't stack.
        if (currentY > HERO_ZONE && !colorShifted) {
          if (delta > DIRECTION_THRESHOLD && !isShrunk) {
            // Scrolling DOWN → shrink
            isShrunk = true;
            header.classList.add(SHRUNK_CLASS);
          } else if (delta < -DIRECTION_THRESHOLD && isShrunk) {
            // Scrolling UP → expand
            isShrunk = false;
            header.classList.remove(SHRUNK_CLASS);
          }
        }

        lastScrollY = currentY;
        ticking = false;
      }

      // Initial state
      update();

      window.addEventListener('scroll', function () {
        if (!ticking) {
          window.requestAnimationFrame(update);
          ticking = true;
        }
      }, { passive: true });
    }
  };

})(Drupal);
