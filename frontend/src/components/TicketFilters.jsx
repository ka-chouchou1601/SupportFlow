const STATUSES   = ['', 'Nouveau', 'En cours', 'Résolu', 'Fermé']
const PRIORITIES = ['', 'Basse', 'Moyenne', 'Haute', 'Urgente']

export default function TicketFilters({ filters, onChange }) {
  return (
    <div className="flex flex-wrap gap-3 mb-6">
      <input
        type="text"
        placeholder="Rechercher un ticket..."
        value={filters.search ?? ''}
        onChange={(e) => onChange({ ...filters, search: e.target.value })}
        className="border border-gray-300 rounded-lg px-3 py-2 text-sm flex-1 min-w-48 focus:outline-none focus:ring-2 focus:ring-blue-500"
      />

      <select
        value={filters.status ?? ''}
        onChange={(e) => onChange({ ...filters, status: e.target.value })}
        className="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
        {STATUSES.map((s) => (
          <option key={s} value={s}>{s || 'Tous les statuts'}</option>
        ))}
      </select>

      <select
        value={filters.priority ?? ''}
        onChange={(e) => onChange({ ...filters, priority: e.target.value })}
        className="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
        {PRIORITIES.map((p) => (
          <option key={p} value={p}>{p || 'Toutes les priorités'}</option>
        ))}
      </select>
    </div>
  )
}
