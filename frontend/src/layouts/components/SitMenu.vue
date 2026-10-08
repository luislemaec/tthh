<template>
  <component :is="vertical ? 'aside' : 'div'" ref="container" :inert="vertical && !expanded ? true : undefined" :tabindex="modal ? -1 : undefined" :role="modal ? 'dialog' : undefined" :aria-modal="modal ? true : undefined" :aria-label="modal ? 'Navegación' : undefined" :class="vertical ? ['sit-sidebar', { 'sit-sidebar--hidden': !expanded }] : 'sit-horizontal'" @keydown.esc="cerrarConTeclado" @keydown.tab="conservarFoco">
    <nav id="sit-menu" ref="menuElement" aria-label="Menú del sistema" :class="vertical ? 'sit-menu' : 'sit-horizontal__groups'">
      <p v-if="vertical && expanded" class="sit-menu__title">Opciones habilitadas</p>
      <div v-for="(items, categoria, index) in grupos" :key="categoria" :class="vertical ? 'sit-menu__group' : 'sit-horizontal__group'">
          <button type="button" class="sit-menu__category" :class="{ 'is-active': !vertical && items.some(i => i.url === activa) }" :aria-expanded="abierta(categoria)" :aria-controls="`sit-group-${index}`" @click="toggle(categoria, $event)" @keydown.down.prevent="abrirYEnfocar(categoria, $event)">
            <span>{{ categoria }}</span><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6" /></svg>
          </button>
          <div v-show="abierta(categoria)" :id="`sit-group-${index}`" :class="!vertical ? 'sit-horizontal__dropdown' : ''" :style="!vertical ? { left: `${dropdownOffset}px` } : undefined">
            <router-link v-for="item in items" :key="item.id || item.url" :to="'/' + item.url" class="sit-menu__link" :aria-current="item.url === activa ? 'page' : undefined" @click="navegar">
              <span class="sit-menu__dot" aria-hidden="true" /><span>{{ item.descripcion }}</span>
              <span v-if="item.url === 'adquisiciones/articulos' && alertasStock" class="sit-menu__badge" :aria-label="`${alertasStock} alertas de stock`">{{ alertasStock }}</span>
            </router-link>
          </div>
      </div>
      <p v-if="!Object.keys(grupos).length && (!vertical || expanded)" class="sit-menu__empty">No tienes opciones asignadas. Contacta al administrador.</p>
    </nav>
  </component>
  <button v-if="!vertical && dropdown" type="button" class="sit-menu-overlay" style="background:transparent" aria-label="Cerrar desplegable" tabindex="-1" @click="dropdown = null" />
</template>
<script setup>
import { nextTick, ref, watch } from 'vue'
const props = defineProps({ vertical: Boolean, expanded: Boolean, modal: Boolean, grupos: { type: Object, required: true }, activa: String, path: String, alertasStock: Number })
const emit = defineEmits(['navigate', 'close'])
const categorias = ref({})
const dropdown = ref(null)
const dropdownOffset = ref(0)
const menuElement = ref(null)
const container = ref(null)
let ultimoBoton
const abierta = categoria => props.vertical ? !!categorias.value[categoria] : dropdown.value === categoria
async function toggle(categoria, event) {
  ultimoBoton = event.currentTarget
  if (props.vertical) categorias.value[categoria] = !categorias.value[categoria]
  else {
    dropdown.value = dropdown.value === categoria ? null : categoria
    dropdownOffset.value = 0
    if (dropdown.value) {
      await nextTick()
      const ancho = ultimoBoton.nextElementSibling.getBoundingClientRect().width
      dropdownOffset.value = Math.min(0, window.innerWidth - 16 - ultimoBoton.getBoundingClientRect().left - ancho)
    }
  }
}
async function abrirYEnfocar(categoria, event) {
  const boton = event.currentTarget
  if (!abierta(categoria)) toggle(categoria, event)
  await nextTick()
  boton.nextElementSibling?.querySelector('a')?.focus()
}
function cerrarConTeclado() {
  if (!props.vertical && dropdown.value) { dropdown.value = null; ultimoBoton?.focus() }
  else emit('close')
}
function navegar() { dropdown.value = null; emit('navigate') }
function conservarFoco(event) {
  if (!props.modal) return
  const items = [...container.value.querySelectorAll('a, button')].filter(el => el.getClientRects().length)
  if (!items.length) { event.preventDefault(); return }
  const primero = items[0], ultimo = items.at(-1)
  if (event.shiftKey && document.activeElement === primero) { event.preventDefault(); ultimo?.focus() }
  else if (!event.shiftKey && document.activeElement === ultimo) { event.preventDefault(); primero?.focus() }
}
watch(() => props.path, () => { dropdown.value = null })
watch(() => props.vertical, () => { dropdown.value = null })
watch(() => [props.activa, props.grupos], () => {
  for (const [categoria, items] of Object.entries(props.grupos)) {
    if (items.some(item => item.url === props.activa)) categorias.value[categoria] = true
  }
}, { immediate: true })
defineExpose({ focusFirst: () => (menuElement.value?.querySelector('button, a') || container.value)?.focus(), closeDropdown: () => { dropdown.value = null } })
</script>
