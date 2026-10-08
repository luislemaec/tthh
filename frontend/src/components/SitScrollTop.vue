<template>
  <button v-if="visible" type="button" class="sit-scroll-top" aria-label="Volver arriba" title="Volver arriba" @click="volver">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 12 6-6 6 6M12 6v13" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
  </button>
</template>
<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
const props = defineProps({ target: Object })
const visible = ref(false)
let area
function actualizar() { visible.value = area?.scrollTop > 400 }
function volver() { area?.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' }); area?.querySelector('#sit-contenido')?.focus({ preventScroll: true }) }
onMounted(() => { area = props.target; area?.addEventListener('scroll', actualizar, { passive: true }); actualizar() })
onUnmounted(() => area?.removeEventListener('scroll', actualizar))
</script>
