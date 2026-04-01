<template>
  <div class="p-4 md:p-6">
    <h1 class="text-2xl font-bold text-white mb-4">Search</h1>

    <div class="relative mb-6">
      <svg
        class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-spotify-lighter"
        fill="currentColor"
        viewBox="0 0 24 24"
      >
        <path
          d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0016 9.5 6.5 6.5 0 109.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"
        />
      </svg>
      <input
        v-model="query"
        @input="onSearch"
        type="text"
        placeholder="Search songs or YouTube..."
        class="w-full bg-spotify-card text-white pl-10 pr-10 py-3 rounded-full text-sm focus:outline-none focus:ring-2 focus:ring-white placeholder-spotify-lighter"
      />
      <button
        v-if="query"
        @click="clearSearch"
        class="absolute right-3 top-1/2 -translate-y-1/2 text-spotify-light hover:text-white"
      >
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
          <path
            d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"
          />
        </svg>
      </button>
    </div>

    <!-- Local songs results -->
    <div v-if="query && localResults.length" class="mb-6">
      <h2
        class="text-sm font-semibold text-spotify-light uppercase tracking-wider mb-3"
      >
        Your Library
      </h2>
      <div class="space-y-1">
        <div
          v-for="(song, index) in localResults"
          :key="song.id"
          @click="playSong(song, index)"
          class="flex items-center gap-3 p-2 rounded-md hover:bg-spotify-card transition-colors cursor-pointer group"
        >
          <div
            class="w-10 h-10 bg-spotify-lighter rounded flex-shrink-0 flex items-center justify-center overflow-hidden"
          >
            <img
              v-if="song.cover"
              :src="song.cover"
              class="w-full h-full object-cover"
            />
            <svg
              v-else
              class="w-5 h-5 text-spotify-light"
              fill="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"
              />
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm text-white truncate">{{ song.title }}</p>
            <p class="text-xs text-spotify-light truncate">
              {{ song.artist || "Unknown Artist" }}
            </p>
          </div>
          <span class="text-xs text-spotify-green flex-shrink-0">Saved</span>
        </div>
      </div>
    </div>

    <!-- Server songs results -->
    <div v-if="query && serverResults.length" class="mb-6">
      <h2
        class="text-sm font-semibold text-spotify-light uppercase tracking-wider mb-3"
      >
        On Server
      </h2>
      <div class="space-y-1">
        <div
          v-for="song in serverResults"
          :key="song.filename"
          class="flex items-center gap-3 p-2 rounded-md hover:bg-spotify-card transition-colors cursor-pointer group"
        >
          <div
            class="w-10 h-10 bg-spotify-lighter rounded flex-shrink-0 flex items-center justify-center overflow-hidden"
          >
            <svg
              class="w-5 h-5 text-spotify-light"
              fill="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"
              />
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm text-white truncate">{{ song.title }}</p>
            <p class="text-xs text-spotify-light truncate">
              {{ formatSize(song.size) }}
            </p>
          </div>
          <button
            v-if="!song.downloading && !song.downloaded"
            @click.stop="downloadServerSong(song)"
            class="px-3 py-1.5 bg-spotify-card text-white text-xs font-semibold rounded-full hover:bg-spotify-lighter/30 transition-colors flex-shrink-0 border border-spotify-lighter/30"
          >
            Sync
          </button>
          <span
            v-else-if="song.downloading"
            class="text-xs text-spotify-light flex-shrink-0"
            >Syncing...</span
          >
          <span v-else class="text-xs text-spotify-green flex-shrink-0"
            >Saved</span
          >
        </div>
      </div>
    </div>

    <!-- YouTube results -->
    <div v-if="query && ytResults.length" class="mb-6">
      <div class="space-y-2">
        <div
          v-for="video in ytResults"
          :key="video.videoId"
          @click="showOptions(video)"
          class="flex items-center gap-3 p-2 rounded-md hover:bg-spotify-card transition-colors group cursor-pointer"
        >
          <!-- Thumbnail -->
          <div
            class="w-16 h-12 bg-spotify-lighter rounded flex-shrink-0 overflow-hidden relative"
          >
            <img
              :src="video.thumbnail"
              class="w-full h-full object-cover"
              loading="lazy"
              @error="$event.target.style.display = 'none'"
            />
            <span
              class="absolute bottom-0.5 right-0.5 bg-black/80 text-[10px] text-white px-1 rounded"
            >
              {{ video.duration }}
            </span>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm text-white truncate">{{ video.title }}</p>
            <p class="text-xs text-spotify-light truncate">
              {{ video.author }} · {{ video.viewCount }}
            </p>
          </div>
          <div v-if="video.streaming" class="flex-shrink-0">
            <div class="w-6 h-6 border-2 border-spotify-green border-t-transparent rounded-full animate-spin"></div>
          </div>
          <div v-else-if="video.downloading" class="flex-shrink-0">
            <div class="w-6 h-6 border-2 border-spotify-green border-t-transparent rounded-full animate-spin"></div>
          </div>
          <span v-else-if="video.downloaded" class="text-xs text-spotify-green flex-shrink-0">Saved</span>
          <span v-else-if="video.error" class="text-xs text-red-400 flex-shrink-0">Failed</span>
        </div>
      </div>
    </div>

    <!-- Options Modal -->
    <div v-if="selectedVideo" class="fixed inset-0 bg-black/60 z-[60] flex items-end justify-center"
      @click.self="selectedVideo = null">
      <div class="bg-spotify-dark rounded-t-xl w-full max-w-lg animate-slide-up">
        <!-- Song info -->
        <div class="flex items-center gap-3 p-4 border-b border-spotify-lighter/20">
          <div class="w-12 h-12 bg-spotify-lighter rounded overflow-hidden flex-shrink-0">
            <img :src="selectedVideo.thumbnail" class="w-full h-full object-cover" />
          </div>
          <div class="min-w-0">
            <p class="text-sm text-white font-semibold truncate">{{ selectedVideo.title }}</p>
            <p class="text-xs text-spotify-light truncate">{{ selectedVideo.author }} · {{ selectedVideo.duration }}</p>
          </div>
        </div>

        <!-- Options -->
        <div class="p-2">
          <button @click="streamFromYT(selectedVideo); selectedVideo = null"
            class="w-full flex items-center gap-4 px-4 py-3 rounded-md hover:bg-spotify-card transition-colors text-left">
            <svg class="w-6 h-6 text-spotify-green flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            <div>
              <p class="text-sm text-white font-medium">Play Now</p>
              <p class="text-xs text-spotify-light">Stream without downloading</p>
            </div>
          </button>
          <button @click="downloadAndPlay(selectedVideo); selectedVideo = null"
            class="w-full flex items-center gap-4 px-4 py-3 rounded-md hover:bg-spotify-card transition-colors text-left">
            <svg class="w-6 h-6 text-spotify-green flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M16 13h-3V3h-2v10H8l4 4 4-4zM4 19v2h16v-2H4z"/></svg>
            <div>
              <p class="text-sm text-white font-medium">Download & Play</p>
              <p class="text-xs text-spotify-light">Save to library for offline</p>
            </div>
          </button>
          <button @click="downloadFromYT(selectedVideo); selectedVideo = null"
            class="w-full flex items-center gap-4 px-4 py-3 rounded-md hover:bg-spotify-card transition-colors text-left">
            <svg class="w-6 h-6 text-white flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16h6v-6h4l-7-7-7 7h4zm-4 2h14v2H5z"/></svg>
            <div>
              <p class="text-sm text-white font-medium">Download Only</p>
              <p class="text-xs text-spotify-light">Save to library</p>
            </div>
          </button>
          <button @click="playlistSong = selectedVideo; selectedVideo = null"
            class="w-full flex items-center gap-4 px-4 py-3 rounded-md hover:bg-spotify-card transition-colors text-left">
            <svg class="w-6 h-6 text-spotify-light flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M14 10H2v2h12v-2zm0-4H2v2h12V6zm4 8v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zM2 16h8v-2H2v2z"/></svg>
            <div>
              <p class="text-sm text-white font-medium">Add to Playlist</p>
              <p class="text-xs text-spotify-light">Save for later, download anytime</p>
            </div>
          </button>
        </div>

        <!-- Cancel -->
        <div class="p-2 border-t border-spotify-lighter/20">
          <button @click="selectedVideo = null"
            class="w-full py-3 text-sm text-spotify-light font-medium hover:text-white transition-colors">
            Cancel
          </button>
        </div>
      </div>
    </div>

    <!-- Add to Playlist modal -->
    <AddToPlaylist :show="!!playlistSong" :song="playlistSong" @close="playlistSong = null" />

    <!-- YouTube loading -->
    <div v-if="query && ytLoading" class="text-center py-6">
      <div
        class="w-8 h-8 border-2 border-spotify-green border-t-transparent rounded-full animate-spin mx-auto mb-2"
      ></div>
      <p class="text-xs text-spotify-light">Searching YouTube...</p>
    </div>

    <!-- Server loading -->
    <div v-if="query && serverLoading && !ytLoading" class="text-center py-4">
      <p class="text-xs text-spotify-light">Searching server...</p>
    </div>

    <!-- No results -->
    <div
      v-if="
        query &&
        !localResults.length &&
        !serverResults.length &&
        !ytResults.length &&
        !serverLoading &&
        !ytLoading
      "
      class="text-center py-12"
    >
      <p class="text-spotify-light">No results found for "{{ query }}"</p>
    </div>

    <!-- Browse genres -->
    <div v-if="!query" class="space-y-6">
      <h2 class="text-lg font-bold text-white">Browse All</h2>
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
        <div v-for="genre in genres" :key="genre.name"
          @click="searchGenre(genre)"
          class="relative h-24 rounded-lg overflow-hidden cursor-pointer hover:scale-105 transition-transform"
          :class="'bg-gradient-to-br ' + genre.color">
          <p class="absolute bottom-3 left-3 text-white font-bold text-lg drop-shadow">{{ genre.name }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from "vue";
import { searchSongs, getAllSongs, saveSong } from "../utils/db";
import { usePlayerStore } from "../stores/player";
import { api } from "../utils/api";
import { useAuthStore } from "../stores/auth";
import AddToPlaylist from "../components/AddToPlaylist.vue";

const player = usePlayerStore();
const auth = useAuthStore();
const query = ref("");
const localResults = ref([]);
const serverResults = ref([]);
const ytResults = ref([]);
const serverLoading = ref(false);
const ytLoading = ref(false);
const selectedVideo = ref(null);
const playlistSong = ref(null);
let debounceTimer = null;
let searchVersion = 0;

const genres = [
  { name: 'OPM', color: 'from-green-600 to-green-900', query: 'OPM hits songs' },
  { name: 'Pop', color: 'from-pink-500 to-purple-700', query: 'popular pop songs' },
  { name: 'R&B', color: 'from-blue-500 to-blue-900', query: 'R&B soul songs' },
  { name: 'Hip Hop', color: 'from-orange-500 to-red-700', query: 'hip hop rap songs' },
  { name: 'Rock', color: 'from-red-600 to-gray-900', query: 'rock songs' },
  { name: 'Acoustic', color: 'from-yellow-600 to-amber-900', query: 'acoustic chill songs' },
  { name: 'Chill', color: 'from-teal-400 to-cyan-800', query: 'chill lofi songs' },
  { name: 'Love Songs', color: 'from-rose-500 to-pink-900', query: 'love songs romantic' },
  { name: 'Worship', color: 'from-indigo-500 to-violet-900', query: 'worship praise songs' },
  { name: 'Trending', color: 'from-emerald-500 to-emerald-900', query: 'trending songs 2025' },
]

function searchGenre(genre) {
  query.value = genre.query
  onSearch()
}

function showOptions(video) {
  if (video.downloading || video.streaming) return;
  selectedVideo.value = video;
}

function clearSearch() {
  query.value = "";
  localResults.value = [];
  serverResults.value = [];
  ytResults.value = [];
  serverLoading.value = false;
  ytLoading.value = false;
}

function onSearch() {
  clearTimeout(debounceTimer);

  const q = query.value.trim();
  if (!q) {
    clearSearch();
    return;
  }

  debounceTimer = setTimeout(() => doSearch(q), 100);
}

async function doSearch(q) {
  const version = ++searchVersion;

  // 1. Search local IndexedDB (instant)
  const local = await searchSongs(q);
  if (searchVersion !== version) return;
  localResults.value = local;

  // 2. Search server songs (admin only) + YouTube in parallel
  serverLoading.value = true;
  ytLoading.value = true;

  const serverPromise = auth.isAdmin
    ? api(`/bars/api/songs.php?q=${encodeURIComponent(q)}`)
        .then((res) => res.json())
        .then((data) => {
          if (searchVersion !== version) return;
          const allServer = data.songs || [];
          const localFilenames = new Set(local.map((s) => s.filename));
          serverResults.value = allServer
            .filter((s) => !localFilenames.has(s.filename))
            .map((s) => reactive({ ...s, downloading: false, downloaded: false }));
        })
        .catch(() => {
          if (searchVersion === version) serverResults.value = [];
        })
        .finally(() => {
          if (searchVersion === version) serverLoading.value = false;
        })
    : Promise.resolve().then(() => {
        serverResults.value = [];
        serverLoading.value = false;
      });

  const ytPromise = api(`/bars/api/yt-search.php?q=${encodeURIComponent(q)}`)
    .then((res) => res.json())
    .then((ytData) => {
      if (searchVersion !== version) return;
      ytResults.value = (ytData.results || []).map((v) =>
        reactive({
          ...v,
          downloading: false,
          downloaded: false,
          streaming: false,
          error: false,
        }),
      );
    })
    .catch(() => {
      if (searchVersion === version) ytResults.value = [];
    })
    .finally(() => {
      if (searchVersion === version) ytLoading.value = false;
    });

  await Promise.all([serverPromise, ytPromise]);
}

async function playSong(song, index) {
  const allSongs = localResults.value.length
    ? localResults.value
    : await getAllSongs();
  player.playSong(song, allSongs, index);
}

async function downloadServerSong(song) {
  song.downloading = true;
  try {
    const audioRes = await fetch(song.url);
    if (!audioRes.ok) throw new Error("Download failed");
    const audioBlob = await audioRes.blob();
    const metadata = {
      title: song.title,
      artist: "Unknown Artist",
      album: "Unknown Album",
      filename: song.filename,
      size: song.size || audioBlob.size,
      type: audioBlob.type || "audio/mpeg",
    };
    const saved = await saveSong(metadata, audioBlob);
    song.downloading = false;
    song.downloaded = true;
    localResults.value.push(saved);
  } catch (err) {
    song.downloading = false;
    console.error("Download failed:", err);
  }
}

async function downloadFromYT(video) {
  video.downloading = true;
  video.error = false;
  try {
    // Step 1: Tell server to download from YouTube as MP3
    const res = await api("/bars/api/yt-download.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        videoId: video.videoId,
        title: video.title,
        artist: video.author,
        cover: `https://i.ytimg.com/vi/${video.videoId}/hqdefault.jpg`,
      }),
    });

    const data = await res.json();
    if (!data.success) throw new Error(data.error || "Download failed");

    // Step 2: Fetch the MP3 from server and save to IndexedDB
    const audioRes = await fetch(data.url);
    if (!audioRes.ok) throw new Error("Failed to fetch audio file");

    const audioBlob = await audioRes.blob();
    const metadata = {
      title: video.title,
      artist: video.author,
      album: "YouTube",
      filename: data.filename,
      size: data.size || audioBlob.size,
      type: "audio/mpeg",
      cover: `https://i.ytimg.com/vi/${video.videoId}/hqdefault.jpg`,
    };

    const saved = await saveSong(metadata, audioBlob);
    video.downloading = false;
    video.downloaded = true;
    localResults.value.push(saved);
  } catch (err) {
    video.downloading = false;
    video.error = true;
    console.error("YT download failed:", err);
  }
}

async function streamFromYT(video) {
  video.streaming = true;
  video.error = false;
  try {
    const res = await api(`/bars/api/yt-stream.php?id=${video.videoId}`);
    const data = await res.json();
    if (!data.success) throw new Error(data.error);

    // Play the stream URL directly
    const song = {
      id: `yt_${video.videoId}`,
      title: video.title,
      artist: video.author,
      album: 'YouTube',
      cover: video.thumbnail,
      _streamUrl: data.url
    };

    // Use Howler directly for streaming
    const { Howl } = await import('howler');
    player.stop();
    player.currentSong = song;

    player.howl = new Howl({
      src: [data.url],
      html5: true,
      volume: player.isMuted ? 0 : player.volume,
      onplay: () => {
        player.isPlaying = true;
        player.duration = player.howl.duration();
        player._startProgress();
      },
      onpause: () => { player.isPlaying = false; player._stopProgress(); },
      onend: () => { player._stopProgress(); player.isPlaying = false; },
      onloaderror: () => { player.isPlaying = false; player._stopProgress(); console.error('Stream load error'); },
      onplayerror: () => {
        player.howl.once('unlock', () => player.howl.play());
      }
    });
    player.howl.play();

    if ('mediaSession' in navigator) {
      navigator.mediaSession.metadata = new MediaMetadata({
        title: song.title,
        artist: song.artist,
        artwork: [{ src: song.cover, sizes: '512x512', type: 'image/jpeg' }]
      });
    }

    video.streaming = false;
  } catch (err) {
    video.streaming = false;
    video.error = true;
    console.error('Stream failed:', err);
  }
}

async function downloadAndPlay(video) {
  video.downloading = true;
  video.error = false;
  try {
    const res = await api("/bars/api/yt-download.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        videoId: video.videoId,
        title: video.title,
        artist: video.author,
        cover: `https://i.ytimg.com/vi/${video.videoId}/hqdefault.jpg`,
      }),
    });

    const data = await res.json();
    if (!data.success) throw new Error(data.error || "Download failed");

    const audioRes = await fetch(data.url);
    if (!audioRes.ok) throw new Error("Failed to fetch");

    const audioBlob = await audioRes.blob();
    const metadata = {
      title: video.title,
      artist: video.author,
      album: "YouTube",
      filename: data.filename,
      size: data.size || audioBlob.size,
      type: "audio/mpeg",
      cover: `https://i.ytimg.com/vi/${video.videoId}/hqdefault.jpg`,
    };

    const saved = await saveSong(metadata, audioBlob);
    video.downloading = false;
    video.downloaded = true;
    localResults.value.push(saved);

    // Auto-play the downloaded song
    player.playSong(saved, [saved], 0);
  } catch (err) {
    video.downloading = false;
    video.error = true;
    console.error("Download+play failed:", err);
  }
}

function formatSize(bytes) {
  if (!bytes) return "";
  const mb = bytes / (1024 * 1024);
  return mb >= 1 ? `${mb.toFixed(1)} MB` : `${(bytes / 1024).toFixed(0)} KB`;
}
</script>
