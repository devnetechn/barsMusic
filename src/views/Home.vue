<template>
  <div class="p-4 md:p-6">
    <!-- 1. Greeting -->
    <h1 class="text-2xl md:text-3xl font-bold text-white mb-6">{{ greeting }}</h1>

    <!-- 2. Recently Played -->
    <section v-if="recentlyPlayed.length" class="mb-8">
      <h2 class="text-xl font-bold text-white mb-4">Recently Played</h2>
      <div class="flex gap-4 overflow-x-auto pb-2 -mx-4 px-4 snap-x scrollbar-none">
        <div v-for="item in recentlyPlayed" :key="item.songId || item.title"
          @click="playFromHistory(item)"
          class="flex-shrink-0 w-36 cursor-pointer group">
          <div class="relative mb-2">
            <div class="w-36 h-36 bg-spotify-lighter rounded-md flex items-center justify-center overflow-hidden shadow-lg">
              <img v-if="item.cover" :src="item.cover" class="w-full h-full object-cover" @error="$event.target.style.display='none'" />
              <svg v-if="!item.cover" class="w-10 h-10 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
            </div>
            <button class="absolute bottom-2 right-2 w-8 h-8 bg-spotify-green rounded-full flex items-center justify-center shadow-lg opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all"
              @click.stop="playFromHistory(item)">
              <svg class="w-4 h-4 text-black ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            </button>
          </div>
          <p class="text-sm font-semibold text-white truncate">{{ item.title }}</p>
          <p class="text-xs text-spotify-light truncate mt-0.5">{{ item.artist || 'Unknown Artist' }}</p>
        </div>
      </div>
    </section>

    <!-- 3. Made For You -->
    <section v-if="topMixes.length" class="mb-8">
      <h2 class="text-xl font-bold text-white mb-4">Made For You</h2>
      <div class="grid grid-cols-2 gap-4">
        <div v-for="(mix, index) in topMixes" :key="mix.artist"
          @click="playArtistMix(mix.artist)"
          class="rounded-lg p-4 cursor-pointer transition-transform hover:scale-[1.02] bg-gradient-to-br"
          :class="mixColors[index % mixColors.length]">
          <div class="h-24 flex flex-col justify-between">
            <div>
              <p class="text-white font-bold text-lg truncate">{{ mix.artist }}</p>
              <p class="text-white/70 text-xs mt-1">{{ mix.play_count }} plays</p>
            </div>
            <div class="flex items-center gap-1">
              <svg class="w-4 h-4 text-white/80" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
              <span class="text-white/80 text-xs">Mix</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 4. Recently Added -->
    <section v-if="recentSongs.length" class="mb-8">
      <h2 class="text-xl font-bold text-white mb-4">Recently Added</h2>
      <div class="flex gap-4 overflow-x-auto pb-2 -mx-4 px-4 snap-x scrollbar-none">
        <div v-for="song in recentSongs" :key="song.id"
          @click="playSong(song)"
          class="flex-shrink-0 w-36 cursor-pointer group">
          <div class="relative mb-2">
            <div class="w-36 h-36 bg-spotify-lighter rounded-md flex items-center justify-center overflow-hidden shadow-lg">
              <img v-if="song.cover" :src="song.cover" class="w-full h-full object-cover" @error="$event.target.style.display='none'" />
              <svg v-if="!song.cover" class="w-10 h-10 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
            </div>
            <button class="absolute bottom-2 right-2 w-8 h-8 bg-spotify-green rounded-full flex items-center justify-center shadow-lg opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all"
              @click.stop="playSong(song)">
              <svg class="w-4 h-4 text-black ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            </button>
          </div>
          <p class="text-sm font-semibold text-white truncate">{{ song.title }}</p>
          <p class="text-xs text-spotify-light truncate mt-0.5">{{ song.artist || 'Unknown Artist' }}</p>
        </div>
      </div>
    </section>

    <!-- 5. Trending / Featured -->
    <section v-if="featured.length" class="mb-8">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h2 class="text-xl font-bold text-white">Trending</h2>
          <p class="text-spotify-light text-xs mt-1">Tap a song to play or download</p>
        </div>
        <button @click="refreshFeatured"
          class="text-spotify-light hover:text-white transition-colors p-2" title="Refresh">
          <svg class="w-5 h-5" :class="{ 'animate-spin': featuredLoading }" fill="currentColor" viewBox="0 0 24 24"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
        </button>
      </div>
      <div class="flex gap-4 overflow-x-auto pb-2 -mx-4 px-4 snap-x scrollbar-none">
        <div v-for="video in featured" :key="video.videoId"
          @click="showOptions(video)"
          class="flex-shrink-0 w-40 cursor-pointer group">
          <div class="relative mb-2">
            <div class="w-40 h-40 bg-spotify-lighter rounded-md flex items-center justify-center overflow-hidden shadow-lg">
              <img :src="video.thumbnail" class="w-full h-full object-cover" loading="lazy" @error="$event.target.style.display='none'" />
            </div>
            <span class="absolute bottom-2 left-2 bg-black/70 text-[10px] text-white px-1.5 py-0.5 rounded">
              {{ video.duration }}
            </span>
            <div class="absolute inset-0 bg-black/30 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity rounded-md">
              <svg class="w-10 h-10 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            </div>
          </div>
          <p class="text-sm font-semibold text-white truncate mb-0.5">{{ video.title }}</p>
          <p class="text-xs text-spotify-light truncate">{{ video.author }}</p>
          <div v-if="video.downloading || video.streaming" class="flex items-center gap-2 mt-1">
            <div class="w-3 h-3 border-2 border-spotify-green border-t-transparent rounded-full animate-spin"></div>
            <span class="text-[10px] text-spotify-light">{{ video.streaming ? 'Loading...' : 'Downloading...' }}</span>
          </div>
          <p v-else-if="video.downloaded" class="text-[10px] text-spotify-green mt-1 font-semibold">Saved</p>
        </div>
      </div>
    </section>

    <!-- Loading featured -->
    <div v-if="featuredLoading && !featured.length" class="text-center py-12">
      <div class="w-8 h-8 border-2 border-spotify-green border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
      <p class="text-spotify-light text-sm">Loading featured songs...</p>
    </div>

    <!-- All Songs -->
    <section v-if="songs.length" class="mb-8">
      <h2 class="text-xl font-bold text-white mb-4">All Songs</h2>
      <div class="space-y-1">
        <div v-for="(song, index) in songs" :key="song.id"
          @click="playSong(song, index)"
          class="flex items-center gap-3 p-2 rounded-md hover:bg-spotify-card transition-colors cursor-pointer group">
          <span class="w-6 text-center text-sm text-spotify-light group-hover:hidden">{{ index + 1 }}</span>
          <button class="w-6 text-center hidden group-hover:block" @click.stop="playSong(song, index)">
            <svg class="w-4 h-4 text-white mx-auto" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
          </button>
          <div class="w-10 h-10 bg-spotify-lighter rounded flex-shrink-0 flex items-center justify-center overflow-hidden">
            <img v-if="song.cover" :src="song.cover" class="w-full h-full object-cover" @error="$event.target.style.display='none'" />
            <svg v-if="!song.cover" class="w-5 h-5 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm text-white truncate"
              :class="{ '!text-spotify-green': player.currentSong?.id === song.id }">
              {{ song.title }}
            </p>
            <p class="text-xs text-spotify-light truncate">{{ song.artist || 'Unknown Artist' }}</p>
          </div>
          <span class="text-xs text-spotify-light">{{ formatSize(song.size) }}</span>
        </div>
      </div>
    </section>

    <!-- Empty state: no songs AND no featured -->
    <section v-if="songs.length === 0 && !featured.length && !featuredLoading">
      <div class="flex flex-col items-center justify-center py-20">
        <svg class="w-16 h-16 text-spotify-light mb-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
        <h2 class="text-xl font-bold text-white mb-2">No music yet</h2>
        <p class="text-spotify-light mb-4">Search and download songs to get started</p>
        <router-link to="/search"
          class="bg-spotify-green text-black font-semibold px-6 py-3 rounded-full hover:bg-spotify-green-hover transition-colors">
          Search Music
        </router-link>
      </div>
    </section>

    <!-- Options Modal -->
    <div v-if="selectedVideo" class="fixed inset-0 bg-black/60 z-[60] flex items-end justify-center"
      @click.self="selectedVideo = null">
      <div class="bg-spotify-dark rounded-t-xl w-full max-w-lg animate-slide-up"
        style="padding-bottom: env(safe-area-inset-bottom)">
        <div class="flex items-center gap-3 p-4 border-b border-spotify-lighter/20">
          <div class="w-12 h-12 bg-spotify-lighter rounded overflow-hidden flex-shrink-0">
            <img :src="selectedVideo.thumbnail" class="w-full h-full object-cover" />
          </div>
          <div class="min-w-0">
            <p class="text-sm text-white font-semibold truncate">{{ selectedVideo.title }}</p>
            <p class="text-xs text-spotify-light truncate">{{ selectedVideo.author }} · {{ selectedVideo.duration }}</p>
          </div>
        </div>
        <div class="p-2">
          <button @click="streamFeatured(selectedVideo); selectedVideo = null"
            class="w-full flex items-center gap-4 px-4 py-3 rounded-md hover:bg-spotify-card transition-colors text-left">
            <svg class="w-6 h-6 text-spotify-green flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            <div>
              <p class="text-sm text-white font-medium">Play Now</p>
              <p class="text-xs text-spotify-light">Stream without downloading</p>
            </div>
          </button>
          <button @click="downloadAndPlayFeatured(selectedVideo); selectedVideo = null"
            class="w-full flex items-center gap-4 px-4 py-3 rounded-md hover:bg-spotify-card transition-colors text-left">
            <svg class="w-6 h-6 text-spotify-green flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M16 13h-3V3h-2v10H8l4 4 4-4zM4 19v2h16v-2H4z"/></svg>
            <div>
              <p class="text-sm text-white font-medium">Download & Play</p>
              <p class="text-xs text-spotify-light">Save to library for offline</p>
            </div>
          </button>
          <button @click="downloadFeatured(selectedVideo); selectedVideo = null"
            class="w-full flex items-center gap-4 px-4 py-3 rounded-md hover:bg-spotify-card transition-colors text-left">
            <svg class="w-6 h-6 text-white flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16h6v-6h4l-7-7-7 7h4zm-4 2h14v2H5z"/></svg>
            <div>
              <p class="text-sm text-white font-medium">Download Only</p>
              <p class="text-xs text-spotify-light">Save to library</p>
            </div>
          </button>
          <button @click="playlistSong = selectedVideo; selectedVideo = null"
            class="w-full flex items-center gap-4 px-4 py-3 rounded-md hover:bg-spotify-card transition-colors text-left">
            <svg class="w-6 h-6 text-spotify-light flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M14 10H2v2h12v-2zm0-4H2v2h12V6zm4 8v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zM2 16h8v-2H2v2z"/></svg>
            <div>
              <p class="text-sm text-white font-medium">Add to Playlist</p>
              <p class="text-xs text-spotify-light">Save for later, download anytime</p>
            </div>
          </button>
        </div>
        <div class="p-2 border-t border-spotify-lighter/20">
          <button @click="selectedVideo = null"
            class="w-full py-3 text-sm text-spotify-light font-medium hover:text-white transition-colors">
            Cancel
          </button>
        </div>
      </div>
    </div>
    <!-- Add to Playlist modal -->
    <AddToPlaylist :show="!!playlistSong" :song="playlistSong" @close="playlistSong = null" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { getAllSongs, saveSong, searchSongs } from '../utils/db'
import { usePlayerStore } from '../stores/player'
import { useAuthStore } from '../stores/auth'
import { api, fetchMusic, autoDownload } from '../utils/api'
import { Howl } from 'howler'
import AddToPlaylist from '../components/AddToPlaylist.vue'

const player = usePlayerStore()
const auth = useAuthStore()
const songs = ref([])
const featured = ref([])
const featuredLoading = ref(false)
const selectedVideo = ref(null)
const playlistSong = ref(null)
const recentlyPlayed = ref([])
const topMixes = ref([])

const mixColors = [
  'from-purple-600 to-blue-800',
  'from-green-600 to-teal-800',
  'from-orange-600 to-red-800',
  'from-pink-600 to-rose-800',
]

// Cache key for featured songs
const FEATURED_CACHE_KEY = 'bars_featured_cache'

const greeting = computed(() => {
  const h = new Date().getHours()
  const name = auth.displayName || ''
  const time = h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening'
  return name ? `${time}, ${name}` : time
})

const recentSongs = computed(() => {
  return [...songs.value]
    .sort((a, b) => new Date(b.addedAt) - new Date(a.addedAt))
    .slice(0, 6)
})

function playSong(song, index) {
  const idx = index ?? songs.value.findIndex(s => s.id === song.id)
  player.playSong(song, songs.value, idx)
}

function formatSize(bytes) {
  if (!bytes) return ''
  const mb = bytes / (1024 * 1024)
  return mb >= 1 ? `${mb.toFixed(1)} MB` : `${(bytes / 1024).toFixed(0)} KB`
}

function showOptions(video) {
  if (video.downloading || video.streaming) return
  selectedVideo.value = video
}

// Play a song from history by matching it in local library
function playFromHistory(item) {
  const match = songs.value.find(s =>
    (item.songId && s.id === item.songId) ||
    s.title.toLowerCase() === (item.title || '').toLowerCase()
  )
  if (match) {
    const idx = songs.value.findIndex(s => s.id === match.id)
    player.playSong(match, songs.value, idx)
  }
}

// Play all local songs by an artist, shuffled
async function playArtistMix(artist) {
  const results = await searchSongs(artist)
  const artistSongs = results.filter(s =>
    s.artist && s.artist.toLowerCase().includes(artist.toLowerCase())
  )
  if (artistSongs.length === 0) return

  // Shuffle
  for (let i = artistSongs.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [artistSongs[i], artistSongs[j]] = [artistSongs[j], artistSongs[i]]
  }

  player.playSong(artistSongs[0], artistSongs, 0)
}

// Load featured from cache first, fetch only if no cache
async function loadFeatured() {
  // Try cache first
  try {
    const cached = localStorage.getItem(FEATURED_CACHE_KEY)
    if (cached) {
      const data = JSON.parse(cached)
      featured.value = data.map(v => reactive({
        ...v,
        downloading: false,
        downloaded: false,
        streaming: false
      }))
      return
    }
  } catch { /* ignore */ }

  // No cache, fetch from server
  await fetchFeatured()
}

async function fetchFeatured() {
  featuredLoading.value = true
  try {
    const res = await api('/bars/api/featured.php')
    const data = await res.json()
    const results = data.results || []

    // Save to cache
    localStorage.setItem(FEATURED_CACHE_KEY, JSON.stringify(results))

    featured.value = results.map(v => reactive({
      ...v,
      downloading: false,
      downloaded: false,
      streaming: false
    }))
  } catch {
    featured.value = []
  }
  featuredLoading.value = false
}

// Manual refresh
function refreshFeatured() {
  localStorage.removeItem(FEATURED_CACHE_KEY)
  fetchFeatured()
}

// Stream from YouTube
async function streamFeatured(video) {
  video.streaming = true
  try {
    const res = await api(`/bars/api/yt-stream.php?id=${video.videoId}`)
    const data = await res.json()
    if (!data.success) throw new Error(data.error)

    player.stop()
    player.currentSong = {
      id: `yt_${video.videoId}`,
      title: video.title,
      artist: video.author,
      album: 'YouTube',
      cover: video.thumbnail
    }

    player.howl = new Howl({
      src: [data.url],
      html5: true,
      volume: player.isMuted ? 0 : player.volume,
      onplay: () => {
        player.isPlaying = true
        player.duration = player.howl.duration()
        player._startProgress()
      },
      onpause: () => { player.isPlaying = false; player._stopProgress() },
      onend: () => { player._stopProgress(); player.isPlaying = false },
      onloaderror: () => { player.isPlaying = false; player._stopProgress(); console.error('Stream load error') },
      onplayerror: () => {
        // Retry once on play error (browser autoplay policy)
        player.howl.once('unlock', () => player.howl.play())
      }
    })
    player.howl.play()

    if ('mediaSession' in navigator) {
      navigator.mediaSession.metadata = new MediaMetadata({
        title: video.title, artist: video.author,
        artwork: [{ src: video.thumbnail, sizes: '512x512', type: 'image/jpeg' }]
      })
    }
    video.streaming = false
    // Auto-download in background for next time
    autoDownload(video.videoId, video.title, video.author, video.thumbnail)
  } catch (err) {
    video.streaming = false
    console.error('Stream failed:', err)
  }
}

// Download and auto-play
async function downloadAndPlayFeatured(video) {
  video.downloading = true
  try {
    const saved = await doDownload(video)
    video.downloading = false
    video.downloaded = true
    songs.value.push(saved)
    player.playSong(saved, [saved], 0)
  } catch (err) {
    video.downloading = false
    alert('Download failed: ' + err.message)
    console.error('Download+play failed:', err)
  }
}

// Download only
async function downloadFeatured(video) {
  video.downloading = true
  try {
    const saved = await doDownload(video)
    video.downloading = false
    video.downloaded = true
    songs.value.push(saved)
  } catch (err) {
    video.downloading = false
    alert('Download failed: ' + err.message)
    console.error('Download failed:', err)
  }
}

// Shared download logic
async function doDownload(video) {
  const res = await api('/bars/api/yt-download.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      videoId: video.videoId,
      title: video.title,
      artist: video.author,
      cover: video.thumbnail
    })
  })
  const data = await res.json()
  if (!data.success) throw new Error(JSON.stringify(data))

  const audioRes = await fetchMusic(data.url)

  const audioBlob = await audioRes.blob()
  return await saveSong({
    title: video.title,
    artist: video.author,
    album: 'YouTube',
    filename: data.filename,
    size: data.size || audioBlob.size,
    type: 'audio/mpeg',
    cover: video.thumbnail
  }, audioBlob)
}

onMounted(async () => {
  // Load local songs from IndexedDB
  const localSongs = await getAllSongs()

  // Also load songs from server and merge
  try {
    const res = await api('/bars/api/songs.php')
    const data = await res.json()
    const serverSongs = data.songs || []

    if (serverSongs.length > 0) {
      // Cache server songs for offline use
      localStorage.setItem('bars_server_songs', JSON.stringify(serverSongs))
    }

    const localIds = new Set(localSongs.map(s => s.filename))
    // Merge: local songs + server songs not already in local
    songs.value = [
      ...localSongs,
      ...serverSongs.filter(s => !localIds.has(s.filename)).map(s => ({
        id: `server_${s.id}`,
        title: s.title,
        artist: s.artist || 'Unknown Artist',
        album: s.album || 'Unknown Album',
        filename: s.filename,
        cover: s.cover,
        size: s.size,
        url: `/bars/music/${s.filename}`,
        addedAt: s.created_at
      }))
    ]
  } catch {
    // Offline — use cached server songs
    let cachedServerSongs = []
    try {
      cachedServerSongs = JSON.parse(localStorage.getItem('bars_server_songs') || '[]')
    } catch { /* ignore */ }

    const localIds = new Set(localSongs.map(s => s.filename))
    songs.value = [
      ...localSongs,
      ...cachedServerSongs.filter(s => !localIds.has(s.filename)).map(s => ({
        id: `server_${s.id}`,
        title: s.title,
        artist: s.artist || 'Unknown Artist',
        album: s.album || 'Unknown Album',
        filename: s.filename,
        cover: s.cover,
        size: s.size,
        url: `/bars/music/${s.filename}`,
        addedAt: s.created_at
      }))
    ]
  }

  // Always load featured/trending
  loadFeatured()

  // Load recently played from API
  try {
    const res = await api('/bars/api/history.php?limit=10')
    const data = await res.json()
    if (data.success && Array.isArray(data.history)) {
      recentlyPlayed.value = data.history
    }
  } catch {
    // Silently fail
  }

  // Load top artists for Made For You mixes
  try {
    const res = await api('/bars/api/history.php?type=top_artists&limit=4')
    const data = await res.json()
    if (data.success && Array.isArray(data.artists)) {
      topMixes.value = data.artists
    }
  } catch {
    // Silently fail
  }
})
</script>

<style scoped>
.scrollbar-none::-webkit-scrollbar { display: none; }
.scrollbar-none { -ms-overflow-style: none; scrollbar-width: none; }
</style>
