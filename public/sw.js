const CACHE_NAME = 'netcrew-pwa-v27';
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
        STATIC_ASSETS.map((url) => cache.add(url).catch(() => { }))
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

  // Solo gestionar peticiones GET del mismo origen
  if (request.method !== 'GET' || url.origin !== self.location.origin) {
    return;
  }

  // Para peticiones de navegación (páginas HTML): Network-First
  // Así siempre se tiene la información más reciente de nodos y estado VPN
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(async () => {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
          return cachedResponse;
        }
        const rootFallback = await caches.match('/');
        return rootFallback || new Response('Sin conexión con el servidor.', {
          status: 503,
          statusText: 'Service Unavailable',
          headers: { 'Content-Type': 'text/plain; charset=utf-8' }
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
        }).catch(() => cached || Response.error());

        return cached || fetchPromise;
      })
    );
    return;
  }

  // Comportamiento habitual para el resto de peticiones
  event.respondWith(
    fetch(request).catch(async () => {
      const cached = await caches.match(request);
      return cached || Response.error();
    })
  );
});

