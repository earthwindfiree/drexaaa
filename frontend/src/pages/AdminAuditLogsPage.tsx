import { useEffect, useState, type FormEvent } from 'react'
import { useSearchParams } from 'react-router-dom'
import { ApiError, apiRequest } from '../lib/api'
import type { AdminAuditLog, AdminAuditLogsResponse } from '../types/api'

function AdminAuditLogsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [logs, setLogs] = useState<AdminAuditLog[]>([])
  const [meta, setMeta] = useState<AdminAuditLogsResponse['meta'] | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [actorInput, setActorInput] = useState(searchParams.get('actor_search') ?? '')
  const [targetInput, setTargetInput] = useState(searchParams.get('target_search') ?? '')
  const [actionInput, setActionInput] = useState(searchParams.get('action') ?? '')
  const [entityTypeInput, setEntityTypeInput] = useState(searchParams.get('entity_type') ?? '')
  const [entityIdInput, setEntityIdInput] = useState(searchParams.get('entity_id') ?? '')

  useEffect(() => {
    let active = true
    const query = searchParams.toString()
    void apiRequest<AdminAuditLogsResponse>(query ? `/api/admin/audit-logs?${query}` : '/api/admin/audit-logs')
      .then((response) => { if (active) { setLogs(response.data); setMeta(response.meta) } })
      .catch((requestError: unknown) => { if (active) { setError(errorMessage(requestError, 'Unable to load audit logs.')); setLogs([]); setMeta(null) } })
      .finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [searchParams])

  function applyFilters(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setLoading(true)
    setError(null)
    const next = new URLSearchParams()
    const filters = [['actor_search', actorInput], ['target_search', targetInput], ['action', actionInput], ['entity_type', entityTypeInput], ['entity_id', entityIdInput]]
    filters.forEach(([name, value]) => { if (value.trim()) next.set(name, value.trim()) })
    next.set('page', '1')
    setSearchParams(next)
  }

  function clearFilters() {
    setLoading(true)
    setError(null)
    setActorInput('')
    setTargetInput('')
    setActionInput('')
    setEntityTypeInput('')
    setEntityIdInput('')
    setSearchParams({ page: '1' })
  }

  function goToPage(page: number) {
    setLoading(true)
    setError(null)
    const next = new URLSearchParams(searchParams)
    next.set('page', String(page))
    setSearchParams(next)
  }

  return <section className="mx-auto max-w-7xl px-6 py-10 lg:px-8"><div className="mb-8"><p className="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">Privileged activity</p><h1 className="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Audit logs</h1><p className="mt-2 max-w-2xl text-sm text-slate-600">Read-only history of administrative changes and their recorded before/after state.</p></div><form onSubmit={applyFilters} className="mb-6 grid gap-3 border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-2 xl:grid-cols-6"><Filter label="Actor" value={actorInput} onChange={setActorInput} placeholder="Name or email" /><Filter label="Target user" value={targetInput} onChange={setTargetInput} placeholder="Name or email" /><Filter label="Action" value={actionInput} onChange={setActionInput} placeholder="e.g. deposit.confirmed" /><Filter label="Entity type" value={entityTypeInput} onChange={setEntityTypeInput} placeholder="App\\Models\\Account" /><Filter label="Entity ID" value={entityIdInput} onChange={setEntityIdInput} placeholder="Numeric ID" /><div className="flex items-end gap-2"><button type="submit" className="bg-slate-950 px-4 py-2 text-sm font-semibold text-white">Apply</button><button type="button" onClick={clearFilters} className="border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Clear</button></div></form>{loading && <p className="border border-slate-200 bg-white p-6 text-sm text-slate-600">Loading audit logs...</p>}{!loading && error && <div role="alert" className="border border-rose-200 bg-rose-50 p-6 text-sm text-rose-800">{error}</div>}{!loading && !error && logs.length === 0 && <div className="border border-slate-200 bg-white p-10 text-center shadow-sm"><h2 className="text-lg font-semibold text-slate-950">No audit logs found</h2><p className="mt-2 text-sm text-slate-600">Try changing the filters.</p></div>}{!loading && !error && logs.length > 0 && <><div className="space-y-4">{logs.map((log) => <AuditLogCard key={log.id} log={log} />)}</div>{meta && <Pagination meta={meta} onPageChange={goToPage} />}</>}</section>
}

function Filter({ label, value, onChange, placeholder }: { label: string; value: string; onChange: (value: string) => void; placeholder: string }) { return <label className="text-sm text-slate-600">{label}<input value={value} onChange={(event) => onChange(event.target.value)} placeholder={placeholder} className="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-600" /></label> }
function AuditLogCard({ log }: { log: AdminAuditLog }) { return <article className="border border-slate-200 bg-white p-5 shadow-sm"><div className="flex flex-wrap items-start justify-between gap-3"><div><p className="font-semibold text-slate-950">{log.action}</p><p className="mt-1 text-xs text-slate-500">Audit #{log.id} · {formatDate(log.created_at)}</p></div><span className="bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">{shortEntityType(log.entity_type)}{log.entity_id ? ` #${log.entity_id}` : ''}</span></div><div className="mt-4 grid gap-3 text-sm md:grid-cols-2"><Info label="Actor" value={personLabel(log.actor)} /><Info label="Target user" value={personLabel(log.target_user)} /></div><div className="mt-4 grid gap-4 border-t border-slate-100 pt-4 lg:grid-cols-3"><JsonBlock label="Previous value" value={log.old_values} /><JsonBlock label="New value" value={log.new_values} /><JsonBlock label="Metadata" value={log.metadata} /></div></article> }
function Info({ label, value }: { label: string; value: string }) { return <div><p className="text-xs uppercase tracking-[0.12em] text-slate-500">{label}</p><p className="mt-1 font-medium text-slate-950">{value}</p></div> }
function JsonBlock({ label, value }: { label: string; value: Record<string, unknown> | null }) { return <div><p className="text-xs uppercase tracking-[0.12em] text-slate-500">{label}</p><pre className="mt-2 max-h-40 overflow-auto whitespace-pre-wrap break-words bg-slate-50 p-3 text-xs text-slate-700">{value ? JSON.stringify(value, null, 2) : 'None'}</pre></div> }
function Pagination({ meta, onPageChange }: { meta: AdminAuditLogsResponse['meta']; onPageChange: (page: number) => void }) { return <div className="flex flex-wrap items-center justify-between gap-3 px-1 py-4 text-sm text-slate-600"><p>{meta.from ?? 0}-{meta.to ?? 0} of {meta.total}</p><div className="flex items-center gap-2"><button type="button" disabled={meta.current_page <= 1} onClick={() => onPageChange(meta.current_page - 1)} className="border border-slate-300 px-3 py-2 font-medium disabled:opacity-40">Previous</button><span className="px-2">Page {meta.current_page} of {meta.last_page}</span><button type="button" disabled={meta.current_page >= meta.last_page} onClick={() => onPageChange(meta.current_page + 1)} className="border border-slate-300 px-3 py-2 font-medium disabled:opacity-40">Next</button></div></div> }
function personLabel(person: AdminAuditLog['actor']): string { return person ? `${person.name} · ${person.email}` : 'None' }
function shortEntityType(value: string | null): string { return value ? value.split('\\').pop() ?? value : 'No entity' }
function formatDate(value: string): string { return new Intl.DateTimeFormat('en', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) }
function errorMessage(error: unknown, fallback: string): string { if (error instanceof ApiError && Object.values(error.errors).length > 0) return Object.values(error.errors).flat().join(' '); return error instanceof Error ? error.message : fallback }

export default AdminAuditLogsPage