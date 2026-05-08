function formatDate(iso) {
  return new Date(iso).toLocaleString('fr-FR')
}

export default function HistoryTimeline({ history }) {
  return (
    <div>
      <h2 className="font-semibold text-gray-900 mb-4">Historique</h2>

      {history.length === 0 ? (
        <p className="text-sm text-gray-400">Aucun historique.</p>
      ) : (
        <ol className="border-l-2 border-gray-200 space-y-5 ml-2">
          {history.map((entry) => (
            <li key={entry.id} className="relative pl-5">
              {/* dot on the timeline */}
              <span className="absolute -left-[7px] top-1 w-3 h-3 rounded-full bg-blue-500 border-2 border-white" />

              <p className="text-sm text-gray-700">{entry.action}</p>
              <p className="text-xs text-gray-400 mt-0.5">
                {entry.user?.name ? `par ${entry.user.name} · ` : ''}
                {formatDate(entry.createdAt)}
              </p>
            </li>
          ))}
        </ol>
      )}
    </div>
  )
}
