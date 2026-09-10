<script setup>
import { computed } from 'vue'
import { RouterView } from 'vue-router'

// Indicador de ambiente. Se controla con VITE_APP_AMBIENTE en el frontend/.env
// de cada servidor (archivo NO versionado, propio de cada máquina):
//   - Servidor de pruebas : VITE_APP_AMBIENTE=pruebas   -> muestra la cinta
//   - Servidor de producción: variable ausente / =produccion -> NO muestra nada
// Cualquier valor distinto de "produccion"/"production" (o ausencia en un
// entorno mal configurado) muestra la cinta: es la dirección segura (avisar
// de más nunca engaña al usuario haciéndole creer que está en producción).
const ambiente = (import.meta.env.VITE_APP_AMBIENTE || '').trim().toLowerCase()
const mostrarAmbiente = computed(
  () => ambiente !== '' && ambiente !== 'produccion' && ambiente !== 'production',
)
const etiquetaAmbiente = computed(() => `AMBIENTE DE ${ambiente.toUpperCase()}`)
</script>

<template>
  <div
    v-if="mostrarAmbiente"
    class="ambiente-cinta"
  >{{ etiquetaAmbiente }}</div>
  <RouterView />
</template>

<style>
/* Cinta flotante superior-centro: no empuja el layout ni bloquea clics.
   z-index por debajo de la pantalla de mantenimiento (9998/9999) y por
   encima del chatbot (9980) y de los modales (50). */
.ambiente-cinta {
  position: fixed;
  top: 0;
  left: 50%;
  transform: translateX(-50%);
  z-index: 9997;
  pointer-events: none;
  padding: 3px 14px 4px;
  background-color: #f59e0b;
  color: #3b2600;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  border-radius: 0 0 8px 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.25);
  white-space: nowrap;
}
</style>
