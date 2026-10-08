<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { mostrarAmbiente, etiquetaAmbiente } from '@/services/ambiente'
import { RouterView } from 'vue-router'
import SitGlobals from '@/components/SitGlobals.vue'

// Indicador de ambiente. Se controla con VITE_APP_AMBIENTE en el frontend/.env
// de cada servidor (archivo NO versionado, propio de cada máquina):
//   - Servidor de pruebas : VITE_APP_AMBIENTE=pruebas   -> muestra la cinta
//   - Servidor de producción: variable ausente / =produccion -> NO muestra nada
// Dentro del layout autenticado el aviso ocupa su propia fila; aquí se
// presenta únicamente en las pantallas públicas, sin duplicarlo.
const route = useRoute()
const mostrarCinta = computed(() => mostrarAmbiente && !route.meta.requiresAuth)
</script>

<template>
  <div
    v-if="mostrarCinta"
    class="ambiente-cinta"
  >{{ etiquetaAmbiente }}</div>
  <RouterView />
  <SitGlobals />
</template>

<style>
/* Cinta de pantallas públicas: no bloquea clics. */
.ambiente-cinta {
  position: fixed;
  top: 0;
  left: 50%;
  transform: translateX(-50%);
  z-index: 9997;
  pointer-events: none;
  padding: 3px 14px 4px;
  background-color: var(--sit-warn);
  color: var(--sit-text-strong);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  border-radius: 0 0 8px 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.25);
  white-space: nowrap;
}
</style>
