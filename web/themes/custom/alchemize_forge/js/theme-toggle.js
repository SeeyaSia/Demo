/**
 * @file
 * Dark mode toggle with localStorage persistence and logo switching.
 */
(function (Drupal) {

  'use strict';

  Drupal.behaviors.themeToggle = {
    attach: function (context) {
      var toggleBtn = context.querySelector('.theme-toggle');
      if (!toggleBtn || toggleBtn.dataset.themeToggleProcessed) {
        return;
      }
      toggleBtn.dataset.themeToggleProcessed = 'true';

      var htmlEl = document.documentElement;

      // Derive dark logo path from the light logo src.
      // Light logo: /themes/custom/alchemize_forge/logo.png
      // Dark logo:  /themes/custom/alchemize_forge/images/logo-dark.png
      var darkLogoPath = null;
      var logo = document.querySelector('.site-header__logo img');
      if (logo) {
        var lightSrc = logo.getAttribute('src');
        var themeDir = lightSrc.substring(0, lightSrc.lastIndexOf('/'));
        darkLogoPath = themeDir + '/images/logo-dark.png';
      }

      // Determine initial theme
      var stored = localStorage.getItem('alchemize-theme');
      if (stored) {
        htmlEl.setAttribute('data-bs-theme', stored);
      } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
        htmlEl.setAttribute('data-bs-theme', 'dark');
      }

      // Update icon and logo
      function updateThemeUI() {
        var isDark = htmlEl.getAttribute('data-bs-theme') === 'dark';
        toggleBtn.querySelector('.theme-toggle__icon').textContent = isDark ? '\u2600\uFE0F' : '\uD83C\uDF19';

        // Logo switching
        if (logo && darkLogoPath) {
          if (!logo.dataset.logoLight) {
            logo.dataset.logoLight = logo.getAttribute('src');
          }
          logo.src = isDark ? darkLogoPath : logo.dataset.logoLight;
        }
      }
      updateThemeUI();

      // Toggle handler
      toggleBtn.addEventListener('click', function () {
        var current = htmlEl.getAttribute('data-bs-theme');
        var next = current === 'dark' ? 'light' : 'dark';
        htmlEl.setAttribute('data-bs-theme', next);
        localStorage.setItem('alchemize-theme', next);
        updateThemeUI();
      });
    }
  };

})(Drupal);
