<template>
  <!-- Login/Signup Screen -->
  <div v-if="!auth.authenticated && !auth.checking" class="fixed inset-0 bg-spotify-black flex items-center justify-center p-6 overflow-y-auto"
    style="padding-top: env(safe-area-inset-top); padding-bottom: env(safe-area-inset-bottom)">
    <div class="w-full max-w-sm">
      <!-- Logo -->
      <div class="text-center mb-8">
        <div class="w-16 h-16 bg-spotify-green rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8 text-black" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
        </div>
        <h1 class="text-2xl font-bold text-white">Bar's Music Player</h1>
      </div>

      <!-- Tabs -->
      <div class="flex mb-6 bg-spotify-card rounded-lg p-1">
        <button @click="authTab = 'login'" class="flex-1 py-2 text-sm font-semibold rounded-md transition-colors"
          :class="authTab === 'login' ? 'bg-spotify-lighter/30 text-white' : 'text-spotify-light'">
          Login
        </button>
        <button @click="authTab = 'signup'" class="flex-1 py-2 text-sm font-semibold rounded-md transition-colors"
          :class="authTab === 'signup' ? 'bg-spotify-lighter/30 text-white' : 'text-spotify-light'">
          Sign Up
        </button>
      </div>

      <!-- Login Form -->
      <form v-if="authTab === 'login'" @submit.prevent="handleLogin">
        <input
          v-model="loginForm.username"
          type="text"
          placeholder="Username"
          autocomplete="username"
          class="w-full bg-spotify-card text-white px-4 py-3 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-spotify-green placeholder-spotify-lighter mb-3"
        />
        <div class="relative mb-4">
          <input
            v-model="loginForm.password"
            :type="showPassword ? 'text' : 'password'"
            placeholder="Password"
            autocomplete="current-password"
            class="w-full bg-spotify-card text-white px-4 py-3 pr-12 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-spotify-green placeholder-spotify-lighter"
          />
          <button type="button" @click="showPassword = !showPassword"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-spotify-light hover:text-white">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
              <path v-if="showPassword" d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
              <path v-else d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/>
            </svg>
          </button>
        </div>
        <p v-if="authError" class="text-red-400 text-sm mb-4 text-center">{{ authError }}</p>
        <button type="submit" :disabled="authLoading"
          class="w-full bg-spotify-green text-black font-semibold py-3 rounded-full hover:bg-spotify-green-hover transition-colors disabled:opacity-50">
          {{ authLoading ? 'Logging in...' : 'Login' }}
        </button>
      </form>

      <!-- Signup Form -->
      <form v-if="authTab === 'signup'" @submit.prevent="handleSignup">
        <input
          v-model="signupForm.displayName"
          type="text"
          placeholder="Display Name"
          class="w-full bg-spotify-card text-white px-4 py-3 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-spotify-green placeholder-spotify-lighter mb-3"
        />
        <input
          v-model="signupForm.username"
          type="text"
          placeholder="Username"
          autocomplete="username"
          class="w-full bg-spotify-card text-white px-4 py-3 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-spotify-green placeholder-spotify-lighter mb-3"
        />
        <input
          v-model="signupForm.password"
          type="password"
          placeholder="Password"
          autocomplete="new-password"
          class="w-full bg-spotify-card text-white px-4 py-3 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-spotify-green placeholder-spotify-lighter mb-3"
        />
        <input
          v-model="signupForm.confirmPassword"
          type="password"
          placeholder="Confirm Password"
          autocomplete="new-password"
          class="w-full bg-spotify-card text-white px-4 py-3 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-spotify-green placeholder-spotify-lighter mb-4"
        />
        <p v-if="authError" class="text-red-400 text-sm mb-4 text-center">{{ authError }}</p>
        <button type="submit" :disabled="authLoading"
          class="w-full bg-spotify-green text-black font-semibold py-3 rounded-full hover:bg-spotify-green-hover transition-colors disabled:opacity-50">
          {{ authLoading ? 'Creating account...' : 'Sign Up' }}
        </button>
      </form>
    </div>
  </div>

  <!-- Loading -->
  <div v-else-if="auth.checking" class="fixed inset-0 bg-spotify-black flex items-center justify-center">
    <div class="w-10 h-10 border-2 border-spotify-green border-t-transparent rounded-full animate-spin"></div>
  </div>

  <!-- Main App -->
  <div v-else class="fixed inset-0 flex flex-col bg-spotify-black"
    style="padding-top: env(safe-area-inset-top)">
    <div class="flex flex-1 min-h-0">
      <Sidebar class="hidden md:flex flex-shrink-0" />
      <main class="flex-1 overflow-y-auto" :style="mainPadding">
        <router-view />
      </main>
    </div>

    <!-- Bottom Player -->
    <BottomPlayer v-if="playerStore.currentSong" class="flex-shrink-0" />

    <!-- Mobile Bottom Nav -->
    <nav class="md:hidden flex-shrink-0 bg-spotify-dark border-t border-spotify-lighter/20"
         style="padding-bottom: env(safe-area-inset-bottom)">
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
        <button v-if="auth.isAdmin" @click="showFeedbackPanel = true" class="flex flex-col items-center gap-1 text-xs relative"
          :class="'text-spotify-light'">
          <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
          <span v-if="unreadCount" class="absolute -top-1 right-0 w-4 h-4 bg-red-500 rounded-full text-[10px] text-white flex items-center justify-center">{{ unreadCount }}</span>
          <span>Inbox</span>
        </button>
        <button v-else @click="showFeedbackForm = true" class="flex flex-col items-center gap-1 text-xs text-spotify-light">
          <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14l4 4V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/></svg>
          <span>Feedback</span>
        </button>
      </div>
    </nav>

    <!-- User Feedback Form -->
    <div v-if="showFeedbackForm" class="fixed inset-0 bg-black/60 z-[95] flex items-end justify-center" @click.self="showFeedbackForm = false">
      <div class="bg-spotify-card rounded-t-2xl w-full max-w-lg p-4" style="padding-bottom: calc(env(safe-area-inset-bottom) + 1rem)">
        <h2 class="text-lg font-bold text-white mb-3">Send Feedback</h2>
        <textarea v-model="feedbackMsg" rows="3" placeholder="Report a bug or suggest a feature..."
          class="w-full bg-spotify-lighter/20 text-white px-4 py-3 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-spotify-green placeholder-spotify-lighter resize-none"></textarea>
        <div class="flex gap-3 mt-3">
          <button @click="showFeedbackForm = false" class="flex-1 py-2.5 text-spotify-light text-sm">Cancel</button>
          <button @click="submitFeedback" :disabled="!feedbackMsg.trim()"
            class="flex-1 py-2.5 bg-spotify-green text-black font-semibold text-sm rounded-full disabled:opacity-30">Send</button>
        </div>
        <p v-if="feedbackSent" class="text-spotify-green text-xs text-center mt-2">Sent! Thanks for your feedback.</p>
        <button @click="auth.logout()" class="mt-4 w-full py-2 text-red-400 text-sm border border-red-400/30 rounded-full">Logout</button>
      </div>
    </div>

    <!-- Admin Feedback Panel -->
    <div v-if="showFeedbackPanel" class="fixed inset-0 bg-black/60 z-[95] flex items-end justify-center" @click.self="showFeedbackPanel = false">
      <div class="bg-spotify-dark rounded-t-2xl w-full max-w-lg max-h-[70vh] flex flex-col" style="padding-bottom: env(safe-area-inset-bottom)">
        <div class="flex items-center justify-between p-4 border-b border-spotify-lighter/20 flex-shrink-0">
          <h2 class="text-lg font-bold text-white">Feedback ({{ unreadCount }} new)</h2>
          <button v-if="unreadCount" @click="markAllRead" class="text-xs text-spotify-green">Mark all read</button>
        </div>
        <div class="flex-1 overflow-y-auto p-4 space-y-3">
          <div v-for="fb in feedbackList" :key="fb.id"
            class="p-3 rounded-lg" :class="fb.is_read ? 'bg-spotify-card/50' : 'bg-spotify-card border-l-2 border-spotify-green'">
            <div class="flex items-center justify-between mb-1">
              <span class="text-sm font-semibold text-white">{{ fb.username }}</span>
              <div class="flex items-center gap-2">
                <span class="text-[10px] text-spotify-lighter">{{ timeAgo(fb.created_at) }}</span>
                <button @click="deleteFeedback(fb)" class="text-spotify-lighter hover:text-red-400"><svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg></button>
              </div>
            </div>
            <p class="text-sm text-spotify-light">{{ fb.message }}</p>
          </div>
          <p v-if="!feedbackList.length" class="text-center text-spotify-light py-8">No feedback yet</p>
        </div>
        <div class="p-4 border-t border-spotify-lighter/20 flex-shrink-0">
          <button @click="auth.logout()" class="w-full py-2 text-red-400 text-sm border border-red-400/30 rounded-full">Logout</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { usePlayerStore } from '../stores/player'
import { useAuthStore } from '../stores/auth'
import { useLikesStore } from '../stores/likes'
import Sidebar from '../components/Sidebar.vue'
import BottomPlayer from '../components/BottomPlayer.vue'
import NavBar from '../components/NavBar.vue'

const playerStore = usePlayerStore()
const auth = useAuthStore()
const likesStore = useLikesStore()

// Feedback
const showFeedbackForm = ref(false)
const showFeedbackPanel = ref(false)
const feedbackMsg = ref('')
const feedbackSent = ref(false)
const feedbackList = ref([])
const unreadCount = ref(0)

async function submitFeedback() {
  if (!feedbackMsg.value.trim()) return
  try {
    const { api } = await import('../utils/api')
    await api('/bars/api/feedback.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'submit', message: feedbackMsg.value.trim() })
    })
    feedbackMsg.value = ''
    feedbackSent.value = true
    setTimeout(() => { feedbackSent.value = false; showFeedbackForm.value = false }, 1500)
  } catch {}
}

async function loadFeedback() {
  if (!auth.isAdmin) return
  try {
    const { api } = await import('../utils/api')
    const res = await api('/bars/api/feedback.php')
    const data = await res.json()
    feedbackList.value = data.feedback || []
    unreadCount.value = data.unread || 0
  } catch {}
}

async function markAllRead() {
  try {
    const { api } = await import('../utils/api')
    await api('/bars/api/feedback.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'read', id: 'all' })
    })
    feedbackList.value.forEach(f => f.is_read = 1)
    unreadCount.value = 0
  } catch {}
}

async function deleteFeedback(fb) {
  try {
    const { api } = await import('../utils/api')
    await api('/bars/api/feedback.php', {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: fb.id })
    })
    feedbackList.value = feedbackList.value.filter(f => f.id !== fb.id)
    if (!fb.is_read) unreadCount.value--
  } catch {}
}

function timeAgo(date) {
  const s = Math.floor((Date.now() - new Date(date).getTime()) / 1000)
  if (s < 60) return 'now'
  if (s < 3600) return Math.floor(s / 60) + 'm'
  if (s < 86400) return Math.floor(s / 3600) + 'h'
  return Math.floor(s / 86400) + 'd'
}

// Extra bottom padding for main content so it doesn't hide behind player + nav
const mainPadding = computed(() => {
  return { paddingBottom: playerStore.currentSong ? '4rem' : '1rem' }
})

const authTab = ref('login')
const showPassword = ref(false)
const authError = ref('')
const authLoading = ref(false)

const loginForm = reactive({ username: '', password: '' })
const signupForm = reactive({ displayName: '', username: '', password: '', confirmPassword: '' })

async function handleLogin() {
  authError.value = ''
  if (!loginForm.username || !loginForm.password) {
    authError.value = 'Please fill in all fields'
    return
  }
  authLoading.value = true
  const result = await auth.login(loginForm.username, loginForm.password)
  authLoading.value = false
  if (!result.success) {
    authError.value = result.error
  }
}

async function handleSignup() {
  authError.value = ''
  if (!signupForm.username || !signupForm.password) {
    authError.value = 'Please fill in all fields'
    return
  }
  if (signupForm.password !== signupForm.confirmPassword) {
    authError.value = 'Passwords do not match'
    return
  }
  authLoading.value = true
  const result = await auth.register(signupForm.username, signupForm.password, signupForm.displayName)
  authLoading.value = false
  if (!result.success) {
    authError.value = result.error
  }
}

onMounted(async () => {
  // Request persistent storage so IndexedDB doesn't get cleared
  if (navigator.storage?.persist) {
    navigator.storage.persist().catch(() => {})
  }

  await auth.checkAuth()
  if (auth.authenticated) {
    likesStore.loadLikedSongs()
    loadFeedback()
    // Check for new feedback every 30 seconds (admin)
    setInterval(loadFeedback, 30000)
  }
})
</script>
