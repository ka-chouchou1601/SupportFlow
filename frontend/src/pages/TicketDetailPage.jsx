import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { getTicket, updateStatus, updatePriority, addComment } from '../api/ticketApi'
import TicketStatusBadge from '../components/TicketStatusBadge'
import TicketPriorityBadge from '../components/TicketPriorityBadge'
import CommentList from '../components/CommentList'
import HistoryTimeline from '../components/HistoryTimeline'

const PRIORITIES = ['Basse', 'Moyenne', 'Haute', 'Urgente']

// Valid next statuses for each current status
const TRANSITIONS = {
  'Nouveau':  ['En cours'],
  'En cours': ['Résolu', 'Nouveau'],
  'Résolu':   ['Fermé', 'En cours'],
  'Fermé':    [],
}

function formatDate(iso) {
  return new Date(iso).toLocaleDateString('fr-FR')
}

export default function TicketDetailPage() {
  const { id }    = useParams()
  const navigate  = useNavigate()

  const [ticket, setTicket]           = useState(null)
  const [loading, setLoading]         = useState(true)
  const [error, setError]             = useState('')
  const [successMessage, setSuccess]  = useState('')
  const [errorMessage, setErrMsg]     = useState('')
  const [newStatus, setNewStatus]     = useState('')
  const [newPriority, setNewPriority] = useState('')

  function showSuccess(msg) {
    setSuccess(msg)
    setErrMsg('')
    setTimeout(() => setSuccess(''), 3000)
  }

  function loadTicket() {
    return getTicket(id)
      .then((res) => {
        setTicket(res.data)
        setNewStatus('')
        setNewPriority('')
      })
      .catch(() => setError('Ticket introuvable.'))
      .finally(() => setLoading(false))
  }

  useEffect(() => { loadTicket() }, [id])

  async function handleSaveStatus() {
    if (!newStatus) return
    try {
      await updateStatus(id, newStatus)
      await loadTicket()
      showSuccess('Statut mis à jour.')
    } catch (err) {
      setErrMsg(err.response?.data?.error ?? 'Transition non autorisée.')
    }
  }

  async function handleSavePriority() {
    if (!newPriority) return
    try {
      await updatePriority(id, newPriority)
      await loadTicket()
      showSuccess('Priorité mise à jour.')
    } catch {
      setErrMsg('Erreur lors de la mise à jour de la priorité.')
    }
  }

  async function handleAddComment(content) {
    await addComment(id, content)
    await loadTicket()
  }

  if (loading) return <p className="p-8 text-gray-400 text-center">Chargement…</p>
  if (error)   return <p className="p-8 text-red-500 text-center">{error}</p>

  const validTransitions = TRANSITIONS[ticket.status] ?? []

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-6xl mx-auto px-6 py-8">
        {/* Back */}
        <button
          onClick={() => navigate(-1)}
          className="text-gray-500 hover:text-gray-700 text-sm mb-6 block"
        >
          ← Retour
        </button>

        <div className="grid lg:grid-cols-3 gap-6">
          {/* ── Left column (info + comments) ── */}
          <div className="lg:col-span-2 space-y-6">
            {/* Info card */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
              <h1 className="text-2xl font-bold text-gray-900 mb-3">{ticket.title}</h1>

              <div className="flex flex-wrap items-center gap-2 mb-4">
                <TicketStatusBadge status={ticket.status} />
                <TicketPriorityBadge priority={ticket.priority} />
                <span className="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
                  {ticket.category}
                </span>
              </div>

              <p className="text-gray-600 text-sm leading-relaxed mb-4">{ticket.description}</p>

              <div className="text-sm text-gray-500 space-y-0.5 border-t border-gray-100 pt-4">
                <p>Créé par <span className="font-medium">{ticket.createdBy?.name ?? 'Inconnu'}</span></p>
                <p>Créé le {formatDate(ticket.createdAt)} · Modifié le {formatDate(ticket.updatedAt)}</p>
              </div>
            </div>

            {/* Comments */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
              <CommentList
                comments={ticket.comments ?? []}
                onAddComment={handleAddComment}
              />
            </div>
          </div>

          {/* ── Right column (actions + history) ── */}
          <div className="space-y-6">
            {/* Actions card */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
              <h2 className="font-semibold text-gray-900 mb-4">Actions</h2>

              {/* Feedback messages */}
              {successMessage && (
                <p className="text-green-600 text-sm mb-4 bg-green-50 rounded-lg px-3 py-2">
                  {successMessage}
                </p>
              )}
              {errorMessage && (
                <p className="text-red-600 text-sm mb-4 bg-red-50 rounded-lg px-3 py-2">
                  {errorMessage}
                </p>
              )}

              {/* Status */}
              <div className="mb-5">
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Modifier le statut
                </label>
                {validTransitions.length === 0 ? (
                  <p className="text-xs text-gray-400 italic">Ticket fermé, aucune action possible.</p>
                ) : (
                  <div className="flex gap-2">
                    <select
                      value={newStatus}
                      onChange={(e) => setNewStatus(e.target.value)}
                      className="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                      <option value="">— choisir —</option>
                      {validTransitions.map((s) => (
                        <option key={s} value={s}>{s}</option>
                      ))}
                    </select>
                    <button
                      onClick={handleSaveStatus}
                      disabled={!newStatus}
                      className="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                      Enregistrer
                    </button>
                  </div>
                )}
              </div>

              {/* Priority */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Modifier la priorité
                </label>
                <div className="flex gap-2">
                  <select
                    value={newPriority}
                    onChange={(e) => setNewPriority(e.target.value)}
                    className="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="">— choisir —</option>
                    {PRIORITIES.filter((p) => p !== ticket.priority).map((p) => (
                      <option key={p} value={p}>{p}</option>
                    ))}
                  </select>
                  <button
                    onClick={handleSavePriority}
                    disabled={!newPriority}
                    className="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                  >
                    Enregistrer
                  </button>
                </div>
              </div>
            </div>

            {/* History */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
              <HistoryTimeline history={ticket.history ?? []} />
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
