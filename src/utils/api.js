import { saveSong, getAudioBlob } from './db'
import { API_BASE, ASSET_BASE } from '../config'

// Auto-download queue - one at a time to avoid bandwidth issues
const _downloadQueue = []
let _isDownloading = false
const _downloaded = new Set()

export function autoDownload(videoId, title, artist, cover) {
  if (!videoId || _downloaded.has(videoId)) return
  // Don't queue duplicates
  if (_downloadQueue.some(q => q.videoId === videoId)) return
  _downloaded.add(videoId)

  // Add to queue
  _downloadQueue.push({ videoId, title, artist, cover })

  // Process queue if not already running
  if (!_isDownloading) _processDownloadQueue()
}

async function _processDownloadQueue() {
  if (_isDownloading || _downloadQueue.length === 0) return
  _isDownloading = true

  while (_downloadQueue.length > 0) {
    const { videoId, title, artist, cover } = _downloadQueue.shift()

    // Wait 3 seconds before starting — let the stream buffer first
    await new Promise(r => setTimeout(r, 3000))

    try {
      // Check if already in IndexedDB
      const existing = await getAudioBlob(`yt_${videoId}`).catch(() => null)
      if (existing) continue

      const res = await api('/bars/api/yt-download.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ videoId, title, artist, cover })
      })
      const data = await res.json()
      if (!data.success) continue

      // Fetch and save to IndexedDB
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
    } catch {
      // Silently fail, don't block queue
    }
  }

  _isDownloading = false
}

// Build full music URL (prepends server base on native)
export function musicUrl(path) {
  return ASSET_BASE + path
}

// Build full cover URL (prepends server base on native)
export function coverUrl(path) {
  return ASSET_BASE + path
}

// Fetch a music file - uses ASSET_BASE for native, proxy for web
export async function fetchMusic(serverUrl) {
  // Prepend ASSET_BASE if not already a full URL
  const fullUrl = serverUrl.startsWith('http') ? serverUrl : ASSET_BASE + serverUrl
  let res = await fetch(fullUrl)
  if (!res.ok && !ASSET_BASE) {
    // Fallback: try direct path (for Vite dev server serving static files)
    const directPath = serverUrl.replace('/bars/music/', '/music/')
    res = await fetch(directPath)
  }
  if (!res.ok) throw new Error('Failed to fetch music file')
  return res
}

// Wrapper around fetch that always includes credentials + auth token
// Automatically prepends API_BASE for native Capacitor builds
export async function api(url, options = {}) {
  const token = localStorage.getItem('bars_token')

  // Prepend API_BASE if not already a full URL
  const fullUrl = url.startsWith('http') ? url : API_BASE + url

  // Merge headers with auth token
  const headers = { ...(options.headers || {}) }
  if (token) {
    headers['X-Auth-Token'] = token
  }

  const res = await fetch(fullUrl, {
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
