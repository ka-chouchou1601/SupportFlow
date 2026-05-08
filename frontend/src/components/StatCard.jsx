const BORDER_COLORS = {
  blue:   'border-blue-500',
  yellow: 'border-yellow-500',
  green:  'border-green-500',
  gray:   'border-gray-400',
}

export default function StatCard({ title, count, color = 'blue' }) {
  const borderCls = BORDER_COLORS[color] ?? BORDER_COLORS.blue
  return (
    <div className={`bg-white rounded-xl shadow-sm border border-gray-200 border-l-4 ${borderCls} p-4`}>
      <p className="text-sm text-gray-500">{title}</p>
      <p className="text-3xl font-bold text-gray-900 mt-1">{count}</p>
    </div>
  )
}
