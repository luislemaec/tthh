// ============================================================
// src/stores/auth.js  — Pinia store de autenticación
// ============================================================
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'
import { menuSistema, agruparMenu } from '@/services/navegacion'

export const useAuthStore = defineStore('auth', () => {
  const token    = ref(sessionStorage.getItem('token') || null)
  const empleado = ref(JSON.parse(sessionStorage.getItem('empleado') || 'null'))
  const roles    = ref(JSON.parse(sessionStorage.getItem('roles')    || '[]'))
  const menu     = ref(JSON.parse(sessionStorage.getItem('menu')     || '[]'))
  const expiresAt = ref(sessionStorage.getItem('expires_at'))
  let expiryTimer

  function programarExpiracion(fecha) {
    expiresAt.value = fecha
    clearTimeout(expiryTimer)
    const restante = Date.parse(fecha) - Date.now()
    if (!Number.isFinite(restante)) return
    expiryTimer = setTimeout(() => {
      token.value = null; empleado.value = null; roles.value = []; menu.value = []
      sessionStorage.clear()
      expiresAt.value = null
      window.location.assign('/login')
    }, Math.max(0, restante))
  }
  const expiraGuardado = sessionStorage.getItem('expires_at')
  if (token.value && expiraGuardado) programarExpiracion(expiraGuardado)

  const isAuthenticated = computed(() => !!token.value)
  const isAdmin = computed(() => roles.value.includes('ADMINISTRADOR'))
  const esSupervisor = computed(() => !!empleado.value?.es_supervisor)
  const tieneAdquisiciones = computed(() =>
    roles.value.includes('ADQUISICIONES') || roles.value.includes('BIENES') || esSupervisor.value
  )

  const opcionesMenu = computed(() => menuSistema(menu.value, empleado.value))
  const menuAgrupado = computed(() => agruparMenu(opcionesMenu.value))

  async function login(identificacion, password) {
    const { data } = await api.post('/login', { identificacion, password })

    token.value    = data.token
    empleado.value = data.empleado
    roles.value    = data.roles
    menu.value     = data.menu

    sessionStorage.setItem('token',    data.token)
    sessionStorage.setItem('empleado', JSON.stringify(data.empleado))
    sessionStorage.setItem('roles',    JSON.stringify(data.roles))
    sessionStorage.setItem('menu',     JSON.stringify(data.menu))
    sessionStorage.setItem('expires_at', data.expires_at)
    programarExpiracion(data.expires_at)

    return data
  }

  async function logout() {
    clearTimeout(expiryTimer)
    try { await api.post('/logout') } catch {}
    token.value = null; empleado.value = null
    expiresAt.value = null
    roles.value = [];   menu.value = []
    sessionStorage.clear()
  }

  function tieneRol(rol) {
    return roles.value.includes(rol.toUpperCase())
  }

  return { token, empleado, roles, menu, expiresAt, isAuthenticated, isAdmin,
           esSupervisor, tieneAdquisiciones, opcionesMenu, menuAgrupado, login, logout, tieneRol }
})
