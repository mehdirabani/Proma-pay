import './bootstrap';

const persianDate = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
    dateStyle: 'full',
    timeZone: 'Asia/Tehran',
});

document.querySelectorAll('[data-persian-today]').forEach((element) => {
    element.textContent = persianDate.format(new Date());
});

document.querySelectorAll('[data-persian-date]').forEach((element) => {
    const value = element.getAttribute('data-persian-date');
    const date = value ? new Date(value) : null;
    if (date && !Number.isNaN(date.getTime())) {
        element.textContent = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
            year: 'numeric', month: 'short', day: 'numeric', timeZone: 'Asia/Tehran',
        }).format(date);
    }
});
