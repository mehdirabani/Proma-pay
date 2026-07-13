(function () {
  document.querySelectorAll('[data-zarinpal-settings]').forEach(function (form) {
    var environment = form.querySelector('[data-zarinpal-environment]');
    if (!environment) return;
    environment.addEventListener('change', function () {
      form.setAttribute('data-environment', environment.value);
    });
    form.setAttribute('data-environment', environment.value);
  });
})();
