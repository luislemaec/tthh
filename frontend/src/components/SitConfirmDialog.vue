<template>
  <dialog ref="dialog" class="sit-confirm" aria-labelledby="sit-confirm-title" aria-describedby="sit-confirm-message" @cancel.prevent="resolverConfirmacion(false, solicitud.id)" @close="resolverConfirmacion(false, solicitud.id)">
    <h2 id="sit-confirm-title">{{ solicitud.titulo }}</h2>
    <p id="sit-confirm-message">{{ solicitud.mensaje }}</p>
    <div class="sit-confirm__actions">
      <button ref="cancelar" type="button" class="sit-confirm__cancel" @click="resolverConfirmacion(false, solicitud.id)">Cancelar</button>
      <button type="button" class="sit-confirm__accept" :class="{ 'sit-confirm__accept--danger': solicitud.peligrosa }" @click="resolverConfirmacion(true, solicitud.id)">{{ solicitud.aceptar }}</button>
    </div>
  </dialog>
</template>
<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue'
import { resolverConfirmacion } from '@/services/ui'
defineProps({ solicitud: { type: Object, required: true } })
const dialog = ref(null)
const cancelar = ref(null)
onMounted(() => { dialog.value.showModal(); cancelar.value.focus() })
onBeforeUnmount(() => dialog.value?.close())
</script>
