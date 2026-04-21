import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  withCredentials: false,
  withXSRFToken: false,
})

api.interceptors.request.use(cfg => {
  const token = localStorage.getItem('token')
  if (token) cfg.headers.Authorization = `Bearer ${token}`
  delete cfg.headers['X-XSRF-TOKEN']
  return cfg
})

api.interceptors.response.use(
  res => res,
  err => {
    if (err.response?.status === 401 && !err.config.url.includes('/login')) {
      localStorage.removeItem('token')
      window.location.href = '/login'
    }
    return Promise.reject(err)
  }
)

export default api
