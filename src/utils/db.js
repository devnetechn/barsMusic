import { openDB } from 'idb'

const DB_NAME = 'muzik-player'
const DB_VERSION = 1

async function getDB() {
  return openDB(DB_NAME, DB_VERSION, {
    upgrade(db) {
      if (!db.objectStoreNames.contains('songs')) {
        const songStore = db.createObjectStore('songs', { keyPath: 'id' })
        songStore.createIndex('title', 'title')
        songStore.createIndex('artist', 'artist')
        songStore.createIndex('album', 'album')
        songStore.createIndex('addedAt', 'addedAt')
      }

      if (!db.objectStoreNames.contains('audio')) {
        db.createObjectStore('audio', { keyPath: 'id' })
      }

      if (!db.objectStoreNames.contains('playlists')) {
        const playlistStore = db.createObjectStore('playlists', { keyPath: 'id' })
        playlistStore.createIndex('name', 'name')
      }
    }
  })
}

// ---- Songs ----

export async function saveSong(metadata, audioBlob) {
  const db = await getDB()
  const id = metadata.id || `song_${Date.now()}_${Math.random().toString(36).slice(2, 9)}`
  const song = {
    ...metadata,
    id,
    addedAt: metadata.addedAt || new Date().toISOString()
  }

  const tx = db.transaction(['songs', 'audio'], 'readwrite')
  await Promise.all([
    tx.objectStore('songs').put(song),
    tx.objectStore('audio').put({ id, blob: audioBlob }),
    tx.done
  ])

  return song
}

export async function getAllSongs() {
  const db = await getDB()
  return db.getAll('songs')
}

export async function getSong(id) {
  const db = await getDB()
  return db.get('songs', id)
}

export async function getAudioBlob(id) {
  const db = await getDB()
  const record = await db.get('audio', id)
  return record?.blob || null
}

export async function deleteSong(id) {
  const db = await getDB()
  const tx = db.transaction(['songs', 'audio'], 'readwrite')
  await Promise.all([
    tx.objectStore('songs').delete(id),
    tx.objectStore('audio').delete(id),
    tx.done
  ])
}

export async function songExists(filename) {
  const songs = await getAllSongs()
  return songs.some(s => s.filename === filename)
}

export async function searchSongs(query) {
  const songs = await getAllSongs()
  const q = query.toLowerCase()
  return songs.filter(s =>
    s.title?.toLowerCase().includes(q) ||
    s.artist?.toLowerCase().includes(q) ||
    s.album?.toLowerCase().includes(q)
  )
}

// ---- Playlists ----

export async function savePlaylist(playlist) {
  const db = await getDB()
  const id = playlist.id || `playlist_${Date.now()}_${Math.random().toString(36).slice(2, 9)}`
  const record = { ...playlist, id }
  await db.put('playlists', record)
  return record
}

export async function getAllPlaylists() {
  const db = await getDB()
  return db.getAll('playlists')
}

export async function getPlaylist(id) {
  const db = await getDB()
  return db.get('playlists', id)
}

export async function deletePlaylist(id) {
  const db = await getDB()
  await db.delete('playlists', id)
}

// ---- Storage Info ----

export async function getStorageEstimate() {
  if (navigator.storage && navigator.storage.estimate) {
    const estimate = await navigator.storage.estimate()
    return {
      usage: estimate.usage,
      quota: estimate.quota,
      usageMB: Math.round(estimate.usage / 1024 / 1024),
      quotaMB: Math.round(estimate.quota / 1024 / 1024)
    }
  }
  return null
}
