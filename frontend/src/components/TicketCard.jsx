import { Link } from 'react-router-dom'
import TicketStatusBadge from './TicketStatusBadge'
import TicketPriorityBadge from './TicketPriorityBadge'

function formatDate(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  return d.toLocaleDateString('fr-FR')
}

export default function TicketCard({ ticket }) {
  return (
    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-5 flex flex-col gap-3">
      <h3 className="font-semibold text-gray-900 leading-snug">{ticket.title}</h3>

      <div className="flex items-center gap-2">
        <TicketStatusBadge status={ticket.status} />
        <TicketPriorityBadge priority={ticket.priority} />
        <span className="ml-auto text-xs text-gray-400">{ticket.category}</span>
      </div>

      <div className="text-sm text-gray-500 space-y-0.5">
        <p>Créé par {ticket.createdBy?.name ?? 'Inconnu'}</p>
        <p>{formatDate(ticket.createdAt)}</p>
      </div>

      <Link
        to={'/tickets/' + ticket.id}
        className="self-start bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium px-4 py-1.5 rounded-lg transition-colors"
      >
        Voir le détail
      </Link>
    </div>
  )
}
