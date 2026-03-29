<template>
  <aside class="w-64 bg-black flex flex-col gap-2 p-2">
    <!-- User info -->
    <div class="bg-spotify-dark rounded-lg px-4 py-3 flex items-center justify-between">
      <div class="flex items-center gap-2 min-w-0">
        <div class="w-8 h-8 bg-spotify-card rounded-full flex items-center justify-center flex-shrink-0 text-sm font-bold text-white">
          {{ auth.displayName?.charAt(0)?.toUpperCase() || '?' }}
        </div>
        <div class="min-w-0">
          <p class="text-sm text-white font-semibold truncate">{{ auth.displayName }}</p>
          <p v-if="auth.isAdmin" class="text-[10px] text-spotify-green font-bold">ADMIN</p>
        </div>
      </div>
      <button @click="auth.logout()" class="text-spotify-light hover:text-white transition-colors flex-shrink-0" title="Logout">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
      </button>
    </div>

    <!-- Navigation -->
    <div class="bg-spotify-dark rounded-lg p-4">
      <router-link to="/" class="flex items-center gap-4 py-2 px-3 rounded-md transition-colors"
        :class="$route.name === 'home' ? 'text-white bg-spotify-card' : 'text-spotify-light hover:text-white'">
        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3L4 9v12h5v-7h6v7h5V9z"/></svg>
        <span class="font-semibold">Home</span>
      </router-link>
      <router-link to="/search" class="flex items-center gap-4 py-2 px-3 rounded-md transition-colors"
        :class="$route.name === 'search' ? 'text-white bg-spotify-card' : 'text-spotify-light hover:text-white'">
        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0016 9.5 6.5 6.5 0 109.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
        <span class="font-semibold">Search</span>
      </router-link>
      <router-link v-if="auth.isAdmin" to="/upload" class="flex items-center gap-4 py-2 px-3 rounded-md transition-colors"
        :class="$route.name === 'upload' ? 'text-white bg-spotify-card' : 'text-spotify-light hover:text-white'">
        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16h6v-6h4l-7-7-7 7h4zm-4 2h14v2H5z"/></svg>
        <span class="font-semibold">Upload</span>
      </router-link>
    </div>

    <!-- Library -->
    <div class="bg-spotify-dark rounded-lg p-4 flex-1 overflow-y-auto">
      <div class="flex items-center justify-between mb-4">
        <router-link to="/library" class="flex items-center gap-3 text-spotify-light hover:text-white transition-colors">
          <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg>
          <span class="font-semibold">Your Library</span>
        </router-link>
        <router-link to="/upload"
          class="w-8 h-8 flex items-center justify-center rounded-full text-spotify-light hover:text-white hover:bg-spotify-card transition-colors">
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
        </router-link>
      </div>

      <div class="space-y-1">
        <router-link v-for="playlist in playlists" :key="playlist.id"
          :to="`/playlist/${playlist.id}`"
          class="flex items-center gap-3 p-2 rounded-md hover:bg-spotify-card transition-colors group">
          <div class="w-12 h-12 bg-spotify-card rounded flex items-center justify-center text-spotify-light">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
          </div>
          <div class="min-w-0">
            <p class="text-sm text-white truncate">{{ playlist.name }}</p>
            <p class="text-xs text-spotify-light truncate">{{ playlist.songIds?.length || 0 }} songs</p>
          </div>
        </router-link>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getAllPlaylists } from '../utils/db'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const playlists = ref([])

onMounted(async () => {
  playlists.value = await getAllPlaylists()
})
</script>
