import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { ApiError, apiRequest, initializeCsrfCookie } from '../lib/api'
import type { AdminDepositDetail, AdminDepositResponse } from '../types/api'

function AdminDepositDetailPage() {
  const { depositId } = useParams<{ depositId: string }>()
  const [deposit, setDeposit] = useState<AdminDepositDetail | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [refreshToken, setRefreshToken] = useState(0)

  useEffect(() => {
    let active = true
    if (!depositId) return () => { active = false }

    void apiRequest<AdminDepositResponse>(`/api/admin/deposits/${depositId}`)
      .then((response) => { if (active) setDeposit(response.data) })
      .catch((requestError: unknown) => { if (active) setError(errorMessage(requestError, 'Unable to load this deposit.')) })
      .finally(() => { if (active) setLoading(false) })

    return () => { active = false }
  }, [depositId, refreshToken])

  const displayError = depositId ? error : 'This deposit could not be identified.'
  const isLoading = Boolean(depositId) && loading

  return <section className="mx-auto max-w-5xl px-6 py-10 lg:px-8"><Link to="/admin/deposits" className="text-sm font-semibold text-cyan-700 hover:text-cyan-900">Back to deposits</Link>{isLoading && <p className="mt-6 border border-slate-200 bg-white p-6 text-sm text-slate-600">Loading deposit...</p>}{!isLoading && displayError && <div role="alert" className="mt-6 border border-rose-200 bg-rose-50 p-6 text-sm text-rose-800">{displayError}</div>}{!isLoading && !displayError && deposit && <DepositDetail deposit={deposit} onReviewed={() => setRefreshToken((value) => value + 1)} />}</section>
}

function DepositDetail({ deposit, onReviewed }: { deposit: AdminDepositDetail; onReviewed: () => void }) {
  const [actionError, setActionError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [reason, setReason] = useState('')

  async function review(action: 'confirm' | 'reject') {
    setActionError(null)
    if (action === 'reject' && reason.trim() === '') { setActionError('A rejection reason is required.'); return }
    const consequence = action === 'confirm' ? 'This will release the pending deposit into the managed balance and create a completed transaction.' : 'This will reject the deposit and release its pending balance without crediting the managed balance.'
    if (!window.confirm(`${consequence}\n\nDeposit: $${deposit.usd_value}\nUser: ${deposit.user?.name ?? 'Unknown user'}`)) return
    setSubmitting(true)
    try {
      await initializeCsrfCookie()
      await apiRequest(`/api/admin/deposits/${deposit.id}/${action}`, { method: 'POST', body: action === 'reject' ? JSON.stringify({ rejection_reason: reason }) : undefined })
      onReviewed()
    } catch (requestError: unknown) {
      if (requestError instanceof ApiError && requestError.status === 403) setActionError('You are not authorized to review deposits.')
      else if (requestError instanceof ApiError && Object.values(requestError.errors).length > 0) setActionError(Object.values(requestError.errors).flat().join(' '))
      else setActionError(requestError instanceof Error ? requestError.message : 'Unable to review this deposit.')
    } finally { setSubmitting(false) }
  }

  return <><div className="mb-8 mt-5 flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">Deposit #{deposit.id}</p><h1 className="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{deposit.user?.name ?? 'Unknown user'}</h1><p className="mt-2 text-sm text-slate-600">Review the stored submission snapshot before taking action.</p></div><StatusBadge status={deposit.status} /></div><div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><Metric label="Crypto amount" value={deposit.crypto_amount} /><Metric label="USD value" value={`$${deposit.usd_value}`} /><Metric label="Price snapshot" value={`$${deposit.price_snapshot}`} /><Metric label="Status" value={deposit.status} /></div><div className="mt-8 grid gap-6 lg:grid-cols-2"><InfoPanel title="Submission"><InfoLine label="User" value={deposit.user?.email ?? 'Not available'} /><InfoLine label="Asset" value={deposit.asset ? `${deposit.asset.symbol} · ${deposit.asset.name}` : 'Not available'} /><InfoLine label="Network" value={deposit.wallet?.network ?? 'No network'} /><InfoLine label="Wallet" value={deposit.wallet?.wallet_address ?? 'Not available'} /><InfoLine label="Reference" value={deposit.transaction_reference} /><InfoLine label="Submitted" value={formatDate(deposit.created_at)} /></InfoPanel><InfoPanel title="Account and review"><InfoLine label="Managed balance" value={deposit.account ? `$${deposit.account.managed_balance}` : 'Not available'} /><InfoLine label="Pending balance" value={deposit.account ? `$${deposit.account.pending_balance}` : 'Not available'} /><InfoLine label="Tier" value={deposit.account?.tier?.name ?? 'Unassigned'} /><InfoLine label="Reviewer" value={deposit.reviewer?.name ?? 'Not reviewed'} /><InfoLine label="Reviewed" value={deposit.reviewed_at ? formatDate(deposit.reviewed_at) : 'Not reviewed'} /></InfoPanel></div>{deposit.status === 'pending' && <section className="mt-8 border border-cyan-200 bg-cyan-50 p-5"><p className="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-800">Review action</p><p className="mt-2 text-sm text-slate-700">Confirmation credits the stored USD value to the managed balance. Rejection releases the pending balance and does not create a completed transaction.</p><label className="mt-4 block max-w-xl text-sm font-medium text-slate-700">Rejection reason<textarea value={reason} onChange={(event) => setReason(event.target.value)} rows={3} placeholder="Required when rejecting" className="mt-1 block w-full border border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-700" /></label><div className="mt-4 flex flex-wrap items-center gap-3"><button type="button" disabled={submitting} onClick={() => void review('confirm')} className="bg-emerald-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">{submitting ? 'Processing...' : 'Confirm deposit'}</button><button type="button" disabled={submitting} onClick={() => void review('reject')} className="border border-rose-300 px-4 py-2 text-sm font-semibold text-rose-700 disabled:opacity-60">Reject deposit</button>{actionError && <p role="alert" className="text-sm font-medium text-rose-700">{actionError}</p>}</div></section>}{deposit.transaction && <InfoPanel title="Completed transaction"><InfoLine label="Transaction" value={`#${deposit.transaction.id}`} /><InfoLine label="Status" value={deposit.transaction.status} /><InfoLine label="USD amount" value={`$${deposit.transaction.usd_amount}`} /><InfoLine label="Reference" value={deposit.transaction.reference} /></InfoPanel>}{deposit.rejection_reason && <div className="mt-8 border border-rose-200 bg-rose-50 p-5 text-sm text-rose-800"><strong>Rejection reason:</strong> {deposit.rejection_reason}</div>}</>
}

function Metric({ label, value }: { label: string; value: string }) { return <article className="border border-slate-200 bg-white p-5 shadow-sm"><p className="text-sm font-medium text-slate-500">{label}</p><p className="mt-3 text-2xl font-semibold tracking-tight text-slate-950">{value}</p></article> }
function InfoPanel({ title, children }: { title: string; children: React.ReactNode }) { return <article className="border border-slate-200 bg-white p-5 shadow-sm"><h2 className="text-lg font-semibold text-slate-950">{title}</h2><div className="mt-4 divide-y divide-slate-100">{children}</div></article> }
function InfoLine({ label, value }: { label: string; value: string }) { return <div className="flex flex-wrap justify-between gap-3 py-3 text-sm"><span className="text-slate-500">{label}</span><span className="max-w-[70%] break-words text-right font-medium text-slate-950">{value}</span></div> }
function StatusBadge({ status }: { status: string }) { const style = status === 'pending' ? 'bg-amber-50 text-amber-700' : status === 'confirmed' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'; return <span className={`inline-block px-3 py-2 text-xs font-semibold uppercase tracking-[0.12em] ${style}`}>{status}</span> }
function formatDate(value: string): string { return new Intl.DateTimeFormat('en', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) }
function errorMessage(error: unknown, fallback: string): string { if (error instanceof ApiError && Object.values(error.errors).length > 0) return Object.values(error.errors).flat().join(' '); return error instanceof Error ? error.message : fallback }

export default AdminDepositDetailPage