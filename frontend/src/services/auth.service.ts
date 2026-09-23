import api from './api'

export interface AuthUser {
  id: number
  name: string
  email: string
  role: 'customer' | 'admin'
}

export const authService = {
  async register(name: string, email: string, password: string, password_confirmation: string) {
    const { data } = await api.post('/auth/register', { name, email, password, password_confirmation })
    return data as { user: AuthUser; token: string }
  },

  async login(email: string, password: string) {
    const { data } = await api.post('/auth/login', { email, password })
    return data as { user: AuthUser; token: string }
  },

  async logout() {
    await api.post('/auth/logout')
  },

  async me() {
    const { data } = await api.get('/auth/me')
    return data.user as AuthUser
  },
}
