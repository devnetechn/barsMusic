<template>
  <div class="p-4 md:p-6" v-if="artist">
    <!-- Header -->
    <div class="flex items-end gap-4 mb-6">
      <div class="w-32 h-32 md:w-48 md:h-48 rounded-full bg-spotify-card flex items-center justify-center shadow-lg flex-shrink-0 overflow-hidden">
        <img v-if="artist.cover" :src="artist.cover" class="w-full h-full object-cover" />
        <span v-else class="text-4xl font-bold text-spotify-light">{{ artist.artist?.charAt(0)?.toUpperCase() }}</span>
      </div>
      <div>
        <p class="text-xs text-spotify-light uppercase tracking-wider">Artist</p>
        <h1 class="text-2xl md:text-4xl font-bold text-white mt-1">{{ artist.artist }}</h1>
        <p class="text-sm text-spotify-light mt-2">{{ songs.length }} songs</p>
      </div>
    </div>

    <!-- Play All + Shuffle -->
    <div class="flex items-center gap-3 mb-6" v-if="songs.length">
      <button @click="playAll"
        class="w-12 h-12 bg-spotify-green rounded-full flex items-center justify-center hover:scale-105 transition-transform">
        <svg class="w-6 h-6 text-black ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
      </button>
      <button @click="shuffleAll"
        class="px-4 py-2 bg-spotify-card text-white text-sm font-semibold rounded-full border border-spotify-lighter/30 hover:bg-spotify-lighter/30 transition-colors">
        Shuffle
      </button>
    </div>

    <!-- Songs list -->
    <div class="space-y-1">
      <div v-for="(song, index) in songs" :key="song.id"
        class="flex items-center gap-3 p-2 rounded-md hover:bg-spotify-card transition-colors cursor-pointer group"
        @click="playSong(song, index)">
        <span class="w-6 text-center text-sm text-spotify-light">{{ index + 1 }}</span>
        <div class="w-10 h-10 bg-spotify-lighter rounded flex-shrink-0 flex items-center justify-center overflow-hidden">
          <img v-if="song.cover" :src="song.cover" class="w-full h-full object-cover" />
          <svg v-else class="w-5 h-5 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm text-white truncate"
            :class="{ '!text-spotify-green': player.currentSong?.id === song.id }">
            {{ song.title }}
          </p>
          <p class="text-xs text-spotify-light truncate">{{ song.album || 'Single' }}</p>
        </div>
        <LikeButton :song="song" size="w-4 h-4" />
      </div>
    </div>

    <div v-if="!songs.length" class="text-center py-16">
      <p class="text-spotify-light">No songs found</p>
    </div>
  </div>

  <!-- Loading -->
  <div v-else class="p-4 flex items-center justify-center py-20">
    <div class="w-8 h-8 border-2 border-spotify-green border-t-transparent rounded-full animate-spin"></div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { usePlayerStore } from '../stores/player'
import { api, musicUrl } from '../utils/api'
import LikeButton from '../components/LikeButton.vue'

const route = useRoute()
const player = usePlayerStore()
const artist = ref(null)
const songs = ref([])

function playSong(song, index) {
  player.playSong(song, songs.value, index)
}

function playAll() {
  if (songs.value.length) {
    player.playSong(songs.value[0], songs.value, 0)
  }
}

function shuffleAll() {
  if (!songs.value.length) return
  const shuffled = [...songs.value]
  for (let i = shuffled.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]]
  }
  player.playSong(shuffled[0], shuffled, 0)
}

onMounted(async () => {
  const name = decodeURIComponent(route.params.name)
  try {
    const res = await api(`/bars/api/artists.php?artist=${encodeURIComponent(name)}`)
    const data = await res.json()
    artist.value = data
    songs.value = (data.songs || []).map(s => ({
      ...s,
      url: musicUrl(`/bars/music/${s.filename}`)
    }))
  } catch {}
})
</script>
