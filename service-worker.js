const CACHE_PREFIX = 'proma-pay-v7-0-3-';

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((key) => key.indexOf(CACHE_PREFIX) === 0).map((key) => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  return;
});

self.addEventListener('push', (event) => {
  let payload = {};
  try { payload = event.data ? event.data.json() : {}; } catch (error) {
    payload = { title: 'اعلان پروما پی', body: event.data ? event.data.text() : '' };
  }
  const title = String(payload.title || 'اعلان پروما پی').slice(0, 120);
  const options = {
    body: String(payload.body || '').slice(0, 1500),
    tag: String(payload.tag || 'proma-notification').slice(0, 120),
    dir: 'rtl',
    lang: 'fa-IR',
    data: { url: payload.url || self.registration.scope }
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = new URL(String(event.notification.data && event.notification.data.url || self.registration.scope), self.registration.scope);
  const scope = new URL(self.registration.scope);
  if (target.origin !== scope.origin || !target.pathname.startsWith(scope.pathname)) return;
  event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
    for (const client of clients) {
      if (new URL(client.url).origin === target.origin) {
        return client.navigate(target.toString()).then((windowClient) => windowClient ? windowClient.focus() : self.clients.openWindow(target.toString()));
      }
    }
    return self.clients.openWindow(target.toString());
  }));
});
