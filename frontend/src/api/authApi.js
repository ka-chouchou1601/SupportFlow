import client from './client'

export const login = (email, password) =>
  client.post('/api/login', { email, password })

export const logout = () => {
  localStorage.removeItem('user')
}
