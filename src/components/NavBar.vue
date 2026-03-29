<template>
  <header class="bg-spotify-dark/80 backdrop-blur-md px-4 py-3 flex items-center justify-between sticky top-0 z-30">
    <h1 class="text-lg font-bold text-white tracking-tight">Bar's Music</h1>
    <div class="flex items-center gap-3">
      <div class="flex items-center gap-1.5">
        <span v-if="auth.isAdmin" class="text-[10px] bg-spotify-green text-black font-bold px-1.5 py-0.5 rounded">ADMIN</span>
        <span class="text-xs text-spotify-light truncate max-w-[80px]">{{ auth.displayName }}</span>
      </div>
      <div class="flex items-center gap-1.5">
        <div class="w-2 h-2 rounded-full" :class="isOnline ? 'bg-spotify-green' : 'bg-red-400'"></div>
      </div>
      <button @click="handleLogout" class="text-spotify-light hover:text-white transition-colors" title="Logout">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
      </button>
    </div>
  </header>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const isOnline = ref(navigator.onLine)

function updateOnline() { isOnline.value = true }
function updateOffline() { isOnline.value = false }
function handleLogout() { auth.logout() }

onMounted(() => {
  window.addEventListener('online', updateOnline)
  window.addEventListener('offline', updateOffline)
})
onUnmounted(() => {
  window.removeEventListener('online', updateOnline)
  window.removeEventListener('offline', updateOffline)
})
</script>
