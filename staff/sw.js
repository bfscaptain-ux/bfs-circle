// staff/sw.js - Service Worker for BFS Field Staff Mobile App
const CACHE_NAME = 'bfs-staff-app-v1';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(keys.map((key) => caches.delete(key)));
        }).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    // Pass through all API requests & live updates dynamically
    event.respondWith(fetch(event.request).catch(() => caches.match(event.request)));
});