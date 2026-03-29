# PWA Offline Music Player Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a Spotify-themed PWA offline music player that imports music via WiFi and stores songs permanently in IndexedDB for offline playback.

**Architecture:** Vue 3 SPA served by XAMPP Apache. PHP API handles file uploads from any device on the local network. Frontend caches songs in IndexedDB for permanent offline storage. Service worker intercepts requests for full offline PWA support. Howler.js manages audio playback with full controls.

**Tech Stack:** Vue 3 + Vite, Tailwind CSS, Howler.js, Pinia, Vue Router, PHP 8.2 (XAMPP), IndexedDB (idb), Workbox (PWA)

---

## Task 1: Project Scaffolding

**Files:**
- Create: `package.json`
- Create: `vite.config.js`
- Create: `tailwind.config.js`
- Create: `postcss.config.js`
- Create: `index.html`
- Create: `src/main.js`
- Create: `src/App.vue`
- Create: `src/style.css`

**Step 1: Initialize Vite + Vue 3 project**

Run:
```bash
cd /c/xampp/htdocs/bars
npm create vite@latest . -- --template vue
```

If prompted about non-empty directory, confirm yes.

**Step 2: Install dependencies**

Run:
```bash
npm install
npm install -D tailwindcss @tailwindcss/vite
npm install pinia vue-router@4 howler idb
```

**Step 3: Configure Tailwind via Vite plugin**

Replace `vite.config.js`:
```js
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  server: {
    host: '0.0.0.0',
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, '/bars/api')
      }
    }
  },
  build: {
    outDir: 'dist',
    assetsDir: 'assets'
  }
})
```

**Step 4: Setup Tailwind CSS**

Replace `src/style.css`:
```css
@import "tailwindcss";

/* Spotify-like custom colors */
@theme {
  --color-spotify-green: #1DB954;
  --color-spotify-green-hover: #1ed760;
  --color-spotify-black: #121212;
  --color-spotify-dark: #181818;
  --color-spotify-card: #282828;
  --color-spotify-light: #b3b3b3;
  --color-spotify-lighter: #535353;
}

/* Custom scrollbar */
::-webkit-scrollbar {
  width: 8px;
}
::-webkit-scrollbar-track {
  background: #121212;
}
::-webkit-scrollbar-thumb {
  background: #535353;
  border-radius: 4px;
}
::-webkit-scrollbar-thumb:hover {
  background: #b3b3b3;
}
```

**Step 5: Setup base App.vue**

Replace `src/App.vue`:
```vue
<template>
  <div class="bg-spotify-black text-white min-h-screen flex flex-col">
    <router-view />
  </div>
</template>

<script setup>
</script>
```

**Step 6: Setup main.js with Pinia + Router**

Replace `src/main.js`:
```js
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import App from './App.vue'
import './style.css'

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.mount('#app')
```

**Step 7: Verify dev server starts**

Run:
```bash
npm run dev
```
Expected: Vite dev server running on http://localhost:5173

**Step 8: Commit**

```bash
git init
git add -A
git commit -m "feat: scaffold Vue 3 + Vite + Tailwind project"
```

---

## Task 2: IndexedDB Storage Layer

**Files:**
- Create: `src/utils/db.js`

**Step 1: Create IndexedDB wrapper using idb**

Create `src/utils/db.js`:
```js
import { openDB } from 'idb'

const DB_NAME = 'muzik-player'
const DB_VERSION = 1

async function getDB() {
  return openDB(DB_NAME, DB_VERSION, {
    upgrade(db) {
      // Songs store - metadata
      if (!db.objectStoreNames.contains('songs')) {
        const songStore = db.createObjectStore('songs', { keyPath: 'id' })
        songStore.createIndex('title', 'title')
        songStore.createIndex('artist', 'artist')
        songStore.createIndex('album', 'album')
        songStore.createIndex('addedAt', 'addedAt')
      }

      // Audio blobs store - actual audio data
      if (!db.objectStoreNames.contains('audio')) {
        db.createObjectStore('audio', { keyPath: 'id' })
      }

      // Playlists store
      if (!db.objectStoreNames.contains('playlists')) {
        const playlistStore = db.createObjectStore('playlists', { keyPath: 'id' })
        playlistStore.createIndex('name', 'name')
      }
    }
  })
}

// ---- Songs ----

export async function saveSong(metadata, audioBlob) {
  const db = await getDB()
  const id = metadata.id || `song_${Date.now()}_${Math.random().toString(36).slice(2, 9)}`
  const song = {
    ...metadata,
    id,
    addedAt: metadata.addedAt || new Date().toISOString()
  }

  const tx = db.transaction(['songs', 'audio'], 'readwrite')
  await Promise.all([
    tx.objectStore('songs').put(song),
    tx.objectStore('audio').put({ id, blob: audioBlob }),
    tx.done
  ])

  return song
}

export async function getAllSongs() {
  const db = await getDB()
  return db.getAll('songs')
}

export async function getSong(id) {
  const db = await getDB()
  return db.get('songs', id)
}

export async function getAudioBlob(id) {
  const db = await getDB()
  const record = await db.get('audio', id)
  return record?.blob || null
}

export async function deleteSong(id) {
  const db = await getDB()
  const tx = db.transaction(['songs', 'audio'], 'readwrite')
  await Promise.all([
    tx.objectStore('songs').delete(id),
    tx.objectStore('audio').delete(id),
    tx.done
  ])
}

export async function searchSongs(query) {
  const songs = await getAllSongs()
  const q = query.toLowerCase()
  return songs.filter(s =>
    s.title?.toLowerCase().includes(q) ||
    s.artist?.toLowerCase().includes(q) ||
    s.album?.toLowerCase().includes(q)
  )
}

// ---- Playlists ----

export async function savePlaylist(playlist) {
  const db = await getDB()
  const id = playlist.id || `playlist_${Date.now()}_${Math.random().toString(36).slice(2, 9)}`
  const record = { ...playlist, id }
  await db.put('playlists', record)
  return record
}

export async function getAllPlaylists() {
  const db = await getDB()
  return db.getAll('playlists')
}

export async function getPlaylist(id) {
  const db = await getDB()
  return db.get('playlists', id)
}

export async function deletePlaylist(id) {
  const db = await getDB()
  await db.delete('playlists', id)
}

// ---- Storage Info ----

export async function getStorageEstimate() {
  if (navigator.storage && navigator.storage.estimate) {
    const estimate = await navigator.storage.estimate()
    return {
      usage: estimate.usage,
      quota: estimate.quota,
      usageMB: Math.round(estimate.usage / 1024 / 1024),
      quotaMB: Math.round(estimate.quota / 1024 / 1024)
    }
  }
  return null
}
```

**Step 2: Commit**

```bash
git add src/utils/db.js
git commit -m "feat: add IndexedDB storage layer for songs and playlists"
```

---

## Task 3: Audio Player Engine (Pinia Store)

**Files:**
- Create: `src/stores/player.js`

**Step 1: Create the player store**

Create `src/stores/player.js`:
```js
import { defineStore } from 'pinia'
import { Howl } from 'howler'
import { getAudioBlob } from '../utils/db'

export const usePlayerStore = defineStore('player', {
  state: () => ({
    currentSong: null,
    queue: [],
    queueIndex: -1,
    isPlaying: false,
    duration: 0,
    currentTime: 0,
    volume: 0.8,
    isMuted: false,
    shuffle: false,
    repeat: 'off', // 'off', 'all', 'one'
    howl: null,
    _progressInterval: null
  }),

  getters: {
    progress: (state) => {
      if (state.duration === 0) return 0
      return (state.currentTime / state.duration) * 100
    },
    hasNext: (state) => {
      if (state.repeat !== 'off') return true
      return state.queueIndex < state.queue.length - 1
    },
    hasPrev: (state) => {
      if (state.repeat !== 'off') return true
      return state.queueIndex > 0
    },
    formattedCurrentTime: (state) => formatTime(state.currentTime),
    formattedDuration: (state) => formatTime(state.duration)
  },

  actions: {
    async playSong(song, queue = null, index = -1) {
      this.stop()

      if (queue) {
        this.queue = [...queue]
        this.queueIndex = index >= 0 ? index : queue.findIndex(s => s.id === song.id)
      }

      this.currentSong = song
      const blob = await getAudioBlob(song.id)
      if (!blob) return

      const url = URL.createObjectURL(blob)

      this.howl = new Howl({
        src: [url],
        format: [getFormat(song.filename || song.title)],
        html5: true,
        volume: this.isMuted ? 0 : this.volume,
        onplay: () => {
          this.isPlaying = true
          this.duration = this.howl.duration()
          this._startProgress()
        },
        onpause: () => {
          this.isPlaying = false
          this._stopProgress()
        },
        onstop: () => {
          this.isPlaying = false
          this._stopProgress()
        },
        onend: () => {
          this._stopProgress()
          this.onSongEnd()
        },
        onloaderror: (id, err) => {
          console.error('Load error:', err)
        }
      })

      this.howl.play()

      // Set media session metadata for lock screen controls
      if ('mediaSession' in navigator) {
        navigator.mediaSession.metadata = new MediaMetadata({
          title: song.title || 'Unknown',
          artist: song.artist || 'Unknown',
          album: song.album || 'Unknown',
          artwork: song.cover ? [{ src: song.cover, sizes: '512x512', type: 'image/jpeg' }] : []
        })
        navigator.mediaSession.setActionHandler('play', () => this.resume())
        navigator.mediaSession.setActionHandler('pause', () => this.pause())
        navigator.mediaSession.setActionHandler('previoustrack', () => this.prev())
        navigator.mediaSession.setActionHandler('nexttrack', () => this.next())
      }
    },

    pause() {
      if (this.howl && this.isPlaying) {
        this.howl.pause()
      }
    },

    resume() {
      if (this.howl && !this.isPlaying) {
        this.howl.play()
      }
    },

    toggle() {
      if (this.isPlaying) this.pause()
      else this.resume()
    },

    stop() {
      if (this.howl) {
        this.howl.unload()
        this.howl = null
      }
      this.isPlaying = false
      this.currentTime = 0
      this.duration = 0
      this._stopProgress()
    },

    seek(percent) {
      if (this.howl && this.duration) {
        const time = (percent / 100) * this.duration
        this.howl.seek(time)
        this.currentTime = time
      }
    },

    setVolume(val) {
      this.volume = val
      this.isMuted = false
      if (this.howl) {
        this.howl.volume(val)
      }
    },

    toggleMute() {
      this.isMuted = !this.isMuted
      if (this.howl) {
        this.howl.volume(this.isMuted ? 0 : this.volume)
      }
    },

    toggleShuffle() {
      this.shuffle = !this.shuffle
    },

    toggleRepeat() {
      const modes = ['off', 'all', 'one']
      const idx = modes.indexOf(this.repeat)
      this.repeat = modes[(idx + 1) % modes.length]
    },

    async next() {
      if (this.queue.length === 0) return

      let nextIndex
      if (this.shuffle) {
        nextIndex = Math.floor(Math.random() * this.queue.length)
      } else if (this.queueIndex < this.queue.length - 1) {
        nextIndex = this.queueIndex + 1
      } else if (this.repeat === 'all') {
        nextIndex = 0
      } else {
        return
      }

      this.queueIndex = nextIndex
      await this.playSong(this.queue[nextIndex])
    },

    async prev() {
      // If more than 3 seconds in, restart current song
      if (this.currentTime > 3) {
        this.seek(0)
        return
      }

      if (this.queue.length === 0) return

      let prevIndex
      if (this.queueIndex > 0) {
        prevIndex = this.queueIndex - 1
      } else if (this.repeat === 'all') {
        prevIndex = this.queue.length - 1
      } else {
        this.seek(0)
        return
      }

      this.queueIndex = prevIndex
      await this.playSong(this.queue[prevIndex])
    },

    onSongEnd() {
      if (this.repeat === 'one') {
        this.seek(0)
        this.howl.play()
      } else {
        this.next()
      }
    },

    _startProgress() {
      this._stopProgress()
      this._progressInterval = setInterval(() => {
        if (this.howl && this.isPlaying) {
          this.currentTime = this.howl.seek() || 0
        }
      }, 250)
    },

    _stopProgress() {
      if (this._progressInterval) {
        clearInterval(this._progressInterval)
        this._progressInterval = null
      }
    }
  }
})

function formatTime(seconds) {
  if (!seconds || isNaN(seconds)) return '0:00'
  const mins = Math.floor(seconds / 60)
  const secs = Math.floor(seconds % 60)
  return `${mins}:${secs.toString().padStart(2, '0')}`
}

function getFormat(filename) {
  if (!filename) return 'mp3'
  const ext = filename.split('.').pop().toLowerCase()
  const formats = { mp3: 'mp3', wav: 'wav', ogg: 'ogg', flac: 'flac', aac: 'aac', m4a: 'mp4', wma: 'wma' }
  return formats[ext] || 'mp3'
}
```

**Step 2: Commit**

```bash
git add src/stores/player.js
git commit -m "feat: add Pinia player store with Howler.js audio engine"
```

---

## Task 4: PHP Upload API

**Files:**
- Create: `api/upload.php`
- Create: `api/songs.php`
- Create: `api/delete.php`
- Create: `api/.htaccess`
- Create: `music/.gitkeep`

**Step 1: Create API directory and CORS-enabled upload endpoint**

Create `api/.htaccess`:
```apache
# CORS headers for API
<IfModule mod_headers.c>
    Header set Access-Control-Allow-Origin "*"
    Header set Access-Control-Allow-Methods "GET, POST, DELETE, OPTIONS"
    Header set Access-Control-Allow-Headers "Content-Type"
</IfModule>

# Handle OPTIONS preflight
RewriteEngine On
RewriteCond %{REQUEST_METHOD} OPTIONS
RewriteRule ^(.*)$ $1 [R=200,L]
```

Create `api/upload.php`:
```php
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$musicDir = __DIR__ . '/../music/';
if (!is_dir($musicDir)) {
    mkdir($musicDir, 0755, true);
}

$allowedTypes = [
    'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg',
    'audio/flac', 'audio/aac', 'audio/mp4', 'audio/x-m4a',
    'audio/x-wav', 'audio/webm', 'audio/x-flac'
];

$maxSize = 50 * 1024 * 1024; // 50MB per file
$uploaded = [];
$errors = [];

if (empty($_FILES['music'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No files uploaded']);
    exit;
}

$files = $_FILES['music'];

// Normalize single file upload to array format
if (!is_array($files['name'])) {
    $files = [
        'name' => [$files['name']],
        'type' => [$files['type']],
        'tmp_name' => [$files['tmp_name']],
        'error' => [$files['error']],
        'size' => [$files['size']]
    ];
}

for ($i = 0; $i < count($files['name']); $i++) {
    $name = $files['name'][$i];
    $type = $files['type'][$i];
    $tmpName = $files['tmp_name'][$i];
    $error = $files['error'][$i];
    $size = $files['size'][$i];

    if ($error !== UPLOAD_ERR_OK) {
        $errors[] = ['file' => $name, 'error' => 'Upload error code: ' . $error];
        continue;
    }

    if ($size > $maxSize) {
        $errors[] = ['file' => $name, 'error' => 'File too large (max 50MB)'];
        continue;
    }

    // Generate unique filename
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($name, PATHINFO_FILENAME));
    $uniqueName = $safeName . '_' . uniqid() . '.' . $ext;
    $destPath = $musicDir . $uniqueName;

    if (move_uploaded_file($tmpName, $destPath)) {
        // Extract basic metadata
        $metadata = [
            'id' => 'song_' . uniqid() . '_' . bin2hex(random_bytes(4)),
            'filename' => $uniqueName,
            'originalName' => $name,
            'title' => pathinfo($name, PATHINFO_FILENAME),
            'artist' => 'Unknown Artist',
            'album' => 'Unknown Album',
            'size' => $size,
            'type' => $type,
            'url' => '/bars/music/' . $uniqueName,
            'addedAt' => date('c')
        ];

        $uploaded[] = $metadata;
    } else {
        $errors[] = ['file' => $name, 'error' => 'Failed to save file'];
    }
}

echo json_encode([
    'success' => count($uploaded) > 0,
    'uploaded' => $uploaded,
    'errors' => $errors,
    'count' => count($uploaded)
]);
```

Create `api/songs.php`:
```php
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$musicDir = __DIR__ . '/../music/';
if (!is_dir($musicDir)) {
    echo json_encode(['songs' => []]);
    exit;
}

$extensions = ['mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a', 'webm'];
$songs = [];

foreach (scandir($musicDir) as $file) {
    if ($file === '.' || $file === '..') continue;
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, $extensions)) continue;

    $filePath = $musicDir . $file;
    $songs[] = [
        'filename' => $file,
        'title' => pathinfo($file, PATHINFO_FILENAME),
        'size' => filesize($filePath),
        'url' => '/bars/music/' . $file,
        'modified' => date('c', filemtime($filePath))
    ];
}

echo json_encode(['songs' => $songs]);
```

Create `api/delete.php`:
```php
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$filename = $input['filename'] ?? '';

if (empty($filename)) {
    http_response_code(400);
    echo json_encode(['error' => 'Filename required']);
    exit;
}

// Prevent directory traversal
$filename = basename($filename);
$filePath = __DIR__ . '/../music/' . $filename;

if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'File not found']);
    exit;
}

if (unlink($filePath)) {
    echo json_encode(['success' => true, 'message' => 'File deleted']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete file']);
}
```

**Step 2: Create music directory**

```bash
mkdir -p music
```

**Step 3: Commit**

```bash
git add api/ music/
git commit -m "feat: add PHP API for music upload, listing, and deletion"
```

---

## Task 5: Vue Router Setup

**Files:**
- Create: `src/router/index.js`

**Step 1: Create router with all app routes**

Create `src/router/index.js`:
```js
import { createRouter, createWebHashHistory } from 'vue-router'

const routes = [
  {
    path: '/',
    component: () => import('../views/Layout.vue'),
    children: [
      { path: '', name: 'home', component: () => import('../views/Home.vue') },
      { path: 'search', name: 'search', component: () => import('../views/Search.vue') },
      { path: 'library', name: 'library', component: () => import('../views/Library.vue') },
      { path: 'upload', name: 'upload', component: () => import('../views/Upload.vue') },
      { path: 'playlist/:id', name: 'playlist', component: () => import('../views/PlaylistView.vue') },
      { path: 'now-playing', name: 'now-playing', component: () => import('../views/NowPlaying.vue') }
    ]
  }
]

const router = createRouter({
  history: createWebHashHistory(),
  routes
})

export default router
```

**Step 2: Commit**

```bash
git add src/router/
git commit -m "feat: add Vue Router with app routes"
```

---

## Task 6: Main Layout (Spotify-like Shell)

**Files:**
- Create: `src/views/Layout.vue`
- Create: `src/components/Sidebar.vue`
- Create: `src/components/BottomPlayer.vue`
- Create: `src/components/NavBar.vue`

**Step 1: Create the Layout shell**

Create `src/views/Layout.vue`:
```vue
<template>
  <div class="h-screen flex flex-col bg-spotify-black overflow-hidden">
    <!-- Mobile Nav (top) -->
    <NavBar class="md:hidden" />

    <div class="flex flex-1 overflow-hidden">
      <!-- Sidebar (desktop) -->
      <Sidebar class="hidden md:flex" />

      <!-- Main content -->
      <main class="flex-1 overflow-y-auto pb-36 md:pb-24">
        <router-view />
      </main>
    </div>

    <!-- Bottom Player -->
    <BottomPlayer v-if="playerStore.currentSong" />

    <!-- Mobile Bottom Nav -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 bg-spotify-dark border-t border-spotify-lighter/20 z-40"
         :class="{ 'bottom-16': playerStore.currentSong }">
      <div class="flex justify-around py-2">
        <router-link to="/" class="flex flex-col items-center gap-1 text-xs"
          :class="$route.name === 'home' ? 'text-white' : 'text-spotify-light'">
          <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3L4 9v12h5v-7h6v7h5V9z"/></svg>
          <span>Home</span>
        </router-link>
        <router-link to="/search" class="flex flex-col items-center gap-1 text-xs"
          :class="$route.name === 'search' ? 'text-white' : 'text-spotify-light'">
          <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0016 9.5 6.5 6.5 0 109.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
          <span>Search</span>
        </router-link>
        <router-link to="/library" class="flex flex-col items-center gap-1 text-xs"
          :class="$route.name === 'library' ? 'text-white' : 'text-spotify-light'">
          <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg>
          <span>Library</span>
        </router-link>
        <router-link to="/upload" class="flex flex-col items-center gap-1 text-xs"
          :class="$route.name === 'upload' ? 'text-white' : 'text-spotify-light'">
          <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16h6v-6h4l-7-7-7 7h4zm-4 2h14v2H5z"/></svg>
          <span>Upload</span>
        </router-link>
      </div>
    </nav>
  </div>
</template>

<script setup>
import { usePlayerStore } from '../stores/player'
import Sidebar from '../components/Sidebar.vue'
import BottomPlayer from '../components/BottomPlayer.vue'
import NavBar from '../components/NavBar.vue'

const playerStore = usePlayerStore()
</script>
```

**Step 2: Create Sidebar**

Create `src/components/Sidebar.vue`:
```vue
<template>
  <aside class="w-64 bg-black flex flex-col gap-2 p-2">
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

      <!-- Playlist list populated by library -->
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

const playlists = ref([])

onMounted(async () => {
  playlists.value = await getAllPlaylists()
})
</script>
```

**Step 3: Create NavBar (mobile top bar)**

Create `src/components/NavBar.vue`:
```vue
<template>
  <header class="bg-spotify-dark/80 backdrop-blur-md px-4 py-3 flex items-center justify-between sticky top-0 z-30">
    <h1 class="text-lg font-bold text-white">Muzik</h1>
    <div class="flex items-center gap-2">
      <span class="text-xs text-spotify-light" v-if="isOnline">Online</span>
      <span class="text-xs text-red-400" v-else>Offline</span>
      <div class="w-2 h-2 rounded-full" :class="isOnline ? 'bg-spotify-green' : 'bg-red-400'"></div>
    </div>
  </header>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const isOnline = ref(navigator.onLine)

function updateOnline() { isOnline.value = true }
function updateOffline() { isOnline.value = false }

onMounted(() => {
  window.addEventListener('online', updateOnline)
  window.addEventListener('offline', updateOffline)
})
onUnmounted(() => {
  window.removeEventListener('online', updateOnline)
  window.removeEventListener('offline', updateOffline)
})
</script>
```

**Step 4: Create BottomPlayer**

Create `src/components/BottomPlayer.vue`:
```vue
<template>
  <div class="fixed bottom-0 md:bottom-0 left-0 right-0 z-50"
       :class="{ 'bottom-14': true }">
    <!-- Mini player (mobile) -->
    <div class="md:hidden bg-spotify-card border-t border-spotify-lighter/20 px-3 py-2"
         @click="$router.push('/now-playing')">
      <div class="flex items-center gap-3">
        <!-- Album art -->
        <div class="w-10 h-10 bg-spotify-lighter rounded flex-shrink-0 flex items-center justify-center overflow-hidden">
          <img v-if="player.currentSong?.cover" :src="player.currentSong.cover" class="w-full h-full object-cover" />
          <svg v-else class="w-5 h-5 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
        </div>

        <!-- Song info -->
        <div class="flex-1 min-w-0">
          <p class="text-sm text-white truncate">{{ player.currentSong?.title || 'Unknown' }}</p>
          <p class="text-xs text-spotify-light truncate">{{ player.currentSong?.artist || 'Unknown' }}</p>
        </div>

        <!-- Play/Pause -->
        <button @click.stop="player.toggle()" class="w-8 h-8 flex items-center justify-center text-white">
          <svg v-if="player.isPlaying" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
          <svg v-else class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
        </button>
      </div>

      <!-- Progress bar (thin) -->
      <div class="h-0.5 bg-spotify-lighter/30 mt-2 rounded-full overflow-hidden">
        <div class="h-full bg-spotify-green rounded-full transition-all duration-300"
             :style="{ width: player.progress + '%' }"></div>
      </div>
    </div>

    <!-- Full player bar (desktop) -->
    <div class="hidden md:flex bg-spotify-dark border-t border-spotify-lighter/20 px-4 py-2 items-center gap-4">
      <!-- Left: Song info -->
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

      <!-- Center: Controls -->
      <div class="flex-1 flex flex-col items-center gap-1 max-w-2xl">
        <div class="flex items-center gap-4">
          <button @click="player.toggleShuffle()" class="text-spotify-light hover:text-white transition-colors"
            :class="{ 'text-spotify-green': player.shuffle }">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M10.59 9.17L5.41 4 4 5.41l5.17 5.17 1.42-1.41zM14.5 4l2.04 2.04L4 18.59 5.41 20 17.96 7.46 20 9.5V4h-5.5zm.33 9.41l-1.41 1.41 3.13 3.13L14.5 20H20v-5.5l-2.04 2.04-3.13-3.13z"/></svg>
          </button>
          <button @click="player.prev()" class="text-spotify-light hover:text-white transition-colors">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/></svg>
          </button>
          <button @click="player.toggle()"
            class="w-8 h-8 bg-white rounded-full flex items-center justify-center hover:scale-105 transition-transform">
            <svg v-if="player.isPlaying" class="w-5 h-5 text-black" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
            <svg v-else class="w-5 h-5 text-black ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
          </button>
          <button @click="player.next()" class="text-spotify-light hover:text-white transition-colors">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg>
          </button>
          <button @click="player.toggleRepeat()" class="text-spotify-light hover:text-white transition-colors relative"
            :class="{ 'text-spotify-green': player.repeat !== 'off' }">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M7 7h10v3l4-4-4-4v3H5v6h2V7zm10 10H7v-3l-4 4 4 4v-3h12v-6h-2v4z"/></svg>
            <span v-if="player.repeat === 'one'" class="absolute -top-1 -right-1 text-[8px] font-bold text-spotify-green">1</span>
          </button>
        </div>

        <!-- Progress bar -->
        <div class="w-full flex items-center gap-2">
          <span class="text-xs text-spotify-light w-10 text-right">{{ player.formattedCurrentTime }}</span>
          <div class="flex-1 h-1 bg-spotify-lighter/30 rounded-full group cursor-pointer relative"
               @click="seekFromClick($event)">
            <div class="h-full bg-white group-hover:bg-spotify-green rounded-full transition-colors relative"
                 :style="{ width: player.progress + '%' }">
              <div class="absolute right-0 top-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
            </div>
          </div>
          <span class="text-xs text-spotify-light w-10">{{ player.formattedDuration }}</span>
        </div>
      </div>

      <!-- Right: Volume -->
      <div class="flex items-center gap-2 w-48 justify-end">
        <button @click="player.toggleMute()" class="text-spotify-light hover:text-white transition-colors">
          <svg v-if="player.isMuted || player.volume === 0" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg>
          <svg v-else-if="player.volume < 0.5" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.5 12c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM5 9v6h4l5 5V4L9 9H5z"/></svg>
          <svg v-else class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
        </button>
        <div class="w-24 h-1 bg-spotify-lighter/30 rounded-full group cursor-pointer"
             @click="volumeFromClick($event)">
          <div class="h-full bg-white group-hover:bg-spotify-green rounded-full transition-colors"
               :style="{ width: (player.isMuted ? 0 : player.volume * 100) + '%' }"></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { usePlayerStore } from '../stores/player'

const player = usePlayerStore()

function seekFromClick(e) {
  const rect = e.currentTarget.getBoundingClientRect()
  const percent = ((e.clientX - rect.left) / rect.width) * 100
  player.seek(Math.max(0, Math.min(100, percent)))
}

function volumeFromClick(e) {
  const rect = e.currentTarget.getBoundingClientRect()
  const val = (e.clientX - rect.left) / rect.width
  player.setVolume(Math.max(0, Math.min(1, val)))
}
</script>
```

**Step 5: Commit**

```bash
git add src/views/Layout.vue src/components/
git commit -m "feat: add Spotify-like layout with sidebar, bottom player, and mobile nav"
```

---

## Task 7: Home View

**Files:**
- Create: `src/views/Home.vue`

**Step 1: Create Home page**

Create `src/views/Home.vue`:
```vue
<template>
  <div class="p-4 md:p-6">
    <!-- Greeting -->
    <h1 class="text-2xl md:text-3xl font-bold text-white mb-6">{{ greeting }}</h1>

    <!-- Recently Added -->
    <section v-if="recentSongs.length" class="mb-8">
      <h2 class="text-xl font-bold text-white mb-4">Recently Added</h2>
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
        <div v-for="song in recentSongs" :key="song.id"
          @click="playSong(song)"
          class="bg-spotify-card hover:bg-spotify-lighter/30 rounded-lg p-4 cursor-pointer transition-colors group">
          <div class="relative mb-4">
            <div class="aspect-square bg-spotify-lighter rounded-md flex items-center justify-center overflow-hidden shadow-lg">
              <img v-if="song.cover" :src="song.cover" class="w-full h-full object-cover" />
              <svg v-else class="w-12 h-12 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
            </div>
            <!-- Play button overlay -->
            <button class="absolute bottom-2 right-2 w-10 h-10 bg-spotify-green rounded-full flex items-center justify-center shadow-lg opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all"
              @click.stop="playSong(song)">
              <svg class="w-5 h-5 text-black ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            </button>
          </div>
          <p class="text-sm font-semibold text-white truncate">{{ song.title }}</p>
          <p class="text-xs text-spotify-light truncate mt-1">{{ song.artist || 'Unknown Artist' }}</p>
        </div>
      </div>
    </section>

    <!-- Quick Actions -->
    <section v-if="songs.length === 0" class="flex flex-col items-center justify-center py-20">
      <svg class="w-16 h-16 text-spotify-light mb-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
      <h2 class="text-xl font-bold text-white mb-2">No music yet</h2>
      <p class="text-spotify-light mb-4">Upload some songs to get started</p>
      <router-link to="/upload"
        class="bg-spotify-green text-black font-semibold px-6 py-3 rounded-full hover:bg-spotify-green-hover transition-colors">
        Upload Music
      </router-link>
    </section>

    <!-- All Songs -->
    <section v-if="songs.length" class="mb-8">
      <h2 class="text-xl font-bold text-white mb-4">All Songs</h2>
      <div class="space-y-1">
        <div v-for="(song, index) in songs" :key="song.id"
          @click="playSong(song, index)"
          class="flex items-center gap-3 p-2 rounded-md hover:bg-spotify-card transition-colors cursor-pointer group">
          <span class="w-6 text-center text-sm text-spotify-light group-hover:hidden">{{ index + 1 }}</span>
          <button class="w-6 text-center hidden group-hover:block" @click.stop="playSong(song, index)">
            <svg class="w-4 h-4 text-white mx-auto" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
          </button>
          <div class="w-10 h-10 bg-spotify-lighter rounded flex-shrink-0 flex items-center justify-center overflow-hidden">
            <img v-if="song.cover" :src="song.cover" class="w-full h-full object-cover" />
            <svg v-else class="w-5 h-5 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm text-white truncate"
              :class="{ 'text-spotify-green': player.currentSong?.id === song.id }">
              {{ song.title }}
            </p>
            <p class="text-xs text-spotify-light truncate">{{ song.artist || 'Unknown Artist' }}</p>
          </div>
          <span class="text-xs text-spotify-light">{{ formatSize(song.size) }}</span>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { getAllSongs } from '../utils/db'
import { usePlayerStore } from '../stores/player'

const player = usePlayerStore()
const songs = ref([])

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'Good morning'
  if (h < 18) return 'Good afternoon'
  return 'Good evening'
})

const recentSongs = computed(() => {
  return [...songs.value]
    .sort((a, b) => new Date(b.addedAt) - new Date(a.addedAt))
    .slice(0, 10)
})

function playSong(song, index) {
  const idx = index ?? songs.value.findIndex(s => s.id === song.id)
  player.playSong(song, songs.value, idx)
}

function formatSize(bytes) {
  if (!bytes) return ''
  const mb = bytes / (1024 * 1024)
  return mb >= 1 ? `${mb.toFixed(1)} MB` : `${(bytes / 1024).toFixed(0)} KB`
}

onMounted(async () => {
  songs.value = await getAllSongs()
})
</script>
```

**Step 2: Commit**

```bash
git add src/views/Home.vue
git commit -m "feat: add Home view with song grid and list"
```

---

## Task 8: Upload View (WiFi Import)

**Files:**
- Create: `src/views/Upload.vue`

**Step 1: Create Upload page with WiFi import**

Create `src/views/Upload.vue`:
```vue
<template>
  <div class="p-4 md:p-6 max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-white mb-2">Upload Music</h1>
    <p class="text-spotify-light mb-6">Import songs from your device or from any device on the same WiFi network</p>

    <!-- WiFi Info -->
    <div class="bg-spotify-card rounded-lg p-4 mb-6">
      <div class="flex items-center gap-3 mb-2">
        <svg class="w-5 h-5 text-spotify-green" fill="currentColor" viewBox="0 0 24 24"><path d="M1 9l2 2c4.97-4.97 13.03-4.97 18 0l2-2C16.93 2.93 7.08 2.93 1 9zm8 8l3 3 3-3c-1.65-1.66-4.34-1.66-6 0zm-4-4l2 2c2.76-2.76 7.24-2.76 10 0l2-2C15.14 9.14 8.87 9.14 5 13z"/></svg>
        <h3 class="text-white font-semibold">WiFi Import</h3>
      </div>
      <p class="text-sm text-spotify-light">
        Open this URL on any device connected to the same network to upload music:
      </p>
      <div class="mt-2 flex items-center gap-2">
        <code class="bg-spotify-black px-3 py-2 rounded text-spotify-green text-sm flex-1 select-all">
          {{ networkUrl }}
        </code>
        <button @click="copyUrl" class="px-3 py-2 bg-spotify-lighter/30 rounded text-white text-sm hover:bg-spotify-lighter/50 transition-colors">
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
      <h3 class="text-white font-semibold">Uploading</h3>
      <div v-for="upload in uploads" :key="upload.name"
        class="bg-spotify-card rounded-lg p-3 flex items-center gap-3">
        <div class="flex-1 min-w-0">
          <p class="text-sm text-white truncate">{{ upload.name }}</p>
          <div class="h-1 bg-spotify-lighter/30 rounded-full mt-2 overflow-hidden">
            <div class="h-full rounded-full transition-all duration-300"
              :class="upload.status === 'error' ? 'bg-red-500' : upload.status === 'done' ? 'bg-spotify-green' : 'bg-spotify-green'"
              :style="{ width: upload.progress + '%' }"></div>
          </div>
        </div>
        <span class="text-xs flex-shrink-0"
          :class="upload.status === 'error' ? 'text-red-400' : upload.status === 'done' ? 'text-spotify-green' : 'text-spotify-light'">
          {{ upload.status === 'done' ? 'Done' : upload.status === 'error' ? 'Error' : upload.progress + '%' }}
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
import { saveSong, getStorageEstimate } from '../utils/db'

const fileInput = ref(null)
const isDragging = ref(false)
const uploads = ref([])
const copied = ref(false)
const storageInfo = ref(null)

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
      // Read file as blob
      upload.progress = 30

      // Upload to server (so other devices can also see it)
      const formData = new FormData()
      formData.append('music', file)

      try {
        await fetch('/api/upload.php', {
          method: 'POST',
          body: formData
        })
      } catch {
        // Server upload optional - works offline too
      }

      upload.progress = 60

      // Save to IndexedDB for offline access
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

  // Refresh storage info
  storageInfo.value = await getStorageEstimate()
}

async function copyUrl() {
  try {
    await navigator.clipboard.writeText(networkUrl.value)
    copied.value = true
    setTimeout(() => copied.value = false, 2000)
  } catch {
    // Fallback
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
```

**Step 2: Commit**

```bash
git add src/views/Upload.vue
git commit -m "feat: add Upload view with drag-drop and WiFi import"
```

---

## Task 9: Search View

**Files:**
- Create: `src/views/Search.vue`

**Step 1: Create Search page**

Create `src/views/Search.vue`:
```vue
<template>
  <div class="p-4 md:p-6">
    <h1 class="text-2xl font-bold text-white mb-4">Search</h1>

    <!-- Search input -->
    <div class="relative mb-6">
      <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-spotify-lighter" fill="currentColor" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0016 9.5 6.5 6.5 0 109.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
      <input
        v-model="query"
        @input="onSearch"
        type="text"
        placeholder="What do you want to listen to?"
        class="w-full bg-spotify-card text-white pl-10 pr-4 py-3 rounded-full text-sm focus:outline-none focus:ring-2 focus:ring-white placeholder-spotify-lighter"
      />
      <button v-if="query" @click="query = ''; results = []"
        class="absolute right-3 top-1/2 -translate-y-1/2 text-spotify-light hover:text-white">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
      </button>
    </div>

    <!-- Results -->
    <div v-if="query && results.length" class="space-y-1">
      <div v-for="(song, index) in results" :key="song.id"
        @click="playSong(song, index)"
        class="flex items-center gap-3 p-2 rounded-md hover:bg-spotify-card transition-colors cursor-pointer group">
        <div class="w-10 h-10 bg-spotify-lighter rounded flex-shrink-0 flex items-center justify-center overflow-hidden">
          <img v-if="song.cover" :src="song.cover" class="w-full h-full object-cover" />
          <svg v-else class="w-5 h-5 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm text-white truncate">{{ song.title }}</p>
          <p class="text-xs text-spotify-light truncate">{{ song.artist || 'Unknown Artist' }}</p>
        </div>
        <button class="opacity-0 group-hover:opacity-100 transition-opacity">
          <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
        </button>
      </div>
    </div>

    <!-- No results -->
    <div v-if="query && !results.length" class="text-center py-12">
      <p class="text-spotify-light">No results found for "{{ query }}"</p>
    </div>

    <!-- Empty state -->
    <div v-if="!query" class="text-center py-12">
      <p class="text-spotify-light">Search your music library</p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { searchSongs, getAllSongs } from '../utils/db'
import { usePlayerStore } from '../stores/player'

const player = usePlayerStore()
const query = ref('')
const results = ref([])
let debounceTimer = null

function onSearch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(async () => {
    if (query.value.trim()) {
      results.value = await searchSongs(query.value.trim())
    } else {
      results.value = []
    }
  }, 200)
}

async function playSong(song, index) {
  const allSongs = results.value.length ? results.value : await getAllSongs()
  player.playSong(song, allSongs, index)
}
</script>
```

**Step 2: Commit**

```bash
git add src/views/Search.vue
git commit -m "feat: add Search view with debounced search"
```

---

## Task 10: Library View & Playlists

**Files:**
- Create: `src/views/Library.vue`
- Create: `src/views/PlaylistView.vue`

**Step 1: Create Library page**

Create `src/views/Library.vue`:
```vue
<template>
  <div class="p-4 md:p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-white">Your Library</h1>
      <button @click="showCreatePlaylist = true"
        class="px-4 py-2 bg-spotify-card text-white text-sm rounded-full hover:bg-spotify-lighter/30 transition-colors">
        New Playlist
      </button>
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 mb-6">
      <button @click="tab = 'songs'" class="px-4 py-1.5 rounded-full text-sm font-medium transition-colors"
        :class="tab === 'songs' ? 'bg-white text-black' : 'bg-spotify-card text-white hover:bg-spotify-lighter/30'">
        Songs ({{ songs.length }})
      </button>
      <button @click="tab = 'playlists'" class="px-4 py-1.5 rounded-full text-sm font-medium transition-colors"
        :class="tab === 'playlists' ? 'bg-white text-black' : 'bg-spotify-card text-white hover:bg-spotify-lighter/30'">
        Playlists ({{ playlists.length }})
      </button>
    </div>

    <!-- Songs list -->
    <div v-if="tab === 'songs'" class="space-y-1">
      <div v-for="(song, index) in songs" :key="song.id"
        class="flex items-center gap-3 p-2 rounded-md hover:bg-spotify-card transition-colors cursor-pointer group"
        @click="playSong(song, index)">
        <div class="w-10 h-10 bg-spotify-lighter rounded flex-shrink-0 flex items-center justify-center overflow-hidden">
          <img v-if="song.cover" :src="song.cover" class="w-full h-full object-cover" />
          <svg v-else class="w-5 h-5 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm text-white truncate"
            :class="{ 'text-spotify-green': player.currentSong?.id === song.id }">
            {{ song.title }}
          </p>
          <p class="text-xs text-spotify-light truncate">{{ song.artist || 'Unknown Artist' }}</p>
        </div>
        <button @click.stop="confirmDelete(song)"
          class="opacity-0 group-hover:opacity-100 text-spotify-light hover:text-red-400 transition-all">
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
        </button>
      </div>
      <div v-if="!songs.length" class="text-center py-12">
        <p class="text-spotify-light">No songs yet</p>
        <router-link to="/upload" class="text-spotify-green text-sm hover:underline">Upload some music</router-link>
      </div>
    </div>

    <!-- Playlists -->
    <div v-if="tab === 'playlists'" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
      <router-link v-for="playlist in playlists" :key="playlist.id"
        :to="`/playlist/${playlist.id}`"
        class="bg-spotify-card hover:bg-spotify-lighter/30 rounded-lg p-4 transition-colors group">
        <div class="aspect-square bg-spotify-lighter rounded-md flex items-center justify-center mb-3">
          <svg class="w-12 h-12 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
        </div>
        <p class="text-sm font-semibold text-white truncate">{{ playlist.name }}</p>
        <p class="text-xs text-spotify-light mt-1">{{ playlist.songIds?.length || 0 }} songs</p>
      </router-link>
      <div v-if="!playlists.length" class="col-span-full text-center py-12">
        <p class="text-spotify-light">No playlists yet</p>
      </div>
    </div>

    <!-- Create Playlist Modal -->
    <div v-if="showCreatePlaylist" class="fixed inset-0 bg-black/60 z-[60] flex items-center justify-center p-4"
      @click.self="showCreatePlaylist = false">
      <div class="bg-spotify-card rounded-lg p-6 w-full max-w-sm">
        <h2 class="text-xl font-bold text-white mb-4">Create Playlist</h2>
        <input v-model="newPlaylistName" type="text" placeholder="Playlist name"
          class="w-full bg-spotify-lighter/30 text-white px-4 py-3 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-spotify-green placeholder-spotify-lighter mb-4"
          @keyup.enter="createPlaylist" />
        <div class="flex gap-3 justify-end">
          <button @click="showCreatePlaylist = false" class="px-4 py-2 text-white text-sm hover:text-spotify-light">
            Cancel
          </button>
          <button @click="createPlaylist"
            class="px-6 py-2 bg-spotify-green text-black font-semibold text-sm rounded-full hover:bg-spotify-green-hover transition-colors">
            Create
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getAllSongs, deleteSong, getAllPlaylists, savePlaylist } from '../utils/db'
import { usePlayerStore } from '../stores/player'

const player = usePlayerStore()
const songs = ref([])
const playlists = ref([])
const tab = ref('songs')
const showCreatePlaylist = ref(false)
const newPlaylistName = ref('')

function playSong(song, index) {
  player.playSong(song, songs.value, index)
}

async function confirmDelete(song) {
  if (confirm(`Delete "${song.title}"?`)) {
    await deleteSong(song.id)
    songs.value = await getAllSongs()
  }
}

async function createPlaylist() {
  if (!newPlaylistName.value.trim()) return
  await savePlaylist({
    name: newPlaylistName.value.trim(),
    songIds: [],
    createdAt: new Date().toISOString()
  })
  newPlaylistName.value = ''
  showCreatePlaylist.value = false
  playlists.value = await getAllPlaylists()
}

onMounted(async () => {
  songs.value = await getAllSongs()
  playlists.value = await getAllPlaylists()
})
</script>
```

**Step 2: Create PlaylistView**

Create `src/views/PlaylistView.vue`:
```vue
<template>
  <div class="p-4 md:p-6" v-if="playlist">
    <!-- Playlist header -->
    <div class="flex items-end gap-4 mb-6">
      <div class="w-32 h-32 md:w-48 md:h-48 bg-spotify-card rounded-lg flex items-center justify-center shadow-lg flex-shrink-0">
        <svg class="w-16 h-16 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
      </div>
      <div>
        <p class="text-xs text-spotify-light uppercase tracking-wider">Playlist</p>
        <h1 class="text-2xl md:text-4xl font-bold text-white mt-1">{{ playlist.name }}</h1>
        <p class="text-sm text-spotify-light mt-2">{{ playlistSongs.length }} songs</p>
      </div>
    </div>

    <!-- Controls -->
    <div class="flex items-center gap-4 mb-6">
      <button v-if="playlistSongs.length" @click="playAll"
        class="w-12 h-12 bg-spotify-green rounded-full flex items-center justify-center hover:scale-105 transition-transform">
        <svg class="w-6 h-6 text-black ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
      </button>
      <button @click="showAddSongs = true"
        class="text-spotify-light hover:text-white transition-colors">
        <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
      </button>
      <button @click="confirmDeletePlaylist"
        class="text-spotify-light hover:text-red-400 transition-colors ml-auto">
        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
      </button>
    </div>

    <!-- Songs -->
    <div class="space-y-1">
      <div v-for="(song, index) in playlistSongs" :key="song.id"
        @click="playSong(song, index)"
        class="flex items-center gap-3 p-2 rounded-md hover:bg-spotify-card transition-colors cursor-pointer group">
        <span class="w-6 text-center text-sm text-spotify-light">{{ index + 1 }}</span>
        <div class="w-10 h-10 bg-spotify-lighter rounded flex-shrink-0 flex items-center justify-center overflow-hidden">
          <svg class="w-5 h-5 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm text-white truncate">{{ song.title }}</p>
          <p class="text-xs text-spotify-light truncate">{{ song.artist || 'Unknown Artist' }}</p>
        </div>
        <button @click.stop="removeSong(song.id)"
          class="opacity-0 group-hover:opacity-100 text-spotify-light hover:text-red-400 transition-all">
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
        </button>
      </div>
    </div>

    <div v-if="!playlistSongs.length" class="text-center py-12">
      <p class="text-spotify-light mb-2">This playlist is empty</p>
      <button @click="showAddSongs = true" class="text-spotify-green text-sm hover:underline">Add songs</button>
    </div>

    <!-- Add Songs Modal -->
    <div v-if="showAddSongs" class="fixed inset-0 bg-black/60 z-[60] flex items-end md:items-center justify-center"
      @click.self="showAddSongs = false">
      <div class="bg-spotify-dark rounded-t-xl md:rounded-xl w-full max-w-lg max-h-[70vh] flex flex-col">
        <div class="p-4 border-b border-spotify-lighter/20">
          <h2 class="text-lg font-bold text-white">Add songs</h2>
        </div>
        <div class="overflow-y-auto p-4 space-y-1">
          <div v-for="song in availableSongs" :key="song.id"
            @click="addSong(song.id)"
            class="flex items-center gap-3 p-2 rounded-md hover:bg-spotify-card transition-colors cursor-pointer">
            <div class="w-10 h-10 bg-spotify-lighter rounded flex items-center justify-center">
              <svg class="w-5 h-5 text-spotify-light" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm text-white truncate">{{ song.title }}</p>
              <p class="text-xs text-spotify-light truncate">{{ song.artist }}</p>
            </div>
            <svg class="w-5 h-5 text-spotify-green" fill="currentColor" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
          </div>
          <p v-if="!availableSongs.length" class="text-center text-spotify-light py-4">No more songs to add</p>
        </div>
        <div class="p-4 border-t border-spotify-lighter/20">
          <button @click="showAddSongs = false" class="w-full py-2 text-white font-semibold">Done</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getPlaylist, savePlaylist, deletePlaylist, getAllSongs, getSong } from '../utils/db'
import { usePlayerStore } from '../stores/player'

const route = useRoute()
const router = useRouter()
const player = usePlayerStore()

const playlist = ref(null)
const allSongs = ref([])
const playlistSongs = ref([])
const showAddSongs = ref(false)

const availableSongs = computed(() => {
  const ids = new Set(playlist.value?.songIds || [])
  return allSongs.value.filter(s => !ids.has(s.id))
})

async function loadPlaylist() {
  playlist.value = await getPlaylist(route.params.id)
  allSongs.value = await getAllSongs()
  if (playlist.value?.songIds) {
    const songs = []
    for (const id of playlist.value.songIds) {
      const song = await getSong(id)
      if (song) songs.push(song)
    }
    playlistSongs.value = songs
  }
}

function playAll() {
  if (playlistSongs.value.length) {
    player.playSong(playlistSongs.value[0], playlistSongs.value, 0)
  }
}

function playSong(song, index) {
  player.playSong(song, playlistSongs.value, index)
}

async function addSong(songId) {
  if (!playlist.value.songIds) playlist.value.songIds = []
  playlist.value.songIds.push(songId)
  await savePlaylist(playlist.value)
  await loadPlaylist()
}

async function removeSong(songId) {
  playlist.value.songIds = playlist.value.songIds.filter(id => id !== songId)
  await savePlaylist(playlist.value)
  await loadPlaylist()
}

async function confirmDeletePlaylist() {
  if (confirm(`Delete playlist "${playlist.value.name}"?`)) {
    await deletePlaylist(playlist.value.id)
    router.push('/library')
  }
}

onMounted(loadPlaylist)
</script>
```

**Step 3: Commit**

```bash
git add src/views/Library.vue src/views/PlaylistView.vue
git commit -m "feat: add Library and Playlist views"
```

---

## Task 11: Now Playing (Full Screen)

**Files:**
- Create: `src/views/NowPlaying.vue`

**Step 1: Create full-screen Now Playing view (mobile)**

Create `src/views/NowPlaying.vue`:
```vue
<template>
  <div class="fixed inset-0 bg-gradient-to-b from-spotify-card to-spotify-black z-[70] flex flex-col p-6"
    v-if="player.currentSong">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <button @click="$router.back()" class="text-white">
        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M7.41 15.41L12 10.83l4.59 4.58L18 14l-6-6-6 6z"/></svg>
      </button>
      <p class="text-xs text-spotify-light uppercase tracking-wider">Now Playing</p>
      <div class="w-6"></div>
    </div>

    <!-- Album Art -->
    <div class="flex-1 flex items-center justify-center px-4">
      <div class="w-full max-w-sm aspect-square bg-spotify-card rounded-lg shadow-2xl flex items-center justify-center overflow-hidden">
        <img v-if="player.currentSong?.cover" :src="player.currentSong.cover" class="w-full h-full object-cover" />
        <svg v-else class="w-24 h-24 text-spotify-lighter" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
      </div>
    </div>

    <!-- Song Info -->
    <div class="mt-8 mb-4">
      <h2 class="text-xl font-bold text-white truncate">{{ player.currentSong?.title || 'Unknown' }}</h2>
      <p class="text-sm text-spotify-light truncate">{{ player.currentSong?.artist || 'Unknown Artist' }}</p>
    </div>

    <!-- Progress -->
    <div class="mb-4">
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
    <div class="flex items-center justify-between mb-8 px-4">
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
import { usePlayerStore } from '../stores/player'

const player = usePlayerStore()

function seekFromClick(e) {
  const rect = e.currentTarget.getBoundingClientRect()
  const percent = ((e.clientX - rect.left) / rect.width) * 100
  player.seek(Math.max(0, Math.min(100, percent)))
}
</script>
```

**Step 2: Commit**

```bash
git add src/views/NowPlaying.vue
git commit -m "feat: add full-screen Now Playing view"
```

---

## Task 12: PWA - Service Worker & Manifest

**Files:**
- Create: `public/manifest.json`
- Create: `public/sw.js`
- Modify: `index.html` (add manifest + SW registration)
- Create: `public/icons/icon-192.svg`
- Create: `public/icons/icon-512.svg`

**Step 1: Create PWA manifest**

Create `public/manifest.json`:
```json
{
  "name": "Muzik Player",
  "short_name": "Muzik",
  "description": "Offline music player PWA",
  "start_url": "/bars/dist/",
  "display": "standalone",
  "background_color": "#121212",
  "theme_color": "#1DB954",
  "orientation": "portrait",
  "icons": [
    {
      "src": "icons/icon-192.svg",
      "sizes": "192x192",
      "type": "image/svg+xml",
      "purpose": "any maskable"
    },
    {
      "src": "icons/icon-512.svg",
      "sizes": "512x512",
      "type": "image/svg+xml",
      "purpose": "any maskable"
    }
  ]
}
```

**Step 2: Create SVG icons**

Create `public/icons/icon-192.svg`:
```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 192 192">
  <rect width="192" height="192" rx="40" fill="#121212"/>
  <circle cx="96" cy="110" r="30" fill="none" stroke="#1DB954" stroke-width="6"/>
  <circle cx="96" cy="110" r="8" fill="#1DB954"/>
  <rect x="120" y="40" width="6" height="70" rx="3" fill="#1DB954"/>
  <rect x="100" y="50" width="40" height="6" rx="3" fill="#1DB954"/>
</svg>
```

Create `public/icons/icon-512.svg` (same content — SVG scales):
```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
  <rect width="512" height="512" rx="100" fill="#121212"/>
  <circle cx="256" cy="290" r="80" fill="none" stroke="#1DB954" stroke-width="14"/>
  <circle cx="256" cy="290" r="20" fill="#1DB954"/>
  <rect x="320" y="100" width="14" height="190" rx="7" fill="#1DB954"/>
  <rect x="260" y="130" width="110" height="14" rx="7" fill="#1DB954"/>
</svg>
```

**Step 3: Create Service Worker**

Create `public/sw.js`:
```js
const CACHE_NAME = 'muzik-v1'
const STATIC_ASSETS = [
  './',
  './index.html',
  './manifest.json'
]

// Install - cache static assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS)
    })
  )
  self.skipWaiting()
})

// Activate - clean old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
      )
    })
  )
  self.clients.claim()
})

// Fetch - network first for API, cache first for assets
self.addEventListener('fetch', (event) => {
  const { request } = event
  const url = new URL(request.url)

  // Skip non-GET requests
  if (request.method !== 'GET') return

  // API calls - network only
  if (url.pathname.includes('/api/')) return

  // Music files - network first, then cache
  if (url.pathname.includes('/music/')) {
    event.respondWith(
      caches.open(CACHE_NAME).then(async (cache) => {
        try {
          const response = await fetch(request)
          if (response.ok) cache.put(request, response.clone())
          return response
        } catch {
          return cache.match(request)
        }
      })
    )
    return
  }

  // Static assets - cache first, network fallback
  event.respondWith(
    caches.match(request).then((cached) => {
      if (cached) return cached
      return fetch(request).then((response) => {
        if (response.ok) {
          const clone = response.clone()
          caches.open(CACHE_NAME).then((cache) => cache.put(request, clone))
        }
        return response
      }).catch(() => {
        // Return offline page for navigation requests
        if (request.mode === 'navigate') {
          return caches.match('./index.html')
        }
      })
    })
  )
})
```

**Step 4: Register SW in index.html**

Add before closing `</body>` in `index.html`:
```html
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('./sw.js')
    })
  }
</script>
```

Also add in `<head>`:
```html
<link rel="manifest" href="./manifest.json" />
<meta name="theme-color" content="#1DB954" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
<meta name="apple-mobile-web-app-title" content="Muzik" />
```

**Step 5: Commit**

```bash
git add public/ index.html
git commit -m "feat: add PWA manifest, service worker, and app icons"
```

---

## Task 13: Build & Deploy to XAMPP

**Step 1: Add build script and configure base path**

Ensure `vite.config.js` has `base: '/bars/dist/'` for production.

**Step 2: Build the project**

```bash
npm run build
```

**Step 3: Test via XAMPP**

Open browser to `http://localhost/bars/dist/`
Verify the app loads with Spotify-like dark UI.

**Step 4: Test on phone**

1. Find PC's local IP: `ipconfig` → look for IPv4 address (e.g., 192.168.1.x)
2. On iPhone Safari, navigate to `http://192.168.1.x/bars/dist/`
3. Tap Share → "Add to Home Screen"
4. App should install as PWA

**Step 5: Commit**

```bash
git add -A
git commit -m "feat: build and deploy PWA to XAMPP"
```

---

## Summary

| Task | Description | Key Files |
|------|-------------|-----------|
| 1 | Project scaffolding | `package.json`, `vite.config.js`, `tailwind.config.js` |
| 2 | IndexedDB storage | `src/utils/db.js` |
| 3 | Audio player engine | `src/stores/player.js` |
| 4 | PHP upload API | `api/upload.php`, `api/songs.php`, `api/delete.php` |
| 5 | Vue Router | `src/router/index.js` |
| 6 | Layout shell | `Layout.vue`, `Sidebar.vue`, `BottomPlayer.vue`, `NavBar.vue` |
| 7 | Home view | `src/views/Home.vue` |
| 8 | Upload view | `src/views/Upload.vue` |
| 9 | Search view | `src/views/Search.vue` |
| 10 | Library + Playlists | `Library.vue`, `PlaylistView.vue` |
| 11 | Now Playing | `src/views/NowPlaying.vue` |
| 12 | PWA (SW + Manifest) | `sw.js`, `manifest.json`, icons |
| 13 | Build & Deploy | XAMPP deployment |
