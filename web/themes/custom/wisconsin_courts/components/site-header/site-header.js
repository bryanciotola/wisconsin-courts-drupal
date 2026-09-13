// Drupal behaviors also attach after AJAX and Layout Builder updates.
(function wisconsinCourtsHeader(Drupal, once) {
  'use strict';

  Drupal.behaviors.wisconsinCourtsHeader = {
    attach(context) {
      once('wc-site-header', '.wc-site-header', context).forEach((header) => {
        const button = header.querySelector('.wc-site-header__menu-button');
        const menu = header.querySelector('.wc-site-header__mobile-nav');
        const label = header.querySelector('.wc-site-header__menu-label');

        if (!button || !menu) {
          return;
        }

        const closeMenu = () => {
          menu.hidden = true;
          button.setAttribute('aria-expanded', 'false');

          if (label) {
            label.textContent = 'Menu';
          }
        };

        const openMenu = () => {
          menu.hidden = false;
          button.setAttribute('aria-expanded', 'true');

          if (label) {
            label.textContent = 'Close';
          }
        };

        button.addEventListener('click', () => {
          const isOpen = button.getAttribute('aria-expanded') === 'true';

          if (isOpen) {
            closeMenu();
          } else {
            openMenu();
          }
        });

        header.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && !menu.hidden) {
            closeMenu();
            button.focus();
          }
        });

        window.addEventListener('resize', () => {
          if (window.matchMedia('(min-width: 64em)').matches) {
            closeMenu();
          }
        });
      });
    },
  };
})(Drupal, once);
