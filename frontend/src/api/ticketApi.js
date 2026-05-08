import client from './client'

export const getTickets = (filters = {}) => {
  const params = new URLSearchParams()
  if (filters.status)   params.append('status',   filters.status)
  if (filters.priority) params.append('priority', filters.priority)
  if (filters.search)   params.append('search',   filters.search)
  return client.get('/api/tickets?' + params.toString())
}

export const getTicket = (id) => client.get('/api/tickets/' + id)

export const createTicket = (data) => client.post('/api/tickets', data)

export const updateStatus = (id, status) =>
  client.patch('/api/tickets/' + id + '/status', { status })

export const updatePriority = (id, priority) =>
  client.patch('/api/tickets/' + id + '/priority', { priority })

export const addComment = (id, content) =>
  client.post('/api/tickets/' + id + '/comments', { content })
