<template>
  <div class="p-4 md:p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-white">Your Library</h1>
      <button @click="showCreatePlaylist = true"
        class="px-4 py-2 bg-spotify-card text-white text-sm rounded-full hover:bg-spotify-lighter/30 transition-colors">
        New Playlist
      </button>
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 mb-6 overflow-x-auto scrollbar-none">
      <button v-for="t in tabs" :key="t.key" @click="tab = t.key"
        class="px-4 py-1.5 rounded-full text-sm font-medium transition-colors whitespace-nowrap"
        :class="tab === t.key ? 'bg-white text-black' : 'bg-spotify-card text-white hover:bg-spotify-lighter/30'">
        {{ t.label }}
      </button>
    </div>

    <!-- Songs list -->
    <div v-if="tab === 'songs'" class="space-y-1">
      <div v-for="(song, index) in songs" :key="song.id"
        class="flex items-center gap-3 p-2 rounded-md hover:bg-spotify-card transition-colors cursor-pointer group"
        @click="playSong(song, index)">
        <div class="w-10 h-10 bg-spotify-lighter rounded flex-shrink-0 flex items-center justify-center overflow-hidden">
          <img v-if="song.cover" :src="song.cover" class="w-full h-full object-cover" />
          <svg v-else class="w-5 h-5 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm text-white truncate"
            :class="{ '!text-spotify-green': player.currentSong?.id === song.id }">
            {{ song.title }}
          </p>
          <p class="text-xs text-spotify-light truncate">{{ song.artist || 'Unknown Artist' }}</p>
        </div>
        <button @click.stop="openContextMenu(song)"
          class="opacity-0 group-hover:opacity-100 text-spotify-light hover:text-white transition-all p-1">
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
        </button>
      </div>
      <div v-if="!songs.length" class="text-center py-12">
        <p class="text-spotify-light">No songs yet</p>
        <router-link to="/upload" class="text-spotify-green text-sm hover:underline">Upload some music</router-link>
      </div>
    </div>

    <!-- Artists list -->
    <div v-if="tab === 'artists'" class="space-y-1">
      <div v-for="artist in artists" :key="artist.name"
        class="flex items-center gap-3 p-2 rounded-md hover:bg-spotify-card transition-colors cursor-pointer"
        @click="playArtist(artist)">
        <div class="w-12 h-12 rounded-full flex-shrink-0 flex items-center justify-center overflow-hidden"
          :class="artist.cover ? '' : 'bg-spotify-lighter'">
          <img v-if="artist.cover" :src="artist.cover" class="w-full h-full object-cover" />
          <span v-else class="text-lg font-bold text-spotify-light">{{ artist.name.charAt(0).toUpperCase() }}</span>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-white truncate">{{ artist.name }}</p>
          <p class="text-xs text-spotify-light">{{ artist.songs.length }} {{ artist.songs.length === 1 ? 'song' : 'songs' }}</p>
        </div>
      </div>
      <div v-if="!artists.length" class="text-center py-12">
        <p class="text-spotify-light">No artists yet</p>
      </div>
    </div>

    <!-- Albums grid -->
    <div v-if="tab === 'albums'" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
      <div v-for="album in albums" :key="album.name + album.artist"
        class="bg-spotify-card hover:bg-spotify-lighter/30 rounded-lg p-4 transition-colors group cursor-pointer"
        @click="playAlbum(album)">
        <div class="aspect-square bg-spotify-lighter rounded-md flex items-center justify-center mb-3 overflow-hidden">
          <img v-if="album.cover" :src="album.cover" class="w-full h-full object-cover" />
          <svg v-else class="w-12 h-12 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 14.5c-2.49 0-4.5-2.01-4.5-4.5S9.51 7.5 12 7.5s4.5 2.01 4.5 4.5-2.01 4.5-4.5 4.5zm0-5.5c-.55 0-1 .45-1 1s.45 1 1 1 1-.45 1-1-.45-1-1-1z"/></svg>
        </div>
        <p class="text-sm font-semibold text-white truncate">{{ album.name }}</p>
        <p class="text-xs text-spotify-light mt-1 truncate">{{ album.artist || 'Unknown Artist' }} · {{ album.songs.length }} {{ album.songs.length === 1 ? 'song' : 'songs' }}</p>
      </div>
      <div v-if="!albums.length" class="col-span-full text-center py-12">
        <p class="text-spotify-light">No albums yet</p>
      </div>
    </div>

    <!-- Playlists -->
    <div v-if="tab === 'playlists'" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
      <router-link v-for="playlist in playlists" :key="playlist.id"
        :to="`/playlist/${playlist.id}`"
        class="bg-spotify-card hover:bg-spotify-lighter/30 rounded-xl p-3 transition-colors group">
        <div class="aspect-square bg-gradient-to-br from-spotify-lighter/40 to-spotify-lighter/10 rounded-lg flex items-center justify-center mb-2">
          <svg class="w-10 h-10 text-spotify-green/80" fill="currentColor" viewBox="0 0 24 24"><path d="M15 6H3v2h12V6zm0 4H3v2h12v-2zM3 16h8v-2H3v2zM17 6v8.18c-.31-.11-.65-.18-1-.18-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3V8h3V6h-5z"/></svg>
        </div>
        <p class="text-sm font-bold text-white truncate">{{ playlist.name }}</p>
        <p class="text-[11px] text-spotify-light mt-0.5">{{ playlist.total || 0 }} songs</p>
      </router-link>
      <div v-if="!playlists.length" class="col-span-full text-center py-12">
        <p class="text-spotify-light">No playlists yet</p>
      </div>
    </div>

    <!-- Create Playlist Modal -->
    <div v-if="showCreatePlaylist" class="fixed inset-0 bg-black/60 z-[60] flex items-center justify-center p-4"
      @click.self="showCreatePlaylist = false">
      <div class="bg-spotify-card rounded-lg p-6 w-full max-w-sm">
        <h2 class="text-xl font-bold text-white mb-4">Create Playlist</h2>
        <input v-model="newPlaylistName" type="text" placeholder="Playlist name"
          class="w-full bg-spotify-lighter/30 text-white px-4 py-3 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-spotify-green placeholder-spotify-lighter mb-4"
          @keyup.enter="createPlaylist" ref="playlistInput" />
        <div class="flex gap-3 justify-end">
          <button @click="showCreatePlaylist = false" class="px-4 py-2 text-white text-sm hover:text-spotify-light">
            Cancel
          </button>
          <button @click="createPlaylist"
            class="px-6 py-2 bg-spotify-green text-black font-semibold text-sm rounded-full hover:bg-spotify-green-hover transition-colors">
            Create
          </button>
        </div>
      </div>
    </div>

    <!-- Context Menu (Bottom Sheet) -->
    <div v-if="contextSong" class="fixed inset-0 bg-black/60 z-[60] flex items-end justify-center"
      @click.self="contextSong = null">
      <div class="bg-spotify-card rounded-t-2xl w-full max-w-lg p-4 pb-8 animate-slide-up">
        <div class="flex items-center gap-3 mb-4 pb-4 border-b border-spotify-lighter/20">
          <div class="w-12 h-12 bg-spotify-lighter rounded flex-shrink-0 flex items-center justify-center overflow-hidden">
            <img v-if="contextSong.cover" :src="contextSong.cover" class="w-full h-full object-cover" />
            <svg v-else class="w-6 h-6 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
          </div>
          <div class="min-w-0">
            <p class="text-sm font-semibold text-white truncate">{{ contextSong.title }}</p>
            <p class="text-xs text-spotify-light truncate">{{ contextSong.artist || 'Unknown Artist' }}</p>
          </div>
        </div>
        <button @click="handlePlayNext"
          class="flex items-center gap-3 w-full p-3 rounded-md hover:bg-spotify-lighter/20 transition-colors">
          <svg class="w-5 h-5 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg>
          <span class="text-sm text-white">Play Next</span>
        </button>
        <button @click="handleAddToQueue"
          class="flex items-center gap-3 w-full p-3 rounded-md hover:bg-spotify-lighter/20 transition-colors">
          <svg class="w-5 h-5 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M15 6H3v2h12V6zm0 4H3v2h12v-2zM3 16h8v-2H3v2zM17 6v8.18c-.31-.11-.65-.18-1-.18-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3V8h3V6h-5z"/></svg>
          <span class="text-sm text-white">Add to Queue</span>
        </button>
        <button @click="showAddToPlaylist = true"
          class="flex items-center gap-3 w-full p-3 rounded-md hover:bg-spotify-lighter/20 transition-colors">
          <svg class="w-5 h-5 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M14 10H2v2h12v-2zm0-4H2v2h12V6zm4 8v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zM2 16h8v-2H2v2z"/></svg>
          <span class="text-sm text-white">Add to Playlist</span>
        </button>
        <button @click="handleDeleteFromContext"
          class="flex items-center gap-3 w-full p-3 rounded-md hover:bg-spotify-lighter/20 transition-colors">
          <svg class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
          <span class="text-sm text-red-400">Delete</span>
        </button>
      </div>
    </div>

    <!-- Add to Playlist Modal -->
    <AddToPlaylist v-if="showAddToPlaylist" :song="contextSong"
      @close="showAddToPlaylist = false; contextSong = null" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import { getAllSongs, deleteSong } from '../utils/db'
import { usePlayerStore } from '../stores/player'
import { api } from '../utils/api'
import AddToPlaylist from '../components/AddToPlaylist.vue'

const player = usePlayerStore()
const songs = ref([])
const playlists = ref([])
const tab = ref('songs')
const showCreatePlaylist = ref(false)
const newPlaylistName = ref('')
const playlistInput = ref(null)
const contextSong = ref(null)
const showAddToPlaylist = ref(false)

const artists = computed(() => {
  const map = {}
  for (const song of songs.value) {
    const name = song.artist || 'Unknown Artist'
    if (!map[name]) map[name] = { name, songs: [], cover: song.cover }
    map[name].songs.push(song)
    if (!map[name].cover && song.cover) map[name].cover = song.cover
  }
  return Object.values(map).sort((a, b) => b.songs.length - a.songs.length)
})

const albums = computed(() => {
  const map = {}
  for (const song of songs.value) {
    const name = song.album || 'Unknown Album'
    if (!map[name]) map[name] = { name, artist: song.artist, songs: [], cover: song.cover }
    map[name].songs.push(song)
    if (!map[name].cover && song.cover) map[name].cover = song.cover
  }
  return Object.values(map).sort((a, b) => b.songs.length - a.songs.length)
})

const tabs = computed(() => [
  { key: 'songs', label: `Songs (${songs.value.length})` },
  { key: 'artists', label: `Artists (${artists.value.length})` },
  { key: 'albums', label: `Albums (${albums.value.length})` },
  { key: 'playlists', label: `Playlists (${playlists.value.length})` },
])

watch(showCreatePlaylist, async (val) => {
  if (val) {
    await nextTick()
    playlistInput.value?.focus()
  }
})

function playSong(song, index) {
  player.playSong(song, songs.value, index)
}

function playArtist(artist) {
  player.playSong(artist.songs[0], artist.songs, 0)
}

function playAlbum(album) {
  player.playSong(album.songs[0], album.songs, 0)
}

function openContextMenu(song) {
  contextSong.value = song
}

function handlePlayNext() {
  if (contextSong.value) {
    player.playNext(contextSong.value)
    contextSong.value = null
  }
}

function handleAddToQueue() {
  if (contextSong.value) {
    player.addToQueue(contextSong.value)
    contextSong.value = null
  }
}

async function handleDeleteFromContext() {
  if (contextSong.value) {
    await confirmDelete(contextSong.value)
    contextSong.value = null
  }
}

async function confirmDelete(song) {
  if (confirm(`Delete "${song.title}"?`)) {
    await deleteSong(song.id)
    songs.value = await getAllSongs()
  }
}

async function createPlaylist() {
  if (!newPlaylistName.value.trim()) return
  try {
    await api('/bars/api/playlists.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'create', name: newPlaylistName.value.trim() })
    })
  } catch {}
  newPlaylistName.value = ''
  showCreatePlaylist.value = false
  await loadPlaylists()
}

async function loadPlaylists() {
  try {
    const res = await api('/bars/api/playlists.php')
    const data = await res.json()
    playlists.value = data.playlists || []
  } catch {
    playlists.value = []
  }
}

onMounted(async () => {
  // Load local songs from IndexedDB
  const localSongs = await getAllSongs()

  // Also load from server and merge
  try {
    const res = await api('/bars/api/songs.php')
    const data = await res.json()
    const serverSongs = data.songs || []
    const localFilenames = new Set(localSongs.map(s => s.filename))
    songs.value = [
      ...localSongs,
      ...serverSongs.filter(s => !localFilenames.has(s.filename)).map(s => ({
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
    songs.value = localSongs
  }

  await loadPlaylists()
})
</script>
