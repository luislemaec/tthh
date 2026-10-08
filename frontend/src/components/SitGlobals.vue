<template>
  <Teleport to="body">
    <div v-if="visible" class="sit-request-status" role="status" aria-live="polite"><span class="sit-request-status__bar" aria-hidden="true" /><span class="sit-visually-hidden">Procesando solicitudes…</span></div>
    <div class="sit-notifications" aria-label="Avisos del sistema"><SitToast v-for="item in mensajes" :key="item.id" :item="item" /></div>
    <div v-if="avisoSesion" class="sit-session-warning" role="status">
      <p><strong>Tu sesión está por terminar.</strong> Quedan {{ restantes }} segundos. Guarda los cambios pendientes; después deberás iniciar sesión nuevamente.</p>
      <button type="button" @click="avisoDescartado = auth.expiresAt">Entendido</button>
    </div>
    <SitConfirmDialog v-if="confirmacion" :key="confirmacion.id" :solicitud="confirmacion" />
  </Teleport>
</template>
<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { cancelarConfirmaciones, estadoUI } from '@/services/ui'
import { segundosRestantes } from '@/services/operaciones'
import SitToast from './SitToast.vue'
import SitConfirmDialog from './SitConfirmDialog.vue'
const auth = useAuthStore()
const route = useRoute()
const { pendientes, mensajes, confirmacion } = estadoUI
const visible = ref(false)
let mostrarTimer, ocultarTimer, inicioVisible, reloj
watch(pendientes, total => {
  clearTimeout(ocultarTimer)
  if (total) {
    if (!visible.value && !mostrarTimer) mostrarTimer = setTimeout(() => { mostrarTimer = null; visible.value = true; inicioVisible = Date.now() }, 200)
  } else {
    clearTimeout(mostrarTimer)
    mostrarTimer = null
    if (visible.value) ocultarTimer = setTimeout(() => { visible.value = false }, Math.max(0, 250 - (Date.now() - inicioVisible)))
  }
}, { immediate: true })
const ahora = ref(Date.now())
const avisoDescartado = ref(null)
const restantes = computed(() => segundosRestantes(auth.expiresAt, ahora.value))
const avisoSesion = computed(() => auth.isAuthenticated && restantes.value > 0 && restantes.value <= 60 && avisoDescartado.value !== auth.expiresAt)
function actualizarReloj() { ahora.value = Date.now() }
watch(() => route.fullPath, cancelarConfirmaciones)
watch(() => auth.isAuthenticated, cancelarConfirmaciones)
onMounted(() => { reloj = setInterval(actualizarReloj, 1000); document.addEventListener('visibilitychange', actualizarReloj) })
onUnmounted(() => { clearTimeout(mostrarTimer); clearTimeout(ocultarTimer); clearInterval(reloj); document.removeEventListener('visibilitychange', actualizarReloj); cancelarConfirmaciones() })
</script>
