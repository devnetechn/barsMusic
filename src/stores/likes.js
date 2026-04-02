import { defineStore } from 'pinia'
import { api } from '../utils/api'

export const useLikesStore = defineStore('likes', {
  state: () => ({
    likedSet: new Set(),
    likedSongs: [],
    loaded: false
  }),

  actions: {
    isLiked(songId) {
      return this.likedSet.has(songId)
    },

    async loadLikedSongs() {
      if (this.loaded) return
      try {
        const res = await api('/bars/api/likes.php')
        const data = await res.json()
        this.likedSongs = data.songs || []
        this.likedSet = new Set(this.likedSongs.map(s => s.song_id))
        this.loaded = true
      } catch {}
    },

    async toggleLike(song) {
      if (!song?.id) return
      const songId = String(song.id)
      const wasLiked = this.likedSet.has(songId)

      // Optimistic update
      if (wasLiked) {
        this.likedSet.delete(songId)
        this.likedSongs = this.likedSongs.filter(s => s.song_id !== songId)
      } else {
        this.likedSet.add(songId)
        this.likedSongs.unshift({
          song_id: songId,
          video_id: song.video_id || song.videoId || null,
          title: song.title || '',
          artist: song.artist || song.author || '',
          cover: song.cover || song.thumbnail || null,
          filename: song.filename || null,
          created_at: new Date().toISOString()
        })
      }

      try {
        if (wasLiked) {
          await api('/bars/api/likes.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ song_id: songId })
          })
        } else {
          await api('/bars/api/likes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              song_id: songId,
              video_id: song.video_id || song.videoId || null,
              title: song.title || '',
              artist: song.artist || song.author || '',
              cover: song.cover || song.thumbnail || null,
              filename: song.filename || null
            })
          })
        }
      } catch {
        // Revert on error
        if (wasLiked) {
          this.likedSet.add(songId)
        } else {
          this.likedSet.delete(songId)
          this.likedSongs = this.likedSongs.filter(s => s.song_id !== songId)
        }
      }
    }
  }
})
