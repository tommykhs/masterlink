<?php
header('Content-Type: application/javascript');
header('Cache-Control: public, max-age=3600');
?>
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(clients.claim()));
self.addEventListener('fetch', (e) => e.respondWith(fetch(e.request)));
