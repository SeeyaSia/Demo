/**
 * @file
 * Global utilities.
 *
 */
(function($, Drupal) {

  'use strict';

  Drupal.behaviors.alchemize_forge = {
    attach: function(context, settings) {

      // Custom code here

    }
  };

  Drupal.behaviors.mobileNavToggle = {
    attach: function(context) {
      var toggle = context.querySelector('.site-header__toggle');
      var nav = context.querySelector('.site-header__nav');
      if (!toggle || !nav) return;

      // Prevent duplicate event listeners on Drupal behavior re-attachment (AJAX)
      if (toggle.dataset.mobileNavProcessed) return;
      toggle.dataset.mobileNavProcessed = 'true';

      toggle.addEventListener('click', function() {
        var isOpen = nav.classList.toggle('site-header__nav--open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      });
    }
  };

})(jQuery, Drupal);
