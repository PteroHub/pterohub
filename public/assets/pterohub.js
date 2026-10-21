/* PteroHub Point Zero — one authoritative JavaScript, no duplicate listeners. */
(() => {
  'use strict';
  const doc = document;
  const clientToggle = doc.querySelector('[data-client-nav-toggle]');
  const clientNav = doc.querySelector('#ph-client-nav');
  function setClientNav(open) {
    if (!clientNav || !clientToggle) return;
    clientNav.classList.toggle('is-open', open);
    clientToggle.setAttribute('aria-expanded', String(open));
  }
  clientToggle?.addEventListener('click', () => setClientNav(!clientNav?.classList.contains('is-open')));
  clientNav?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setClientNav(false)));
  doc.addEventListener('click', event => {
    if (clientNav?.classList.contains('is-open') && !event.target.closest('.ph-client-header-inner')) setClientNav(false);
  });
  doc.addEventListener('keydown', event => {
    if (event.key === 'Escape' && clientNav?.classList.contains('is-open')) {
      setClientNav(false);
      clientToggle?.focus();
    }
  });
  window.addEventListener('resize', () => { if (window.innerWidth > 680) setClientNav(false); });
  const body = doc.body;
  const sidebar = doc.querySelector('#ph-sidebar');
  const mobileToggle = doc.querySelector('[data-ph-menu-toggle]');
  const closeControls = doc.querySelectorAll('[data-ph-menu-close]');
  const dashboardPanels = [...doc.querySelectorAll('[data-tab-panel]')];
  const sections = new Set(['overview','games','eggs','plans']);

  function setMenuOpen(open) {
    body.classList.toggle('ph-menu-open', open);
    mobileToggle?.setAttribute('aria-expanded', String(open));
    if (open) doc.querySelector('.ph-sidebar-close')?.focus();
    else if (sidebar?.contains(doc.activeElement)) mobileToggle?.focus();
  }
  mobileToggle?.addEventListener('click', () => setMenuOpen(!body.classList.contains('ph-menu-open')));
  closeControls.forEach(button => button.addEventListener('click', () => setMenuOpen(false)));
  sidebar?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setMenuOpen(false)));
  doc.addEventListener('keydown', e => { if (e.key === 'Escape') setMenuOpen(false); });
  window.addEventListener('resize', () => { if (window.innerWidth > 760) setMenuOpen(false); });

  function normalized(path) { return path.replace(/\/$/, '') || '/'; }
  function renderDashboardSection() {
    const selected = sections.has(location.hash.slice(1)) ? location.hash.slice(1) : 'overview';
    for (const panel of dashboardPanels) {
      const active = panel.dataset.tabPanel === selected;
      panel.hidden = !active;
      panel.style.display = active ? '' : 'none';
      panel.setAttribute('aria-hidden', String(!active));
    }
    updateActive(selected);
    // Prevent browsing to a hash from focusing a section far down the long dashboard document.
    if (dashboardPanels.length) window.scrollTo(0,0);
  }
  function updateActive(selected = 'overview') {
    const here = normalized(location.pathname);
    const isDashboard = dashboardPanels.length > 0;
    const links = [...doc.querySelectorAll('.sidebar .nav-link[href]')];
    let winner = null;
    for (const link of links) {
      const url = new URL(link.href, location.href);
      if (normalized(url.pathname) !== here) continue;
      if (isDashboard) {
        if ((url.hash.slice(1) || 'overview') === selected) { winner=link; break; }
      } else if (!winner && !url.hash) { winner=link; }
    }
    links.forEach(link => {
      const active = link === winner;
      link.classList.toggle('active', active);
      if (active) link.setAttribute('aria-current','page');
      else link.removeAttribute('aria-current');
    });
  }
  window.addEventListener('hashchange', () => { renderDashboardSection(); setMenuOpen(false); });
  if (dashboardPanels.length) {
    if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
    renderDashboardSection();
    window.addEventListener('pageshow', () => window.scrollTo(0,0));
  } else updateActive();

  // Egg search, previously in pterohub-admin.js.
  const eggSearch = doc.getElementById('eggSearch');
  const eggTable = doc.getElementById('eggTable');
  const eggCounter = doc.getElementById('eggCount');
  if (eggSearch && eggTable) {
    const rows=[...eggTable.querySelectorAll('tbody tr')];
    eggSearch.addEventListener('input', () => {
      const query=eggSearch.value.trim().toLowerCase();
      let count=0;
      rows.forEach(row => {
        const matches=(row.dataset.search || '').toLowerCase().includes(query);
        row.hidden=!matches;
        if(matches)count++;
      });
      if(eggCounter) eggCounter.textContent=`Showing ${count} of ${rows.length} imported Eggs`;
    });
  }

  // Product editor tabs, Egg filter and product list search, previously in pterohub-products-v10.js.
  const productTabs=[...doc.querySelectorAll('[data-phtab]')];
  const productPanels=[...doc.querySelectorAll('[data-phpanel]')];
  function activateProductTab(name) {
    if (!productPanels.length) return;
    productTabs.forEach(tab => {
      const active=tab.dataset.phtab === name;
      tab.classList.toggle('selected',active);
      tab.setAttribute('aria-selected',String(active));
    });
    productPanels.forEach(panel => { panel.hidden=panel.dataset.phpanel !== name; });
    // Tab identifiers are local to editor; never mutate dashboard hashes.
    if (history.replaceState) history.replaceState(null,'','#'+name);
  }
  productTabs.forEach(tab => tab.addEventListener('click', () => activateProductTab(tab.dataset.phtab)));
  doc.querySelectorAll('[data-phnext]').forEach(button => button.addEventListener('click', () => activateProductTab(button.dataset.phnext)));
  if (productPanels.length) {
    const tabName=location.hash.slice(1);
    activateProductTab(['general','resources','pricing'].includes(tabName) ? tabName : 'general');
  }
  const game=doc.getElementById('product-game');
  const egg=doc.getElementById('product-egg');
  if(game && egg) {
    function filterEggs() {
      [...egg.options].forEach(option => { if(option.value) option.hidden=option.dataset.game !== game.value; });
      if(egg.selectedOptions[0]?.hidden) egg.value='';
    }
    game.addEventListener('change',filterEggs); filterEggs();
  }
  const productSearch=doc.getElementById('ph-products-search');
  productSearch?.addEventListener('input', () => {
    const query=productSearch.value.trim().toLowerCase();
    doc.querySelectorAll('#ph-products-table tbody tr[data-search]').forEach(row => {
      row.hidden=!(row.dataset.search || '').includes(query);
    });
  });
})();

/* dev1.10: price preview is cosmetic; checkout validates prices server-side. */
(() => {
  'use strict';
  const form = document.querySelector('[data-ph-price-form]');
  const total = form?.querySelector('[data-ph-price-total]');
  if (!form || !total) return;
  const format = new Intl.NumberFormat('en-IE', {style:'currency', currency:'EUR'});
  const update = () => {
    const chosen = form.querySelector('input[name="billing_months"]:checked');
    if (!chosen) return;
    const amount = Number(chosen.dataset.price) + Number(chosen.dataset.setup);
    if (Number.isFinite(amount) && amount >= 0) total.textContent = format.format(amount / 100);
  };
  form.querySelectorAll('input[name="billing_months"]').forEach(input => input.addEventListener('change', update));
  update();
})();

/* dev1.20 — cart uses standard POST forms with CSRF and server-side pricing. */

/* Canary 1.10: keep business-only billing fields out of individual accounts. */
(() => {
  'use strict';
  const form = document.querySelector('[data-ph-billing-form]');
  const selector = form?.querySelector('[data-ph-customer-type]');
  if (!form || !selector) return;
  const fields = [...form.querySelectorAll('[data-ph-business-field]')];
  const update = () => {
    const business = selector.value === 'business';
    fields.forEach(field => {
      field.hidden = !business;
      field.querySelectorAll('input').forEach(input => { input.disabled = !business; });
    });
  };
  selector.addEventListener('change',update);
  update();
})();

/* RC 1.02: no additional global listeners; existing mobile navigation preserved. */
