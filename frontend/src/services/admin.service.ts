import api from './api'

export const adminService = {
  async dashboard() {
    const { data } = await api.get('/admin/dashboard')
    return data
  },

  async customers(params: Record<string, unknown> = {}) {
    const { data } = await api.get('/admin/customers', { params })
    return data
  },

  async customer(userId: number) {
    const { data } = await api.get(`/admin/customers/${userId}`)
    return data.data
  },

  async activityLogs(params: Record<string, unknown> = {}) {
    const { data } = await api.get('/admin/activity-logs', { params })
    return data
  },
}
