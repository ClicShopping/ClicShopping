// Offline page service worker with versioned cache and activate cleanup

const CACHE = "pwabuilder-page-v1";
const offlineFallbackPage = "offline.php";

// Install stage: cache offline page
self.addEventListener("install", function (event) {
  event.waitUntil(
    caches.open(CACHE).then(function (cache) {
      return cache.add(offlineFallbackPage);
    })
  );
});

// Activate stage: clean up old caches
self.addEventListener("activate", function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(
        keys.filter(key => key !== CACHE).map(key => caches.delete(key))
      );
    })
  );
});

// Fetch: serve cached offline page on failed navigation
self.addEventListener("fetch", function (event) {
  if (event.request.method !== "GET") return;

  event.respondWith(
    fetch(event.request).catch(function () {
      if (event.request.destination !== "document" || event.request.mode !== "navigate") {
        return;
      }
      return caches.open(CACHE).then(function (cache) {
        return cache.match(offlineFallbackPage);
      });
    })
  );
});

// Refresh offline page via postMessage from the page
self.addEventListener("refreshOffline", function () {
  const offlinePageRequest = new Request(offlineFallbackPage);
  return fetch(offlineFallbackPage).then(function (response) {
    return caches.open(CACHE).then(function (cache) {
      return cache.put(offlinePageRequest, response);
    });
  });
});