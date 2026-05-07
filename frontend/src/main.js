// ============================================================
// src/main.js
// ============================================================
import { createApp }  from 'vue'
import { createPinia } from 'pinia'
import App    from './App.vue'
import router from './router'
import './style.css'

const app = createApp(App)
app.use(createPinia())
app.use(router)

app.directive('uppercase', {
  beforeMount(el) {
    el.addEventListener('input', function () {
      const start = this.selectionStart
      const upper = this.value.toUpperCase()
      if (this.value !== upper) {
        this.value = upper
        this.setSelectionRange(start, start)
        this.dispatchEvent(new Event('input', { bubbles: true }))
      }
    })
  }
})

app.mount('#app')
