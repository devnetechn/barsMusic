import { defineStore } from 'pinia'
import { api, fetchMusic } from '../utils/api'
import { getAllSongs, saveSong } from '../utils/db'

export const useSyncStore = defineStore('sync', {
  state: () => ({
    syncing: false,
    progress: { current: 0, total: 0 },
    status: null // { type: 'info'|'success'|'error', message: '' }
  }),

  actions: {
    async syncFromServer() {
      if (this.syncing) return // Already syncing
      this.syncing = true
      this.status = { type: 'info', message: 'Checking server...' }
      this.progress = { current: 0, total: 0 }

      try {
        const res = await api('/bars/api/songs.php?sync')
        const data = await res.json()
        const serverSongs = data.songs || []

        if (serverSongs.length === 0) {
          this.status = { type: 'info', message: 'No songs on server' }
          this.syncing = false
          return
        }

        const localSongs = await getAllSongs()
        const localFilenames = new Set(localSongs.map(s => s.filename))
        const newSongs = serverSongs.filter(s => !localFilenames.has(s.filename))

        if (newSongs.length === 0) {
          this.status = { type: 'success', message: 'All synced!' }
          this.syncing = false
          return
        }

        this.status = { type: 'info', message: `Downloading ${newSongs.length} song(s)...` }
        this.progress = { current: 0, total: newSongs.length }

        let downloaded = 0
        let failed = 0

        for (const song of newSongs) {
          try {
            const audioRes = await fetchMusic(song.url)
            const audioBlob = await audioRes.blob()
            await saveSong({
              title: song.title,
              artist: song.artist || 'Unknown Artist',
              album: song.album || 'Unknown Album',
              filename: song.filename,
              cover: song.cover || null,
              size: song.size || audioBlob.size,
              type: audioBlob.type || 'audio/mpeg'
            }, audioBlob)
            downloaded++
          } catch {
            failed++
          }
          this.progress.current = downloaded + failed
          this.status = { type: 'info', message: `Downloading... ${downloaded + failed}/${newSongs.length}` }
        }

        this.status = failed === 0
          ? { type: 'success', message: `Downloaded ${downloaded} song(s)!` }
          : { type: 'error', message: `Done: ${downloaded} ok, ${failed} failed` }
      } catch (err) {
        this.status = { type: 'error', message: 'Sync failed: ' + err.message }
      }

      this.syncing = false
    }
  }
})
