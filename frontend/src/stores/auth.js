// ============================================================
// src/stores/auth.js  — Pinia store de autenticación
// ============================================================
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const useAuthStore = defineStore('auth', () => {
  const token    = ref(sessionStorage.getItem('token') || null)
  const empleado = ref(JSON.parse(sessionStorage.getItem('empleado') || 'null'))
  const roles    = ref(JSON.parse(sessionStorage.getItem('roles')    || '[]'))
  const menu     = ref(JSON.parse(sessionStorage.getItem('menu')     || '[]'))
  let expiryTimer

  function programarExpiracion(fecha) {
    clearTimeout(expiryTimer)
    const restante = Date.parse(fecha) - Date.now()
    if (!Number.isFinite(restante)) return
    expiryTimer = setTimeout(() => {
      token.value = null; empleado.value = null; roles.value = []; menu.value = []
      sessionStorage.clear()
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

  // Menú agrupado por categoría
  const menuAgrupado = computed(() => {
    const vistas = new Set()
    return menu.value.reduce((acc, item) => {
      if (vistas.has(item.url)) return acc
      vistas.add(item.url)
      if (!acc[item.categoria]) acc[item.categoria] = []
      acc[item.categoria].push(item)
      return acc
    }, {})
  })

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
    roles.value = [];   menu.value = []
    sessionStorage.clear()
  }

  function tieneRol(rol) {
    return roles.value.includes(rol.toUpperCase())
  }

  return { token, empleado, roles, menu, isAuthenticated, isAdmin,
           esSupervisor, tieneAdquisiciones, menuAgrupado, login, logout, tieneRol }
})
