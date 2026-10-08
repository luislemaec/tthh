<template>
  <header class="sit-topbar" :class="{ 'sit-topbar--account-open': cuentaAbierta }">
    <div class="sit-topbar__navigation">
      <button v-if="vertical" ref="menuButton" type="button" class="sit-icon-button sit-topbar__menu-toggle" :aria-label="expanded ? 'Ocultar menú' : 'Mostrar menú'" :title="expanded ? 'Ocultar menú' : 'Mostrar menú'" aria-controls="sit-menu" :aria-expanded="expanded" @click="$emit('toggle-sidebar')">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="mobile ? (expanded ? 'm6 15 6-6 6 6' : 'm6 9 6 6 6-6') : (expanded ? 'm15 6-6 6 6 6' : 'm9 6 6 6-6 6')" /></svg>
      </button>
    </div>
    <router-link to="/dashboard" class="sit-topbar__brand" aria-label="SIT — Inicio">
      <img src="@/assets/LOGOS-CONSEJOBLANCOH.png" alt="Consejo de Comunicación" class="sit-topbar__logo" />
      <span class="sit-topbar__brand-text"><span class="sit-topbar__name">SIT</span><span class="sit-topbar__subtitle">Sistema Integral Tecnológico</span></span>
    </router-link>
    <div class="sit-topbar__actions">
      <button ref="preferencesButton" type="button" class="sit-icon-button" title="Preferencias de visualización" aria-label="Preferencias de visualización" aria-haspopup="dialog" aria-controls="sit-preferences" :aria-expanded="preferencesOpen" @click="abrirPreferencias">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m10 3-.5 2a8 8 0 0 0-1.8 1L5.8 5.5l-2 3.5 1.4 1.5a8 8 0 0 0 0 2L3.8 14l2 3.5 1.9-.5a8 8 0 0 0 1.8 1l.5 2h4l.5-2a8 8 0 0 0 1.8-1l1.9.5 2-3.5-1.4-1.5a8 8 0 0 0 0-2L20.2 10l-2-3.5-1.9.5a8 8 0 0 0-1.8-1L14 3h-4Z" /><circle cx="12" cy="12" r="3" stroke-width="1.8" /></svg>
      </button>
      <div ref="cuenta" class="sit-account" @keydown.esc.stop.prevent="cerrarCuenta(true)" @focusout="salirDeCuenta">
        <button ref="cuentaButton" type="button" class="sit-profile" aria-label="Menú de usuario" :title="nombre || 'Mi cuenta'" aria-controls="sit-account-options" :aria-expanded="cuentaAbierta" @click="toggleCuenta" @keydown.down.prevent="abrirCuenta()" @keydown.up.prevent="abrirCuenta(true)">
          <span class="sit-profile__text"><span class="sit-profile__name">{{ nombre }}</span><span class="sit-profile__label" :title="rolesTexto">{{ rolesTexto || 'Mi cuenta' }}</span></span>
          <span class="sit-avatar"><img v-if="foto" :src="foto" alt="" /><span v-else>{{ iniciales }}</span></span>
          <svg class="sit-account__chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6" /></svg>
        </button>
        <nav v-if="cuentaAbierta" id="sit-account-options" ref="cuentaOpciones" class="sit-account__options" aria-label="Opciones de usuario" @keydown="navegarCuenta">
          <div class="sit-account__identity"><strong>{{ nombre || 'Mi cuenta' }}</strong><span v-if="rolesTexto">{{ rolesTexto }}</span></div>
          <router-link to="/perfil" class="sit-account__option" @click="cerrarCuenta()">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-width="1.8" d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM5 21v-2a7 7 0 0 1 14 0v2" /></svg>Perfil
          </router-link>
          <button type="button" class="sit-account__option sit-account__option--logout" @click="cerrarSesion">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 16l4-4-4-4M21 12H8m4-8H5v16h7" /></svg>Cerrar sesión
          </button>
        </nav>
      </div>
    </div>
  </header>
</template>
<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
const props = defineProps({ vertical: Boolean, mobile: Boolean, expanded: Boolean, preferencesOpen: Boolean, nombre: String, roles: { type: Array, default: () => [] }, iniciales: String, foto: String })
const emit = defineEmits(['toggle-sidebar', 'open-account', 'open-preferences', 'logout'])
const rolesTexto = computed(() => props.roles.join(' · '))
const route = useRoute()
const menuButton = ref(null)
const preferencesButton = ref(null)
const cuenta = ref(null)
const cuentaButton = ref(null)
const cuentaOpciones = ref(null)
const cuentaAbierta = ref(false)

function cerrarCuenta(devolverFoco = false) {
  cuentaAbierta.value = false
  if (devolverFoco) cuentaButton.value?.focus()
}
async function abrirCuenta(ultima = false) {
  emit('open-account')
  // El cierre del menú móvil actualiza expanded; esperar antes de abrir la cuenta.
  await nextTick()
  cuentaAbierta.value = true
  await nextTick()
  const opciones = cuentaOpciones.value?.querySelectorAll('a, button')
  opciones?.[ultima ? opciones.length - 1 : 0]?.focus()
}
function toggleCuenta() { if (cuentaAbierta.value) cerrarCuenta(); else abrirCuenta() }
function salirDeCuenta(event) { if (!cuenta.value?.contains(event.relatedTarget)) cerrarCuenta() }
function clickFuera(event) { if (!cuenta.value?.contains(event.target)) cerrarCuenta() }
function navegarCuenta(event) {
  if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return
  event.preventDefault()
  const opciones = [...cuentaOpciones.value.querySelectorAll('a, button')]
  const actual = opciones.indexOf(document.activeElement)
  const siguiente = event.key === 'Home' ? 0 : event.key === 'End' ? opciones.length - 1 : (actual + (event.key === 'ArrowDown' ? 1 : -1) + opciones.length) % opciones.length
  opciones[siguiente]?.focus()
}
function cerrarSesion() { cerrarCuenta(true); emit('logout') }
function abrirPreferencias() { cerrarCuenta(); emit('open-preferences') }
watch(() => route.fullPath, () => cerrarCuenta())
watch(() => [props.vertical, props.expanded], () => cerrarCuenta())
onMounted(() => document.addEventListener('pointerdown', clickFuera))
onUnmounted(() => document.removeEventListener('pointerdown', clickFuera))
defineExpose({ focusMenu: () => menuButton.value?.focus(), focusPreferences: () => preferencesButton.value?.focus() })
</script>
