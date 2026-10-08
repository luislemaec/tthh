<template>
  <p v-if="cargando" role="status" class="py-10 text-center text-sm text-gray-500">Cargando…</p>
  <section v-else-if="mantenimiento && !exento" class="mx-auto max-w-lg rounded-xl border border-amber-200 bg-white p-8 text-center shadow-sm" aria-labelledby="titulo-mantenimiento">
    <h1 id="titulo-mantenimiento" class="text-xl font-bold text-gray-800">Sección en mantenimiento</h1>
    <p class="mt-3 text-sm text-gray-600">Esta sección está temporalmente en mantenimiento. Puedes utilizar las demás opciones habilitadas en el menú.</p>
    <router-link to="/perfil" class="sit-primary-action mt-6">Ir a mi perfil</router-link>
  </section>
  <template v-else>
    <p v-if="mantenimiento" role="status" class="sit-message sit-message--warn mb-4">Esta sección está en mantenimiento. Tu rol permite seguir trabajando.</p>
    <router-view />
  </template>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import { puedeTrabajarEnMantenimiento } from '@/services/navegacion'

const route = useRoute()
const auth = useAuthStore()
const cargando = ref(false)
const mantenimiento = ref(false)
const exento = computed(() => puedeTrabajarEnMantenimiento(route.meta.modulo, auth.roles))

watch(() => route.meta.modulo, async (modulo, _anterior, onCleanup) => {
  const controller = new AbortController()
  onCleanup(() => controller.abort())
  mantenimiento.value = false
  cargando.value = !!modulo
  if (!modulo) return
  try {
    const { data } = await api.get('/modo-mantenimiento', { params: { modulo }, signal: controller.signal })
    if (!controller.signal.aborted) mantenimiento.value = !!data.activo
  } catch {
    // Conserva el comportamiento actual si la comprobación no está disponible.
  } finally {
    if (!controller.signal.aborted) cargando.value = false
  }
}, { immediate: true })
</script>
