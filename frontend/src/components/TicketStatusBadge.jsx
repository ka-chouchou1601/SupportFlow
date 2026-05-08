const STATUS_STYLES = {
  'Nouveau':  'bg-blue-100 text-blue-800',
  'En cours': 'bg-yellow-100 text-yellow-800',
  'Résolu':   'bg-green-100 text-green-800',
  'Fermé':    'bg-gray-100 text-gray-600',
}

export default function TicketStatusBadge({ status }) {
  const cls = STATUS_STYLES[status] ?? 'bg-gray-100 text-gray-600'
  return (
    <span className={`text-xs font-medium px-2.5 py-0.5 rounded-full ${cls}`}>
      {status}
    </span>
  )
}
