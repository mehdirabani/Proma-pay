(function () {
  'use strict';

  function enhanceTables() {
    document.querySelectorAll('.proma-progress-avatar[data-progress]').forEach(function (avatar) {
      var progress = Number(avatar.dataset.progress);
      if (Number.isFinite(progress)) avatar.style.setProperty('--progress', String(Math.min(100, Math.max(0, progress))));
    });
    document.querySelectorAll('.proma-page-content table').forEach(function (table) {
      if (table.dataset.v2Table === 'off' || table.closest('[data-no-mobile-cards]') || table.classList.contains('proma-calendar-table')) return;
      var headingCells = Array.from(table.querySelectorAll('thead th'));
      if ((table.tHead && table.tHead.rows.length !== 1) || headingCells.some(function (cell) { return cell.colSpan > 1 || cell.rowSpan > 1; })) return;
      var headings = headingCells.map(function (cell) {
        return (cell.textContent || '').replace(/\s+/g, ' ').trim();
      });
      if (!headings.length) return;
      table.classList.add('proma-v2-data-table');
      table.querySelectorAll('tbody tr').forEach(function (row) {
        Array.from(row.children).forEach(function (cell, index) {
          if (!cell.dataset.label && headings[index]) cell.dataset.label = headings[index];
        });
      });
    });
  }

  function enhanceBannerRails() {
    document.querySelectorAll('[data-v2-banner-rail]').forEach(function (rail) {
      var track = rail.querySelector('.proma-customer-banner-track');
      var slides = Array.from(rail.querySelectorAll('[data-v2-banner-slide]'));
      var dots = Array.from(rail.querySelectorAll('.proma-customer-banner-dots span'));
      if (!track || slides.length < 2 || !dots.length) return;
      var queued = false;
      var update = function () {
        queued = false;
        var width = Math.max(1, track.clientWidth);
        var index = Math.max(0, Math.min(slides.length - 1, Math.round(Math.abs(track.scrollLeft) / width)));
        dots.forEach(function (dot, dotIndex) { dot.classList.toggle('active', dotIndex === index); });
      };
      track.addEventListener('scroll', function () {
        if (queued) return;
        queued = true;
        window.requestAnimationFrame(update);
      }, { passive: true });
      window.addEventListener('resize', update, { passive: true });
      update();
    });
  }

  function addShellState() {
    var main = document.querySelector('.proma-page-content');
    if (!main) return;
    var route = (main.dataset.route || 'dashboard').replace(/[^a-z0-9_-]+/gi, '-').replace(/^-|-$/g, '');
    if (route) document.body.classList.add('proma-v2-route-' + route);
  }

  function navigation() {
    var sidebar = document.getElementById('proma-navigation');
    if (!sidebar) return;
    var mobile = window.matchMedia('(max-width: 991px)');
    var buttons = Array.from(document.querySelectorAll('.toggle-sidebar'));
    var opener = null;
    var backdrop = document.createElement('button');
    backdrop.type = 'button';
    backdrop.className = 'proma-navigation-backdrop';
    backdrop.setAttribute('aria-label', 'بستن منو');
    backdrop.hidden = true;
    document.body.appendChild(backdrop);
    function setOpen(open, restore) {
      document.body.classList.toggle('proma-navigation-open', open && mobile.matches);
      sidebar.classList.toggle('open', open && mobile.matches);
      sidebar.inert = mobile.matches && !open;
      backdrop.hidden = !mobile.matches || !open;
      buttons.forEach(function (button) { button.setAttribute('aria-expanded', String(!mobile.matches || open)); });
      if (restore && opener) opener.focus();
    }
    buttons.forEach(function (button) {
      button.addEventListener('click', function () {
        if (!mobile.matches) return;
        var open = !sidebar.classList.contains('open');
        if (open) opener = button;
        setOpen(open, !open);
        if (open) sidebar.querySelector('button, a').focus();
      });
    });
    backdrop.addEventListener('click', function () { setOpen(false, true); });
    document.addEventListener('keydown', function (event) {
      if (!mobile.matches || !sidebar.classList.contains('open')) return;
      if (event.key === 'Escape') { setOpen(false, true); return; }
      if (event.key !== 'Tab') return;
      var links = Array.from(sidebar.querySelectorAll('button, a[href]')).filter(function (el) { return el.getClientRects().length && !el.disabled; });
      var first = links[0], last = links[links.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    sidebar.querySelectorAll('[data-navigation-group]').forEach(function (button, index) {
      var submenu = button.nextElementSibling;
      if (!submenu) return;
      submenu.id = 'proma-nav-group-' + index;
      button.setAttribute('aria-controls', submenu.id);
      submenu.hidden = button.getAttribute('aria-expanded') !== 'true';
      button.addEventListener('click', function () {
        var open = button.getAttribute('aria-expanded') !== 'true';
        button.setAttribute('aria-expanded', String(open));
        submenu.hidden = !open;
        submenu.classList.toggle('is-open', open);
      });
    });
    mobile.addEventListener('change', function () { setOpen(false, false); });
    setOpen(false, false);
  }

  function observeTables() {
    var main = document.querySelector('.proma-page-content');
    if (!main) return;
    var scheduled = false;
    new MutationObserver(function (records) {
      if (scheduled || !records.some(function (record) { return record.addedNodes.length; })) return;
      scheduled = true;
      window.requestAnimationFrame(function () { scheduled = false; enhanceTables(); });
    }).observe(main, { childList: true, subtree: true });
  }

  function init() {
    addShellState();
    enhanceTables();
    enhanceBannerRails();
    navigation();
    observeTables();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
}());
