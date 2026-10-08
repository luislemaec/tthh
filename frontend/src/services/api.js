import axios from 'axios'
import { iniciarOperacion } from './ui.js'

const api = axios.create({
  baseURL: import.meta.env?.VITE_API_URL || 'http://localhost:8000/api',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  withCredentials: false,
  withXSRFToken: false,
})

api.interceptors.request.use(cfg => {
  const token = sessionStorage.getItem('token')
  if (token) cfg.headers.Authorization = `Bearer ${token}`
  delete cfg.headers['X-XSRF-TOKEN']
  if (cfg.data instanceof FormData) {
    delete cfg.headers['Content-Type']
  }
  if (!cfg.sitBackground) cfg._sitFinish = iniciarOperacion()
  return cfg
})

api.interceptors.response.use(
  res => { res.config._sitFinish?.(); return res },
  err => {
    err.config?._sitFinish?.()
    if (err.response?.status === 401 && !err.config.url.includes('/login')) {
      sessionStorage.clear()
      window.location.href = '/login'
    }
    return Promise.reject(err)
  }
)

export default api
