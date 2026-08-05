const CACHE_NAME = 'bubble-royale-home-v1';
const APP_SHELL = [
  './',
  './index-modular.html',
  './styles.css',
  './app.js',
  './manifest.webmanifest',
  './assets/bubble-royale-logo.png',
  './assets/bubble-royale-game-board.png',
  './assets/bubble-royale-launcher.png',
  './assets/bubble-royale-prize-orb-5000.png',
  './assets/bubble-royale-tournament-trophy.png',
  './assets/bubble-royale-decorative-bubble-cluster.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL)));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(
      keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)),
    )),
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;
  event.respondWith(
    caches.match(event.request).then((cached) => cached || fetch(event.request).then((response) => {
      const copy = response.clone();
      caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
      return response;
    }).catch(() => caches.match('./index-modular.html'))),
  );
});
