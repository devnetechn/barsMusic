<template>
  <div class="p-4 md:p-6 max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-white mb-2">Upload Music</h1>
    <p class="text-spotify-light mb-6">Import songs from your device or sync from the server</p>

    <!-- Sync from Server (Admin only) -->
    <div v-if="auth.isAdmin" class="bg-spotify-card rounded-lg p-4 mb-6">
      <div class="flex items-center justify-between mb-2">
        <div class="flex items-center gap-3">
          <svg class="w-5 h-5 text-spotify-green" fill="currentColor" viewBox="0 0 24 24"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg>
          <h3 class="text-white font-semibold">Sync from Server</h3>
        </div>
        <button @click="syncFromServer" :disabled="syncing"
          class="px-4 py-2 bg-spotify-green text-black font-semibold text-sm rounded-full hover:bg-spotify-green-hover transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
          {{ syncing ? 'Syncing...' : 'Sync Now' }}
        </button>
      </div>
      <p class="text-sm text-spotify-light">
        Download songs uploaded from other devices on the same WiFi network
      </p>
      <!-- Sync progress -->
      <div v-if="syncStatus" class="mt-3 text-sm"
        :class="syncStatus.type === 'error' ? 'text-red-400' : syncStatus.type === 'success' ? 'text-spotify-green' : 'text-spotify-light'">
        {{ syncStatus.message }}
      </div>
      <div v-if="syncProgress.total > 0" class="mt-2">
        <div class="h-1.5 bg-spotify-lighter/30 rounded-full overflow-hidden">
          <div class="h-full bg-spotify-green rounded-full transition-all duration-300"
            :style="{ width: (syncProgress.current / syncProgress.total * 100) + '%' }"></div>
        </div>
        <p class="text-xs text-spotify-light mt-1">{{ syncProgress.current }} / {{ syncProgress.total }} songs</p>
      </div>
    </div>

    <!-- WiFi Info (Admin only) -->
    <div v-if="auth.isAdmin" class="bg-spotify-card rounded-lg p-4 mb-6">
      <div class="flex items-center gap-3 mb-2">
        <svg class="w-5 h-5 text-spotify-green" fill="currentColor" viewBox="0 0 24 24"><path d="M1 9l2 2c4.97-4.97 13.03-4.97 18 0l2-2C16.93 2.93 7.08 2.93 1 9zm8 8l3 3 3-3c-1.65-1.66-4.34-1.66-6 0zm-4-4l2 2c2.76-2.76 7.24-2.76 10 0l2-2C15.14 9.14 8.87 9.14 5 13z"/></svg>
        <h3 class="text-white font-semibold">WiFi Import</h3>
      </div>
      <p class="text-sm text-spotify-light">
        Open this URL on any device connected to the same network to upload music:
      </p>
      <div class="mt-2 flex items-center gap-2">
        <code class="bg-spotify-black px-3 py-2 rounded text-spotify-green text-sm flex-1 select-all break-all">
          {{ networkUrl }}
        </code>
        <button @click="copyUrl" class="px-3 py-2 bg-spotify-lighter/30 rounded text-white text-sm hover:bg-spotify-lighter/50 transition-colors flex-shrink-0">
          {{ copied ? 'Copied!' : 'Copy' }}
        </button>
      </div>
    </div>

    <!-- Drop zone -->
    <div
      @dragover.prevent="isDragging = true"
      @dragleave="isDragging = false"
      @drop.prevent="handleDrop"
      @click="triggerFileInput"
      class="border-2 border-dashed rounded-lg p-8 md:p-12 text-center cursor-pointer transition-colors"
      :class="isDragging ? 'border-spotify-green bg-spotify-green/10' : 'border-spotify-lighter hover:border-spotify-light'">
      <svg class="w-12 h-12 text-spotify-light mx-auto mb-4" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16h6v-6h4l-7-7-7 7h4zm-4 2h14v2H5z"/></svg>
      <p class="text-white font-semibold mb-1">Drop music files here</p>
      <p class="text-sm text-spotify-light">or click to browse</p>
      <p class="text-xs text-spotify-lighter mt-2">Supports MP3, WAV, OGG, FLAC, AAC, M4A</p>
      <input ref="fileInput" type="file" accept="audio/*" multiple class="hidden" @change="handleFiles" />
    </div>

    <!-- Upload progress -->
    <div v-if="uploads.length" class="mt-6 space-y-3">
      <h3 class="text-white font-semibold">Uploads</h3>
      <div v-for="(upload, i) in uploads" :key="i"
        class="bg-spotify-card rounded-lg p-3 flex items-center gap-3">
        <div class="flex-1 min-w-0">
          <p class="text-sm text-white truncate">{{ upload.name }}</p>
          <div class="h-1 bg-spotify-lighter/30 rounded-full mt-2 overflow-hidden">
            <div class="h-full rounded-full transition-all duration-300"
              :class="upload.status === 'error' ? 'bg-red-500' : upload.status === 'duplicate' ? 'bg-yellow-400' : 'bg-spotify-green'"
              :style="{ width: upload.progress + '%' }"></div>
          </div>
        </div>
        <span class="text-xs flex-shrink-0"
          :class="upload.status === 'error' ? 'text-red-400' : upload.status === 'duplicate' ? 'text-yellow-400' : upload.status === 'done' ? 'text-spotify-green' : 'text-spotify-light'">
          {{ upload.status === 'done' ? 'Done' : upload.status === 'duplicate' ? 'Already exists' : upload.status === 'error' ? 'Error' : upload.progress + '%' }}
        </span>
      </div>
    </div>

    <!-- Storage info -->
    <div v-if="storageInfo" class="mt-6 bg-spotify-card rounded-lg p-4">
      <h3 class="text-sm font-semibold text-white mb-2">Storage</h3>
      <div class="h-2 bg-spotify-lighter/30 rounded-full overflow-hidden mb-2">
        <div class="h-full bg-spotify-green rounded-full"
          :style="{ width: (storageInfo.usage / storageInfo.quota * 100) + '%' }"></div>
      </div>
      <p class="text-xs text-spotify-light">{{ storageInfo.usageMB }} MB / {{ storageInfo.quotaMB }} MB used</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { saveSong, getAllSongs, songExists, getStorageEstimate } from '../utils/db'
import { api, fetchMusic } from '../utils/api'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()

const fileInput = ref(null)
const isDragging = ref(false)
const uploads = ref([])
const copied = ref(false)
const storageInfo = ref(null)
const syncing = ref(false)
const syncStatus = ref(null)
const syncProgress = ref({ current: 0, total: 0 })

const networkUrl = ref(window.location.origin + window.location.pathname)

function triggerFileInput() {
  fileInput.value?.click()
}

function handleDrop(e) {
  isDragging.value = false
  const files = Array.from(e.dataTransfer.files).filter(f => f.type.startsWith('audio/'))
  processFiles(files)
}

function handleFiles(e) {
  const files = Array.from(e.target.files)
  processFiles(files)
  e.target.value = ''
}

async function processFiles(files) {
  for (const file of files) {
    const upload = {
      name: file.name,
      progress: 0,
      status: 'uploading'
    }
    uploads.value.push(upload)

    try {
      // Check duplicate in IndexedDB
      if (await songExists(file.name)) {
        upload.progress = 100
        upload.status = 'duplicate'
        continue
      }

      upload.progress = 30

      const formData = new FormData()
      formData.append('music', file)
      try {
        await api('/bars/api/upload.php', { method: 'POST', body: formData })
      } catch {
        // Server upload optional
      }

      upload.progress = 60

      const metadata = {
        title: file.name.replace(/\.[^/.]+$/, ''),
        artist: 'Unknown Artist',
        album: 'Unknown Album',
        filename: file.name,
        size: file.size,
        type: file.type
      }

      await saveSong(metadata, file)
      upload.progress = 100
      upload.status = 'done'
    } catch (err) {
      upload.status = 'error'
      console.error('Upload failed:', err)
    }
  }

  storageInfo.value = await getStorageEstimate()
}

async function syncFromServer() {
  syncing.value = true
  syncStatus.value = { type: 'info', message: 'Checking server for songs...' }
  syncProgress.value = { current: 0, total: 0 }

  try {
    // Get ALL songs from server (admin sync)
    const res = await api('/bars/api/songs.php?sync')
    const data = await res.json()
    const serverSongs = data.songs || []

    if (serverSongs.length === 0) {
      syncStatus.value = { type: 'info', message: 'No songs found on server' }
      syncing.value = false
      return
    }

    // Get existing songs from IndexedDB
    const localSongs = await getAllSongs()
    const localFilenames = new Set(localSongs.map(s => s.filename))

    // Find songs not yet downloaded
    const newSongs = serverSongs.filter(s => !localFilenames.has(s.filename))

    if (newSongs.length === 0) {
      syncStatus.value = { type: 'success', message: 'All songs already synced!' }
      syncing.value = false
      return
    }

    syncStatus.value = { type: 'info', message: `Downloading ${newSongs.length} new song(s)...` }
    syncProgress.value = { current: 0, total: newSongs.length }

    let downloaded = 0
    let failed = 0

    for (const song of newSongs) {
      try {
        // Download the audio file from server - use relative URL
        const audioRes = await fetchMusic(song.url)

        const audioBlob = await audioRes.blob()

        // Save to IndexedDB
        const metadata = {
          title: song.title,
          artist: song.artist || 'Unknown Artist',
          album: song.album || 'Unknown Album',
          filename: song.filename,
          cover: song.cover || null,
          size: song.size || audioBlob.size,
          type: audioBlob.type || 'audio/mpeg'
        }

        await saveSong(metadata, audioBlob)
        downloaded++
      } catch (err) {
        console.error(`Failed to sync ${song.filename}:`, err)
        failed++
      }

      syncProgress.value.current = downloaded + failed
    }

    if (failed === 0) {
      syncStatus.value = { type: 'success', message: `Successfully downloaded ${downloaded} song(s)!` }
    } else {
      syncStatus.value = { type: 'error', message: `Downloaded ${downloaded}, failed ${failed} song(s)` }
    }

    storageInfo.value = await getStorageEstimate()
  } catch (err) {
    console.error('Sync failed:', err)
    syncStatus.value = { type: 'error', message: 'Failed to connect to server. Make sure you are on the same WiFi.' }
  }

  syncing.value = false
}

async function copyUrl() {
  try {
    await navigator.clipboard.writeText(networkUrl.value)
    copied.value = true
    setTimeout(() => copied.value = false, 2000)
  } catch {
    const el = document.createElement('textarea')
    el.value = networkUrl.value
    document.body.appendChild(el)
    el.select()
    document.execCommand('copy')
    document.body.removeChild(el)
    copied.value = true
    setTimeout(() => copied.value = false, 2000)
  }
}

onMounted(async () => {
  storageInfo.value = await getStorageEstimate()
})
</script>
