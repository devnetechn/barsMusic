<template>
  <div v-if="show" class="fixed inset-0 bg-black/60 z-[65] flex items-end justify-center"
    @click.self="$emit('close')">
    <div class="bg-spotify-dark rounded-t-xl w-full max-w-lg max-h-[70vh] flex flex-col animate-slide-up"
      style="padding-bottom: env(safe-area-inset-bottom)">

      <!-- Header -->
      <div class="p-4 border-b border-spotify-lighter/20 flex-shrink-0">
        <h2 class="text-lg font-bold text-white">
          {{ mode === 'pick' ? 'Pick Playlist' : mode === 'browse' ? 'Add from Library' : 'Add to Playlist' }}
        </h2>
        <p v-if="song && mode === 'pick'" class="text-xs text-spotify-light truncate mt-1">{{ song.title }}</p>
        <p v-if="mode === 'browse' && targetPlaylist" class="text-xs text-spotify-light truncate mt-1">Adding to: {{ targetPlaylist.name }}</p>
      </div>

      <!-- MODE: pick — choose which playlist (existing behavior) -->
      <template v-if="mode === 'pick'">
        <!-- Create new -->
        <div class="px-4 pt-3 flex-shrink-0">
          <div class="flex gap-2">
            <input v-model="newName" type="text" placeholder="New playlist name..."
              class="flex-1 bg-spotify-card text-white px-3 py-2 rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-spotify-green placeholder-spotify-lighter"
              @keyup.enter="createAndAdd" />
            <button @click="createAndAdd" :disabled="!newName.trim()"
              class="px-4 py-2 bg-spotify-green text-black text-sm font-semibold rounded-md disabled:opacity-30">
              Create
            </button>
          </div>
        </div>

        <!-- Existing playlists -->
        <div class="flex-1 overflow-y-auto p-4 space-y-1">
          <div v-for="pl in playlists" :key="pl.id"
            @click="addSongToPlaylist(pl.id)"
            class="flex items-center gap-3 p-3 rounded-md hover:bg-spotify-card transition-colors cursor-pointer">
            <div class="w-10 h-10 bg-spotify-card rounded flex items-center justify-center text-spotify-light flex-shrink-0">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm text-white truncate">{{ pl.name }}</p>
              <p class="text-xs text-spotify-light">{{ pl.total || 0 }} songs</p>
            </div>
            <svg class="w-5 h-5 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
          </div>
          <p v-if="!playlists.length" class="text-center text-spotify-light text-sm py-4">No playlists yet</p>
        </div>
      </template>

      <!-- MODE: browse — pick songs from library to add to a playlist -->
      <template v-if="mode === 'browse'">
        <!-- Search filter -->
        <div class="px-4 pt-3 flex-shrink-0">
          <input v-model="searchQuery" type="text" placeholder="Search your library..."
            class="w-full bg-spotify-card text-white px-3 py-2 rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-spotify-green placeholder-spotify-lighter" />
        </div>

        <!-- Song list -->
        <div class="flex-1 overflow-y-auto p-4 space-y-1">
          <div v-for="s in filteredSongs" :key="s.id || s.filename"
            @click="addLibrarySong(s)"
            class="flex items-center gap-3 p-2 rounded-md hover:bg-spotify-card transition-colors cursor-pointer"
            :class="{ 'opacity-50': addedSongs.has(s.filename || s.title) }">
            <div class="w-10 h-10 bg-spotify-lighter rounded flex-shrink-0 flex items-center justify-center overflow-hidden">
              <img v-if="s.cover" :src="s.cover" class="w-full h-full object-cover" />
              <svg v-else class="w-5 h-5 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm text-white truncate">{{ s.title }}</p>
              <p class="text-xs text-spotify-light truncate">{{ s.artist || 'Unknown Artist' }}</p>
            </div>
            <span v-if="addedSongs.has(s.filename || s.title)" class="text-xs text-spotify-green flex-shrink-0">Added</span>
            <svg v-else class="w-5 h-5 text-spotify-light flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
          </div>
          <p v-if="!filteredSongs.length" class="text-center text-spotify-light text-sm py-4">No songs found</p>
        </div>
      </template>

      <!-- Added confirmation -->
      <div v-if="addedMessage" class="px-4 py-2 bg-spotify-green/20 flex-shrink-0">
        <p class="text-sm text-spotify-green text-center font-medium">{{ addedMessage }}</p>
      </div>

      <div class="p-3 border-t border-spotify-lighter/20 flex-shrink-0 flex gap-2">
        <button v-if="mode === 'browse'" @click="mode = 'pick'; targetPlaylist = null"
          class="flex-1 py-2 text-sm text-spotify-light hover:text-white">Back</button>
        <button @click="$emit('close')" class="flex-1 py-2 text-sm text-spotify-light hover:text-white">Close</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { api } from '../utils/api'
import { getAllSongs } from '../utils/db'

const props = defineProps({
  show: Boolean,
  song: Object,
  playlistId: { type: [Number, String], default: null }
})
const emit = defineEmits(['close', 'added'])

const playlists = ref([])
const newName = ref('')
const addedMessage = ref('')
const mode = ref('pick') // 'pick' = choose playlist, 'browse' = choose songs from library
const targetPlaylist = ref(null)
const librarySongs = ref([])
const searchQuery = ref('')
const addedSongs = ref(new Set())

const filteredSongs = computed(() => {
  if (!searchQuery.value.trim()) return librarySongs.value
  const q = searchQuery.value.toLowerCase()
  return librarySongs.value.filter(s =>
    s.title?.toLowerCase().includes(q) ||
    s.artist?.toLowerCase().includes(q)
  )
})

watch(() => props.show, async (val) => {
  if (val) {
    addedMessage.value = ''
    addedSongs.value = new Set()
    searchQuery.value = ''

    // If playlistId is provided, go straight to browse mode
    if (props.playlistId) {
      mode.value = 'browse'
      targetPlaylist.value = { id: props.playlistId, name: 'Playlist' }
      await loadLibrarySongs()
    } else {
      mode.value = 'pick'
    }

    try {
      const res = await api('/bars/api/playlists.php')
      const data = await res.json()
      playlists.value = data.playlists || []
      // Update targetPlaylist name if we have playlistId
      if (props.playlistId) {
        const found = playlists.value.find(p => p.id == props.playlistId)
        if (found) targetPlaylist.value = found
      }
    } catch { playlists.value = [] }
  }
})

async function loadLibrarySongs() {
  // Load from IndexedDB
  const local = await getAllSongs()
  // Also load from server
  try {
    const res = await api('/bars/api/songs.php')
    const data = await res.json()
    const serverSongs = data.songs || []
    const localFilenames = new Set(local.map(s => s.filename))
    librarySongs.value = [
      ...local,
      ...serverSongs.filter(s => !localFilenames.has(s.filename)).map(s => ({
        id: s.id,
        title: s.title,
        artist: s.artist || 'Unknown Artist',
        filename: s.filename,
        cover: s.cover,
        size: s.size
      }))
    ]
  } catch {
    librarySongs.value = local
  }
}

async function createAndAdd() {
  if (!newName.value.trim()) return
  try {
    const res = await api('/bars/api/playlists.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'create', name: newName.value.trim() })
    })
    const data = await res.json()
    if (data.success) {
      if (props.song) {
        await addSongToPlaylist(data.id)
      } else {
        // Switch to browse mode for the new playlist
        targetPlaylist.value = { id: data.id, name: newName.value.trim() }
        mode.value = 'browse'
        await loadLibrarySongs()
      }
      newName.value = ''
      // Refresh list
      const res2 = await api('/bars/api/playlists.php')
      const data2 = await res2.json()
      playlists.value = data2.playlists || []
    }
  } catch {}
}

async function addSongToPlaylist(playlistId) {
  const s = props.song
  if (s) {
    // Adding a specific song (from search/context menu)
    try {
      await api('/bars/api/playlists.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'add_item',
          playlist_id: playlistId,
          song_id: s.id || null,
          video_id: s.videoId || s.video_id || null,
          title: s.title || '',
          artist: s.artist || s.author || '',
          cover: s.cover || s.thumbnail || null,
          duration: s.duration || ''
        })
      })
      addedMessage.value = 'Added to playlist!'
      setTimeout(() => addedMessage.value = '', 2000)
      emit('added')
    } catch {}
  } else {
    // No song — switch to browse mode to pick songs
    const pl = playlists.value.find(p => p.id === playlistId)
    targetPlaylist.value = pl || { id: playlistId, name: 'Playlist' }
    mode.value = 'browse'
    await loadLibrarySongs()
  }
}

async function addLibrarySong(song) {
  const key = song.filename || song.title
  if (addedSongs.value.has(key)) return

  const plId = targetPlaylist.value?.id || props.playlistId
  if (!plId) return

  try {
    await api('/bars/api/playlists.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'add_item',
        playlist_id: plId,
        song_id: song.id || null,
        title: song.title || '',
        artist: song.artist || '',
        cover: song.cover || null,
        duration: song.duration || ''
      })
    })
    addedSongs.value = new Set([...addedSongs.value, key])
    addedMessage.value = `Added "${song.title}"!`
    setTimeout(() => addedMessage.value = '', 1500)
    emit('added')
  } catch {}
}
</script>
