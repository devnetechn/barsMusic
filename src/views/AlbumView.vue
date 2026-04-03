<template>
  <div class="p-4 md:p-6" v-if="album">
    <!-- Header -->
    <div class="flex items-end gap-4 mb-6">
      <div class="w-32 h-32 md:w-48 md:h-48 bg-spotify-card rounded-lg flex items-center justify-center shadow-lg flex-shrink-0 overflow-hidden">
        <img v-if="album.cover" :src="album.cover" class="w-full h-full object-cover" />
        <svg v-else class="w-16 h-16 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 14.5c-2.49 0-4.5-2.01-4.5-4.5S9.51 7.5 12 7.5s4.5 2.01 4.5 4.5-2.01 4.5-4.5 4.5zm0-5.5c-.55 0-1 .45-1 1s.45 1 1 1 1-.45 1-1-.45-1-1-1z"/></svg>
      </div>
      <div>
        <p class="text-xs text-spotify-light uppercase tracking-wider">Album</p>
        <h1 class="text-2xl md:text-4xl font-bold text-white mt-1">{{ album.name }}</h1>
        <router-link :to="`/artist/${encodeURIComponent(album.artist)}`" class="text-sm text-spotify-light mt-1 hover:underline">{{ album.artist }}</router-link>
        <p class="text-xs text-spotify-light mt-1">{{ songs.length }} songs</p>
      </div>
    </div>

    <!-- Play All -->
    <div class="flex items-center gap-3 mb-6" v-if="songs.length">
      <button @click="playAll" class="w-12 h-12 bg-spotify-green rounded-full flex items-center justify-center hover:scale-105 transition-transform">
        <svg class="w-6 h-6 text-black ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
      </button>
      <button @click="shuffleAll" class="px-4 py-2 bg-spotify-card text-white text-sm font-semibold rounded-full border border-spotify-lighter/30 hover:bg-spotify-lighter/30">
        Shuffle
      </button>
    </div>

    <!-- Songs -->
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
          <p class="text-sm text-white truncate" :class="{ '!text-spotify-green': player.currentSong?.id === song.id }">{{ song.title }}</p>
          <p class="text-xs text-spotify-light truncate">{{ song.artist }}</p>
        </div>
        <LikeButton :song="song" size="w-4 h-4" />
      </div>
    </div>
  </div>
  <div v-else class="p-4 flex items-center justify-center py-20">
    <div class="w-8 h-8 border-2 border-spotify-green border-t-transparent rounded-full animate-spin"></div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { usePlayerStore } from '../stores/player'
import { api } from '../utils/api'
import LikeButton from '../components/LikeButton.vue'

const route = useRoute()
const player = usePlayerStore()
const album = ref(null)
const songs = ref([])

function playSong(song, index) {
  player.playSong(song, songs.value, index)
}

function playAll() {
  if (songs.value.length) player.playSong(songs.value[0], songs.value, 0)
}

function shuffleAll() {
  const shuffled = [...songs.value]
  for (let i = shuffled.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]]
  }
  player.playSong(shuffled[0], shuffled, 0)
}

onMounted(async () => {
  try {
    const res = await api(`/bars/api/albums.php?id=${route.params.id}`)
    const data = await res.json()
    album.value = data.album
    songs.value = (data.album?.songs || []).map(s => ({ ...s, url: `/bars/music/${s.filename}` }))
  } catch {}
})
</script>
