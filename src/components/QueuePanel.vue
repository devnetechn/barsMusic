<script setup>
import { usePlayerStore } from '../stores/player'

const player = usePlayerStore()

function close() {
  player.showQueue = false
}

function removeFromQueue(index) {
  player.removeFromQueue(index)
}

function clearQueue() {
  player.clearQueue()
}
</script>

<template>
  <Transition name="fade">
    <div
      v-if="player.showQueue"
      class="fixed inset-0 z-[75] flex items-end md:justify-end"
    >
        <!-- Backdrop -->
        <div
          class="absolute inset-0 bg-black/50"
          @click="close"
        />

        <!-- Panel -->
        <Transition name="slide">
          <div
            v-if="player.showQueue"
            class="relative w-full max-h-[70vh] md:w-80 md:max-h-full md:h-full bg-spotify-dark flex flex-col transition-transform duration-300"
          >
            <!-- Header -->
            <div class="flex items-center justify-between p-4 border-b border-white/10">
              <h2 class="text-lg font-bold text-white">Queue</h2>
              <button
                class="text-spotify-light hover:text-white transition-colors p-1"
                @click="close"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Scrollable content -->
            <div class="flex-1 overflow-y-auto p-4 space-y-6">
              <!-- Now Playing -->
              <div v-if="player.currentSong">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-spotify-light mb-3">Now Playing</h3>
                <div class="flex items-center gap-3 p-2 rounded-md bg-spotify-card border-l-2 border-spotify-green">
                  <img
                    :src="player.currentSong.cover || '/default-cover.png'"
                    :alt="player.currentSong.title"
                    class="w-12 h-12 rounded object-cover"
                  />
                  <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-white truncate">{{ player.currentSong.title || 'Unknown' }}</p>
                    <p class="text-xs text-spotify-light truncate">{{ player.currentSong.artist || 'Unknown' }}</p>
                  </div>
                </div>
              </div>

              <!-- Next Up -->
              <div v-if="player.upcomingQueue.length > 0">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-spotify-light mb-3">Next Up</h3>
                <ul class="space-y-1">
                  <li
                    v-for="(song, index) in player.upcomingQueue"
                    :key="index"
                    class="flex items-center gap-3 p-2 rounded-md hover:bg-spotify-card group transition-colors"
                  >
                    <img
                      :src="song.cover || '/default-cover.png'"
                      :alt="song.title"
                      class="w-10 h-10 rounded object-cover flex-shrink-0"
                    />
                    <div class="min-w-0 flex-1">
                      <p class="text-sm font-medium text-white truncate">{{ song.title || 'Unknown' }}</p>
                      <p class="text-xs text-spotify-light truncate">{{ song.artist || 'Unknown' }}</p>
                    </div>
                    <span
                      :class="song._source === 'queue'
                        ? 'bg-spotify-green/20 text-spotify-green'
                        : 'bg-white/10 text-spotify-light'"
                      class="text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded flex-shrink-0"
                    >
                      {{ song._source === 'queue' ? 'Queue' : 'Playlist' }}
                    </span>
                    <button
                      v-if="song._source === 'queue'"
                      class="text-spotify-lighter hover:text-white opacity-0 group-hover:opacity-100 transition-opacity p-1 flex-shrink-0"
                      @click="removeFromQueue(index)"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </li>
                </ul>
              </div>

              <!-- Empty state -->
              <div
                v-if="!player.currentSong && player.upcomingQueue.length === 0"
                class="flex flex-col items-center justify-center py-12 text-spotify-lighter"
              >
                <svg class="w-12 h-12 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                </svg>
                <p class="text-sm">Add songs to your queue</p>
              </div>
            </div>

            <!-- Clear Queue button -->
            <div v-if="player.userQueue.length > 0" class="p-4 border-t border-white/10">
              <button
                class="w-full py-2 px-4 rounded-full text-sm font-semibold text-white border border-spotify-lighter hover:border-white hover:scale-105 transition-all"
                @click="clearQueue"
              >
                Clear Queue
              </button>
            </div>
          </div>
        </Transition>
      </div>
  </Transition>
</template>

<style scoped>
/* Backdrop fade */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Panel slide - mobile: from bottom, desktop: from right */
.slide-enter-active,
.slide-leave-active {
  transition: transform 0.3s ease;
}

/* Mobile: slide up from bottom */
.slide-enter-from,
.slide-leave-to {
  transform: translateY(100%);
}

/* Desktop: slide in from right */
@media (min-width: 768px) {
  .slide-enter-from,
  .slide-leave-to {
    transform: translateX(100%);
  }
}
</style>
