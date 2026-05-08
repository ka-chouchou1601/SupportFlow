import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { createTicket } from '../api/ticketApi'

const CATEGORIES = ['', 'Bug', 'Demande utilisateur', 'Amélioration', 'Incident', 'Autre']
const PRIORITIES = ['', 'Basse', 'Moyenne', 'Haute', 'Urgente']

export default function CreateTicketPage() {
  const navigate = useNavigate()

  const [form, setForm]       = useState({ title: '', description: '', category: '', priority: '' })
  const [errors, setErrors]   = useState({})
  const [apiError, setApiError] = useState('')
  const [loading, setLoading] = useState(false)

  function update(field, value) {
    setForm((prev) => ({ ...prev, [field]: value }))
    setErrors((prev) => ({ ...prev, [field]: undefined }))
  }

  function validate() {
    const e = {}
    if (!form.title.trim())       e.title       = 'Le titre est obligatoire'
    if (!form.description.trim()) e.description = 'La description est obligatoire'
    if (!form.category)           e.category    = 'Choisissez une catégorie'
    if (!form.priority)           e.priority    = 'Choisissez une priorité'
    return e
  }

  async function handleSubmit(e) {
    e.preventDefault()
    const e_ = validate()
    if (Object.keys(e_).length) { setErrors(e_); return }

    setLoading(true)
    setApiError('')
    try {
      const res = await createTicket(form)
      navigate('/tickets/' + res.data.id)
    } catch (err) {
      if (err.response?.status === 422) {
        setErrors(err.response.data.errors ?? {})
      } else {
        setApiError('Une erreur est survenue. Veuillez réessayer.')
      }
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-2xl mx-auto px-6 py-8">
        {/* Header */}
        <div className="flex items-center gap-4 mb-6">
          <button
            onClick={() => navigate(-1)}
            className="text-gray-500 hover:text-gray-700 text-sm"
          >
            ← Retour
          </button>
          <h1 className="text-2xl font-bold text-gray-900">Nouveau ticket</h1>
        </div>

        <form onSubmit={handleSubmit} className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">
          <Field label="Titre *" error={errors.title}>
            <input
              type="text"
              value={form.title}
              onChange={(e) => update('title', e.target.value)}
              placeholder="Ex : Erreur sur la page de connexion"
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </Field>

          <Field label="Description *" error={errors.description}>
            <textarea
              value={form.description}
              onChange={(e) => update('description', e.target.value)}
              placeholder="Décrivez le problème ou la demande..."
              rows={4}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </Field>

          <div className="grid grid-cols-2 gap-4">
            <Field label="Catégorie *" error={errors.category}>
              <select
                value={form.category}
                onChange={(e) => update('category', e.target.value)}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                {CATEGORIES.map((c) => (
                  <option key={c} value={c} disabled={c === ''}>{c || '— choisir —'}</option>
                ))}
              </select>
            </Field>

            <Field label="Priorité *" error={errors.priority}>
              <select
                value={form.priority}
                onChange={(e) => update('priority', e.target.value)}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                {PRIORITIES.map((p) => (
                  <option key={p} value={p} disabled={p === ''}>{p || '— choisir —'}</option>
                ))}
              </select>
            </Field>
          </div>

          {apiError && <p className="text-red-600 text-sm">{apiError}</p>}

          <div className="flex gap-3 pt-2">
            <button
              type="submit"
              disabled={loading}
              className="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg font-medium transition-colors disabled:opacity-50"
            >
              {loading ? 'Création…' : 'Créer le ticket'}
            </button>
            <button
              type="button"
              onClick={() => navigate(-1)}
              className="flex-1 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 py-2 rounded-lg font-medium transition-colors"
            >
              Annuler
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

function Field({ label, error, children }) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
      {children}
      {error && <p className="text-red-500 text-sm mt-1">{error}</p>}
    </div>
  )
}
