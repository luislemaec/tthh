<template>
  <section v-if="avisos.length" class="mb-5 rounded-xl border border-sit-border bg-sit-surface px-5 py-4" aria-label="Avisos institucionales">
    <h2 class="mb-2 text-xs font-bold uppercase tracking-wide text-sit-primary">Avisos institucionales</h2>
    <ul :class="direccion === 'vertical' ? 'max-h-40 space-y-2 overflow-y-auto' : 'flex flex-wrap gap-x-8 gap-y-2'">
      <li v-for="aviso in avisos" :key="aviso.id || aviso.texto" class="text-sm text-sit-text">{{ aviso.texto }}</li>
    </ul>
  </section>
  <Teleport to="body">
    <div v-if="modalPendientes" class="fixed inset-0 z-[10000] flex items-center justify-center bg-sit-overlay p-4" @keydown.esc="modalPendientes = false" @keydown.tab.prevent="aceptar?.focus()">
      <section role="dialog" aria-modal="true" aria-labelledby="titulo-pendientes" class="w-full max-w-sm overflow-hidden rounded-xl bg-sit-surface shadow-sit-float">
        <h2 id="titulo-pendientes" class="bg-sit-primary px-6 py-4 font-bold text-sit-on-color">Tiene pendientes por aprobar</h2>
        <div class="p-6">
          <p class="mb-4 text-sm text-sit-text">Los siguientes trámites están esperando su aprobación:</p>
          <ul class="space-y-3">
            <li v-for="item in pendientesVisibles" :key="item.key" class="flex items-center gap-3 text-sm text-sit-text">
              <span class="flex h-8 min-w-8 items-center justify-center rounded-full bg-sit-primary px-2 text-xs font-bold text-sit-on-color">{{ item.total }}</span>{{ item.label }}
            </li>
          </ul>
          <button ref="aceptar" @click="modalPendientes = false" class="sit-primary-action mt-6 w-full text-sm">Aceptar</button>
        </div>
      </section>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import api from '@/services/api'

const avisos = ref([])
const direccion = ref('horizontal')
const pendientes = ref({})
const modalPendientes = ref(false)
const aceptar = ref(null)
const controller = new AbortController()
onUnmounted(() => controller.abort())
const pendientesVisibles = computed(() => [
  { key: 'permisos', label: 'Permisos pendientes' },
  { key: 'vacaciones', label: 'Solicitudes de vacaciones pendientes' },
  { key: 'horas_extras', label: 'Planificaciones de horas extras pendientes' },
  { key: 'materiales', label: 'Solicitudes de materiales pendientes' },
].map(item => ({ ...item, total: Number(pendientes.value[item.key] || 0) })).filter(item => item.total > 0))

onMounted(async () => {
  const signal = controller.signal
  const mostrarPendientes = !!sessionStorage.getItem('show_pendientes')
  sessionStorage.removeItem('show_pendientes')
  await Promise.allSettled([
    api.get('/admin/avisos/activos', { signal }).then(({ data }) => {
      avisos.value = data.avisos || []
      direccion.value = data.direccion
    }),
    mostrarPendientes ? api.get('/dashboard/pendientes', { signal }).then(async ({ data }) => {
      pendientes.value = data
      modalPendientes.value = pendientesVisibles.value.length > 0
      await nextTick()
      aceptar.value?.focus()
    }) : Promise.resolve(),
  ])
})
</script>
