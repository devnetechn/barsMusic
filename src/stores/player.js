import { defineStore } from 'pinia'
import { Howl } from 'howler'
import { getAudioBlob } from '../utils/db'
import { autoDownload } from '../utils/api'

export const usePlayerStore = defineStore('player', {
  state: () => ({
    currentSong: null,
    queue: [],
    queueIndex: -1,
    isPlaying: false,
    duration: 0,
    currentTime: 0,
    volume: 0.8,
    isMuted: false,
    shuffle: false,
    repeat: 'off',
    howl: null,
    _progressInterval: null,
    userQueue: [],
    shuffleOrder: [],
    shuffleIndex: -1,
    showQueue: false,
    _nextAutoplay: null // Preloaded next autoplay song { song, howl }
  }),

  getters: {
    progress: (state) => {
      if (state.duration === 0) return 0
      return (state.currentTime / state.duration) * 100
    },
    hasNext: (state) => {
      if (state.userQueue.length > 0) return true
      if (state.repeat !== 'off') return true
      return state.queueIndex < state.queue.length - 1
    },
    hasPrev: (state) => {
      if (state.repeat !== 'off') return true
      return state.queueIndex > 0
    },
    formattedCurrentTime: (state) => formatTime(state.currentTime),
    formattedDuration: (state) => formatTime(state.duration),
    upcomingQueue(state) {
      const items = []
      for (const song of state.userQueue) {
        items.push({ ...song, _source: 'queue' })
      }
      const startIndex = state.queueIndex + 1
      for (let i = startIndex; i < state.queue.length; i++) {
        items.push({ ...state.queue[i], _source: 'playlist' })
      }
      return items
    }
  },

  actions: {
    async playSong(song, queue = null, index = -1) {
      const isCrossfade = this._crossfadeNext
      this._crossfadeNext = false

      if (isCrossfade) {
        // Don't stop — let old song fade out
        // Don't stop — crossfade handles the old howl cleanup
        this.howl = null
        this._stopProgress()
      } else {
        this.stop()
      }

      if (queue) {
        this.queue = [...queue]
        this.queueIndex = index >= 0 ? index : queue.findIndex(s => s.id === song.id)
        if (this.shuffle) {
          this._generateShuffleOrder()
        }
      }

      this.currentSong = song
      this.isPlaying = true // Show playing state immediately

      // Record play history (fire and forget)
      import('../utils/api').then(({ api: apiFn }) => {
        apiFn('/bars/api/history.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            title: song.title,
            artist: song.artist,
            cover: song.cover,
            song_id: song.id
          })
        }).catch(() => {})
      }).catch(() => {})

      // Resolve audio URL
      let url = song._preloadedUrl || null
      if (!url) {
        if (song.filename) {
          // Has filename = on server
          url = `/bars/music/${song.filename}`
        } else if (song.url) {
          // Has URL (stream or direct link)
          url = song.url
        } else if (song.video_id || song.videoId) {
          // YouTube song — fetch stream URL
          try {
            const { api: apiFn } = await import('../utils/api')
            const vid = song.video_id || song.videoId
            const res = await apiFn(`/bars/api/yt-stream.php?id=${vid}`)
            const data = await res.json()
            if (data.success) url = data.url
          } catch {}
        }
        // Last resort: IndexedDB (offline)
        if (!url) {
          const blob = await getAudioBlob(song.id)
          if (blob) url = URL.createObjectURL(blob)
        }
        if (!url) {
          this.isPlaying = false
          return
        }
      }

      const howlOpts = {
        src: [url],
        html5: true,
        preload: true,
        volume: this.isMuted ? 0 : this.volume,
        onplay: () => {
          this.isPlaying = true
          this.duration = this.howl.duration()
          this._startProgress()
        },
        onpause: () => {
          this.isPlaying = false
          this._stopProgress()
        },
        onstop: () => {
          this.isPlaying = false
          this._stopProgress()
        },
        onend: () => {
          this._stopProgress()
          this.onSongEnd()
        },
        onplayerror: () => {
          // Mobile autoplay policy — retry on unlock
          if (this.howl) {
            this.howl.once('unlock', () => this.howl.play())
          }
        },
        onloaderror: (id, err) => {
          console.error('Load error:', err)
          this.isPlaying = false
        }
      }
      // Only add format hint for local files, not streams
      if (song.filename) {
        howlOpts.format = [getFormat(song.filename)]
      }

      this.howl = new Howl(howlOpts)

      // Crossfade: start at volume 0 and fade in
      if (isCrossfade) {
        this.howl.volume(0)
        this.howl.play()
        this.howl.fade(0, this.isMuted ? 0 : this.volume, 2500)
      } else {
        this.howl.play()
      }

      // Preload next song + autoplay in background
      this._preloadNext()
      this._preloadAutoplay()

      if ('mediaSession' in navigator) {
        navigator.mediaSession.metadata = new MediaMetadata({
          title: song.title || 'Unknown',
          artist: song.artist || 'Unknown',
          album: song.album || 'Unknown',
          artwork: song.cover ? [{ src: song.cover, sizes: '512x512', type: 'image/jpeg' }] : []
        })
        navigator.mediaSession.setActionHandler('play', () => this.resume())
        navigator.mediaSession.setActionHandler('pause', () => this.pause())
        navigator.mediaSession.setActionHandler('previoustrack', () => this.prev())
        navigator.mediaSession.setActionHandler('nexttrack', () => this.next())
      }

      // Handle Bluetooth/audio route changes - auto-resume
      if (!this._audioHandlersSet) {
        this._audioHandlersSet = true

        // Resume when audio becomes available again (Bluetooth connect/disconnect)
        document.addEventListener('visibilitychange', () => {
          if (!document.hidden && this.howl && this.currentSong && !this.isPlaying) {
            // Page became visible and was playing - try resume
            setTimeout(() => {
              if (this.howl && !this.isPlaying && this.currentTime > 0) {
                this.howl.play()
              }
            }, 500)
          }
        })

        // Handle audio interruption (phone call, Bluetooth switch)
        if ('mediaSession' in navigator) {
          navigator.mediaSession.setActionHandler('pause', () => this.pause())
          navigator.mediaSession.setActionHandler('play', () => this.resume())
        }
      }
    },

    pause() {
      if (this.howl && this.isPlaying) {
        this.howl.pause()
      }
    },

    resume() {
      if (this.howl && !this.isPlaying) {
        this.howl.play()
      }
    },

    toggle() {
      if (this.isPlaying) this.pause()
      else this.resume()
    },

    stop() {
      if (this.howl) {
        this.howl.unload()
        this.howl = null
      }
      this.isPlaying = false
      this.currentTime = 0
      this.duration = 0
      this._stopProgress()
    },

    seek(percent) {
      if (this.howl && this.duration) {
        const time = (percent / 100) * this.duration
        this.howl.seek(time)
        this.currentTime = time
      }
    },

    setVolume(val) {
      this.volume = val
      this.isMuted = false
      if (this.howl) {
        this.howl.volume(val)
      }
    },

    toggleMute() {
      this.isMuted = !this.isMuted
      if (this.howl) {
        this.howl.volume(this.isMuted ? 0 : this.volume)
      }
    },

    toggleShuffle() {
      this.shuffle = !this.shuffle
      if (this.shuffle) {
        this._generateShuffleOrder()
      } else {
        this.shuffleOrder = []
        this.shuffleIndex = -1
      }
    },

    toggleRepeat() {
      const modes = ['off', 'all', 'one']
      const idx = modes.indexOf(this.repeat)
      this.repeat = modes[(idx + 1) % modes.length]
    },

    async next() {
      // Check userQueue first
      if (this.userQueue.length > 0) {
        const nextSong = this.userQueue.shift()
        await this.playSong(nextSong)
        return
      }

      if (this.queue.length === 0) return

      let nextIndex
      if (this.shuffle) {
        if (this.shuffleOrder.length === 0) {
          this._generateShuffleOrder()
        }
        this.shuffleIndex++
        if (this.shuffleIndex >= this.shuffleOrder.length) {
          if (this.repeat === 'all') {
            this._generateShuffleOrder()
            this.shuffleIndex = 0
          } else {
            return
          }
        }
        nextIndex = this.shuffleOrder[this.shuffleIndex]
      } else if (this.queueIndex < this.queue.length - 1) {
        nextIndex = this.queueIndex + 1
      } else if (this.repeat === 'all') {
        nextIndex = 0
      } else {
        return
      }

      this.queueIndex = nextIndex
      await this.playSong(this.queue[nextIndex])
    },

    async prev() {
      if (this.currentTime > 3) {
        this.seek(0)
        return
      }

      if (this.queue.length === 0) return

      let prevIndex
      if (this.queueIndex > 0) {
        prevIndex = this.queueIndex - 1
      } else if (this.repeat === 'all') {
        prevIndex = this.queue.length - 1
      } else {
        this.seek(0)
        return
      }

      this.queueIndex = prevIndex
      await this.playSong(this.queue[prevIndex])
    },

    onSongEnd() {
      // Skip if crossfade already handled the transition
      if (this._crossfading) {
        this._crossfading = false
        return
      }

      if (this.repeat === 'one') {
        this.seek(0)
        this.howl.play()
      } else {
        const hasUserQueue = this.userQueue.length > 0
        const hasShuffleNext = this.shuffle && (this.shuffleIndex + 1 < this.shuffleOrder.length || this.repeat === 'all')
        const hasLinearNext = !this.shuffle && (this.queueIndex < this.queue.length - 1 || this.repeat === 'all')
        const hasMore = hasUserQueue || hasShuffleNext || hasLinearNext

        if (hasMore) {
          this.next()
        } else if (this._nextAutoplay) {
          const { song, howl } = this._nextAutoplay
          this._nextAutoplay = null
          this._stopProgress()
          if (this.howl) this.howl.unload()

          this.currentSong = song
          this.queue = []
          this.queueIndex = -1
          this.howl = howl

          // Add error handler for expired URLs
          this.howl.once('loaderror', () => {
            // Preloaded URL expired — fall back to async autoplay
            this._autoPlayNext()
          })
          this.howl.play()

          if ('mediaSession' in navigator) {
            navigator.mediaSession.metadata = new MediaMetadata({
              title: song.title || 'Unknown',
              artist: song.artist || 'Unknown',
              album: song.album || 'Auto-Play',
              artwork: song.cover ? [{ src: song.cover, sizes: '512x512', type: 'image/jpeg' }] : []
            })
          }

          // Preload next one
          this._preloadAutoplay()
        } else {
          // No preloaded song ready, try async (may not work on iOS)
          this._autoPlayNext()
        }
      }
    },

    playNext(song) {
      const existingIndex = this.userQueue.findIndex(s => s.id === song.id)
      if (existingIndex !== -1) {
        this.userQueue.splice(existingIndex, 1)
      }
      this.userQueue.unshift(song)
    },

    addToQueue(song) {
      const exists = this.userQueue.some(s => s.id === song.id)
      if (!exists) {
        this.userQueue.push(song)
      }
    },

    removeFromQueue(index) {
      this.userQueue.splice(index, 1)
    },

    clearQueue() {
      this.userQueue = []
    },

    toggleQueue() {
      this.showQueue = !this.showQueue
    },

    _generateShuffleOrder() {
      const indices = []
      for (let i = 0; i < this.queue.length; i++) {
        if (i !== this.queueIndex) {
          indices.push(i)
        }
      }
      // Fisher-Yates shuffle
      for (let i = indices.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [indices[i], indices[j]] = [indices[j], indices[i]]
      }
      this.shuffleOrder = indices
      this.shuffleIndex = -1
    },

    async _autoPlayNext() {
      if (!this.currentSong) return

      // Mix: 60% chance YouTube related, 40% chance local library
      // If online fails, always try local
      const tryOnlineFirst = Math.random() < 0.6

      if (tryOnlineFirst) {
        try {
          await this._autoPlayRelated()
          return
        } catch {}
      }

      // Try local songs
      const playedLocal = await this._autoPlayLocal()
      if (playedLocal) return

      // If local failed and didn't try online yet, try online
      if (!tryOnlineFirst) {
        try {
          await this._autoPlayRelated()
          return
        } catch {}
      }

      // Last resort: search for popular music
      try {
        await this._autoPlayRadio()
      } catch {}
    },

    async _autoPlayLocal() {
      try {
        const { getAllSongs } = await import('../utils/db')
        const songs = await getAllSongs()
        if (songs.length === 0) return false

        // Filter out recently played (last 5)
        const recentIds = this._recentAutoplay || []
        let candidates = songs.filter(s => s.id !== this.currentSong?.id && !recentIds.includes(s.id))
        if (candidates.length === 0) {
          // Reset history if all played
          this._recentAutoplay = []
          candidates = songs.filter(s => s.id !== this.currentSong?.id)
        }
        if (candidates.length === 0) return false

        const pick = candidates[Math.floor(Math.random() * candidates.length)]

        // Track recently played to avoid repeats
        if (!this._recentAutoplay) this._recentAutoplay = []
        this._recentAutoplay.push(pick.id)
        if (this._recentAutoplay.length > 10) this._recentAutoplay.shift()

        await this.playSong(pick)
        return true
      } catch {
        return false
      }
    },

    async _autoPlayRadio() {
      const genres = ['OPM hits', 'RnB songs', 'trending pop songs', 'acoustic love songs', 'hip hop hits']
      const query = genres[Math.floor(Math.random() * genres.length)]

      const { api: apiFn } = await import('../utils/api')
      const res = await apiFn(`/bars/api/yt-search.php?q=${encodeURIComponent(query)}`)
      const data = await res.json()
      const results = data.results || []
      if (results.length === 0) return

      const pick = results[Math.floor(Math.random() * results.length)]

      const streamRes = await apiFn(`/bars/api/yt-stream.php?id=${pick.videoId}`)
      const streamData = await streamRes.json()
      if (!streamData.success) return

      const song = {
        id: `yt_${pick.videoId}`,
        title: pick.title,
        artist: pick.author,
        album: 'Radio',
        cover: pick.thumbnail,
        url: streamData.url
      }

      await this.playSong(song)
      autoDownload(pick.videoId, pick.title, pick.author, pick.thumbnail)
    },

    async _autoPlayRelated() {
      if (!this.currentSong) throw new Error('No current song')

      const { api: apiFn } = await import('../utils/api')
      const params = new URLSearchParams({
        title: this.currentSong.title || '',
        artist: this.currentSong.artist || ''
      })
      const res = await apiFn(`/bars/api/related.php?${params}`)
      const data = await res.json()
      const results = data.results || []
      if (results.length === 0) throw new Error('No related results')

      // Pick a random candidate from top 5
      const top = results.slice(0, 5)
      const pick = top[Math.floor(Math.random() * top.length)]

      // Get stream URL
      const streamRes = await apiFn(`/bars/api/yt-stream.php?id=${pick.videoId}`)
      const streamData = await streamRes.json()
      if (!streamData.success) throw new Error('Stream failed')

      // Use playSong — same flow as user-triggered play
      const song = {
        id: `yt_${pick.videoId}`,
        title: pick.title,
        artist: pick.author || this.currentSong.artist,
        album: 'Auto-Play',
        cover: pick.thumbnail,
        url: streamData.url
      }

      await this.playSong(song)

      // Auto-download in background
      autoDownload(pick.videoId, pick.title, pick.author || '', pick.thumbnail)
    },

    _startProgress() {
      this._stopProgress()
      this._crossfading = false
      this._progressInterval = setInterval(() => {
        if (this.howl && this.isPlaying) {
          const seek = this.howl.seek()
          if (typeof seek === 'number' && seek >= 0) {
            // Add 0.3s offset to compensate for audio buffer delay
            this.currentTime = seek + 0.3
          }

          // Update duration if not set yet
          if (this.howl.duration() > 0) {
            this.duration = this.howl.duration()
          }

          // Crossfade: start fading 4 seconds before end
          // Only if we've played at least 30s (avoid false triggers on loading)
          if (!this._crossfading && this.duration > 30 && this.currentTime > 30) {
            const remaining = this.duration - this.currentTime
            if (remaining <= 4 && remaining > 0.5) {
              this._crossfading = true
              this._startCrossfade()
            }
          }
        }
      }, 200)
    },

    _startCrossfade() {
      if (!this.howl) return
      const vol = this.isMuted ? 0 : this.volume

      // Disable onend on old howl so it doesn't double-advance
      const oldHowlRef = this.howl
      oldHowlRef.off('end')

      // Fade out current song, then unload
      oldHowlRef.fade(vol, 0, 3500)
      setTimeout(() => { try { oldHowlRef.unload() } catch {} }, 4000)

      // Check what to play next
      const hasUserQueue = this.userQueue.length > 0
      const hasShuffleNext = this.shuffle && (this.shuffleIndex + 1 < this.shuffleOrder.length || this.repeat === 'all')
      const hasLinearNext = !this.shuffle && (this.queueIndex < this.queue.length - 1 || this.repeat === 'all')
      const hasMore = hasUserQueue || hasShuffleNext || hasLinearNext

      if (hasMore || this._nextAutoplay) {
        // Start next song with fade in after 1 second overlap
        setTimeout(() => {
          if (hasMore) {
            this._crossfadeNext = true
            this.next()
          } else if (this._nextAutoplay) {
            const { song, howl } = this._nextAutoplay
            this._nextAutoplay = null

            this.currentSong = song
            this.queue = []
            this.queueIndex = -1

            // Don't unload old howl yet — let it fade out
            const oldHowl = this.howl
            this.howl = howl
            this.howl.volume(0)
            this.howl.play()
            this.howl.fade(0, vol, 2500)

            // Clean up old after fade
            setTimeout(() => { if (oldHowl) oldHowl.unload() }, 3500)

            if ('mediaSession' in navigator) {
              navigator.mediaSession.metadata = new MediaMetadata({
                title: song.title || 'Unknown', artist: song.artist || 'Unknown',
                album: song.album || 'Auto-Play',
                artwork: song.cover ? [{ src: song.cover, sizes: '512x512', type: 'image/jpeg' }] : []
              })
            }
            this._preloadAutoplay()
          }
        }, 1000)
      }
    },

    _stopProgress() {
      if (this._progressInterval) {
        clearInterval(this._progressInterval)
        this._progressInterval = null
      }
    },

    async _preloadNext() {
      // Preload the next song's audio URL so it plays instantly
      let nextSong = null
      if (this.userQueue.length > 0) {
        nextSong = this.userQueue[0]
      } else if (this.queueIndex < this.queue.length - 1) {
        nextSong = this.queue[this.queueIndex + 1]
      }
      if (!nextSong || nextSong._preloadedUrl) return

      try {
        const blob = await getAudioBlob(nextSong.id)
        if (blob) {
          nextSong._preloadedUrl = URL.createObjectURL(blob)
        } else if (nextSong.url || nextSong.filename) {
          // Pre-fetch the audio to browser cache
          const url = nextSong.url || `/bars/music/${nextSong.filename}`
          fetch(url, { mode: 'no-cors' }).catch(() => {})
          nextSong._preloadedUrl = url
        }
      } catch {}
    },

    async _preloadAutoplay() {
      // Don't preload if there's still queue to play
      if (this.userQueue.length > 0 || this.queueIndex < this.queue.length - 1) return
      if (this._nextAutoplay) return
      if (!this.currentSong) return

      try {
        const { api: apiFn } = await import('../utils/api')

        // Try related songs first
        let streamUrl = null
        let song = null

        try {
          const params = new URLSearchParams({
            title: this.currentSong.title || '',
            artist: this.currentSong.artist || ''
          })
          const res = await apiFn(`/bars/api/related.php?${params}`)
          const data = await res.json()
          const results = data.results || []
          if (results.length > 0) {
            const pick = results[Math.floor(Math.random() * Math.min(5, results.length))]
            const streamRes = await apiFn(`/bars/api/yt-stream.php?id=${pick.videoId}`)
            const streamData = await streamRes.json()
            if (streamData.success) {
              streamUrl = streamData.url
              song = {
                id: `yt_${pick.videoId}`,
                title: pick.title,
                artist: pick.author || '',
                album: 'Auto-Play',
                cover: pick.thumbnail,
                url: streamUrl,
                _videoId: pick.videoId
              }
            }
          }
        } catch {}

        // Fallback: try local songs
        if (!song) {
          try {
            const { getAllSongs } = await import('../utils/db')
            const songs = await getAllSongs()
            const others = songs.filter(s => s.id !== this.currentSong?.id)
            if (others.length > 0) {
              const pick = others[Math.floor(Math.random() * others.length)]
              const blob = await getAudioBlob(pick.id)
              if (blob) {
                song = { ...pick, url: URL.createObjectURL(blob) }
              } else if (pick.url || pick.filename) {
                song = { ...pick, url: pick.url || `/bars/music/${pick.filename}` }
              }
            }
          } catch {}
        }

        if (!song) return

        // Create Howl ready to play (preloaded)
        const howlOpts = {
          src: [song.url],
          html5: true,
          preload: true,
          volume: this.isMuted ? 0 : this.volume,
          onplay: () => {
            this.isPlaying = true
            this.duration = this.howl?.duration() || 0
            this._startProgress()
          },
          onpause: () => { this.isPlaying = false; this._stopProgress() },
          onstop: () => { this.isPlaying = false; this._stopProgress() },
          onend: () => { this._stopProgress(); this.onSongEnd() },
          onplayerror: () => {
            if (this.howl) this.howl.once('unlock', () => this.howl.play())
          },
          onloaderror: () => { this.isPlaying = false }
        }
        if (song.filename) howlOpts.format = [getFormat(song.filename)]
        const howl = new Howl(howlOpts)

        this._nextAutoplay = { song, howl }

        // Auto-download YouTube songs
        if (song._videoId) {
          autoDownload(song._videoId, song.title, song.artist, song.cover)
        }
      } catch {}
    }
  }
})

function formatTime(seconds) {
  if (!seconds || isNaN(seconds)) return '0:00'
  const mins = Math.floor(seconds / 60)
  const secs = Math.floor(seconds % 60)
  return `${mins}:${secs.toString().padStart(2, '0')}`
}

function getFormat(filename) {
  if (!filename) return 'mp3'
  const ext = filename.split('.').pop().toLowerCase()
  const formats = { mp3: 'mp3', wav: 'wav', ogg: 'ogg', flac: 'flac', aac: 'aac', m4a: 'mp4', wma: 'wma' }
  return formats[ext] || 'mp3'
}
