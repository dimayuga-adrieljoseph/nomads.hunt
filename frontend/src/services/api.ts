/**
 * Axios instance — all API calls go through here.
 * Token is read from localStorage on every request via the interceptor.
 */
import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  withCredentials: false,
})

// Attach Bearer token if present
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// On 401 — clear stale token (don't redirect here; router guard handles it)
api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401) {
      localStorage.removeItem('auth_token')
      localStorage.removeItem('auth_user')
    }
    return Promise.reject(err)
  },
)

export default api
