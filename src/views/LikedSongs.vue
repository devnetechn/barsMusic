<template>
  <div class="p-4 md:p-6">
    <!-- Header -->
    <div class="flex items-end gap-4 mb-6">
      <div class="w-32 h-32 md:w-48 md:h-48 bg-gradient-to-br from-purple-700 to-blue-400 rounded-lg flex items-center justify-center shadow-lg flex-shrink-0">
        <svg class="w-16 h-16 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
      </div>
      <div>
        <p class="text-xs text-spotify-light uppercase tracking-wider">Playlist</p>
        <h1 class="text-2xl md:text-4xl font-bold text-white mt-1">Liked Songs</h1>
        <p class="text-sm text-spotify-light mt-2">{{ likes.likedSongs.length }} songs</p>
      </div>
    </div>

    <!-- Play All -->
    <div class="flex items-center gap-3 mb-6" v-if="likes.likedSongs.length">
      <button @click="playAll"
        class="w-12 h-12 bg-spotify-green rounded-full flex items-center justify-center hover:scale-105 transition-transform">
        <svg class="w-6 h-6 text-black ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
      </button>
    </div>

    <!-- Songs list -->
    <div class="space-y-1">
      <div v-for="(song, index) in likes.likedSongs" :key="song.song_id"
        class="flex items-center gap-3 p-2 rounded-md hover:bg-spotify-card transition-colors cursor-pointer group"
        @click="playSong(song, index)">
        <span class="w-6 text-center text-sm text-spotify-light">{{ index + 1 }}</span>
        <div class="w-10 h-10 bg-spotify-lighter rounded flex-shrink-0 flex items-center justify-center overflow-hidden">
          <img v-if="song.cover" :src="song.cover" class="w-full h-full object-cover" />
          <svg v-else class="w-5 h-5 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm text-white truncate"
            :class="{ '!text-spotify-green': player.currentSong?.id === song.song_id }">
            {{ song.title }}
          </p>
          <p class="text-xs text-spotify-light truncate">{{ song.artist || 'Unknown Artist' }}</p>
        </div>
        <LikeButton :song="{ id: song.song_id, ...song }" size="w-5 h-5" />
      </div>
    </div>

    <div v-if="!likes.likedSongs.length" class="text-center py-16">
      <svg class="w-16 h-16 text-spotify-lighter mx-auto mb-4" fill="currentColor" viewBox="0 0 24 24">
        <path d="M16.5 3c-1.74 0-3.41.81-4.5 2.09C10.91 3.81 9.24 3 7.5 3 4.42 3 2 5.42 2 8.5c0 3.78 3.4 6.86 8.55 11.54L12 21.35l1.45-1.32C18.6 15.36 22 12.28 22 8.5 22 5.42 19.58 3 16.5 3z"/>
      </svg>
      <p class="text-spotify-light">Songs you like will appear here</p>
      <router-link to="/search" class="text-spotify-green text-sm hover:underline mt-2 inline-block">Find songs</router-link>
    </div>
  </div>
</template>

<script setup>
import { usePlayerStore } from '../stores/player'
import { useLikesStore } from '../stores/likes'
import { api, musicUrl } from '../utils/api'
import LikeButton from '../components/LikeButton.vue'

const player = usePlayerStore()
const likes = useLikesStore()

async function buildSongObj(song) {
  const obj = {
    id: song.song_id,
    title: song.title,
    artist: song.artist,
    cover: song.cover,
    video_id: song.video_id,
    filename: song.filename || null
  }

  // 1. Has filename = play from server (fast)
  if (song.filename) {
    obj.url = musicUrl(`/bars/music/${song.filename}`)
    return obj
  }

  // 2. YouTube song = get stream URL
  if (song.video_id) {
    try {
      const res = await api(`/bars/api/yt-stream.php?id=${song.video_id}`)
      const data = await res.json()
      if (data.success) obj.url = data.url
    } catch {}
  }

  return obj
}

async function playSong(song, index) {
  const songObj = await buildSongObj(song)
  if (!songObj.url) return

  // Build queue with basic info (URLs resolved on play)
  const allSongs = likes.likedSongs.map(s => ({
    id: s.song_id,
    title: s.title,
    artist: s.artist,
    cover: s.cover,
    video_id: s.video_id,
    filename: s.filename || null,
    url: s.filename ? musicUrl(`/bars/music/${s.filename}`) : null
  }))
  // Set resolved URL for current song
  allSongs[index] = songObj

  player.playSong(songObj, allSongs, index)
}

function playAll() {
  if (likes.likedSongs.length) {
    playSong(likes.likedSongs[0], 0)
  }
}
</script>
