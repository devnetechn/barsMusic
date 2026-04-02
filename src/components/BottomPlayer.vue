<template>
  <div class="flex-shrink-0 z-50">
    <!-- Mini player (mobile) -->
    <div class="md:hidden bg-spotify-card border-t border-spotify-lighter/20 overflow-hidden" ref="miniPlayer">
      <div class="px-3 py-2 transition-transform duration-200"
           :style="{ transform: `translateX(${swipeX}px)`, opacity: swipeOpacity }"
           @touchstart="onTouchStart" @touchmove="onTouchMove" @touchend="onTouchEnd"
           @click="openNowPlaying">
        <div class="flex items-center gap-2">
          <div class="w-10 h-10 bg-spotify-lighter rounded flex-shrink-0 flex items-center justify-center overflow-hidden">
            <img v-if="player.currentSong?.cover" :src="player.currentSong.cover" class="w-full h-full object-cover" />
            <svg v-else class="w-5 h-5 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm text-white truncate">{{ player.currentSong?.title || 'Unknown' }}</p>
            <p class="text-xs text-spotify-light truncate">{{ player.currentSong?.artist || 'Unknown' }}</p>
          </div>
          <!-- Like button -->
          <LikeButton :song="player.currentSong" size="w-4 h-4" />
          <!-- Queue button -->
          <button @click.stop="player.toggleQueue()" class="w-8 h-8 flex items-center justify-center text-spotify-light hover:text-white">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M15 6H3v2h12V6zm0 4H3v2h12v-2zM3 16h8v-2H3v2zM17 6v8.18c-.31-.11-.65-.18-1-.18-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3V8h3V6h-5z"/></svg>
          </button>
          <button @click.stop="player.toggle()" class="w-8 h-8 flex items-center justify-center text-white">
            <svg v-if="player.isPlaying" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
            <svg v-else class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
          </button>
          <button @click.stop="dismiss()" class="w-6 h-6 flex items-center justify-center text-spotify-light">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
          </button>
        </div>
        <div class="h-0.5 bg-spotify-lighter/30 mt-2 rounded-full overflow-hidden">
          <div class="h-full bg-spotify-green rounded-full transition-all duration-300"
               :style="{ width: player.progress + '%' }"></div>
        </div>
      </div>
    </div>

    <!-- Desktop player bar -->
    <div class="hidden md:flex bg-spotify-dark border-t border-spotify-lighter/20 px-4 py-2 items-center gap-4">
      <div class="flex items-center gap-3 w-72">
        <div class="w-14 h-14 bg-spotify-card rounded flex-shrink-0 flex items-center justify-center overflow-hidden">
          <img v-if="player.currentSong?.cover" :src="player.currentSong.cover" class="w-full h-full object-cover" />
          <svg v-else class="w-7 h-7 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
        </div>
        <div class="min-w-0">
          <p class="text-sm text-white truncate">{{ player.currentSong?.title || 'Unknown' }}</p>
          <p class="text-xs text-spotify-light truncate">{{ player.currentSong?.artist || 'Unknown' }}</p>
        </div>
      </div>
      <div class="flex-1 flex flex-col items-center gap-1 max-w-2xl">
        <div class="flex items-center gap-4">
          <button @click="player.toggleShuffle()" class="text-spotify-light hover:text-white" :class="{ '!text-spotify-green': player.shuffle }">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M10.59 9.17L5.41 4 4 5.41l5.17 5.17 1.42-1.41zM14.5 4l2.04 2.04L4 18.59 5.41 20 17.96 7.46 20 9.5V4h-5.5zm.33 9.41l-1.41 1.41 3.13 3.13L14.5 20H20v-5.5l-2.04 2.04-3.13-3.13z"/></svg>
          </button>
          <button @click="player.prev()" class="text-spotify-light hover:text-white">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/></svg>
          </button>
          <button @click="player.toggle()" class="w-8 h-8 bg-white rounded-full flex items-center justify-center hover:scale-105 transition-transform">
            <svg v-if="player.isPlaying" class="w-5 h-5 text-black" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
            <svg v-else class="w-5 h-5 text-black ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
          </button>
          <button @click="player.next()" class="text-spotify-light hover:text-white">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg>
          </button>
          <button @click="player.toggleRepeat()" class="text-spotify-light hover:text-white relative" :class="{ '!text-spotify-green': player.repeat !== 'off' }">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M7 7h10v3l4-4-4-4v3H5v6h2V7zm10 10H7v-3l-4 4 4 4v-3h12v-6h-2v4z"/></svg>
            <span v-if="player.repeat === 'one'" class="absolute -top-1 -right-1 text-[8px] font-bold text-spotify-green">1</span>
          </button>
          <button @click="showLyrics = true" class="text-spotify-light hover:text-spotify-green transition-colors" title="Lyrics">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 9H8v-1h5v1zm3-2H8V8h8v1zm-8-2V5h5.5L16 7.5V7H8z"/></svg>
          </button>
          <!-- Queue button (desktop) -->
          <button @click="player.toggleQueue()" class="text-spotify-light hover:text-spotify-green transition-colors" :class="{ '!text-spotify-green': player.showQueue }" title="Queue">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M15 6H3v2h12V6zm0 4H3v2h12v-2zM3 16h8v-2H3v2zM17 6v8.18c-.31-.11-.65-.18-1-.18-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3V8h3V6h-5z"/></svg>
          </button>
        </div>
        <div class="w-full flex items-center gap-2">
          <span class="text-xs text-spotify-light w-10 text-right">{{ player.formattedCurrentTime }}</span>
          <div class="flex-1 h-1 bg-spotify-lighter/30 rounded-full group cursor-pointer" @click="seekFromClick($event)">
            <div class="h-full bg-white group-hover:bg-spotify-green rounded-full relative" :style="{ width: player.progress + '%' }">
              <div class="absolute right-0 top-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
            </div>
          </div>
          <span class="text-xs text-spotify-light w-10">{{ player.formattedDuration }}</span>
        </div>
      </div>
      <div class="flex items-center gap-2 w-48 justify-end">
        <button @click="player.toggleMute()" class="text-spotify-light hover:text-white">
          <svg v-if="player.isMuted || player.volume === 0" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg>
          <svg v-else class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
        </button>
        <div class="w-24 h-1 bg-spotify-lighter/30 rounded-full group cursor-pointer" @click="volumeFromClick($event)">
          <div class="h-full bg-white group-hover:bg-spotify-green rounded-full" :style="{ width: (player.isMuted ? 0 : player.volume * 100) + '%' }"></div>
        </div>
      </div>
    </div>

    <!-- FULL-SCREEN NOW PLAYING OVERLAY -->
    <Transition name="full-player">
      <div v-if="showFullPlayer" class="fixed inset-0 bg-gradient-to-b from-spotify-card to-spotify-black z-[70] flex flex-col"
        style="padding-top: calc(env(safe-area-inset-top) + 1rem); padding-bottom: calc(env(safe-area-inset-bottom) + 1rem)"
        @touchstart="onFullPlayerTouchStart" @touchmove="onFullPlayerTouchMove" @touchend="onFullPlayerTouchEnd"
        :style="fullPlayerDragStyle">

        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 flex-shrink-0">
          <button @click="showFullPlayer = false" class="text-white p-2">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M7.41 15.41L12 10.83l4.59 4.58L18 14l-6-6-6 6z"/></svg>
          </button>
          <p class="text-xs text-spotify-light uppercase tracking-wider">Now Playing</p>
          <!-- Toggle Cover/Lyrics -->
          <button @click="toggleFullPlayerView"
            class="px-3 py-1.5 rounded-full text-xs font-semibold transition-colors"
            :class="fullPlayerShowLyrics ? 'bg-spotify-green text-black' : 'bg-spotify-card text-white'">
            {{ fullPlayerShowLyrics ? 'Cover' : 'Lyrics' }}
          </button>
        </div>

        <!-- Content area: Cover or Lyrics -->
        <div class="flex-1 min-h-0 flex items-center justify-center px-6">
          <!-- Album Cover -->
          <div v-if="!fullPlayerShowLyrics" class="w-full max-w-sm aspect-square bg-spotify-card rounded-lg shadow-2xl flex items-center justify-center overflow-hidden">
            <img v-if="player.currentSong?.cover" :src="player.currentSong.cover" class="w-full h-full object-cover" />
            <svg v-else class="w-24 h-24 text-spotify-lighter" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
          </div>

          <!-- Lyrics in full player -->
          <div v-else class="w-full h-full flex flex-col">
            <div v-if="lyricsLoading" class="flex-1 flex items-center justify-center">
              <div class="w-8 h-8 border-2 border-spotify-green border-t-transparent rounded-full animate-spin"></div>
            </div>

            <div v-else-if="lyricsText" class="flex-1 overflow-y-auto overscroll-contain" ref="fullPlayerLyricsScroll">
              <div class="py-4 space-y-3">
                <p v-for="(line, i) in lyricsLines" :key="i"
                  class="text-center font-semibold px-4"
                  :style="{ transition: 'all 0.4s ease-out', transform: activeLine === i ? 'scale(1.08)' : 'scale(1)', opacity: activeLine === i ? 1 : (line.trim() ? 0.4 : 0.1) }"
                  :class="activeLine === i ? 'text-white text-xl' : 'text-spotify-light text-base'">
                  {{ line.trim() || '\u00A0' }}
                </p>
                <div class="h-20"></div>
              </div>
            </div>

            <div v-else class="flex-1 flex flex-col items-center justify-center gap-3">
              <svg class="w-12 h-12 text-spotify-lighter" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 9H8v-1h5v1zm3-2H8V8h8v1zm0-3H8V5h5.5L16 7.5V6z"/></svg>
              <p class="text-spotify-light text-sm">No lyrics found</p>
              <p class="text-spotify-lighter text-xs">{{ player.currentSong?.title }}</p>
            </div>
          </div>
        </div>

        <!-- Song Info + Three-dot menu -->
        <div class="mt-4 mb-3 px-6 flex-shrink-0 flex items-start justify-between">
          <div class="min-w-0 flex-1">
            <h2 class="text-xl font-bold text-white truncate">{{ player.currentSong?.title || 'Unknown' }}</h2>
            <p class="text-sm text-spotify-light truncate">{{ player.currentSong?.artist || 'Unknown Artist' }}</p>
          </div>
          <LikeButton :song="player.currentSong" size="w-6 h-6" />
          <!-- Three-dot menu -->
          <div class="relative flex-shrink-0 ml-1">
            <button @click="showSongMenu = !showSongMenu" class="text-spotify-light hover:text-white p-1">
              <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
            </button>
            <!-- Context menu dropdown -->
            <div v-if="showSongMenu" class="absolute right-0 bottom-full mb-2 w-56 bg-spotify-card rounded-lg shadow-xl border border-spotify-lighter/20 py-1 z-10">
              <button @click="handleToggleLike" class="w-full text-left px-4 py-2.5 text-sm text-white hover:bg-spotify-lighter/20 flex items-center gap-3">
                <svg v-if="likesStore.isLiked(player.currentSong?.id)" class="w-4 h-4 text-spotify-green" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                <svg v-else class="w-4 h-4 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M16.5 3c-1.74 0-3.41.81-4.5 2.09C10.91 3.81 9.24 3 7.5 3 4.42 3 2 5.42 2 8.5c0 3.78 3.4 6.86 8.55 11.54L12 21.35l1.45-1.32C18.6 15.36 22 12.28 22 8.5 22 5.42 19.58 3 16.5 3zm-4.4 15.55l-.1.1-.1-.1C7.14 14.24 4 11.39 4 8.5 4 6.5 5.5 5 7.5 5c1.54 0 3.04.99 3.57 2.36h1.87C13.46 5.99 14.96 5 16.5 5c2 0 3.5 1.5 3.5 3.5 0 2.89-3.14 5.74-7.9 10.05z"/></svg>
                {{ likesStore.isLiked(player.currentSong?.id) ? 'Remove from Liked' : 'Add to Liked Songs' }}
              </button>
              <button @click="handlePlayNext" class="w-full text-left px-4 py-2.5 text-sm text-white hover:bg-spotify-lighter/20 flex items-center gap-3">
                <svg class="w-4 h-4 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg>
                Play Next
              </button>
              <button @click="handleAddToQueue" class="w-full text-left px-4 py-2.5 text-sm text-white hover:bg-spotify-lighter/20 flex items-center gap-3">
                <svg class="w-4 h-4 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M15 6H3v2h12V6zm0 4H3v2h12v-2zM3 16h8v-2H3v2zM17 6v8.18c-.31-.11-.65-.18-1-.18-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3V8h3V6h-5z"/></svg>
                Add to Queue
              </button>
            </div>
          </div>
        </div>

        <!-- Progress -->
        <div class="mb-3 px-6 flex-shrink-0">
          <div class="h-1 bg-spotify-lighter/30 rounded-full cursor-pointer group" @click="seekFromClick($event)">
            <div class="h-full bg-white group-hover:bg-spotify-green rounded-full relative" :style="{ width: player.progress + '%' }">
              <div class="absolute right-0 top-1/2 -translate-y-1/2 w-4 h-4 bg-white rounded-full shadow"></div>
            </div>
          </div>
          <div class="flex justify-between mt-1">
            <span class="text-xs text-spotify-light">{{ player.formattedCurrentTime }}</span>
            <span class="text-xs text-spotify-light">{{ player.formattedDuration }}</span>
          </div>
        </div>

        <!-- Controls -->
        <div class="flex items-center justify-between mb-2 px-6 flex-shrink-0">
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

        <!-- Bottom action buttons (Lyrics + Queue) -->
        <div class="flex items-center justify-center gap-6 px-6 pb-2 flex-shrink-0">
          <button @click="showLyrics = true; showFullPlayer = false" class="text-spotify-light hover:text-spotify-green transition-colors flex items-center gap-1.5" title="Lyrics">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 9H8v-1h5v1zm3-2H8V8h8v1zm-8-2V5h5.5L16 7.5V7H8z"/></svg>
            <span class="text-xs font-semibold">Lyrics</span>
          </button>
          <button @click="player.toggleQueue()" class="text-spotify-light hover:text-spotify-green transition-colors flex items-center gap-1.5" :class="{ '!text-spotify-green': player.showQueue }" title="Queue">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M15 6H3v2h12V6zm0 4H3v2h12v-2zM3 16h8v-2H3v2zM17 6v8.18c-.31-.11-.65-.18-1-.18-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3V8h3V6h-5z"/></svg>
            <span class="text-xs font-semibold">Queue</span>
          </button>
        </div>
      </div>
    </Transition>

    <!-- LYRICS OVERLAY (fullscreen) -->
    <div v-if="showLyrics" class="fixed inset-0 bg-gradient-to-b from-spotify-card to-spotify-black z-[80] flex flex-col"
      style="padding-top: env(safe-area-inset-top); padding-bottom: env(safe-area-inset-bottom)">
      <!-- Header -->
      <div class="flex items-center justify-between px-4 py-3 flex-shrink-0">
        <button @click="showLyrics = false" class="text-white p-1">
          <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M7.41 15.41L12 10.83l4.59 4.58L18 14l-6-6-6 6z"/></svg>
        </button>
        <div class="text-center">
          <p class="text-xs text-spotify-light uppercase tracking-wider">Lyrics</p>
        </div>
        <div class="w-6"></div>
      </div>

      <!-- Song info -->
      <div class="px-6 mb-4 flex-shrink-0">
        <h2 class="text-lg font-bold text-white truncate">{{ player.currentSong?.title || 'Unknown' }}</h2>
        <p class="text-sm text-spotify-light truncate">{{ player.currentSong?.artist || 'Unknown Artist' }}</p>
      </div>

      <!-- Lyrics content -->
      <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain px-6" ref="lyricsScrollEl">
        <div v-if="lyricsLoading" class="flex items-center justify-center h-full">
          <div class="w-8 h-8 border-2 border-spotify-green border-t-transparent rounded-full animate-spin"></div>
        </div>

        <div v-else-if="lyricsText" class="py-4 space-y-3">
          <p v-for="(line, i) in lyricsLines" :key="i"
            class="text-center font-semibold px-2"
            :style="{ transition: 'all 0.4s ease-out', transform: activeLine === i ? 'scale(1.08)' : 'scale(1)', opacity: activeLine === i ? 1 : (line.trim() ? 0.4 : 0.1) }"
            :class="activeLine === i ? 'text-white text-xl' : 'text-spotify-light text-base'">
            {{ line.trim() || '\u00A0' }}
          </p>
          <div class="h-32"></div>
        </div>

        <div v-else class="flex flex-col items-center justify-center h-full gap-3">
          <svg class="w-16 h-16 text-spotify-lighter" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
          <p class="text-spotify-light">No lyrics found</p>
        </div>
      </div>

      <!-- Mini controls at bottom -->
      <div class="flex-shrink-0 px-4 py-3 bg-spotify-dark/80 backdrop-blur">
        <div class="h-1 bg-spotify-lighter/30 rounded-full mb-3 cursor-pointer" @click="seekFromClick($event)">
          <div class="h-full bg-spotify-green rounded-full" :style="{ width: player.progress + '%' }"></div>
        </div>
        <div class="flex items-center justify-center gap-6">
          <button @click="player.prev()" class="text-white">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/></svg>
          </button>
          <button @click="player.toggle()" class="w-12 h-12 bg-white rounded-full flex items-center justify-center">
            <svg v-if="player.isPlaying" class="w-6 h-6 text-black" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
            <svg v-else class="w-6 h-6 text-black ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
          </button>
          <button @click="player.next()" class="text-white">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Queue Panel -->
    <QueuePanel />
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, defineAsyncComponent } from 'vue'
import { useRoute } from 'vue-router'
import { usePlayerStore } from '../stores/player'
import { api } from '../utils/api'
import { useLikesStore } from '../stores/likes'
import LikeButton from './LikeButton.vue'

const QueuePanel = defineAsyncComponent(() => import('./QueuePanel.vue'))

const player = usePlayerStore()
const likesStore = useLikesStore()
const route = useRoute()



// Full player overlay state
const showFullPlayer = ref(false)
const fullPlayerShowLyrics = ref(false)
const showSongMenu = ref(false)
const fullPlayerLyricsScroll = ref(null)

// Drag-down-to-dismiss for full player
let fpTouchStartY = 0
let fpTouchStartX = 0
let fpIsDragging = false
const fpDragY = ref(0)

const fullPlayerDragStyle = computed(() => {
  if (fpDragY.value > 0) {
    return {
      transform: `translateY(${fpDragY.value}px)`,
      transition: 'none'
    }
  }
  return {}
})

function onFullPlayerTouchStart(e) {
  fpTouchStartY = e.touches[0].clientY
  fpTouchStartX = e.touches[0].clientX
  fpIsDragging = false
}

function onFullPlayerTouchMove(e) {
  const dy = e.touches[0].clientY - fpTouchStartY
  const dx = e.touches[0].clientX - fpTouchStartX
  if (!fpIsDragging && dy > 10 && Math.abs(dy) > Math.abs(dx)) {
    fpIsDragging = true
  }
  if (fpIsDragging && dy > 0) {
    fpDragY.value = dy
  }
}

function onFullPlayerTouchEnd() {
  if (fpDragY.value > 150) {
    showFullPlayer.value = false
  }
  fpDragY.value = 0
  fpIsDragging = false
}

function toggleFullPlayerView() {
  fullPlayerShowLyrics.value = !fullPlayerShowLyrics.value
  if (fullPlayerShowLyrics.value && !lyricsText.value) {
    fetchLyrics()
  }
}

// Close song menu when clicking outside
watch(showSongMenu, (val) => {
  if (val) {
    const handler = () => {
      showSongMenu.value = false
      document.removeEventListener('click', handler)
    }
    setTimeout(() => document.addEventListener('click', handler), 0)
  }
})

// Close full player when song is dismissed
watch(() => player.currentSong, (val) => {
  if (!val) {
    showFullPlayer.value = false
  }
})

function handlePlayNext() {
  if (player.currentSong) {
    player.playNext({ ...player.currentSong })
  }
  showSongMenu.value = false
}

function handleAddToQueue() {
  if (player.currentSong) {
    player.addToQueue({ ...player.currentSong })
  }
  showSongMenu.value = false
}

function handleToggleLike() {
  if (player.currentSong) {
    likesStore.toggleLike(player.currentSong)
  }
  showSongMenu.value = false
}

// Lyrics state
const showLyrics = ref(false)
const lyricsText = ref(null)
const lyricsLoading = ref(false)
const lyricsScrollEl = ref(null)
const syncedData = ref(null)
let lastLyricsSong = ''

const lyricsLines = computed(() => {
  // Use synced lyrics if available (more accurate)
  if (syncedData.value && syncedData.value.length > 0) {
    return syncedData.value.map(s => s.text)
  }
  if (!lyricsText.value) return []
  return lyricsText.value.split('\n')
})

// Active synced lyric
const activeLine = computed(() => {
  if (!syncedData.value) return -1
  const time = player.currentTime
  let active = -1
  for (let i = 0; i < syncedData.value.length; i++) {
    if (syncedData.value[i].time <= time) active = i
    else break
  }
  return active
})

// Auto-scroll lyrics (both overlays)
watch(activeLine, async (idx) => {
  if (idx < 0) return
  await nextTick()
  // Lyrics overlay scroll
  if (lyricsScrollEl.value) {
    const lines = lyricsScrollEl.value.querySelectorAll('p')
    if (lines[idx]) {
      lines[idx].scrollIntoView({ behavior: 'smooth', block: 'center' })
    }
  }
  // Full player lyrics scroll
  if (fullPlayerLyricsScroll.value) {
    const lines = fullPlayerLyricsScroll.value.querySelectorAll('p')
    if (lines[idx]) {
      lines[idx].scrollIntoView({ behavior: 'smooth', block: 'center' })
    }
  }
})

// Fetch lyrics when overlay opens
watch(showLyrics, (val) => {
  if (val) fetchLyrics()
})

// Also fetch lyrics when full player lyrics view is active
watch(fullPlayerShowLyrics, (val) => {
  if (val) fetchLyrics()
})

// Re-fetch when song changes while lyrics open
watch(() => player.currentSong?.id, () => {
  lastLyricsSong = ''
  lyricsText.value = null
  syncedData.value = null
  if (showLyrics.value || fullPlayerShowLyrics.value) fetchLyrics()
})

async function fetchLyrics() {
  const song = player.currentSong
  if (!song) return
  const key = `${song.title}-${song.artist}`
  if (key === lastLyricsSong && lyricsText.value) return
  lastLyricsSong = key

  lyricsLoading.value = true
  lyricsText.value = null
  syncedData.value = null

  try {
    const params = new URLSearchParams({ title: song.title || '', artist: song.artist || '' })
    const res = await api(`/bars/api/lyrics.php?${params}`)
    const data = await res.json()
    if (data.lyrics) {
      lyricsText.value = data.lyrics.text || null
      // Parse synced lyrics
      if (data.lyrics.synced) {
        syncedData.value = data.lyrics.synced.split('\n').map(line => {
          const m = line.match(/\[(\d+):(\d+\.\d+)\](.*)/)
          if (!m) return null
          return { time: parseInt(m[1]) * 60 + parseFloat(m[2]), text: m[3].trim() }
        }).filter(Boolean)
      }
    }
  } catch { /* ignore */ }
  lyricsLoading.value = false
}

// Swipe to dismiss mini player
const swipeX = ref(0)
const swipeOpacity = computed(() => Math.max(0, 1 - Math.abs(swipeX.value) / 200))
let touchStartX = 0, touchStartY = 0, isSwiping = false

function onTouchStart(e) { touchStartX = e.touches[0].clientX; touchStartY = e.touches[0].clientY; isSwiping = false }
function onTouchMove(e) {
  const dx = e.touches[0].clientX - touchStartX
  const dy = e.touches[0].clientY - touchStartY
  if (!isSwiping && Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 10) isSwiping = true
  if (isSwiping) { e.preventDefault(); swipeX.value = dx > 0 ? dx : 0 }
}
function onTouchEnd() {
  if (swipeX.value > 100) dismiss()
  else swipeX.value = 0
  isSwiping = false
}

function dismiss() { player.stop(); player.currentSong = null; swipeX.value = 0 }
function openNowPlaying() { if (!isSwiping) showFullPlayer.value = true }

function seekFromClick(e) {
  const rect = e.currentTarget.getBoundingClientRect()
  player.seek(Math.max(0, Math.min(100, ((e.clientX - rect.left) / rect.width) * 100)))
}
function volumeFromClick(e) {
  const rect = e.currentTarget.getBoundingClientRect()
  player.setVolume(Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width)))
}
</script>

<style scoped>
.full-player-enter-active, .full-player-leave-active {
  transition: transform 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}
.full-player-enter-from, .full-player-leave-to {
  transform: translateY(100%);
}
</style>
