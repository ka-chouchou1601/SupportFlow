const PRIORITY_STYLES = {
  'Basse':   'bg-gray-100 text-gray-600',
  'Moyenne': 'bg-blue-100 text-blue-700',
  'Haute':   'bg-orange-100 text-orange-800',
  'Urgente': 'bg-red-100 text-red-800 font-bold',
}

export default function TicketPriorityBadge({ priority }) {
  const cls = PRIORITY_STYLES[priority] ?? 'bg-gray-100 text-gray-600'
  return (
    <span className={`text-xs font-medium px-2.5 py-0.5 rounded-full ${cls}`}>
      {priority}
    </span>
  )
}
