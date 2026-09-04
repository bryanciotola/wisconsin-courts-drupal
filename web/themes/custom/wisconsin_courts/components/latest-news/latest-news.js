/**
 * Wisconsin Courts - Latest News and Opinions
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.wisconsinCourtsLatestNews = {
    attach(context) {
      once(
        'wisconsin-courts-latest-news',
        '[data-wc-latest-news]',
        context
      ).forEach((component) => {
        const tabs = component.querySelectorAll('[data-news-tab]');
        const results = component.querySelector('[data-news-results]');
        const viewAll = component.querySelector('[data-news-view-all]');
        const sourceItems = component.querySelectorAll('[data-news-item]');

        if (!tabs.length || !results || !sourceItems.length) {
          return;
        }

        const items = Array.from(sourceItems).map((element) => ({
          type: element.dataset.type || '',
          title: element.dataset.title || '',
          date: element.dataset.date || '',
          summary: element.dataset.summary || '',
          url: element.dataset.url || '',
          image: element.dataset.image || '',
          imageAlt: element.dataset.imageAlt || '',
          caseNumber: element.dataset.caseNumber || '',
        }));


        const escapeHtml = (value) => {
          const div = document.createElement('div');
          div.textContent = value || '';
          return div.innerHTML;
        };


        const renderFeatured = (item) => {
          const imageMarkup = item.image
            ? `
              <div class="wc-latest-news__featured-image-wrapper">
                <img
                  src="${escapeHtml(item.image)}"
                  alt="${escapeHtml(item.imageAlt)}"
                  class="wc-latest-news__featured-image"
                >
              </div>
            `
            : '';

          const featuredLabel = item.type === 'opinion'
            ? 'Featured Opinion'
            : 'Featured News';

          const titleMarkup = item.url
            ? `
              <a href="${escapeHtml(item.url)}">
                ${escapeHtml(item.title)}
              </a>
            `
            : escapeHtml(item.title);

          const caseMarkup = item.caseNumber
            ? `
              <div class="wc-latest-news__case-number">
                ${escapeHtml(item.caseNumber)}
              </div>
            `
            : '';

          const dateMarkup = item.date
            ? `
              <div class="wc-latest-news__featured-date">
                ${escapeHtml(item.date)}
              </div>
            `
            : '';

          const summaryMarkup = item.summary
            ? `
              <div class="wc-latest-news__featured-summary">
                ${escapeHtml(item.summary)}
              </div>
            `
            : '';

          const ctaText = item.type === 'opinion'
            ? 'View opinion'
            : 'Read full story';

          const ctaMarkup = item.url
            ? `
              <a
                href="${escapeHtml(item.url)}"
                class="wc-latest-news__read-more"
              >
                ${ctaText}
                <span aria-hidden="true">›</span>
              </a>
            `
            : '';

          return `
            <article class="wc-latest-news__featured">

              ${imageMarkup}

              <div class="wc-latest-news__featured-content">

                <div class="wc-latest-news__badge-wrapper">
                  <span class="wc-latest-news__badge">
                    ${featuredLabel}
                  </span>
                </div>

                <h3 class="wc-latest-news__featured-title">
                  ${titleMarkup}
                </h3>

                ${caseMarkup}
                ${dateMarkup}
                ${summaryMarkup}
                ${ctaMarkup}

              </div>

            </article>
          `;
        };


        const renderListItem = (item) => {
          const dateMarkup = item.date
            ? `
              <div class="wc-latest-news__item-date">
                ${escapeHtml(item.date)}
              </div>
            `
            : '';

          const caseMarkup = item.caseNumber
            ? `
              <div class="wc-latest-news__item-case-number">
                ${escapeHtml(item.caseNumber)}
              </div>
            `
            : '';

          const titleMarkup = item.url
            ? `
              <a
                href="${escapeHtml(item.url)}"
                class="wc-latest-news__item-title"
              >
                ${escapeHtml(item.title)}
              </a>
            `
            : `
              <div class="wc-latest-news__item-title">
                ${escapeHtml(item.title)}
              </div>
            `;

          const arrowMarkup = item.url
            ? `
              <span
                class="wc-latest-news__item-arrow"
                aria-hidden="true"
              >
                ›
              </span>
            `
            : '';

          return `
            <article class="wc-latest-news__item">

              <div class="wc-latest-news__item-content">
                ${dateMarkup}
                ${caseMarkup}
                ${titleMarkup}
              </div>

              ${arrowMarkup}

            </article>
          `;
        };


        const updateViewAll = (filter) => {
          if (!viewAll) {
            return;
          }

          if (filter === 'opinions') {
            viewAll.href = '/opinions';
            viewAll.innerHTML =
              'View all opinions <span aria-hidden="true">›</span>';
          }
          else {
            viewAll.href = '/news';
            viewAll.innerHTML =
              'View all news <span aria-hidden="true">›</span>';
          }
        };


        const render = (filter) => {
          const filteredItems = items.filter((item) => {
            if (filter === 'both') {
              return true;
            }

            if (filter === 'opinions') {
              return item.type === 'opinion';
            }

            return item.type === 'news';
          });

          updateViewAll(filter);

          if (!filteredItems.length) {
            results.innerHTML = `
              <p class="wc-latest-news__empty">
                No items are currently available.
              </p>
            `;
            return;
          }

          const featured = filteredItems[0];
          const secondary = filteredItems.slice(1, 4);

          const listMarkup = secondary
            .map((item) => renderListItem(item))
            .join('');

          results.innerHTML = `
            ${renderFeatured(featured)}

            <div class="wc-latest-news__list">
              ${listMarkup}
            </div>
          `;
        };


        tabs.forEach((tab) => {
          tab.addEventListener('click', () => {
            const filter = tab.dataset.newsTab;

            tabs.forEach((otherTab) => {
              const active = otherTab === tab;

              otherTab.classList.toggle(
                'wc-latest-news__tab--active',
                active
              );

              otherTab.setAttribute(
                'aria-selected',
                active ? 'true' : 'false'
              );
            });

            render(filter);
          });
        });
      });
    },
  };
})(Drupal, once);