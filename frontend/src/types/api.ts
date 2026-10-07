export interface ApiErrorPayload {
  message?: string
  errors?: Record<string, string[]>
}

export interface UserDashboardAccount {
  id: number
  managed_balance: string
  pending_balance: string
  total_profit_loss: string
  performance_percentage: string
  trading_status: string
  tier: {
    id: number
    name: string
    minimum_balance: string
    description: string
    strategy: {
      id: number
      name: string
      description: string
      risk_profile: string
      active: boolean
    } | null
  } | null
}

export interface UserDashboardTransaction {
  id: number
  type: string
  status: string
  asset: { id: number; symbol: string; name: string } | null
  crypto_amount: string | null
  usd_amount: string
  reference: string
  description: string | null
  occurred_at: string | null
}

export interface UserDashboardResponse {
  data: {
    account: UserDashboardAccount
    recent_transactions: UserDashboardTransaction[]
  }
}

export interface UserPerformanceSnapshot {
  id: number
  account_value: string
  profit_loss: string
  performance_percentage: string
  snapshot_at: string | null
}

export interface UserPortfolioResponse {
  data: {
    id: number
    managed_balance: string
    pending_balance: string
    total_profit_loss: string
    performance_percentage: string
    trading_status: string
    tier: UserDashboardAccount['tier']
    performance_history: UserPerformanceSnapshot[]
  }
}

export interface UserNotificationsResponse {
  unread_count: number
}

export interface UserNotification {
  id: number
  category: string
  title: string
  message: string
  read_at: string | null
  created_at: string | null
}

export interface UserNotificationsListResponse {
  data: UserNotification[]
  unread_count: number
  meta: {
    current_page: number
    last_page: number
    per_page: number
    from: number | null
    to: number | null
    total: number
  }
}

export interface UserNotificationResponse {
  data: UserNotification
}

export interface UserNotificationsMarkAllResponse {
  data: { updated: number }
}

export interface AdminNotification extends UserNotification {
  user: { id: number; name: string; email: string } | null
}

export interface AdminNotificationsResponse {
  data: AdminNotification[]
  meta: UserNotificationsListResponse['meta']
}

export interface UserMarketAsset {
  id: number
  symbol: string
  name: string
  active: boolean
  market_price: {
    current_price: string | null
    change_24h_percentage: string | null
  } | null
}

export interface UserMarketsResponse {
  data: UserMarketAsset[]
}

export interface MarketHistoryPoint {
  timestamp: string
  price: string
}

export interface MarketHistoryResponse {
  data: {
    asset: {
      id: number
      symbol: string
      name: string
      current_price: string | null
    }
    range: '24h' | '7d' | '30d'
    points: MarketHistoryPoint[]
  }
}

export interface PublicStrategyTier {
  id: number
  name: string
  minimum_balance: string
  description: string
  benefits: string[]
  features: string[]
}

export interface PublicStrategy {
  id: number
  name: string
  description: string
  risk_profile: string
  tiers: PublicStrategyTier[]
}

export interface PublicStrategiesResponse {
  data: PublicStrategy[]
}

export interface PublicStrategyResponse {
  data: PublicStrategy
}

export interface UserWalletAsset {
  id: number
  symbol: string
  name: string
  market_price: {
    current_price: string
    change_24h_percentage: string
  } | null
  wallets: Array<{
    network: string | null
    wallet_address: string
  }>
}

export interface UserWalletDeposit {
  id: number
  asset: { id: number; symbol: string; name: string } | null
  crypto_amount: string
  price_snapshot: string
  usd_value: string
  wallet: { network: string | null; wallet_address: string } | null
  transaction_reference: string
  status: string
  rejection_reason: string | null
  submitted_at: string | null
  reviewed_at: string | null
}

export interface UserWalletWithdrawal {
  id: number
  asset: { id: number; symbol: string; name: string } | null
  amount: string
  destination_wallet: string
  crypto_amount: string | null
  price_snapshot: string | null
  status: string
  rejection_reason: string | null
  submitted_at: string | null
  reviewed_at: string | null
}

export interface UserWalletData {
  account: {
    managed_balance: string
    pending_balance: string
    withdrawable_amount: string
  }
  assets: UserWalletAsset[]
  deposits: UserWalletDeposit[]
  withdrawals: UserWalletWithdrawal[]
}

export interface UserWalletResponse {
  data: UserWalletData
}

export interface UserTransaction {
  id: number
  type: string
  status: string
  asset: { id: number; symbol: string; name: string } | null
  crypto_amount: string | null
  usd_amount: string
  price_snapshot: string | null
  reference: string
  description: string | null
  occurred_at: string | null
}

export interface UserTransactionsResponse {
  data: UserTransaction[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    from: number | null
    to: number | null
    total: number
  }
}

export interface AdminUserAccount {
  id: number
  managed_balance: string
  pending_balance: string
  tier: { id: number; name: string } | null
  trading_status: string
}

export interface AdminUser {
  id: number
  name: string
  email: string
  country: string | null
  phone: string | null
  role: string
  status: string
  account: AdminUserAccount | null
  created_at: string
}

export interface AdminUsersResponse {
  data: AdminUser[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    from: number | null
    to: number | null
    total: number
  }
}

export interface AdminAccountListItem {
  id: number
  user: { id: number; name: string; email: string } | null
  managed_balance: string
  pending_balance: string
  total_profit_loss: string
  performance_percentage: string
  tier: { id: number; name: string } | null
  trading_status: string
  created_at: string
  updated_at: string
}

export interface AdminAccountsResponse {
  data: AdminAccountListItem[]
  meta: AdminUsersResponse['meta']
}

export interface AdminAccountDetail {
  id: number
  managed_balance: string
  pending_balance: string
  total_profit_loss: string
  performance_percentage: string
  trading_status: string
  created_at: string
  updated_at: string
  user: {
    id: number
    name: string
    email: string
    country: string | null
    phone: string | null
    role: string
    status: string
    created_at: string
  } | null
  tier: {
    id: number
    name: string
    minimum_balance: string
    description: string
    benefits: string[] | null
    feature_access: string[] | null
    display_settings: Record<string, unknown> | null
    strategy: { id: number; name: string; description: string; risk_profile: string; active: boolean } | null
  } | null
  performance_snapshots: Array<{ id: number; managed_balance: string; total_profit_loss: string; performance_percentage: string; snapshot_at: string }>
  transactions: Array<{ id: number; type: string; status: string; asset: { id: number; symbol: string; name: string } | null; crypto_amount: string | null; usd_amount: string; price_snapshot: string | null; reference: string; description: string | null; occurred_at: string }>
  deposits: Array<{ id: number; asset: { id: number; symbol: string; name: string } | null; crypto_amount: string; price_snapshot: string; usd_value: string; transaction_reference: string; status: string; rejection_reason: string | null; reviewed_at: string | null; created_at: string }>
  withdrawals: Array<{ id: number; asset: { id: number; symbol: string; name: string } | null; amount: string; destination_wallet: string; crypto_amount: string | null; price_snapshot: string | null; status: string; rejection_reason: string | null; reviewed_at: string | null; created_at: string }>
}

export interface AdminAccountResponse {
  data: AdminAccountDetail
}

export interface AdminAccountSimulationResponse {
  data: Pick<AdminAccountDetail, 'id' | 'managed_balance' | 'pending_balance' | 'total_profit_loss' | 'performance_percentage' | 'trading_status'> & { tier_id: number | null }
}

export interface AdminWallet {
  id: number
  asset_id: number
  network: string | null
  wallet_address: string
  active: boolean
  created_at: string
  updated_at: string
}

export interface AdminMarketAsset {
  id: number
  symbol: string
  name: string
  active: boolean
  market_price: { id: number; current_price: string; change_24h_percentage: string } | null
  wallets: AdminWallet[]
  active_wallet_count: number
  has_usable_wallet: boolean
}

export interface AdminMarketsResponse {
  data: AdminMarketAsset[]
}

export interface AdminDepositListItem {
  id: number
  user: { id: number; name: string; email: string } | null
  asset: { id: number; symbol: string; name: string } | null
  wallet: { id: number; network: string | null; wallet_address: string } | null
  crypto_amount: string
  price_snapshot: string
  usd_value: string
  transaction_reference: string
  status: string
  reviewer: { id: number; name: string } | null
  created_at: string
  reviewed_at: string | null
}

export interface AdminDepositsResponse {
  data: AdminDepositListItem[]
  meta: AdminUsersResponse['meta']
}

export interface AdminDepositDetail extends AdminDepositListItem {
  account: {
    id: number
    managed_balance: string
    pending_balance: string
    total_profit_loss: string
    performance_percentage: string
    trading_status: string
    tier: { id: number; name: string } | null
  } | null
  user: {
    id: number
    name: string
    email: string
    country: string | null
    phone: string | null
    role: string
    status: string
  } | null
  transaction: {
    id: number
    type: string
    status: string
    asset: { id: number; symbol: string; name: string } | null
    crypto_amount: string | null
    usd_amount: string
    price_snapshot: string | null
    reference: string
    description: string | null
    occurred_at: string
  } | null
  rejection_reason: string | null
}

export interface AdminDepositResponse {
  data: AdminDepositDetail
}

export interface AdminWithdrawalListItem {
  id: number
  user: { id: number; name: string; email: string } | null
  account_id: number
  asset: { id: number; symbol: string; name: string } | null
  amount: string
  destination_wallet: string
  crypto_amount: string | null
  price_snapshot: string | null
  status: string
  transaction: { id: number; reference: string } | null
  reviewer: { id: number; name: string } | null
  reviewed_at: string | null
  rejection_reason: string | null
  created_at: string
}

export interface AdminWithdrawalsResponse {
  data: AdminWithdrawalListItem[]
  meta: AdminUsersResponse['meta']
}

export interface AdminWithdrawalDetail extends AdminWithdrawalListItem {
  account: {
    id: number
    managed_balance: string
    pending_balance: string
    total_profit_loss: string
    performance_percentage: string
    trading_status: string
    tier: { id: number; name: string } | null
    withdrawable_amount: string
  } | null
  user: {
    id: number
    name: string
    email: string
    country: string | null
    phone: string | null
    role: string
    status: string
  } | null
  transaction: {
    id: number
    type: string
    status: string
    asset: { id: number; symbol: string; name: string } | null
    crypto_amount: string | null
    usd_amount: string
    price_snapshot: string | null
    reference: string
    description: string | null
    occurred_at: string
  } | null
}

export interface AdminWithdrawalResponse {
  data: AdminWithdrawalDetail
}

export interface AdminTransaction {
  id: number
  user: { id: number; name: string; email: string } | null
  account: { id: number } | null
  type: string
  status: string
  asset: { id: number; symbol: string; name: string } | null
  crypto_amount: string | null
  usd_amount: string
  price_snapshot: string | null
  reference: string
  description: string | null
  related_deposit: { id: number; reference: string; status: string } | null
  related_withdrawal: { id: number; amount: string; status: string; destination_wallet: string } | null
  occurred_at: string
  created_at: string
}

export interface AdminTransactionsResponse {
  data: AdminTransaction[]
  meta: AdminUsersResponse['meta']
}

export interface AdminAuditLog {
  id: number
  actor: { id: number; name: string; email: string } | null
  target_user: { id: number; name: string; email: string } | null
  action: string
  entity_type: string | null
  entity_id: number | null
  old_values: Record<string, unknown> | null
  new_values: Record<string, unknown> | null
  metadata: Record<string, unknown> | null
  created_at: string
  updated_at: string
}

export interface AdminAuditLogsResponse {
  data: AdminAuditLog[]
  meta: AdminUsersResponse['meta']
}

export interface AdminStrategy {
  id: number
  name: string
  description: string
  risk_profile: string
  active: boolean
}

export interface AdminTier {
  id: number
  name: string
  minimum_balance: string
  description: string
  benefits: unknown[] | null
  feature_access: unknown[] | null
  display_settings: Record<string, unknown> | null
  strategy: AdminStrategy | null
}

export interface AdminTiersResponse {
  data: {
    tiers: AdminTier[]
    strategies: AdminStrategy[]
  }
}