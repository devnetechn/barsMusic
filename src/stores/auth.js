import { defineStore } from 'pinia'
import { api } from '../utils/api'
import { API_BASE } from '../config'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    authenticated: false,
    checking: true
  }),

  getters: {
    isAdmin: (state) => state.user?.role === 'admin',
    displayName: (state) => state.user?.display_name || state.user?.username || ''
  },

  actions: {
    async checkAuth() {
      this.checking = true
      try {
        const token = localStorage.getItem('bars_token')
        const headers = token ? { 'X-Auth-Token': token } : {}
        const res = await fetch(API_BASE + '/bars/api/login.php', {
          credentials: 'include',
          headers,
          // Bypass service worker to avoid interference with auth
          cache: 'no-store'
        })
        const data = await res.json()
        this.authenticated = data.authenticated === true
        this.user = data.user || null
        if (this.authenticated && this.user) {
          localStorage.setItem('bars_user', JSON.stringify(this.user))
        } else {
          // Only clear token if server explicitly said not authenticated
          // (don't clear on network errors — handled in catch)
          localStorage.removeItem('bars_user')
          localStorage.removeItem('bars_token')
        }
      } catch {
        // Offline or network error — trust cached user
        const saved = localStorage.getItem('bars_user')
        if (saved) {
          try {
            this.user = JSON.parse(saved)
            this.authenticated = true
          } catch {
            localStorage.removeItem('bars_user')
          }
        }
      }
      this.checking = false
    },

    async login(username, password) {
      try {
        const res = await fetch(API_BASE + '/bars/api/login.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ username, password })
        })
        const data = await res.json()
        if (data.success) {
          this.authenticated = true
          this.user = data.user
          localStorage.setItem('bars_user', JSON.stringify(data.user))
          if (data.token) localStorage.setItem('bars_token', data.token)
          return { success: true }
        }
        return { success: false, error: data.error || 'Login failed' }
      } catch {
        return { success: false, error: 'Cannot connect to server' }
      }
    },

    async register(username, password, displayName) {
      try {
        const res = await fetch(API_BASE + '/bars/api/register.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ username, password, display_name: displayName })
        })
        const data = await res.json()
        if (data.success) {
          this.authenticated = true
          this.user = data.user
          localStorage.setItem('bars_user', JSON.stringify(data.user))
          if (data.token) localStorage.setItem('bars_token', data.token)
          return { success: true }
        }
        return { success: false, error: data.error || 'Registration failed' }
      } catch {
        return { success: false, error: 'Cannot connect to server' }
      }
    },

    async logout() {
      try {
        const token = localStorage.getItem('bars_token')
        const headers = token ? { 'X-Auth-Token': token } : {}
        await fetch(API_BASE + '/bars/api/logout.php', { credentials: 'include', headers })
      } catch { /* ignore */ }
      this.authenticated = false
      this.user = null
      localStorage.removeItem('bars_user')
      localStorage.removeItem('bars_token')
    }
  }
})
