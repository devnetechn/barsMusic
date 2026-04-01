<template>
  <div class="fixed inset-0 bg-gradient-to-b from-spotify-card to-spotify-black z-[70] flex flex-col p-6"
    style="padding-top: calc(env(safe-area-inset-top) + 1.5rem); padding-bottom: calc(env(safe-area-inset-bottom) + 1.5rem)"
    v-if="player.currentSong">

    <!-- Header -->
    <div class="flex items-center justify-between mb-4 flex-shrink-0">
      <button @click="$router.back()" class="text-white p-2">
        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M7.41 15.41L12 10.83l4.59 4.58L18 14l-6-6-6 6z"/></svg>
      </button>
      <p class="text-xs text-spotify-light uppercase tracking-wider">Now Playing</p>
      <!-- Toggle Cover/Lyrics -->
      <button @click="toggleView"
        class="px-3 py-1.5 rounded-full text-xs font-semibold transition-colors"
        :class="showLyrics ? 'bg-spotify-green text-black' : 'bg-spotify-card text-white'">
        {{ showLyrics ? 'Cover' : 'Lyrics' }}
      </button>
    </div>

    <!-- Content area: Cover or Lyrics -->
    <div class="flex-1 min-h-0 flex items-center justify-center px-2">
      <!-- Album Cover -->
      <div v-if="!showLyrics" class="w-full max-w-sm aspect-square bg-spotify-card rounded-lg shadow-2xl flex items-center justify-center overflow-hidden">
        <img v-if="player.currentSong?.cover" :src="player.currentSong.cover" class="w-full h-full object-cover" />
        <svg v-else class="w-24 h-24 text-spotify-lighter" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
      </div>

      <!-- Lyrics -->
      <div v-else class="w-full h-full flex flex-col">
        <!-- Lyrics loading -->
        <div v-if="lyricsLoading" class="flex-1 flex items-center justify-center">
          <div class="w-8 h-8 border-2 border-spotify-green border-t-transparent rounded-full animate-spin"></div>
        </div>

        <!-- Lyrics content -->
        <div v-else-if="lyricsData" class="flex-1 overflow-y-auto overscroll-contain" ref="lyricsScroll">
          <div class="py-4 space-y-3">
            <p v-for="(line, i) in lyricsLines" :key="i"
              class="text-center text-lg font-medium transition-all duration-300 px-4"
              :class="activeLyricIndex === i ? 'text-white scale-105' : line.trim() ? 'text-spotify-light/60' : ''">
              {{ line || '&nbsp;' }}
            </p>
            <!-- Extra padding at bottom -->
            <div class="h-20"></div>
          </div>
        </div>

        <!-- No lyrics found -->
        <div v-else class="flex-1 flex flex-col items-center justify-center gap-3">
          <svg class="w-12 h-12 text-spotify-lighter" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 9H8v-1h5v1zm3-2H8V8h8v1zm0-3H8V5h5.5L16 7.5V6z"/></svg>
          <p class="text-spotify-light text-sm">No lyrics found</p>
          <p class="text-spotify-lighter text-xs">{{ player.currentSong?.title }}</p>
        </div>
      </div>
    </div>

    <!-- Song Info -->
    <div class="mt-4 mb-3 flex-shrink-0">
      <h2 class="text-xl font-bold text-white truncate">{{ player.currentSong?.title || 'Unknown' }}</h2>
      <p class="text-sm text-spotify-light truncate">{{ player.currentSong?.artist || 'Unknown Artist' }}</p>
    </div>

    <!-- Progress -->
    <div class="mb-3 flex-shrink-0">
      <div class="h-1 bg-spotify-lighter/30 rounded-full cursor-pointer" @click="seekFromClick($event)">
        <div class="h-full bg-white rounded-full relative" :style="{ width: player.progress + '%' }">
          <div class="absolute right-0 top-1/2 -translate-y-1/2 w-4 h-4 bg-white rounded-full shadow"></div>
        </div>
      </div>
      <div class="flex justify-between mt-1">
        <span class="text-xs text-spotify-light">{{ player.formattedCurrentTime }}</span>
        <span class="text-xs text-spotify-light">{{ player.formattedDuration }}</span>
      </div>
    </div>

    <!-- Controls -->
    <div class="flex items-center justify-between mb-4 px-4 flex-shrink-0">
      <button @click="player.toggleShuffle()" class="transition-colors"
        :class="player.shuffle ? 'text-spotify-green' : 'text-spotify-light'">
        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M10.59 9.17L5.41 4 4 5.41l5.17 5.17 1.42-1.41zM14.5 4l2.04 2.04L4 18.59 5.41 20 17.96 7.46 20 9.5V4h-5.5zm.33 9.41l-1.41 1.41 3.13 3.13L14.5 20H20v-5.5l-2.04 2.04-3.13-3.13z"/></svg>
      </button>
      <button @click="player.prev()" class="text-white">
        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/></svg>
      </button>
      <button @click="player.toggle()"
        class="w-16 h-16 bg-white rounded-full flex items-center justify-center hover:scale-105 transition-transform">
        <svg v-if="player.isPlaying" class="w-8 h-8 text-black" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
        <svg v-else class="w-8 h-8 text-black ml-1" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
      </button>
      <button @click="player.next()" class="text-white">
        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg>
      </button>
      <button @click="player.toggleRepeat()" class="transition-colors relative"
        :class="player.repeat !== 'off' ? 'text-spotify-green' : 'text-spotify-light'">
        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M7 7h10v3l4-4-4-4v3H5v6h2V7zm10 10H7v-3l-4 4 4 4v-3h12v-6h-2v4z"/></svg>
        <span v-if="player.repeat === 'one'" class="absolute -top-1 -right-1 text-[10px] font-bold">1</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { usePlayerStore } from '../stores/player'
import { api } from '../utils/api'

const player = usePlayerStore()
const showLyrics = ref(false)
const lyricsData = ref(null)
const lyricsLoading = ref(false)
const lyricsScroll = ref(null)
let lastFetchedSong = ''

// Parse synced lyrics [mm:ss.xx] format
const syncedLyrics = computed(() => {
  if (!lyricsData.value?.synced) return null
  const lines = lyricsData.value.synced.split('\n')
  return lines.map(line => {
    const match = line.match(/\[(\d+):(\d+\.\d+)\](.*)/)
    if (!match) return null
    return {
      time: parseInt(match[1]) * 60 + parseFloat(match[2]),
      text: match[3].trim()
    }
  }).filter(Boolean)
})

// Plain lyrics lines
const lyricsLines = computed(() => {
  if (!lyricsData.value?.text) return []
  return lyricsData.value.text.split('\n')
})

// Active synced lyric index based on current time
const activeLyricIndex = computed(() => {
  if (!syncedLyrics.value) return -1
  const time = player.currentTime
  let active = -1
  for (let i = 0; i < syncedLyrics.value.length; i++) {
    if (syncedLyrics.value[i].time <= time) {
      active = i
    } else break
  }
  // Map synced index to plain text index (approximate)
  return active
})

// Auto-scroll to active lyric
watch(activeLyricIndex, async (idx) => {
  if (idx < 0 || !lyricsScroll.value) return
  await nextTick()
  const container = lyricsScroll.value
  const lines = container.querySelectorAll('p')
  if (lines[idx]) {
    lines[idx].scrollIntoView({ behavior: 'smooth', block: 'center' })
  }
})

function toggleView() {
  showLyrics.value = !showLyrics.value
  if (showLyrics.value && !lyricsData.value) {
    fetchLyrics()
  }
}

async function fetchLyrics() {
  const song = player.currentSong
  if (!song) return

  const songKey = `${song.title}-${song.artist}`
  if (songKey === lastFetchedSong) return
  lastFetchedSong = songKey

  lyricsLoading.value = true
  lyricsData.value = null

  try {
    const params = new URLSearchParams({
      title: song.title || '',
      artist: song.artist || ''
    })
    const res = await api(`/bars/api/lyrics.php?${params}`)
    const data = await res.json()
    lyricsData.value = data.lyrics || null
  } catch {
    lyricsData.value = null
  }
  lyricsLoading.value = false
}

// Re-fetch lyrics when song changes
watch(() => player.currentSong?.id, () => {
  lastFetchedSong = ''
  lyricsData.value = null
  if (showLyrics.value) {
    fetchLyrics()
  }
})

function seekFromClick(e) {
  const rect = e.currentTarget.getBoundingClientRect()
  const percent = ((e.clientX - rect.left) / rect.width) * 100
  player.seek(Math.max(0, Math.min(100, percent)))
}
</script>
