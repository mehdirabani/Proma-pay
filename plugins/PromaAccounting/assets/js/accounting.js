document.addEventListener('submit', function (event) {
  var form = event.target.closest('[data-accounting-confirm]');
  if (form && !window.confirm(form.getAttribute('data-accounting-confirm'))) {
    event.preventDefault();
  }
});
