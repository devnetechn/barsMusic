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
      { path: 'liked-songs', name: 'liked-songs', component: () => import('../views/LikedSongs.vue') },
      { path: 'artist/:name', name: 'artist', component: () => import('../views/ArtistView.vue') }
    ]
  }
]

const router = createRouter({
  history: createWebHashHistory(),
  routes
})

export default router
