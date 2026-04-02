import { saveSong, getAudioBlob } from './db'

// Auto-download a YouTube stream in background so next play is instant
const _downloading = new Set()
export async function autoDownload(videoId, title, artist, cover) {
  if (!videoId || _downloading.has(videoId)) return
  // Check if already downloaded
  const existing = await getAudioBlob(`yt_${videoId}`).catch(() => null)
  if (existing) return

  _downloading.add(videoId)
  try {
    const res = await api('/bars/api/yt-download.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ videoId, title, artist, cover })
    })
    const data = await res.json()
    if (!data.success) return

    // Fetch the downloaded file and save to IndexedDB
    const audioRes = await fetchMusic(data.url)
    const blob = await audioRes.blob()
    await saveSong({
      id: `yt_${videoId}`,
      title, artist,
      album: 'YouTube',
      filename: data.filename,
      size: data.size || blob.size,
      type: 'audio/mpeg',
      cover
    }, blob)
    console.log('Auto-downloaded:', title)
  } catch (err) {
    console.error('Auto-download failed:', err)
  } finally {
    _downloading.delete(videoId)
  }
}

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
