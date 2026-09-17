import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import './styles/global.css'
// Side-effect import: initializes the theme (data-theme + arco-theme) on every
// route, even ones that don't render the header toggle.
import './composables/useTheme'

createApp(App).use(router).mount('#app')