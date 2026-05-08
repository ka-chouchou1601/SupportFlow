import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import LoginPage from './pages/LoginPage'
import TicketDashboardPage from './pages/TicketDashboardPage'
import CreateTicketPage from './pages/CreateTicketPage'
import TicketDetailPage from './pages/TicketDetailPage'

// Reads the logged-in user persisted by LoginPage
const user = JSON.parse(localStorage.getItem('user'))

// Wraps protected routes: redirects to /login when no session exists
function PrivateRoute({ children }) {
  if (!user) return <Navigate to="/login" replace />
  return children
}

export default function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/login" element={<LoginPage />} />

        <Route
          path="/"
          element={<PrivateRoute><TicketDashboardPage /></PrivateRoute>}
        />
        <Route
          path="/tickets/new"
          element={<PrivateRoute><CreateTicketPage /></PrivateRoute>}
        />
        <Route
          path="/tickets/:id"
          element={<PrivateRoute><TicketDetailPage /></PrivateRoute>}
        />
      </Routes>
    </BrowserRouter>
  )
}
