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
import AdminNotificationsPage from './pages/AdminNotificationsPage'
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
import PublicStrategyDetailsPage from './pages/PublicStrategyDetailsPage'
import PublicTermsPage from './pages/PublicTermsPage'
import PublicPrivacyPage from './pages/PublicPrivacyPage'
import PublicContactPage from './pages/PublicContactPage'

function AppRouter() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route path="/" element={<HomePage />} />
          <Route path="/strategies/:strategyId" element={<PublicStrategyDetailsPage />} />
          <Route element={<AppLayout />}>
            <Route path="/terms" element={<PublicTermsPage />} />
            <Route path="/privacy" element={<PublicPrivacyPage />} />
            <Route path="/contact" element={<PublicContactPage />} />
          </Route>
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
              <Route path="/admin/notifications" element={<AdminNotificationsPage />} />
              <Route path="/admin/audit-logs" element={<AdminAuditLogsPage />} />
              <Route path="/admin/tiers" element={<AdminTiersPage />} />
              <Route path="/admin/settings" element={<AdminSettingsPage />} />
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