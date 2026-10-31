const CACHE_NAME = 'netcrew-pwa-v1';
const STATIC_ASSETS = [
  '/',
  '/manifest.json',
  '/assets/css/styles.css',
  '/assets/css/custom.css',
  '/assets/images/logos/favicon.png',
  '/assets/images/logos/icon-192.png',
  '/assets/images/logos/icon-512.png',
  '/assets/images/logos/apple-touch-icon.png',
  '/assets/images/logos/dark-logo.svg',
  '/assets/images/logos/light-logo.svg',
  '/assets/libs/jquery/dist/jquery.min.js',
  '/assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js'
];

// Instalación del Service Worker
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      // Intentar cachear los recursos estáticos principales; si alguno falla no bloquear la instalación
      return Promise.allSettled(
        STATIC_ASSETS.map((url) => cache.add(url).catch(() => {}))
      );
    }).then(() => self.skipWaiting())
  );
});

// Activación y limpieza de cachés antiguos
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Intercepción de peticiones (Fetch)
self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  // Solo gestionar peticiones HTTP(S) GET
  if (request.method !== 'GET' || !url.protocol.startsWith('http')) {
    return;
  }

  // Para peticiones de navegación (páginas HTML): Network-First
  // Así siempre se tiene la información más reciente de nodos y estado WireGuard
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => {
        return caches.match(request).then((cachedResponse) => {
          if (cachedResponse) {
            return cachedResponse;
          }
          return caches.match('/');
        });
      })
    );
    return;
  }

  // Para recursos estáticos (CSS, JS, imágenes, fuentes): Stale-While-Revalidate
  if (
    request.destination === 'style' ||
    request.destination === 'script' ||
    request.destination === 'image' ||
    request.destination === 'font'
  ) {
    event.respondWith(
      caches.match(request).then((cached) => {
        const fetchPromise = fetch(request).then((networkResponse) => {
          if (networkResponse && networkResponse.status === 200) {
            const responseClone = networkResponse.clone();
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(request, responseClone);
            });
          }
          return networkResponse;
        }).catch(() => cached);

        return cached || fetchPromise;
      })
    );
    return;
  }

  // Comportamiento habitual para el resto de peticiones
  event.respondWith(
    fetch(request).catch(() => caches.match(request))
  );
});

