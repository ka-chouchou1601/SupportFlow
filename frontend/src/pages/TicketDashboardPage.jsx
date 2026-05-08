import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { getTickets } from '../api/ticketApi'
import { logout } from '../api/authApi'
import TicketCard from '../components/TicketCard'
import TicketFilters from '../components/TicketFilters'
import StatCard from '../components/StatCard'

export default function TicketDashboardPage() {
  const navigate = useNavigate()
  const user = JSON.parse(localStorage.getItem('user'))

  const [tickets, setTickets] = useState([])
  const [filters, setFilters] = useState({})
  const [loading, setLoading] = useState(true)
  const [error, setError]     = useState('')

  useEffect(() => {
    setLoading(true)
    setError('')
    getTickets(filters)
      .then((res) => setTickets(res.data))
      .catch(() => setError('Impossible de charger les tickets.'))
      .finally(() => setLoading(false))
  }, [filters])

  function handleLogout() {
    logout()
    navigate('/login')
  }

  const count = (status) => tickets.filter((t) => t.status === status).length

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <header className="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
        <h1 className="text-xl font-bold text-blue-600">SupportFlow</h1>
        <div className="flex items-center gap-4 text-sm text-gray-600">
          <span>{user?.name}</span>
          <button
            onClick={handleLogout}
            className="text-gray-500 hover:text-red-600 transition-colors"
          >
            Déconnexion
          </button>
        </div>
      </header>

      <main className="max-w-7xl mx-auto px-6 py-8">
        {/* Title row */}
        <div className="flex items-center justify-between mb-6">
          <h2 className="text-2xl font-bold text-gray-900">Tableau de bord</h2>
          <button
            onClick={() => navigate('/tickets/new')}
            className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
          >
            + Nouveau ticket
          </button>
        </div>

        {/* Stats */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
          <StatCard title="Total tickets" count={tickets.length}         color="blue"   />
          <StatCard title="Nouveau"        count={count('Nouveau')}       color="yellow" />
          <StatCard title="En cours"       count={count('En cours')}      color="blue"   />
          <StatCard title="Résolu"         count={count('Résolu')}        color="green"  />
        </div>

        {/* Filters */}
        <TicketFilters filters={filters} onChange={setFilters} />

        {/* States */}
        {loading && (
          <p className="text-gray-400 text-sm py-8 text-center">Chargement...</p>
        )}
        {!loading && error && (
          <p className="text-red-600 text-sm">{error}</p>
        )}
        {!loading && !error && tickets.length === 0 && (
          <p className="text-gray-500 text-sm py-8 text-center">
            Aucun ticket trouvé pour ces critères.
          </p>
        )}

        {/* Ticket grid */}
        {!loading && tickets.length > 0 && (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {tickets.map((ticket) => (
              <TicketCard key={ticket.id} ticket={ticket} />
            ))}
          </div>
        )}
      </main>
    </div>
  )
}
