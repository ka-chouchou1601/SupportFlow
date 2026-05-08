import { useState } from 'react'

function initials(name) {
  return (name ?? '?').charAt(0).toUpperCase()
}

function formatDate(iso) {
  return new Date(iso).toLocaleString('fr-FR')
}

export default function CommentList({ comments, onAddComment }) {
  const [content, setContent] = useState('')
  const [error, setError]     = useState('')
  const [loading, setLoading] = useState(false)

  async function handleSubmit(e) {
    e.preventDefault()
    if (!content.trim()) return
    setLoading(true)
    setError('')
    try {
      await onAddComment(content.trim())
      setContent('')
    } catch {
      setError('Impossible d\'ajouter le commentaire.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div>
      <h2 className="font-semibold text-gray-900 mb-4">Commentaires</h2>

      {comments.length === 0 ? (
        <p className="text-sm text-gray-400 mb-6">Aucun commentaire pour l'instant.</p>
      ) : (
        <ul className="space-y-4 mb-6">
          {comments.map((c) => (
            <li key={c.id} className="flex gap-3">
              <div className="shrink-0 w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-sm font-semibold">
                {initials(c.author?.name)}
              </div>
              <div className="flex-1">
                <div className="flex items-baseline gap-2 mb-1">
                  <span className="text-sm font-medium text-gray-900">{c.author?.name ?? 'Inconnu'}</span>
                  <span className="text-xs text-gray-400">{formatDate(c.createdAt)}</span>
                </div>
                <p className="text-sm text-gray-700">{c.content}</p>
              </div>
            </li>
          ))}
        </ul>
      )}

      <form onSubmit={handleSubmit} className="space-y-2">
        <textarea
          value={content}
          onChange={(e) => setContent(e.target.value)}
          placeholder="Ajouter un commentaire..."
          rows={3}
          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
        {error && <p className="text-red-500 text-xs">{error}</p>}
        <button
          type="submit"
          disabled={!content.trim() || loading}
          className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-lg text-sm font-medium transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
        >
          {loading ? 'Publication…' : 'Publier'}
        </button>
      </form>
    </div>
  )
}
