const CACHE_NAME = 'muzik-v1775611172987'
const PRECACHE_ASSETS = [
  "./",
  "./index.html",
  "./manifest.json",
  "./icons/icon-192.svg",
  "./icons/icon-512.svg",
  "./assets/AddToPlaylist-lc9PWOaB.js",
  "./assets/AlbumView-Bmnd3ZgC.js",
  "./assets/api-BEngS9Vp.js",
  "./assets/ArtistView-Bn7GQ6u0.js",
  "./assets/auth-8KkQgSRH.js",
  "./assets/db-Ds1lkQJc.js",
  "./assets/Home-B1JPq5Tb.js",
  "./assets/Home-Bsxl8vCU.css",
  "./assets/howler-CdqKDEqT.js",
  "./assets/index-CCa71-ku.js",
  "./assets/index-PAnx4MzF.css",
  "./assets/Layout-BAyytDgx.js",
  "./assets/Layout-BOwGlFse.css",
  "./assets/Library-Dd3yon04.js",
  "./assets/LikeButton-BMQFRmA1.js",
  "./assets/LikedSongs-dT90X7tD.js",
  "./assets/likes-CNTNKgDu.js",
  "./assets/pinia-Cm2UAAgM.js",
  "./assets/player-D2R-7Elh.js",
  "./assets/PlaylistView-BgfvNgjX.js",
  "./assets/QueuePanel-BMRmnZOL.css",
  "./assets/QueuePanel-DN3UDLJb.js",
  "./assets/Search-DOHF0R7q.js",
  "./assets/Upload-CXFJsmNU.js"
]

// Install - pre-cache ALL app files so it works offline immediately
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(PRECACHE_ASSETS)
    })
  )
  self.skipWaiting()
})

// Activate - clean old caches and take control
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
      )
    }).then(() => self.clients.claim())
  )
})

self.addEventListener('fetch', (event) => {
  const { request } = event
  const url = new URL(request.url)

  if (request.method !== 'GET') return

  // Auth endpoints - let browser handle directly, no SW interference
  if (url.pathname.includes('/api/login.php') || url.pathname.includes('/api/logout.php') || url.pathname.includes('/api/register.php')) {
    return
  }

  // API calls - network only, return offline fallback when no connection
  if (url.pathname.includes('/api/')) {
    event.respondWith(
      fetch(request).catch(() => {
        return new Response(JSON.stringify({ error: 'offline', songs: [], playlists: [], results: [], history: [], artists: [] }), {
          headers: { 'Content-Type': 'application/json' }
        })
      })
    )
    return
  }

  // Music files - network first, fallback cache
  if (url.pathname.includes('/music/')) {
    event.respondWith(
      caches.open(CACHE_NAME).then(async (cache) => {
        try {
          const response = await fetch(request)
          if (response.ok) cache.put(request, response.clone())
          return response
        } catch {
          const cached = await cache.match(request)
          return cached || new Response('Offline', { status: 503 })
        }
      })
    )
    return
  }

  // App shell - cache first (pre-cached), update in background
  event.respondWith(
    caches.match(request).then((cached) => {
      // Update cache in background
      const fetchPromise = fetch(request).then((response) => {
        if (response.ok) {
          caches.open(CACHE_NAME).then((cache) => cache.put(request, response.clone()))
        }
        return response
      }).catch(() => null)

      // Return cached immediately if available
      if (cached) return cached

      // Otherwise wait for network
      return fetchPromise.then((response) => {
        if (response) return response

        // SPA fallback - serve index.html for navigation
        if (request.mode === 'navigate') {
          return caches.match('./index.html')
        }
        return new Response('Offline', { status: 503 })
      })
    })
  )
})
