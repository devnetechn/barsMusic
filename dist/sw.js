const CACHE_NAME = 'muzik-v1775116474386'
const PRECACHE_ASSETS = [
  "./",
  "./index.html",
  "./manifest.json",
  "./icons/icon-192.svg",
  "./icons/icon-512.svg",
  "./assets/AddToPlaylist-SGXPBBF4.js",
  "./assets/api-BPGeZ2SM.js",
  "./assets/auth-DcFo41KE.js",
  "./assets/db-Ds1lkQJc.js",
  "./assets/Home-BAdFNuaI.js",
  "./assets/Home-Htf81lDq.css",
  "./assets/howler-CdqKDEqT.js",
  "./assets/index-CVR0Tbz-.css",
  "./assets/index-D0OA1G_E.js",
  "./assets/Layout-6TxgUZC-.js",
  "./assets/Layout-BOwGlFse.css",
  "./assets/Library-CaebCITg.js",
  "./assets/LikeButton-EDqxAjtf.js",
  "./assets/LikedSongs-B9fFWTM6.js",
  "./assets/likes-C715v4kC.js",
  "./assets/pinia-Cm2UAAgM.js",
  "./assets/player-6AbbDnhg.js",
  "./assets/PlaylistView--Sr_uaIv.js",
  "./assets/QueuePanel-BMRmnZOL.css",
  "./assets/QueuePanel-DxffULsM.js",
  "./assets/Search-z-lqS-J5.js",
  "./assets/Upload-qBpyKyqW.js"
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
