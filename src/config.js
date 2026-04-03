/**
 * App configuration - detects Capacitor native vs web browser
 *
 * For mobile builds, set your server IP/domain here.
 * The app will use this as the base URL for all API calls.
 */

// Change this to your server's IP or domain when building for mobile
const SERVER_URL = 'http://192.168.100.35'

// Detect if running inside Capacitor native shell
const isNative = typeof window !== 'undefined' &&
  window.Capacitor &&
  window.Capacitor.isNativePlatform &&
  window.Capacitor.isNativePlatform()

// On native: use full server URL. On web: use relative paths (proxy handles it)
export const API_BASE = isNative ? SERVER_URL : ''

// For music/cover files
export const ASSET_BASE = isNative ? SERVER_URL : ''
