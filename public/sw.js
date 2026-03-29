const CACHE_NAME = 'muzik-v4'

self.addEventListener('install', (event) => {
  // Take over immediately
  self.skipWaiting()
})

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

  // Skip non-GET
  if (request.method !== 'GET') return

  // Auth endpoints - let browser handle directly, no SW interference
  if (url.pathname.includes('/api/login.php') || url.pathname.includes('/api/logout.php')) {
    return
  }

  // API calls - network only, don't cache (stale data is worse)
  if (url.pathname.includes('/api/')) {
    event.respondWith(
      fetch(request).catch(() => {
        return new Response(JSON.stringify({ songs: [], error: 'offline' }), {
          headers: { 'Content-Type': 'application/json' }
        })
      })
    )
    return
  }

  // Music files from server - network first, fallback cache
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

  // App shell (HTML, JS, CSS, icons, manifest) - stale-while-revalidate
  // Serve from cache immediately, update cache in background
  event.respondWith(
    caches.open(CACHE_NAME).then(async (cache) => {
      const cached = await cache.match(request)

      // Fetch fresh copy in background
      const fetchPromise = fetch(request).then((response) => {
        if (response.ok) {
          cache.put(request, response.clone())
        }
        return response
      }).catch(() => null)

      // Return cached version immediately, or wait for network
      if (cached) return cached

      const networkResponse = await fetchPromise
      if (networkResponse) return networkResponse

      // Last resort for navigation - serve index.html (SPA)
      if (request.mode === 'navigate') {
        const indexCached = await cache.match('./index.html')
        if (indexCached) return indexCached
      }

      return new Response('Offline', { status: 503 })
    })
  )
})
