const CACHE_NAME = 'muzik-v1775119117069'
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
  "./assets/Home-DbzDSwkZ.js",
  "./assets/Home-Htf81lDq.css",
  "./assets/howler-CdqKDEqT.js",
  "./assets/index-CRv2cUkp.css",
  "./assets/index-D5vxARv3.js",
  "./assets/Layout-BOwGlFse.css",
  "./assets/Layout-C-iborgl.js",
  "./assets/Library-DP5a_Vs6.js",
  "./assets/LikeButton-DTsZsTcR.js",
  "./assets/LikedSongs-FSRtcwJz.js",
  "./assets/likes-B5TDNMaH.js",
  "./assets/pinia-Cm2UAAgM.js",
  "./assets/player-DyXKu8RX.js",
  "./assets/PlaylistView-BIFuqgSl.js",
  "./assets/QueuePanel-BMRmnZOL.css",
  "./assets/QueuePanel-Cwwk2kzl.js",
  "./assets/Search-D74D6A3t.js",
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
