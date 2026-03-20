<?php
header('Content-Type: application/javascript');
header('Cache-Control: public, max-age=3600');
?>
var CACHE_NAME = 'pwa-cache-v1';

self.addEventListener('install', function(e) {
  self.skipWaiting();
});

self.addEventListener('activate', function(e) {
  e.waitUntil(
    caches.keys().then(function(names) {
      return Promise.all(
        names.filter(function(n) { return n !== CACHE_NAME; })
             .map(function(n) { return caches.delete(n); })
      );
    }).then(function() { return clients.claim(); })
  );
});

self.addEventListener('fetch', function(e) {
  // Only cache same-origin GET requests
  if (e.request.method !== 'GET') {
    e.respondWith(fetch(e.request));
    return;
  }

  // Cross-origin (Google iframe etc) — network only, no cache
  if (!e.request.url.startsWith(self.location.origin)) {
    e.respondWith(fetch(e.request).catch(function() {
      return new Response('', { status: 503 });
    }));
    return;
  }

  // Same-origin: network first, fall back to cache
  e.respondWith(
    fetch(e.request).then(function(resp) {
      if (resp.ok) {
        var clone = resp.clone();
        caches.open(CACHE_NAME).then(function(cache) {
          cache.put(e.request, clone);
        });
      }
      return resp;
    }).catch(function() {
      return caches.match(e.request).then(function(cached) {
        return cached || new Response('Offline — page not cached', {
          status: 503,
          headers: { 'Content-Type': 'text/plain' }
        });
      });
    })
  );
});
