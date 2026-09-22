(function () {
  'use strict';

  function enhanceTables() {
    document.querySelectorAll('.proma-page-content table').forEach(function (table) {
      if (table.dataset.v2Table === 'off' || table.closest('[data-no-mobile-cards]') || table.classList.contains('proma-calendar-table')) return;
      var headings = Array.from(table.querySelectorAll('thead th')).map(function (cell) {
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

  function modalSafetyNet() {
    document.addEventListener('click', function (event) {
      var close = event.target.closest('[data-close-modal]');
      if (!close) return;
      var modal = close.closest('.modal');
      if (!modal) return;
      window.setTimeout(function () {
        modal.classList.remove('open', 'is-open', 'show');
        modal.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('.modal.open, .modal.is-open, .modal.show')) {
          document.body.classList.remove('proma-modal-open', 'modal-open');
          document.documentElement.classList.remove('proma-modal-open');
        }
      }, 0);
    }, true);
  }

  function init() {
    addShellState();
    enhanceTables();
    enhanceBannerRails();
    modalSafetyNet();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
}());
