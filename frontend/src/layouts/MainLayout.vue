<template>
  <div class="sit-shell" :class="{ 'sit-shell--ambiente': mostrarAmbiente }">
    <a href="#sit-contenido" class="sit-skip">Ir al contenido</a>
    <SitTopbar ref="topbar" :vertical="vertical" :mobile="movil" :expanded="sidebarOpen" :preferences-open="configuradorAbierto" :nombre="nombre" :roles="auth.roles" :iniciales="iniciales" :foto="fotoEmpleado" @toggle-sidebar="toggleSidebar" @open-account="prepararCuenta" @open-preferences="abrirConfigurador" @logout="handleLogout" />
    <div v-if="mostrarAmbiente" class="sit-ambiente" role="status">{{ etiquetaAmbiente }}</div>
    <!-- El contenido conserva su instancia al cambiar la orientación del menú. -->
    <div class="sit-shell__body" :class="{ 'flex-col': !vertical }">
      <SitMenu ref="menu" :vertical="vertical" :expanded="sidebarOpen" :modal="vertical && movil && sidebarOpen" :grupos="auth.menuAgrupado" :activa="contexto.opcion?.url" :path="route.path" :alertas-stock="alertasStock" @navigate="cerrarMenuMovil" @close="cerrarMenu" />
      <button v-if="vertical && movil && sidebarOpen" type="button" class="sit-menu-overlay" tabindex="-1" aria-label="Cerrar menú lateral" @click="cerrarMenu" />
      <div ref="workspace" class="sit-shell__workspace" :inert="vertical && movil && sidebarOpen ? true : undefined">
        <SitBreadcrumb :items="contexto.migas" />
        <main id="sit-contenido" tabindex="-1" class="sit-shell__main">
          <ContenidoSistema />
        </main>
        <SitFooter />
        <SitScrollTop v-if="workspace" :target="workspace" />
      </div>
    </div>
    <ChatbotFAB />
    <SitConfigurator v-if="configuradorAbierto" @close="cerrarConfigurador" />
  </div>
</template>

<script setup>
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useAlertasSistema } from '@/composables/useAlertasSistema'
import { useSitPreferences } from '@/composables/useSitPreferences'
import { contextoNavegacion } from '@/services/navegacion'
import { mostrarAmbiente, etiquetaAmbiente } from '@/services/ambiente'
import ChatbotFAB from '@/components/ChatbotFAB.vue'
import ContenidoSistema from '@/components/ContenidoSistema.vue'
import SitTopbar from './components/SitTopbar.vue'
import SitMenu from './components/SitMenu.vue'
import SitBreadcrumb from './components/SitBreadcrumb.vue'
import SitFooter from './components/SitFooter.vue'
import SitConfigurator from './components/SitConfigurator.vue'
import SitScrollTop from '@/components/SitScrollTop.vue'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const { alertasStock } = useAlertasSistema(auth, router)
const media = window.matchMedia('(max-width: 991.98px)')
const movil = ref(media.matches)
const sidebarOpen = ref(!movil.value)
const topbar = ref(null)
const workspace = ref(null)
const menu = ref(null)
const configuradorAbierto = ref(false)
const { preferencias } = useSitPreferences()
// En móvil ambos modos utilizan un panel lateral, sin sobrescribir la preferencia.
const vertical = computed(() => movil.value || preferencias.value.menuMode === 'vertical')
const contexto = computed(() => contextoNavegacion(route, auth.opcionesMenu))
const nombre = computed(() => [auth.empleado?.apellido, auth.empleado?.nombre].filter(Boolean).join(' '))
const iniciales = computed(() => ((auth.empleado?.nombre?.[0] || '') + (auth.empleado?.apellido?.[0] || '')).toUpperCase())
const fotoEmpleado = computed(() => auth.empleado?.foto ? `${import.meta.env.VITE_API_URL}/storage-file/${auth.empleado.foto}` : undefined)

function actualizarViewport(event) { movil.value = event.matches; sidebarOpen.value = !event.matches }
media.addEventListener('change', actualizarViewport)
onUnmounted(() => media.removeEventListener('change', actualizarViewport))
async function toggleSidebar() {
  sidebarOpen.value = !sidebarOpen.value
  if (movil.value && sidebarOpen.value) { await nextTick(); menu.value?.focusFirst() }
}
function cerrarMenu() { sidebarOpen.value = false; topbar.value?.focusMenu() }
function cerrarMenuMovil() { if (movil.value) sidebarOpen.value = false }
function prepararCuenta() { cerrarMenuMovil(); menu.value?.closeDropdown() }
watch(vertical, () => { sidebarOpen.value = !movil.value })
function abrirConfigurador() { cerrarMenuMovil(); menu.value?.closeDropdown(); configuradorAbierto.value = true }
async function cerrarConfigurador() {
  if (!configuradorAbierto.value) return
  configuradorAbierto.value = false
  await nextTick()
  topbar.value?.focusPreferences()
}
watch(() => route.path, cerrarMenuMovil)
watch(() => route.path, () => { if (configuradorAbierto.value) cerrarConfigurador() })
watch(() => contexto.value.titulo, titulo => { document.title = `${titulo} · SIT` }, { immediate: true })
async function handleLogout() { await auth.logout(); router.push('/login') }
</script>
