<?php
header('Content-Type: application/javascript');
header('Cache-Control: public, max-age=3600');
?>
self.addEventListener('install', function(e) {
  self.skipWaiting();
});

self.addEventListener('activate', function(e) {
  e.waitUntil(clients.claim());
});

self.addEventListener('fetch', function(e) {});
