// Fetch a music file - tries proxy path first, falls back to direct path
export async function fetchMusic(serverUrl) {
  // serverUrl is like /bars/music/filename.mp3
  let res = await fetch(serverUrl)
  if (!res.ok) {
    // Fallback: try direct path (for Vite dev server serving static files)
    const directPath = serverUrl.replace('/bars/music/', '/music/')
    res = await fetch(directPath)
  }
  if (!res.ok) throw new Error('Failed to fetch music file')
  return res
}

// Wrapper around fetch that always includes credentials + auth token
export async function api(url, options = {}) {
  const token = localStorage.getItem('bars_token')

  // Merge headers with auth token
  const headers = { ...(options.headers || {}) }
  if (token) {
    headers['X-Auth-Token'] = token
  }

  const res = await fetch(url, {
    ...options,
    headers,
    credentials: 'include'
  })

  // If 401, clear auth and reload (once, to avoid infinite loop)
  if (res.status === 401) {
    localStorage.removeItem('bars_user')
    localStorage.removeItem('bars_token')
    if (!sessionStorage.getItem('auth_redirect')) {
      sessionStorage.setItem('auth_redirect', '1')
      window.location.reload()
    }
    throw new Error('Login required')
  }

  // Clear redirect flag on successful API call
  sessionStorage.removeItem('auth_redirect')
  return res
}
