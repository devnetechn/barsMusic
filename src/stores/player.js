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
    showQueue: false
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
      this.stop()

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

      // Use preloaded URL if available, otherwise resolve
      let url = song._preloadedUrl || null
      if (!url) {
        const blob = await getAudioBlob(song.id)
        if (blob) {
          url = URL.createObjectURL(blob)
        } else if (song.url) {
          url = song.url
        } else if (song.filename) {
          url = `/bars/music/${song.filename}`
        } else {
          this.isPlaying = false
          return
        }
      }

      this.howl = new Howl({
        src: [url],
        format: [getFormat(song.filename || song.title)],
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
        onloaderror: (id, err) => {
          console.error('Load error:', err)
          this.isPlaying = false
        }
      })

      this.howl.play()

      // Preload next song in background
      this._preloadNext()

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
        } else {
          this._autoPlayRelated()
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

    async _autoPlayRelated() {
      if (!this.currentSong) return

      try {
        const { api: apiFn } = await import('../utils/api')
        const params = new URLSearchParams({
          title: this.currentSong.title || '',
          artist: this.currentSong.artist || ''
        })
        const res = await apiFn(`/bars/api/related.php?${params}`)
        const data = await res.json()
        const results = data.results || []
        if (results.length === 0) return

        // Try multiple candidates in case one fails
        const shuffled = [...results.slice(0, 5)].sort(() => Math.random() - 0.5)

        for (const pick of shuffled) {
          try {
            const streamRes = await apiFn(`/bars/api/yt-stream.php?id=${pick.videoId}`)
            const streamData = await streamRes.json()
            if (!streamData.success) continue

            this.stop()

            const song = {
              id: `yt_${pick.videoId}`,
              title: pick.title,
              artist: pick.author || this.currentSong.artist,
              album: 'Auto-Play',
              cover: pick.thumbnail
            }

            this.currentSong = song
            this.queue = []
            this.queueIndex = -1

            // Try to play, with error recovery
            const played = await new Promise((resolve) => {
              this.howl = new Howl({
                src: [streamData.url],
                html5: true,
                volume: this.isMuted ? 0 : this.volume,
                onplay: () => {
                  this.isPlaying = true
                  this.duration = this.howl.duration()
                  this._startProgress()
                  resolve(true)
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
                  this._autoPlayRelated()
                },
                onloaderror: () => {
                  console.error('Auto-play load error, trying next...')
                  resolve(false)
                },
                onplayerror: () => {
                  console.error('Auto-play play error, trying next...')
                  resolve(false)
                }
              })

              this.howl.play()

              // Timeout: if nothing happens in 10s, try next
              setTimeout(() => resolve(false), 10000)
            })

            if (played) {
              if ('mediaSession' in navigator) {
                navigator.mediaSession.metadata = new MediaMetadata({
                  title: song.title || 'Unknown',
                  artist: song.artist || 'Unknown',
                  album: 'Auto-Play',
                  artwork: song.cover ? [{ src: song.cover, sizes: '512x512', type: 'image/jpeg' }] : []
                })
              }
              // Auto-download in background
              autoDownload(pick.videoId, pick.title, pick.author || '', pick.thumbnail)
              return // Successfully playing
            }

            // Failed, clean up and try next
            if (this.howl) {
              this.howl.unload()
              this.howl = null
            }
          } catch {
            continue
          }
        }

        // All candidates failed
        this.isPlaying = false
      } catch (err) {
        console.error('Auto-play related failed:', err)
      }
    },

    _startProgress() {
      this._stopProgress()
      this._progressInterval = setInterval(() => {
        if (this.howl && this.isPlaying) {
          this.currentTime = this.howl.seek() || 0
        }
      }, 250)
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
