import api from './api'

export type AnnouncementStatus = 'DRAFT' | 'PUBLISHED' | 'ARCHIVED'
export type AnnouncementDisplayStatus =
  | AnnouncementStatus
  | 'ACTIVE'
  | 'SCHEDULED'
  | 'EXPIRED'

export interface PublicAnnouncement {
  id: number
  title: string
  label: string | null
  description: string | null
  event_date: string | null
  event_time: string | null
  location: string | null
  image_url: string | null
}

export interface Announcement extends PublicAnnouncement {
  status: AnnouncementStatus
  display_status: AnnouncementDisplayStatus
  priority: number
  starts_at: string | null
  ends_at: string | null
  is_eligible: boolean
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface AnnouncementPayload {
  title: string
  label: string | null
  description: string | null
  event_date: string | null
  event_time: string | null
  location: string | null
  status: AnnouncementStatus
  priority: number
  starts_at: string | null
  ends_at: string | null
}

export interface PaginatedAnnouncements {
  data: Announcement[]
  meta: { current_page: number; last_page: number; total: number }
  links: unknown
}

function appendPayload(form: FormData, payload: AnnouncementPayload): void {
  Object.entries(payload).forEach(([key, value]) => {
    if (value !== null && value !== undefined) {
      form.append(key, String(value))
    }
  })
}

function announcementForm(
  payload: AnnouncementPayload,
  file: File | null,
  removeImage: boolean,
): FormData {
  const form = new FormData()
  appendPayload(form, payload)
  if (file) form.append('image', file)
  if (removeImage) form.append('remove_image', '1')
  return form
}

export const announcementService = {
  async active() {
    const { data } = await api.get('/announcements/active')
    return data.data as PublicAnnouncement | null
  },

  async adminList(params: Record<string, unknown> = {}) {
    const { data } = await api.get('/admin/announcements', { params })
    return data as PaginatedAnnouncements
  },

  async adminGet(id: number) {
    const { data } = await api.get(`/admin/announcements/${id}`)
    return data.data as Announcement
  },

  async adminCreate(payload: AnnouncementPayload, file: File | null = null) {
    const form = announcementForm(payload, file, false)
    const { data } = await api.post('/admin/announcements', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data.data as Announcement
  },

  async adminUpdate(
    id: number,
    payload: AnnouncementPayload,
    file: File | null = null,
    removeImage = false,
  ) {
    const form = announcementForm(payload, file, removeImage)
    const { data } = await api.put(`/admin/announcements/${id}`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data.data as Announcement
  },

  async adminAction(
    id: number,
    action: 'publish' | 'unpublish' | 'archive',
  ) {
    const { data } = await api.post(`/admin/announcements/${id}/${action}`)
    return data.data as Announcement
  },

  async adminDelete(id: number) {
    await api.delete(`/admin/announcements/${id}`)
  },

  async adminUploadImage(id: number, file: File) {
    const form = new FormData()
    form.append('image', file)
    const { data } = await api.post(`/admin/announcements/${id}/upload-image`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data.data as Announcement
  },
}
