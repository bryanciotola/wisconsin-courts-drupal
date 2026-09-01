(() => {
  const initializeHeaders = () => {
    document.querySelectorAll('.wc-site-header').forEach((header) => {
      if (header.dataset.mobileMenuInitialized === 'true') {
        return;
      }

      const button = header.querySelector('.wc-site-header__menu-button');
      const menu = header.querySelector('.wc-site-header__mobile-nav');
      const label = header.querySelector('.wc-site-header__menu-label');

      if (!button || !menu) {
        return;
      }

      header.dataset.mobileMenuInitialized = 'true';

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
        }
        else {
          openMenu();
        }
      });

      window.addEventListener('resize', () => {
        if (window.matchMedia('(min-width: 64em)').matches) {
          closeMenu();
        }
      });
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeHeaders);
  }
  else {
    initializeHeaders();
  }
})();