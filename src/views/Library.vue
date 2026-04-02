<template>
  <div class="p-4 md:p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-5">
      <h1 class="text-2xl font-bold text-white">Your Library</h1>
      <button @click="showCreatePlaylist = true"
        class="w-8 h-8 bg-spotify-card rounded-full flex items-center justify-center hover:bg-spotify-lighter/30 transition-colors">
        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
      </button>
    </div>

    <!-- Downloads card -->
    <div @click="showDownloads = !showDownloads"
      class="flex items-center gap-3 p-3 rounded-lg bg-spotify-card hover:bg-spotify-lighter/30 transition-colors cursor-pointer mb-2">
      <div class="w-14 h-14 bg-gradient-to-br from-spotify-green to-green-700 rounded flex-shrink-0 flex items-center justify-center shadow">
        <svg class="w-7 h-7 text-black" fill="currentColor" viewBox="0 0 24 24"><path d="M16 13h-3V3h-2v10H8l4 4 4-4zM4 19v2h16v-2H4z"/></svg>
      </div>
      <div class="flex-1 min-w-0">
        <p class="text-base font-bold text-white">Downloads</p>
        <p class="text-xs text-spotify-light">{{ songs.length }} songs</p>
      </div>
      <svg class="w-5 h-5 text-spotify-light transition-transform" :class="{ 'rotate-180': showDownloads }" fill="currentColor" viewBox="0 0 24 24"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6z"/></svg>
    </div>

    <!-- Downloads expanded -->
    <div v-if="showDownloads" class="mb-4 space-y-1 pl-2">
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
          class="md:opacity-0 md:group-hover:opacity-100 text-spotify-light hover:text-white transition-all p-1">
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
        </button>
      </div>
      <div v-if="!songs.length" class="text-center py-6">
        <p class="text-spotify-light text-sm">No downloaded songs</p>
      </div>
    </div>

    <!-- Playlists -->
    <div class="space-y-2">
      <router-link v-for="playlist in playlists" :key="playlist.id"
        :to="`/playlist/${playlist.id}`"
        class="flex items-center gap-3 p-3 rounded-lg hover:bg-spotify-card transition-colors">
        <div class="w-14 h-14 bg-gradient-to-br from-purple-700 to-blue-900 rounded flex-shrink-0 flex items-center justify-center shadow">
          <svg class="w-7 h-7 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M15 6H3v2h12V6zm0 4H3v2h12v-2zM3 16h8v-2H3v2zM17 6v8.18c-.31-.11-.65-.18-1-.18-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3V8h3V6h-5z"/></svg>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-base font-medium text-white truncate">{{ playlist.name }}</p>
          <p class="text-xs text-spotify-light">Playlist · {{ playlist.total || 0 }} songs</p>
        </div>
      </router-link>
    </div>

    <div v-if="!playlists.length && !songs.length" class="text-center py-16">
      <svg class="w-16 h-16 text-spotify-lighter mx-auto mb-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
      <p class="text-spotify-light">Your library is empty</p>
      <router-link to="/search" class="text-spotify-green text-sm hover:underline mt-2 inline-block">Search for music</router-link>
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
import { ref, onMounted, nextTick, watch } from 'vue'
import { getAllSongs, deleteSong } from '../utils/db'
import { usePlayerStore } from '../stores/player'
import { api } from '../utils/api'
import AddToPlaylist from '../components/AddToPlaylist.vue'

const player = usePlayerStore()
const songs = ref([])
const playlists = ref([])
const showDownloads = ref(false)
const showCreatePlaylist = ref(false)
const newPlaylistName = ref('')
const playlistInput = ref(null)
const contextSong = ref(null)
const showAddToPlaylist = ref(false)

watch(showCreatePlaylist, async (val) => {
  if (val) {
    await nextTick()
    playlistInput.value?.focus()
  }
})

function playSong(song, index) {
  player.playSong(song, songs.value, index)
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
  const localSongs = await getAllSongs()

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
