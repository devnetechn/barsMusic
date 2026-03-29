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
