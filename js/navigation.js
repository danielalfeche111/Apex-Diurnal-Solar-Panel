/**
 * Apex Diurnal - Navigation, Header & Search Controller
 */

// User Dropdown functionality
function toggleUserDropdown() {
  const dropdownMenu = document.getElementById('user-dropdown-menu');
  if (dropdownMenu) {
    dropdownMenu.classList.toggle('show');
  }
}

function closeUserDropdown() {
  const dropdownMenu = document.getElementById('user-dropdown-menu');
  if (dropdownMenu) {
    dropdownMenu.classList.remove('show');
  }
}

// Make globally accessible for inline onclick fallback
window.toggleUserDropdown = toggleUserDropdown;
window.closeUserDropdown = closeUserDropdown;

// Close dropdown when clicking outside
document.addEventListener('click', function (e) {
  const userDropdown = document.getElementById('user-dropdown');
  const dropdownMenu = document.getElementById('user-dropdown-menu');
  if (userDropdown && dropdownMenu && !userDropdown.contains(e.target)) {
    dropdownMenu.classList.remove('show');
  }
});

document.addEventListener('DOMContentLoaded', function () {
  // Setup User Dropdown listeners
  try {
    const userAccountBtn = document.getElementById('user-account-btn');
    const userDropdownMenu = document.getElementById('user-dropdown-menu');

    if (userAccountBtn && userDropdownMenu) {
      userAccountBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        toggleUserDropdown();
      });
      userAccountBtn.setAttribute('onclick', 'toggleUserDropdown(); event.stopPropagation();');
    }
  } catch (e) {
    console.error('Error setting up user dropdown:', e);
  }

  // Setup Search functionality
  try {
    const toggle = document.getElementById('search-toggle');
    const bar = document.getElementById('search-bar');
    const input = document.getElementById('search-input');
    const closeBtn = document.getElementById('search-close');
    const container = document.getElementById('search-container');
    const results = document.getElementById('search-results');
    const cards = document.querySelectorAll('.product-card');
    const navLinks = document.querySelectorAll('.nav-link');
    const footerLinks = document.querySelectorAll('.footer-links a');

    if (toggle && bar && input && closeBtn && container && results) {
      function openSearch() {
        bar.classList.add('active');
        toggle.setAttribute('aria-expanded', 'true');
        setTimeout(function () { input.focus(); }, 100);
      }

      function closeSearch() {
        bar.classList.remove('active');
        results.classList.remove('active');
        results.innerHTML = '';
        toggle.setAttribute('aria-expanded', 'false');
        input.value = '';
        document.body.classList.remove('is-searching');
      }

      function navigateTo(href) {
        closeSearch();
        const target = document.querySelector(href);
        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        else window.location.hash = href;
      }

      function buildResultItem(label, href, type) {
        const div = document.createElement('div');
        div.className = 'search-result-item';
        div.innerHTML = '<span class="result-type">' + type + '</span> ' + label;
        div.addEventListener('click', function () { navigateTo(href); });
        return div;
      }

      toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        if (bar.classList.contains('active')) closeSearch(); else openSearch();
      });

      closeBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        closeSearch();
      });

      document.addEventListener('click', function (e) {
        if (!container.contains(e.target) && bar.classList.contains('active')) closeSearch();
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && bar.classList.contains('active')) closeSearch();
      });

      input.addEventListener('input', function () {
        const q = input.value.toLowerCase().trim();
        results.innerHTML = '';
        results.classList.remove('active');

        if (!q) {
          document.body.classList.remove('is-searching');
          return;
        }
        document.body.classList.add('is-searching');

        // Product search
        let productMatches = [];
        cards.forEach(function (card) {
          const titleEl = card.querySelector('.product-title');
          const title = (titleEl?.textContent || '').toLowerCase();
          const price = (card.querySelector('.product-price')?.textContent || '').toLowerCase();
          const match = title.includes(q) || price.includes(q) ||
            (q.includes('professional') && title.includes('professional')) ||
            (q.includes('proffesional') && title.includes('professional'));
          if (match) productMatches.push({ card: card, title: titleEl?.textContent.trim() || 'Product', href: '#' + card.id });
        });

        // Navigation search
        let navMatches = [];
        navLinks.forEach(function (link) {
          const text = (link.textContent || '').toLowerCase();
          const href = (link.getAttribute('href') || '').toLowerCase();
          const match = text.includes(q) || href.includes(q);
          if (match) {
            navMatches.push({ label: link.textContent.trim(), href: link.getAttribute('href'), type: 'NAV' });
          }
        });
        footerLinks.forEach(function (link) {
          const text = (link.textContent || '').toLowerCase();
          if (text.includes(q)) {
            if (!navMatches.some(function (m) { return m.href === link.getAttribute('href'); })) {
              navMatches.push({ label: link.textContent.trim(), href: link.getAttribute('href'), type: 'NAV' });
            }
          }
        });

        const isProductSearch = q.includes('product') || navMatches.some(function (m) { return m.href === '#products'; });
        if (isProductSearch && productMatches.length === 0) {
          productMatches = Array.from(cards).map(function (card) {
            return { card: card, title: card.querySelector('.product-title')?.textContent.trim() || 'Product', href: '#' + card.id };
          });
        }

        let hasResults = false;
        navMatches.forEach(function (m) {
          const div = document.createElement('div');
          div.className = 'search-result-item';
          div.textContent = m.label;
          div.addEventListener('click', function () { navigateTo(m.href); });
          results.appendChild(div);
          hasResults = true;
        });
        productMatches.forEach(function (m) {
          results.appendChild(buildResultItem(m.title, m.href, 'PRODUCT'));
          hasResults = true;
        });

        if (hasResults) {
          results.classList.add('active');
        } else {
          const empty = document.createElement('div');
          empty.className = 'search-result-item';
          empty.style.opacity = '0.6';
          empty.style.cursor = 'default';
          empty.textContent = 'No results for "' + input.value + '"';
          results.appendChild(empty);
          results.classList.add('active');
        }
      });

      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          const firstResult = results.querySelector('.search-result-item');
          if (firstResult && firstResult.textContent.indexOf('No results') === -1) {
            firstResult.click();
            return;
          }
          const qNav = input.value.toLowerCase().trim();
          let firstNav = null;
          if (qNav) {
            firstNav = Array.from(navLinks).find(function (l) {
              return (l.textContent || '').toLowerCase().includes(qNav) || (l.getAttribute('href') || '').toLowerCase().includes(qNav);
            });
          }
          if (firstNav) {
            const href = firstNav.getAttribute('href');
            const target = href ? document.querySelector(href) : null;
            if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
          }
          const firstCard = document.querySelector('.product-card:not([style*="display: none"])');
          if (firstCard) firstCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      });
    }
  } catch (e) {
    console.error('Error setting up search:', e);
  }
});
