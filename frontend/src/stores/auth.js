// ============================================================
// src/stores/auth.js  — Pinia store de autenticación
// ============================================================
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const useAuthStore = defineStore('auth', () => {
  const token    = ref(localStorage.getItem('token') || null)
  const empleado = ref(JSON.parse(localStorage.getItem('empleado') || 'null'))
  const roles    = ref(JSON.parse(localStorage.getItem('roles')    || '[]'))
  const menu     = ref(JSON.parse(localStorage.getItem('menu')     || '[]'))

  const isAuthenticated = computed(() => !!token.value)
  const isAdmin = computed(() => roles.value.includes('ADMINISTRADOR'))

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

    localStorage.setItem('token',    data.token)
    localStorage.setItem('empleado', JSON.stringify(data.empleado))
    localStorage.setItem('roles',    JSON.stringify(data.roles))
    localStorage.setItem('menu',     JSON.stringify(data.menu))

    return data
  }

  async function logout() {
    try { await api.post('/logout') } catch {}
    token.value = null; empleado.value = null
    roles.value = [];   menu.value = []
    localStorage.clear()
  }

  function tieneRol(rol) {
    return roles.value.includes(rol.toUpperCase())
  }

  return { token, empleado, roles, menu, isAuthenticated, isAdmin,
           menuAgrupado, login, logout, tieneRol }
})
