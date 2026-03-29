# Spotify-Like Enhancement Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Transform Bar's Music Player into a Spotify-like experience with proper queue management, full-screen player overlay, auto-play, recently played history, genre browsing, and organized library with artists/albums tabs.

**Architecture:** Three-phase enhancement. Phase A upgrades the player core (queue, now-playing overlay, auto-play, shuffle). Phase B adds discovery features (play history, mixes, genres, trending). Phase C reorganizes the library (artist/album tabs, playlist drag-reorder). All changes build on existing Vue 3 + Pinia + Howler.js stack with IndexedDB for offline and PHP/MySQL backend.

**Tech Stack:** Vue 3 (Composition API), Pinia, Howler.js, idb (IndexedDB), Tailwind CSS 4, PHP 8, MySQL, YouTube InnerTube API

---

## PHASE A: Player & Queue System

### Task 1: Enhanced Queue System in Player Store

**Files:**
- Modify: `src/stores/player.js`

**What to do:**

Add these new state properties to the player store:

```javascript
// Add to state:
userQueue: [],        // Songs manually added via "Play Next" / "Add to Queue"
shuffleOrder: [],     // Pre-computed shuffle order (Fisher-Yates)
shuffleIndex: -1,     // Current position in shuffle order
showQueue: false,     // Toggle queue panel visibility
```

Add these new actions:

```javascript
playNext(song) {
  // Insert song right after current position in userQueue
  // If song already in userQueue, remove it first, then re-insert
  const existingIdx = this.userQueue.findIndex(s => s.id === song.id)
  if (existingIdx !== -1) this.userQueue.splice(existingIdx, 1)
  this.userQueue.unshift(song)
},

addToQueue(song) {
  // Append song to end of userQueue
  if (!this.userQueue.some(s => s.id === song.id)) {
    this.userQueue.push(song)
  }
},

removeFromQueue(index) {
  this.userQueue.splice(index, 1)
},

clearQueue() {
  this.userQueue = []
},

toggleQueue() {
  this.showQueue = !this.showQueue
},
```

Modify the `next()` action:

```javascript
async next() {
  // Priority 1: Play from userQueue first
  if (this.userQueue.length > 0) {
    const nextSong = this.userQueue.shift()
    // Don't change queueIndex - we'll return to the main queue after
    await this.playSong(nextSong)
    return
  }

  // Priority 2: Normal queue navigation
  if (this.queue.length === 0) return

  let nextIndex
  if (this.shuffle) {
    // Fisher-Yates: use pre-computed shuffle order
    this.shuffleIndex++
    if (this.shuffleIndex >= this.shuffleOrder.length) {
      if (this.repeat === 'all') {
        this._generateShuffleOrder()
        this.shuffleIndex = 0
      } else {
        return // End of shuffled queue
      }
    }
    nextIndex = this.shuffleOrder[this.shuffleIndex]
  } else if (this.queueIndex < this.queue.length - 1) {
    nextIndex = this.queueIndex + 1
  } else if (this.repeat === 'all') {
    nextIndex = 0
  } else {
    return // End of queue - auto-play will handle this
  }

  this.queueIndex = nextIndex
  await this.playSong(this.queue[nextIndex])
},
```

Add Fisher-Yates shuffle helper:

```javascript
_generateShuffleOrder() {
  const order = Array.from({ length: this.queue.length }, (_, i) => i)
  // Remove current song from shuffle so it doesn't repeat immediately
  const currentIdx = order.indexOf(this.queueIndex)
  if (currentIdx !== -1) order.splice(currentIdx, 1)
  // Fisher-Yates shuffle
  for (let i = order.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [order[i], order[j]] = [order[j], order[i]]
  }
  this.shuffleOrder = order
  this.shuffleIndex = -1
},
```

Modify `toggleShuffle()`:

```javascript
toggleShuffle() {
  this.shuffle = !this.shuffle
  if (this.shuffle && this.queue.length > 0) {
    this._generateShuffleOrder()
  }
},
```

Also modify `playSong` to NOT reset queue if no queue param passed (so userQueue songs don't wipe the main queue):

```javascript
async playSong(song, queue = null, index = -1) {
  this.stop()
  if (queue) {
    this.queue = [...queue]
    this.queueIndex = index >= 0 ? index : queue.findIndex(s => s.id === song.id)
    if (this.shuffle) this._generateShuffleOrder()
  }
  this.currentSong = song
  // ... rest stays the same
```

Add a getter for upcoming songs:

```javascript
// Add to getters:
upcomingQueue(state) {
  const upcoming = []
  // User queue first
  upcoming.push(...state.userQueue.map(s => ({ ...s, _source: 'queue' })))
  // Then remaining main queue
  if (state.queue.length > 0) {
    const startIdx = state.queueIndex + 1
    for (let i = startIdx; i < state.queue.length; i++) {
      upcoming.push({ ...state.queue[i], _source: 'playlist' })
    }
    if (state.repeat === 'all') {
      for (let i = 0; i < state.queueIndex; i++) {
        upcoming.push({ ...state.queue[i], _source: 'playlist' })
      }
    }
  }
  return upcoming
},
```

---

### Task 2: Queue Panel UI Component

**Files:**
- Create: `src/components/QueuePanel.vue`

**What to do:**

Create a slide-out panel that shows the current queue. It should be a fixed overlay that slides in from the right side on desktop, or slides up from the bottom on mobile.

Template structure:
- Header: "Queue" title with close button
- "Now Playing" section showing current song
- "Next Up" section listing `player.upcomingQueue`
- Each item shows: cover thumbnail, title, artist, source badge ("Added to queue" or playlist name), remove button
- Empty state: "Queue is empty — play some music!"

The panel is toggled via `player.showQueue`. Add a queue button to BottomPlayer.vue (desktop) next to the lyrics button:

```html
<button @click="player.toggleQueue()" class="text-spotify-light hover:text-white"
  :class="{ '!text-spotify-green': player.showQueue }">
  <!-- queue/list icon -->
  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
    <path d="M15 6H3v2h12V6zm0 4H3v2h12v-2zM3 16h8v-2H3v2zM17 6v8.18c-.31-.11-.65-.18-1-.18-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3V8h3V6h-5z"/>
  </svg>
</button>
```

Include `<QueuePanel />` in BottomPlayer.vue template.

---

### Task 3: Now Playing as Full-Screen Overlay (Not Route)

**Files:**
- Modify: `src/components/BottomPlayer.vue` — add overlay state and full-screen player
- Modify: `src/views/NowPlaying.vue` — keep as route but redirect to overlay approach
- Modify: `src/router/index.js` — remove now-playing route

**What to do:**

Move the NowPlaying view into BottomPlayer as a full-screen overlay toggled by `showFullPlayer` ref. This allows smooth slide-up animation instead of route navigation.

In BottomPlayer.vue, add:

```javascript
const showFullPlayer = ref(false)
```

Change `openNowPlaying()` from `router.push('/now-playing')` to `showFullPlayer.value = true`.

Move the entire NowPlaying.vue template into BottomPlayer.vue as a new overlay section (similar to how lyrics overlay already works). The overlay should:

- Slide up from bottom with CSS transition (`transform: translateY(0)` when open, `translateY(100%)` when closed)
- Have a drag-down-to-dismiss gesture (touch: drag down > 100px dismisses)
- Include all existing NowPlaying features: cover/lyrics toggle, synced lyrics, progress bar, controls
- Add a queue button in the controls that opens QueuePanel
- Add "Play Next" and "Add to Queue" in a context menu (long-press or three-dot menu on current song)

Remove the `/now-playing` route from router/index.js. Update any `router.push('/now-playing')` calls to emit an event or use the player store to toggle the overlay.

The CSS transition:

```css
.now-playing-overlay {
  transition: transform 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}
```

---

### Task 4: Auto-Play Related Songs

**Files:**
- Create: `api/related.php`
- Modify: `src/stores/player.js` — modify `onSongEnd()` and `next()`

**What to do:**

Create `api/related.php`:

```php
<?php
require_once __DIR__ . '/config.php';
requireAuth();
header('Content-Type: application/json');

$title = $_GET['title'] ?? '';
$artist = $_GET['artist'] ?? '';

if (empty($title)) {
    echo json_encode(['results' => []]);
    exit;
}

// Search YouTube for related songs
$query = $artist ? "$artist songs" : "$title related songs";

$postData = json_encode([
    'context' => [
        'client' => [
            'clientName' => 'WEB',
            'clientVersion' => '2.20240101.00.00',
            'hl' => 'en',
            'gl' => 'PH'
        ]
    ],
    'query' => $query,
    'params' => 'EgIQAQ%3D%3D'
]);

$ch = curl_init('https://www.youtube.com/youtubei/v1/search?prettyPrint=false');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ],
    CURLOPT_TIMEOUT => 5,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_SSL_VERIFYPEER => false
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
$results = [];
$contents = $data['contents']['twoColumnSearchResultsRenderer']['primaryContents']['sectionListRenderer']['contents'] ?? [];

foreach ($contents as $section) {
    $items = $section['itemSectionRenderer']['contents'] ?? [];
    foreach ($items as $item) {
        $video = $item['videoRenderer'] ?? null;
        if (!$video) continue;
        $videoId = $video['videoId'] ?? '';
        if (empty($videoId)) continue;
        $durationText = $video['lengthText']['simpleText'] ?? '';
        $parts = array_reverse(explode(':', $durationText));
        $secs = 0;
        foreach ($parts as $i => $p) $secs += intval($p) * pow(60, $i);
        if ($secs > 600 || $secs < 30) continue;

        $results[] = [
            'videoId' => $videoId,
            'title' => $video['title']['runs'][0]['text'] ?? 'Unknown',
            'author' => $video['ownerText']['runs'][0]['text'] ?? 'Unknown',
            'duration' => $durationText,
            'thumbnail' => 'https://i.ytimg.com/vi/' . $videoId . '/hqdefault.jpg'
        ];
        if (count($results) >= 10) break 2;
    }
}

echo json_encode(['results' => $results]);
```

In `player.js`, modify `onSongEnd()`:

```javascript
async onSongEnd() {
  if (this.repeat === 'one') {
    this.seek(0)
    this.howl.play()
    return
  }

  // Try normal next (includes userQueue)
  const hadNext = this.userQueue.length > 0 || this.queueIndex < this.queue.length - 1 || this.repeat === 'all'
  if (hadNext) {
    await this.next()
    return
  }

  // Auto-play: fetch related songs and stream one
  await this._autoPlayRelated()
},

async _autoPlayRelated() {
  if (!this.currentSong) return
  try {
    const { api } = await import('../utils/api')
    const params = new URLSearchParams({
      title: this.currentSong.title || '',
      artist: this.currentSong.artist || ''
    })
    const res = await api.api(`../api/related.php?${params}`)
    const data = await res.json()
    const results = data.results || []
    if (results.length === 0) return

    // Pick a random result from top 5
    const pick = results[Math.floor(Math.random() * Math.min(5, results.length))]

    // Stream it
    const streamRes = await api.api(`../api/yt-stream.php?id=${pick.videoId}`)
    const streamData = await streamRes.json()
    if (!streamData.success) return

    const { Howl } = await import('howler')
    this.stop()
    this.currentSong = {
      id: `yt_${pick.videoId}`,
      title: pick.title,
      artist: pick.author,
      album: 'Auto-Play',
      cover: pick.thumbnail
    }
    this.queue = []
    this.queueIndex = -1

    this.howl = new Howl({
      src: [streamData.url],
      html5: true,
      volume: this.isMuted ? 0 : this.volume,
      onplay: () => { this.isPlaying = true; this.duration = this.howl.duration(); this._startProgress() },
      onpause: () => { this.isPlaying = false; this._stopProgress() },
      onend: () => { this._stopProgress(); this.isPlaying = false; this._autoPlayRelated() },
    })
    this.howl.play()

    if ('mediaSession' in navigator) {
      navigator.mediaSession.metadata = new MediaMetadata({
        title: pick.title, artist: pick.author,
        artwork: [{ src: pick.thumbnail, sizes: '512x512', type: 'image/jpeg' }]
      })
    }
  } catch (err) {
    console.error('Auto-play failed:', err)
  }
},
```

Note: The import for `api` needs to use dynamic import since `player.js` shouldn't have circular dependency. Use:
```javascript
const apiModule = await import('../utils/api')
const res = await apiModule.api(`../api/related.php?${params}`)
```

---

### Task 5: "Play Next" and "Add to Queue" in Context Menus

**Files:**
- Modify: `src/views/Search.vue` — add queue options to YouTube options modal
- Modify: `src/views/Home.vue` — add queue options to featured options modal
- Modify: `src/views/Library.vue` — add long-press context menu for songs
- Modify: `src/views/PlaylistView.vue` — add context menu for playlist items

**What to do:**

In every options modal where songs appear, add two new buttons:

```html
<button @click="player.playNext(songObject); close()"
  class="w-full flex items-center gap-4 px-4 py-3 rounded-md hover:bg-spotify-card transition-colors text-left">
  <svg class="w-6 h-6 text-spotify-light flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
    <path d="M15 6H3v2h12V6zm0 4H3v2h12v-2zM3 16h8v-2H3v2zM17 6v8.18c-.31-.11-.65-.18-1-.18-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3V8h3V6h-5z"/>
  </svg>
  <div>
    <p class="text-sm text-white font-medium">Play Next</p>
    <p class="text-xs text-spotify-light">Add after current song</p>
  </div>
</button>
<button @click="player.addToQueue(songObject); close()"
  class="w-full flex items-center gap-4 px-4 py-3 rounded-md hover:bg-spotify-card transition-colors text-left">
  <svg class="w-6 h-6 text-spotify-light flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
    <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
  </svg>
  <div>
    <p class="text-sm text-white font-medium">Add to Queue</p>
    <p class="text-xs text-spotify-light">Play after current queue</p>
  </div>
</button>
```

For Library songs list, add a three-dot menu button on each song row that opens a bottom-sheet modal with: Play Next, Add to Queue, Add to Playlist, Delete.

---

## PHASE B: Home & Discovery

### Task 6: Play History Tracking

**Files:**
- Modify: `src/utils/db.js` — add history store and functions
- Modify: `src/stores/player.js` — record play history on song play

**What to do:**

In `db.js`, upgrade the IndexedDB schema to version 2 with a new `history` object store:

```javascript
const DB_VERSION = 2

async function getDB() {
  return openDB(DB_NAME, DB_VERSION, {
    upgrade(db, oldVersion) {
      if (oldVersion < 1) {
        const songStore = db.createObjectStore('songs', { keyPath: 'id' })
        songStore.createIndex('title', 'title')
        songStore.createIndex('artist', 'artist')
        songStore.createIndex('album', 'album')
        songStore.createIndex('addedAt', 'addedAt')
        db.createObjectStore('audio', { keyPath: 'id' })
        const playlistStore = db.createObjectStore('playlists', { keyPath: 'id' })
        playlistStore.createIndex('name', 'name')
      }
      if (oldVersion < 2) {
        const historyStore = db.createObjectStore('history', { keyPath: 'id', autoIncrement: true })
        historyStore.createIndex('songId', 'songId')
        historyStore.createIndex('playedAt', 'playedAt')
        historyStore.createIndex('artist', 'artist')
      }
    }
  })
}
```

Add history functions:

```javascript
export async function addToHistory(song) {
  const db = await getDB()
  await db.put('history', {
    songId: song.id,
    title: song.title,
    artist: song.artist || 'Unknown Artist',
    album: song.album || '',
    cover: song.cover || null,
    playedAt: new Date().toISOString()
  })
}

export async function getRecentlyPlayed(limit = 20) {
  const db = await getDB()
  const all = await db.getAllFromIndex('history', 'playedAt')
  // Reverse for newest first, deduplicate by songId
  const seen = new Set()
  const unique = []
  for (let i = all.length - 1; i >= 0; i--) {
    if (!seen.has(all[i].songId)) {
      seen.add(all[i].songId)
      unique.push(all[i])
    }
    if (unique.length >= limit) break
  }
  return unique
}

export async function getTopArtists(limit = 10) {
  const db = await getDB()
  const all = await db.getAll('history')
  const counts = {}
  for (const h of all) {
    const artist = h.artist || 'Unknown'
    counts[artist] = (counts[artist] || 0) + 1
  }
  return Object.entries(counts)
    .sort((a, b) => b[1] - a[1])
    .slice(0, limit)
    .map(([artist, count]) => ({ artist, count }))
}
```

In `player.js`, at the start of `playSong()` after setting `this.currentSong = song`, add:

```javascript
// Record play history
import('../utils/db').then(db => db.addToHistory(song)).catch(() => {})
```

---

### Task 7: Enhanced Home Page

**Files:**
- Modify: `src/views/Home.vue`

**What to do:**

Redesign Home.vue to show these sections (in order):

1. **Greeting** (keep existing)

2. **Recently Played** — horizontal scroll row of songs from `getRecentlyPlayed(10)`. Each card: square cover art, title below, artist below. Click to play.

3. **Your Top Mixes** — generated "mixes" based on top artists from history. For each top artist (max 4), show a card like "Mix: [Artist Name]" with a gradient background. Clicking it searches local songs by that artist and plays them all as a queue.

4. **Recently Added** (keep existing but limit to 6)

5. **Popular Songs / Trending** (keep existing featured section but show even when library has songs, in a horizontal scroll)

The Recently Played section template:

```html
<section v-if="recentlyPlayed.length" class="mb-8">
  <h2 class="text-xl font-bold text-white mb-4">Recently Played</h2>
  <div class="flex gap-4 overflow-x-auto pb-2 -mx-4 px-4 snap-x">
    <div v-for="item in recentlyPlayed" :key="item.id"
      @click="playFromHistory(item)"
      class="flex-shrink-0 w-36 cursor-pointer group snap-start">
      <div class="relative mb-2">
        <div class="w-36 h-36 bg-spotify-card rounded-md overflow-hidden shadow-lg">
          <img v-if="item.cover" :src="item.cover" class="w-full h-full object-cover" />
          <div v-else class="w-full h-full flex items-center justify-center">
            <svg class="w-10 h-10 text-spotify-light" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>
            </svg>
          </div>
        </div>
        <button class="absolute bottom-2 right-2 w-10 h-10 bg-spotify-green rounded-full flex items-center justify-center shadow-lg opacity-0 group-hover:opacity-100 transition-all">
          <svg class="w-5 h-5 text-black ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
        </button>
      </div>
      <p class="text-sm text-white truncate">{{ item.title }}</p>
      <p class="text-xs text-spotify-light truncate">{{ item.artist }}</p>
    </div>
  </div>
</section>
```

The Top Mixes section:

```html
<section v-if="topMixes.length" class="mb-8">
  <h2 class="text-xl font-bold text-white mb-4">Made For You</h2>
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div v-for="mix in topMixes" :key="mix.artist"
      @click="playArtistMix(mix.artist)"
      class="bg-gradient-to-br from-spotify-green/30 to-spotify-card rounded-lg p-4 cursor-pointer hover:from-spotify-green/40 transition-colors">
      <p class="text-lg font-bold text-white mb-1">{{ mix.artist }}</p>
      <p class="text-xs text-spotify-light">Mix · {{ mix.count }} plays</p>
    </div>
  </div>
</section>
```

Add the data loading in script:

```javascript
import { getRecentlyPlayed, getTopArtists, searchSongs } from '../utils/db'

const recentlyPlayed = ref([])
const topMixes = ref([])

onMounted(async () => {
  songs.value = await getAllSongs()
  recentlyPlayed.value = await getRecentlyPlayed(10)
  topMixes.value = await getTopArtists(4)
  if (songs.value.length === 0) loadFeatured()
})

async function playFromHistory(item) {
  const song = songs.value.find(s => s.id === item.songId)
  if (song) player.playSong(song, songs.value, songs.value.indexOf(song))
}

async function playArtistMix(artist) {
  const artistSongs = await searchSongs(artist)
  if (artistSongs.length > 0) {
    // Shuffle them
    const shuffled = [...artistSongs].sort(() => Math.random() - 0.5)
    player.playSong(shuffled[0], shuffled, 0)
  }
}
```

---

### Task 8: Genre/Mood Categories on Search Page

**Files:**
- Modify: `src/views/Search.vue`

**What to do:**

When the search input is empty (`!query`), show a grid of genre/mood cards instead of just the empty state icon. Each card has a gradient background and a genre name. Tapping a card fills the search input with that genre and triggers a search.

Genre data (hardcoded array):

```javascript
const genres = [
  { name: 'OPM', color: 'from-green-600 to-green-900', query: 'OPM hits songs' },
  { name: 'Pop', color: 'from-pink-500 to-purple-700', query: 'pop hits songs' },
  { name: 'R&B', color: 'from-blue-500 to-blue-900', query: 'R&B soul songs' },
  { name: 'Hip Hop', color: 'from-orange-500 to-red-700', query: 'hip hop rap songs' },
  { name: 'Rock', color: 'from-red-600 to-gray-900', query: 'rock songs' },
  { name: 'Acoustic', color: 'from-yellow-600 to-amber-900', query: 'acoustic chill songs' },
  { name: 'Chill', color: 'from-teal-400 to-cyan-800', query: 'chill lofi songs' },
  { name: 'Love Songs', color: 'from-rose-500 to-pink-900', query: 'love songs romantic' },
  { name: 'Worship', color: 'from-indigo-500 to-violet-900', query: 'worship praise songs' },
  { name: 'Trending', color: 'from-spotify-green to-emerald-900', query: 'trending songs 2025' },
]
```

Template (replace existing empty state):

```html
<div v-if="!query" class="space-y-6">
  <h2 class="text-lg font-bold text-white">Browse All</h2>
  <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
    <div v-for="genre in genres" :key="genre.name"
      @click="searchGenre(genre)"
      class="relative h-24 rounded-lg overflow-hidden cursor-pointer hover:scale-105 transition-transform"
      :class="'bg-gradient-to-br ' + genre.color">
      <p class="absolute bottom-3 left-3 text-white font-bold text-lg">{{ genre.name }}</p>
    </div>
  </div>
</div>
```

```javascript
function searchGenre(genre) {
  query.value = genre.query
  onSearch()
}
```

---

### Task 9: Trending Section Always Visible on Home

**Files:**
- Modify: `src/views/Home.vue`

**What to do:**

Show the "Popular Songs" / trending section on Home even when the user has songs in their library. Move the featured section out of the `v-if="songs.length === 0"` conditional. Always load featured songs (with caching).

Change the `onMounted`:

```javascript
onMounted(async () => {
  songs.value = await getAllSongs()
  recentlyPlayed.value = await getRecentlyPlayed(10)
  topMixes.value = await getTopArtists(4)
  loadFeatured() // Always load, not just when empty
})
```

Show the featured section as a horizontal scroll row at the bottom of the page, always visible:

```html
<section v-if="featured.length" class="mb-8">
  <h2 class="text-xl font-bold text-white mb-4">Trending</h2>
  <div class="flex gap-4 overflow-x-auto pb-2 -mx-4 px-4 snap-x">
    <div v-for="video in featured" :key="video.videoId"
      @click="showOptions(video)"
      class="flex-shrink-0 w-40 cursor-pointer group snap-start">
      <!-- card content similar to existing but compact -->
    </div>
  </div>
</section>
```

---

## PHASE C: Library & Organization

### Task 10: Library Tabs — Songs, Artists, Albums

**Files:**
- Modify: `src/views/Library.vue`

**What to do:**

Add "Artists" and "Albums" tabs alongside existing "Songs" and "Playlists" tabs.

```javascript
const tab = ref('songs') // songs | artists | albums | playlists

const artists = computed(() => {
  const map = {}
  for (const song of songs.value) {
    const name = song.artist || 'Unknown Artist'
    if (!map[name]) map[name] = { name, songs: [], cover: song.cover }
    map[name].songs.push(song)
  }
  return Object.values(map).sort((a, b) => b.songs.length - a.songs.length)
})

const albums = computed(() => {
  const map = {}
  for (const song of songs.value) {
    const name = song.album || 'Unknown Album'
    if (!map[name]) map[name] = { name, artist: song.artist, songs: [], cover: song.cover }
    map[name].songs.push(song)
  }
  return Object.values(map).sort((a, b) => b.songs.length - a.songs.length)
})
```

Artists tab template — show a list of artists with their song counts. Clicking an artist filters the song list to show only that artist's songs and plays them all:

```html
<div v-if="tab === 'artists'" class="space-y-1">
  <div v-for="artist in artists" :key="artist.name"
    @click="playArtist(artist)"
    class="flex items-center gap-3 p-2 rounded-md hover:bg-spotify-card transition-colors cursor-pointer">
    <div class="w-12 h-12 bg-spotify-lighter rounded-full flex-shrink-0 flex items-center justify-center overflow-hidden">
      <img v-if="artist.cover" :src="artist.cover" class="w-full h-full object-cover" />
      <span v-else class="text-lg font-bold text-spotify-light">{{ artist.name.charAt(0) }}</span>
    </div>
    <div class="flex-1 min-w-0">
      <p class="text-sm text-white truncate">{{ artist.name }}</p>
      <p class="text-xs text-spotify-light">{{ artist.songs.length }} songs</p>
    </div>
  </div>
</div>
```

Albums tab — similar grid layout to playlists:

```html
<div v-if="tab === 'albums'" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
  <div v-for="album in albums" :key="album.name"
    @click="playAlbum(album)"
    class="bg-spotify-card hover:bg-spotify-lighter/30 rounded-lg p-4 transition-colors cursor-pointer group">
    <div class="aspect-square bg-spotify-lighter rounded-md flex items-center justify-center mb-3 overflow-hidden">
      <img v-if="album.cover" :src="album.cover" class="w-full h-full object-cover" />
      <svg v-else class="w-12 h-12 text-spotify-light" fill="currentColor" viewBox="0 0 24 24">
        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 14.5c-2.49 0-4.5-2.01-4.5-4.5S9.51 7.5 12 7.5s4.5 2.01 4.5 4.5-2.01 4.5-4.5 4.5zm0-5.5c-.55 0-1 .45-1 1s.45 1 1 1 1-.45 1-1-.45-1-1-1z"/>
      </svg>
    </div>
    <p class="text-sm font-semibold text-white truncate">{{ album.name }}</p>
    <p class="text-xs text-spotify-light truncate mt-1">{{ album.artist }} · {{ album.songs.length }} songs</p>
  </div>
</div>
```

```javascript
function playArtist(artist) {
  player.playSong(artist.songs[0], artist.songs, 0)
}

function playAlbum(album) {
  player.playSong(album.songs[0], album.songs, 0)
}
```

Update the tab buttons to include all four tabs:

```html
<div class="flex gap-2 mb-6 overflow-x-auto">
  <button v-for="t in ['songs', 'artists', 'albums', 'playlists']" :key="t"
    @click="tab = t"
    class="px-4 py-1.5 rounded-full text-sm font-medium transition-colors whitespace-nowrap"
    :class="tab === t ? 'bg-white text-black' : 'bg-spotify-card text-white hover:bg-spotify-lighter/30'">
    {{ t === 'songs' ? `Songs (${songs.length})` :
       t === 'artists' ? `Artists (${artists.length})` :
       t === 'albums' ? `Albums (${albums.length})` :
       `Playlists (${playlists.length})` }}
  </button>
</div>
```

---

### Task 11: Playlist Enhancements — Drag Reorder

**Files:**
- Modify: `src/views/PlaylistView.vue`
- Modify: `api/playlists.php` — add reorder endpoint

**What to do:**

Add touch-based drag-to-reorder for playlist items. Use native HTML5 drag events on desktop and touch events on mobile.

Add to PlaylistView.vue:

```javascript
const dragIndex = ref(-1)
const dragOverIndex = ref(-1)

function onDragStart(index) {
  dragIndex.value = index
}

function onDragOver(e, index) {
  e.preventDefault()
  dragOverIndex.value = index
}

async function onDrop(index) {
  if (dragIndex.value === -1 || dragIndex.value === index) {
    dragIndex.value = -1
    dragOverIndex.value = -1
    return
  }

  // Reorder locally
  const [moved] = items.value.splice(dragIndex.value, 1)
  items.value.splice(index, 0, moved)

  dragIndex.value = -1
  dragOverIndex.value = -1

  // Save new order to server
  const order = items.value.map((item, i) => ({ id: item.item_id, position: i }))
  try {
    await api('../api/playlists.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'reorder', playlist_id: playlist.value.id, order })
    })
  } catch {}
}
```

Add draggable attributes to playlist item template:

```html
<div v-for="(item, index) in items" :key="item.item_id"
  draggable="true"
  @dragstart="onDragStart(index)"
  @dragover="onDragOver($event, index)"
  @drop="onDrop(index)"
  class="flex items-center gap-3 p-2 rounded-md transition-colors cursor-pointer group"
  :class="dragOverIndex === index ? 'bg-spotify-green/20 border-t-2 border-spotify-green' : 'hover:bg-spotify-card'">
  <!-- drag handle icon -->
  <svg class="w-4 h-4 text-spotify-lighter cursor-grab flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
    <path d="M11 18c0 1.1-.9 2-2 2s-2-.9-2-2 .9-2 2-2 2 .9 2 2zm-2-8c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0-6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm6 4c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/>
  </svg>
  <!-- rest of item content -->
</div>
```

Add the reorder handler in `api/playlists.php` under the POST section:

```php
if ($action === 'reorder') {
    $playlistId = $input['playlist_id'] ?? 0;
    $order = $input['order'] ?? [];

    // Verify ownership
    $stmt = $db->prepare('SELECT id FROM playlists WHERE id = ? AND user_id = ?');
    $stmt->execute([$playlistId, $userId]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Not your playlist']);
        exit;
    }

    // Update positions
    $stmt = $db->prepare('UPDATE playlist_songs SET position = ? WHERE id = ? AND playlist_id = ?');
    foreach ($order as $item) {
        $stmt->execute([$item['position'], $item['id'], $playlistId]);
    }

    echo json_encode(['success' => true]);
    exit;
}
```

---

## Build & Test

After each task, run:

```bash
cd C:\xampp\htdocs\bars && npm run build
```

Then test at `http://localhost/bars/dist/` or via tunnel.

---

## Task Execution Order

1. Task 1 — Queue system in player store (foundation for everything)
2. Task 2 — Queue panel UI
3. Task 3 — Now Playing overlay (replaces route)
4. Task 4 — Auto-play related songs
5. Task 5 — Play Next / Add to Queue in context menus
6. Task 6 — Play history tracking in IndexedDB
7. Task 7 — Enhanced Home page (recently played, mixes)
8. Task 8 — Genre/mood categories on Search
9. Task 9 — Trending always visible on Home
10. Task 10 — Library tabs (Artists, Albums)
11. Task 11 — Playlist drag reorder
