const CACHE_NAME = 'proma-pay-static-v1.0.8';
const STATIC_ASSETS = [
  'offline.html',
  'assets/css/app.css?v=1.0.7',
  'assets/js/app.js?v=1.0.7',
  'manifest.json'
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS)));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);
  if (request.mode === 'navigate' || url.pathname.endsWith('.php')) {
    event.respondWith(fetch(request).catch(() => caches.match('offline.html')));
    return;
  }
  event.respondWith(caches.match(request).then((cached) => cached || fetch(request)));
});
