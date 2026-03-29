<template>
  <div class="p-4 md:p-6" v-if="playlist">
    <!-- Header -->
    <div class="flex items-end gap-4 mb-6">
      <div class="w-32 h-32 md:w-48 md:h-48 bg-spotify-card rounded-lg flex items-center justify-center shadow-lg flex-shrink-0 overflow-hidden">
        <img v-if="firstCover" :src="firstCover" class="w-full h-full object-cover" />
        <svg v-else class="w-16 h-16 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
      </div>
      <div>
        <p class="text-xs text-spotify-light uppercase tracking-wider">Playlist</p>
        <h1 class="text-2xl md:text-4xl font-bold text-white mt-1">{{ playlist.name }}</h1>
        <p class="text-sm text-spotify-light mt-2">{{ items.length }} songs · {{ downloadedCount }} downloaded</p>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-3 mb-6">
      <button v-if="items.length" @click="playAll"
        class="w-12 h-12 bg-spotify-green rounded-full flex items-center justify-center hover:scale-105 transition-transform">
        <svg class="w-6 h-6 text-black ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
      </button>
      <!-- Download All -->
      <button v-if="undownloadedCount > 0" @click="downloadAll" :disabled="isDownloadingAll"
        class="px-4 py-2 bg-spotify-card text-white text-sm font-semibold rounded-full border border-spotify-lighter/30 hover:bg-spotify-lighter/30 transition-colors disabled:opacity-50">
        {{ isDownloadingAll ? `Downloading ${dlProgress}/${undownloadedCount}...` : `Download All (${undownloadedCount})` }}
      </button>
      <!-- Add Songs from Library -->
      <button @click="showAddSongs = true"
        class="px-4 py-2 bg-spotify-card text-white text-sm font-semibold rounded-full border border-spotify-lighter/30 hover:bg-spotify-lighter/30 transition-colors">
        Add Songs
      </button>
      <button @click="confirmDelete"
        class="text-spotify-light hover:text-red-400 transition-colors ml-auto">
        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
      </button>
    </div>

    <!-- Songs list -->
    <div class="space-y-1">
      <div v-for="(item, index) in items" :key="item.item_id"
        class="flex items-center gap-3 p-2 rounded-md hover:bg-spotify-card transition-colors cursor-pointer group"
        @click="playItem(item, index)">
        <span class="w-6 text-center text-sm text-spotify-light">{{ index + 1 }}</span>
        <div class="w-10 h-10 bg-spotify-lighter rounded flex-shrink-0 flex items-center justify-center overflow-hidden">
          <img v-if="item.thumbnail" :src="item.thumbnail" class="w-full h-full object-cover" />
          <svg v-else class="w-5 h-5 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm text-white truncate">{{ item.title }}</p>
          <p class="text-xs text-spotify-light truncate">{{ item.artist || 'Unknown' }}</p>
        </div>
        <!-- Status -->
        <span v-if="item.is_downloaded" class="text-[10px] text-spotify-green font-medium">Saved</span>
        <div v-else-if="item._downloading" class="w-5 h-5 border-2 border-spotify-green border-t-transparent rounded-full animate-spin"></div>
        <span v-else class="text-[10px] text-spotify-lighter">Not downloaded</span>
        <!-- Remove -->
        <button @click.stop="removeItem(item)" class="opacity-0 group-hover:opacity-100 text-spotify-light hover:text-red-400 transition-all">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
        </button>
      </div>
    </div>

    <div v-if="!items.length" class="text-center py-12">
      <p class="text-spotify-light mb-2">This playlist is empty</p>
      <button @click="showAddSongs = true" class="text-spotify-green text-sm hover:underline">Add songs from your library</button>
    </div>

    <!-- Add Songs Modal -->
    <AddToPlaylist :show="showAddSongs" :playlist-id="route.params.id" @close="showAddSongs = false" @added="loadPlaylist" />
  </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePlayerStore } from '../stores/player'
import { saveSong, getAudioBlob } from '../utils/db'
import AddToPlaylist from '../components/AddToPlaylist.vue'
import { api } from '../utils/api'

const route = useRoute()
const router = useRouter()
const player = usePlayerStore()

const playlist = ref(null)
const items = ref([])
const isDownloadingAll = ref(false)
const showAddSongs = ref(false)
const dlProgress = ref(0)

const firstCover = computed(() => {
  const item = items.value.find(i => i.thumbnail)
  return item?.thumbnail || null
})
const downloadedCount = computed(() => items.value.filter(i => i.is_downloaded).length)
const undownloadedCount = computed(() => items.value.filter(i => !i.is_downloaded).length)
const downloadedItems = computed(() => items.value.filter(i => i.is_downloaded && i.song_id))

async function loadPlaylist() {
  try {
    const res = await api(`../api/playlists.php?id=${route.params.id}`)
    const data = await res.json()
    playlist.value = data.playlist
    items.value = (data.playlist?.items || []).map(i => reactive({ ...i, _downloading: false }))
  } catch {}
}

function buildSong(item) {
  // Has song_id or filename — can play from IndexedDB or server
  if (item.song_id || item.filename) {
    return {
      id: item.song_id || `server_${item.item_id}`,
      title: item.title,
      artist: item.artist,
      cover: item.thumbnail,
      filename: item.filename || null
    }
  }
  // YouTube song not yet downloaded — will need stream URL
  if (item.video_id) {
    return {
      id: `yt_${item.video_id}`,
      title: item.title,
      artist: item.artist,
      cover: item.thumbnail,
      video_id: item.video_id
    }
  }
  return null
}

async function resolveAndPlay(song) {
  // YouTube song without filename — get stream URL first
  if (song.video_id && !song.filename) {
    try {
      const res = await api(`../api/yt-stream.php?id=${song.video_id}`)
      const data = await res.json()
      if (data.success) song.url = data.url
      else return null
    } catch { return null }
  }
  return song
}

async function playAll() {
  const songs = items.value.map(i => buildSong(i)).filter(Boolean)
  if (!songs.length) return
  const first = await resolveAndPlay(songs[0])
  if (first) player.playSong(first, songs, 0)
}

async function playItem(item, index) {
  const song = buildSong(item)
  if (!song) return
  const resolved = await resolveAndPlay(song)
  if (!resolved) return
  // Build full queue
  const allSongs = items.value.map(i => buildSong(i)).filter(Boolean)
  const songIndex = allSongs.findIndex(s => s.id === resolved.id)
  player.playSong(resolved, allSongs, songIndex >= 0 ? songIndex : 0)
}

async function downloadAll() {
  const toDownload = items.value.filter(i => !i.is_downloaded && i.video_id)
  if (!toDownload.length) return

  isDownloadingAll.value = true
  dlProgress.value = 0

  for (const item of toDownload) {
    item._downloading = true
    try {
      const res = await api('../api/yt-download.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          videoId: item.video_id,
          title: item.title,
          artist: item.artist,
          cover: item.thumbnail
        })
      })
      const data = await res.json()
      if (data.success) {
        // Save to IndexedDB for offline
        const audioRes = await fetch(`../${data.url.replace('/bars/', '')}`)
        if (audioRes.ok) {
          const blob = await audioRes.blob()
          await saveSong({
            title: item.title,
            artist: item.artist,
            album: 'YouTube',
            filename: data.filename,
            size: data.size || blob.size,
            type: 'audio/mpeg',
            cover: item.thumbnail
          }, blob)
        }
        item.is_downloaded = 1
        item.filename = data.filename
      }
    } catch (err) {
      console.error('Download failed:', item.title, err)
    }
    item._downloading = false
    dlProgress.value++
  }

  isDownloadingAll.value = false
  // Refresh
  await loadPlaylist()
}

async function removeItem(item) {
  try {
    await api('../api/playlists.php', {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'remove_item', item_id: item.item_id })
    })
    items.value = items.value.filter(i => i.item_id !== item.item_id)
  } catch {}
}

async function confirmDelete() {
  if (confirm(`Delete playlist "${playlist.value.name}"?`)) {
    await api('../api/playlists.php', {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete_playlist', id: playlist.value.id })
    })
    router.push('/library')
  }
}

onMounted(loadPlaylist)
</script>
