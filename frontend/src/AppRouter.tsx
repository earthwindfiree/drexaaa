import { BrowserRouter, Route, Routes } from 'react-router-dom'
import { AuthProvider } from './features/auth/AuthProvider'
import AuthPage from './features/auth/AuthPage'
import { GuestRoute, ProtectedRoute } from './features/auth/RouteGuards'
import AppLayout from './layouts/AppLayout'
import HomePage from './pages/HomePage'
import NotFoundPage from './pages/NotFoundPage'
import RoutePlaceholder from './components/RoutePlaceholder'

const userRoutes = [
  { path: '/dashboard', title: 'Dashboard' },
  { path: '/portfolio', title: 'Portfolio' },
  { path: '/markets', title: 'Markets' },
  { path: '/wallet', title: 'Wallet' },
  { path: '/transactions', title: 'Transactions' },
  { path: '/notifications', title: 'Notifications' },
  { path: '/settings', title: 'Settings' },
]

const adminRoutes = [
  { path: '/admin', title: 'Admin' },
  { path: '/admin/users', title: 'Admin Users' },
  { path: '/admin/accounts', title: 'Admin Accounts' },
  { path: '/admin/tiers', title: 'Admin Tiers' },
  { path: '/admin/markets', title: 'Admin Markets' },
  { path: '/admin/deposits', title: 'Admin Deposits' },
  { path: '/admin/withdrawals', title: 'Admin Withdrawals' },
  { path: '/admin/transactions', title: 'Admin Transactions' },
  { path: '/admin/notifications', title: 'Admin Notifications' },
  { path: '/admin/audit-logs', title: 'Admin Audit Logs' },
  { path: '/admin/settings', title: 'Admin Settings' },
]

function AppRouter() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route path="/" element={<HomePage />} />
          <Route element={<GuestRoute />}>
            <Route element={<AppLayout />}>
              <Route path="/login" element={<AuthPage mode="login" />} />
              <Route path="/register" element={<AuthPage mode="register" />} />
            </Route>
          </Route>
          <Route element={<ProtectedRoute />}>
            <Route element={<AppLayout />}>
              {userRoutes.map((route) => (
                <Route key={route.path} path={route.path} element={<RoutePlaceholder title={route.title} />} />
              ))}
              {adminRoutes.map((route) => (
                <Route key={route.path} path={route.path} element={<RoutePlaceholder title={route.title} />} />
              ))}
            </Route>
          </Route>
          <Route path="*" element={<NotFoundPage />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  )
}

export default AppRouter