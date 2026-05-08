import axios from 'axios'

const client = axios.create({
  // Use 127.0.0.1 (IPv4) instead of localhost: on Windows, localhost resolves
  // to ::1 (IPv6) first, which is intercepted by wslrelay and returns an empty response.
  baseURL: 'http://127.0.0.1:8000',
})

// Attach Content-Type and auth token from localStorage on every request
client.interceptors.request.use((config) => {
  config.headers['Content-Type'] = 'application/json'

  const stored = localStorage.getItem('user')
  if (stored) {
    try {
      const user = JSON.parse(stored)
      if (user?.token) {
        config.headers['Authorization'] = `Bearer ${user.token}`
      }
    } catch {
      // malformed localStorage entry — ignore
    }
  }

  return config
})

// On 401, clear session and redirect to login
client.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('user')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default client
