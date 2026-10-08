<template>
  <Teleport to="body">
    <dialog id="sit-preferences" ref="panel" class="sit-configurator" aria-labelledby="sit-preferences-title" aria-describedby="sit-preferences-description" @cancel.prevent="$emit('close')" @close="$emit('close')" @click="clickExterior" @keydown.tab="conservarFoco">
      <header class="sit-configurator__header">
        <div>
          <p class="sit-configurator__eyebrow">SIT · Tema Tribunal</p>
          <h2 id="sit-preferences-title">Preferencias de visualización</h2>
        </div>
        <button ref="cerrar" type="button" class="sit-configurator__close" aria-label="Cerrar preferencias" @click="$emit('close')">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-width="2" d="m6 6 12 12M6 18 18 6" /></svg>
        </button>
      </header>
      <div class="sit-configurator__content">
        <p id="sit-preferences-description" class="sit-configurator__description">Ajusta tu espacio de trabajo. Los cambios se guardan en este navegador.</p>
        <fieldset class="sit-configurator__section">
          <legend>Distribución del menú</legend>
          <div class="sit-configurator__modes">
            <label v-for="modo in modos" :key="modo.value" class="sit-configurator__mode" :class="{ 'is-selected': preferencias.menuMode === modo.value }">
              <input type="radio" name="sit-menu-mode" :value="modo.value" :checked="preferencias.menuMode === modo.value" @change="cambiarMenu(modo.value)" />
              <svg class="sit-configurator__preview" fill="none" stroke="currentColor" viewBox="0 0 64 44" aria-hidden="true">
                <rect x="2" y="2" width="60" height="40" rx="3" stroke-width="1.5" />
                <path d="M2 10h60" />
                <path v-if="modo.value === 'vertical'" d="M18 10v32M7 17h6m-6 6h6m-6 6h6M25 18h29m-29 7h23m-23 7h26" />
                <path v-else d="M2 19h60M8 14h10m6 0h10m6 0h10M10 26h43m-43 7h32" />
              </svg>
              <span>{{ modo.label }}</span>
            </label>
          </div>
        </fieldset>
        <section class="sit-configurator__section" aria-labelledby="sit-font-label">
          <h3 id="sit-font-label">Tamaño de texto</h3>
          <div class="sit-configurator__font" role="group" aria-labelledby="sit-font-label">
            <button type="button" aria-label="Reducir tamaño de texto" :disabled="preferencias.fontScale === FONT_SCALES[0]" @click="cambiarTamano(-1)">A−</button>
            <output aria-live="polite" aria-atomic="true"><strong>{{ etiquetaTamano }}</strong><span>{{ porcentaje }} %</span></output>
            <button type="button" aria-label="Aumentar tamaño de texto" :disabled="preferencias.fontScale === FONT_SCALES.at(-1)" @click="cambiarTamano(1)">A+</button>
          </div>
          <p class="sit-configurator__sample">Texto de ejemplo para lectura diaria.</p>
        </section>
        <button type="button" class="sit-configurator__reset" @click="restablecer">Restablecer preferencias</button>
        <p class="sit-configurator__note">Vuelve al menú vertical y al tamaño normal.</p>
      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useSitPreferences } from '@/composables/useSitPreferences'
import { FONT_SCALES } from '@/services/preferencias'

const emit = defineEmits(['close'])
const { preferencias, cambiarMenu, cambiarTamano, restablecer } = useSitPreferences()
const panel = ref(null)
const cerrar = ref(null)
const modos = [{ value: 'vertical', label: 'Vertical' }, { value: 'horizontal', label: 'Horizontal' }]
const etiquetaTamano = computed(() => ({ 100: 'Normal', 112.5: 'Ampliado', 125: 'Grande' })[preferencias.value.fontScale])
const porcentaje = computed(() => String(preferencias.value.fontScale).replace('.', ','))

function clickExterior(event) {
  if (event.target !== panel.value) return
  const rect = panel.value.getBoundingClientRect()
  if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) emit('close')
}

function conservarFoco(event) {
  const items = [...panel.value.querySelectorAll('button:not(:disabled), input:checked')]
  const primero = items[0], ultimo = items.at(-1)
  if (event.shiftKey && document.activeElement === primero) { event.preventDefault(); ultimo?.focus() }
  else if (!event.shiftKey && document.activeElement === ultimo) { event.preventDefault(); primero?.focus() }
}

onMounted(() => { panel.value.showModal(); cerrar.value.focus() })
onBeforeUnmount(() => panel.value?.close())
</script>
