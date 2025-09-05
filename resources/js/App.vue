<!--
TODO: Main App Component
Requirements from specification:
- Vue 3 SPA, Options API, JS
- Vue Router + built-in reactivity
- Dark/light theme toggle
- Navigation between /tickets, /tickets/:id, /dashboard
-->

<template>
  <div class="app">
    <header class="app__header">
      <div class="app__container">
        <h1 class="app__title">Smart Ticket Triage</h1>
        <nav class="app__nav">
          <router-link to="/tickets" class="app__nav-link" :class="{ 'app__nav-link--active': $route.path.startsWith('/tickets') }">
            <TicketIcon class="app__nav-icon" />
            Tickets
          </router-link>
          <router-link to="/dashboard" class="app__nav-link" :class="{ 'app__nav-link--active': $route.path === '/dashboard' }">
            <ChartBarIcon class="app__nav-icon" />
            Dashboard
          </router-link>
          <button @click="toggleTheme" class="app__theme-toggle" :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'">
            <SunIcon v-if="isDark" class="app__theme-icon" />
            <MoonIcon v-else class="app__theme-icon" />
          </button>
        </nav>
      </div>
    </header>

    <main class="app__main">
      <div class="app__container">
        <router-view />
      </div>
    </main>
  </div>
</template>

<script>
import { 
  TicketIcon, 
  ChartBarIcon, 
  SunIcon, 
  MoonIcon 
} from '@heroicons/vue/24/outline'

export default {
  name: 'App',
  components: {
    TicketIcon,
    ChartBarIcon,
    SunIcon,
    MoonIcon
  },
  data() {
    return {
      isDark: false
    }
  },
  mounted() {
    // Initialize theme
    this.isDark = localStorage.getItem('theme') === 'dark' || 
                  (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
    this.applyTheme();
  },
  methods: {
    toggleTheme() {
      this.isDark = !this.isDark;
      this.applyTheme();
      localStorage.setItem('theme', this.isDark ? 'dark' : 'light');
    },
    applyTheme() {
      document.documentElement.classList.toggle('app--dark', this.isDark);
    }
  }
}
</script>
