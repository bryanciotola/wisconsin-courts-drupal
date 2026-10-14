
/**
 * Wisconsin Courts - Latest News and Opinions.
 *
 * News stories may be featured.
 * Opinions always appear in a regular list.
 */

(function wisconsinCourtsLatestNews(Drupal, once) {
  'use strict';

  Drupal.behaviors.wisconsinCourtsLatestNews = {
    attach(context) {
      once(
        'wisconsin-courts-latest-news',
        '[data-wc-latest-news]',
        context,
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


        // ==================================================
        // ESCAPE HTML
        //
        // Protect text and HTML attributes when rendering.
        // ==================================================

        const escapeHtml = (value) => {
          const div = document.createElement('div');
          div.textContent = value || '';

          return div.innerHTML
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
        };


        // ==================================================
        // FEATURED NEWS STORY
        //
        // Only News stories can be featured.
        // ==================================================

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

          const ctaMarkup = item.url
            ? `
              <a
                href="${escapeHtml(item.url)}"
                class="wc-latest-news__read-more"
              >
                Read full story
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
                    Featured News
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


        // ==================================================
        // REGULAR LIST ITEM
        //
        // Used for all Opinions and secondary News stories.
        //
        // Opinion summaries are displayed beneath the title.
        // Secondary News summaries remain hidden.
        // ==================================================

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

          const summaryMarkup =
            item.type === 'opinion' && item.summary
              ? `
                <p class="wc-latest-news__item-summary">
                  ${escapeHtml(item.summary)}
                </p>
              `
              : '';

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
                ${summaryMarkup}
              </div>

              ${arrowMarkup}

            </article>
          `;
        };


        // ==================================================
        // VIEW ALL LINK
        //
        // Update the destination when switching tabs.
        // ==================================================

        const updateViewAll = (filter) => {
          if (!viewAll) {
            return;
          }

          if (filter === 'opinions') {
            viewAll.href = '/opinions';
            viewAll.innerHTML =
              'View all opinions <span aria-hidden="true">›</span>';
          } else {
            viewAll.href = '/news';
            viewAll.innerHTML =
              'View all news <span aria-hidden="true">›</span>';
          }
        };


        // ==================================================
        // RENDER SELECTED TAB
        //
        // News:
        //   1 featured News story and up to 3 other stories.
        //
        // Opinions:
        //   Up to 4 regular list items, including summaries.
        //
        // Both:
        //   1 featured News story and up to 3 additional
        //   News or Opinion items.
        //
        // If there is no News story, show a regular list.
        // ==================================================

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


          // Find the first News story.
          // An Opinion must never become the featured item.

          const featured =
            filteredItems.find((item) => item.type === 'news') || null;


          // Exclude the featured News story from the list.
          // Display four items when no featured story exists.

          const secondary = filteredItems
            .filter((item) => item !== featured)
            .slice(0, featured ? 3 : 4);


          // Generate the HTML for the featured item, if any.

          const featuredMarkup = featured
            ? renderFeatured(featured)
            : '';


          // Generate the HTML for the remaining items.

          const listMarkup = secondary
            .map((item) => renderListItem(item))
            .join('');


          // Replace the currently displayed content.

          results.innerHTML = `
            ${featuredMarkup}

            <div class="wc-latest-news__list">
              ${listMarkup}
            </div>
          `;
        };


        // ==================================================
        // TAB INTERACTIONS
        //
        // Switch between News, Opinions, and Both.
        // ==================================================

        tabs.forEach((tab) => {
          tab.addEventListener('click', () => {
            const filter = tab.dataset.newsTab;

            tabs.forEach((otherTab) => {
              const active = otherTab === tab;

              otherTab.classList.toggle(
                'wc-latest-news__tab--active',
                active,
              );

              otherTab.setAttribute(
                'aria-selected',
                active ? 'true' : 'false',
              );
            });

            render(filter);
          });
        });
      });
    },
  };
})(Drupal, once);
