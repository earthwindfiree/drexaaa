import { BrowserRouter, Route, Routes } from 'react-router-dom'
import { AuthProvider } from './features/auth/AuthProvider'
import AuthPage from './features/auth/AuthPage'
import { AdminRoute, EmailVerificationRoute, GuestRoute, ProtectedRoute, VerifiedUserRoute } from './features/auth/RouteGuards'
import AdminLayout from './layouts/AdminLayout'
import AppLayout from './layouts/AppLayout'
import AdminDashboardPage from './pages/AdminDashboardPage'
import AdminAccountDetailPage from './pages/AdminAccountDetailPage'
import AdminAccountsPage from './pages/AdminAccountsPage'
import AdminUsersPage from './pages/AdminUsersPage'
import AdminMarketsPage from './pages/AdminMarketsPage'
import AdminDepositDetailPage from './pages/AdminDepositDetailPage'
import AdminDepositsPage from './pages/AdminDepositsPage'
import AdminWithdrawalDetailPage from './pages/AdminWithdrawalDetailPage'
import AdminWithdrawalsPage from './pages/AdminWithdrawalsPage'
import AdminTransactionsPage from './pages/AdminTransactionsPage'
import AdminAuditLogsPage from './pages/AdminAuditLogsPage'
import AdminTiersPage from './pages/AdminTiersPage'
import AdminSettingsPage from './pages/AdminSettingsPage'
import PasswordResetPage from './pages/PasswordResetPage'
import EmailVerificationPage from './pages/EmailVerificationPage'
import UserDashboardPage from './pages/UserDashboardPage'
import UserPortfolioPage from './pages/UserPortfolioPage'
import UserMarketsPage from './pages/UserMarketsPage'
import UserWalletPage from './pages/UserWalletPage'
import UserTransactionsPage from './pages/UserTransactionsPage'
import UserSettingsPage from './pages/UserSettingsPage'
import UserNotificationsPage from './pages/UserNotificationsPage'
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
              <Route path="/forgot-password" element={<PasswordResetPage mode="request" />} />
              <Route path="/reset-password" element={<PasswordResetPage mode="reset" />} />
            </Route>
          </Route>
          <Route element={<ProtectedRoute />}>
            <Route element={<EmailVerificationRoute />}>
              <Route element={<AppLayout />}>
                <Route path="/verify-email" element={<EmailVerificationPage />} />
              </Route>
            </Route>
            <Route element={<VerifiedUserRoute />}>
              <Route element={<AppLayout />}>
                <Route path="/dashboard" element={<UserDashboardPage />} />
                <Route path="/portfolio" element={<UserPortfolioPage />} />
                <Route path="/markets" element={<UserMarketsPage />} />
                <Route path="/wallet" element={<UserWalletPage />} />
                <Route path="/transactions" element={<UserTransactionsPage />} />
                <Route path="/notifications" element={<UserNotificationsPage />} />
                <Route path="/settings" element={<UserSettingsPage />} />
                {userRoutes.map((route) => (
                  route.path !== '/dashboard' && route.path !== '/portfolio' && route.path !== '/markets' && route.path !== '/wallet' && route.path !== '/transactions' && route.path !== '/notifications' && route.path !== '/settings' && (
                    <Route key={route.path} path={route.path} element={<RoutePlaceholder title={route.title} />} />
                  )
                ))}
              </Route>
            </Route>
            <Route element={<AdminRoute />}>
              <Route element={<AdminLayout />}>
              <Route path="/admin" element={<AdminDashboardPage />} />
              <Route path="/admin/users" element={<AdminUsersPage />} />
              <Route path="/admin/accounts" element={<AdminAccountsPage />} />
              <Route path="/admin/accounts/:accountId" element={<AdminAccountDetailPage />} />
              <Route path="/admin/markets" element={<AdminMarketsPage />} />
              <Route path="/admin/deposits" element={<AdminDepositsPage />} />
              <Route path="/admin/deposits/:depositId" element={<AdminDepositDetailPage />} />
              <Route path="/admin/withdrawals" element={<AdminWithdrawalsPage />} />
              <Route path="/admin/withdrawals/:withdrawalId" element={<AdminWithdrawalDetailPage />} />
              <Route path="/admin/transactions" element={<AdminTransactionsPage />} />
              <Route path="/admin/audit-logs" element={<AdminAuditLogsPage />} />
              <Route path="/admin/tiers" element={<AdminTiersPage />} />
              <Route path="/admin/settings" element={<AdminSettingsPage />} />
              {adminRoutes.filter((route) => route.path !== '/admin' && route.path !== '/admin/users' && route.path !== '/admin/accounts' && route.path !== '/admin/markets' && route.path !== '/admin/deposits' && route.path !== '/admin/withdrawals' && route.path !== '/admin/transactions' && route.path !== '/admin/audit-logs' && route.path !== '/admin/tiers' && route.path !== '/admin/settings').map((route) => (
                <Route key={route.path} path={route.path} element={<RoutePlaceholder title={route.title} />} />
              ))}
              </Route>
            </Route>
          </Route>
          <Route path="*" element={<NotFoundPage />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  )
}

export default AppRouter