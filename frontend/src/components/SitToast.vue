<template>
  <div class="sit-toast" :class="`sit-toast--${item.tipo}`" :role="item.tipo === 'danger' ? 'alert' : 'status'" @mouseenter="pausar" @mouseleave="reanudar" @focusin="pausar" @focusout="reanudar">
    <svg class="sit-toast__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke-width="1.8" /><path v-if="item.tipo === 'success'" d="m8 12 3 3 5-6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /><path v-else d="M12 8v5m0 3h.01" stroke-width="1.8" stroke-linecap="round" /></svg>
    <div class="sit-toast__message"><strong>{{ titulos[item.tipo] }}</strong><p>{{ item.mensaje }}</p></div>
    <button type="button" class="sit-toast__close" aria-label="Cerrar aviso" @click="cerrarNotificacion(item.id)"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" stroke-width="1.8" stroke-linecap="round" /></svg></button>
  </div>
</template>
<script setup>
import { onMounted, onUnmounted } from 'vue'
import { cerrarNotificacion } from '@/services/ui'
const props = defineProps({ item: { type: Object, required: true } })
const titulos = { success: 'Operación completada', warn: 'Advertencia', danger: 'Error', info: 'Información' }
let timer, inicio, restante = 5000
function pausar() { if (!timer) return; clearTimeout(timer); timer = null; restante -= Date.now() - inicio }
function reanudar(event) {
  if (props.item.tipo === 'danger' || event?.currentTarget?.contains(event.relatedTarget) || event?.currentTarget?.contains(document.activeElement) || event?.currentTarget?.matches(':hover')) return
  if (timer) return
  inicio = Date.now()
  timer = setTimeout(() => cerrarNotificacion(props.item.id), Math.max(0, restante))
}
onMounted(() => reanudar())
onUnmounted(() => clearTimeout(timer))
</script>
