import './App.css'
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { apiRequest } from './lib/api'
import type { PublicStrategiesResponse } from './types/api'
import { useDocumentTitle } from './hooks/useDocumentTitle'

const navItems = [
  { label: 'Home', href: '/' },
  { label: 'How It Works', href: '#how-it-works' },
  { label: 'Strategies', href: '#strategies' },
  { label: 'Markets', href: '#markets' },
  { label: 'FAQ', href: '#faq' },
]

const highlights = [
  { title: 'Managed Strategies', description: 'Dedicated portfolio oversight with transparent account monitoring.' },
  { title: 'Multi-Asset Access', description: 'Track BTC, ETH, LTC, and USDT through a controlled demo market view.' },
  { title: 'Transparent Account Monitoring', description: 'Review performance snapshots and balance movement with clear status updates.' },
  { title: 'Secure Account Management', description: 'Register, verify, fund, and manage access from a protected account dashboard.' },
]

const steps = [
  'Create Account',
  'Fund Account',
  'Qualify for Strategy',
  'Monitor Account',
]

const markets = [
  { symbol: 'BTC', name: 'Bitcoin', price: '$67,540', change: '+3.42%' },
  { symbol: 'ETH', name: 'Ethereum', price: '$3,420', change: '+2.17%' },
  { symbol: 'LTC', name: 'Litecoin', price: '$92.30', change: '-0.84%' },
  { symbol: 'USDT', name: 'Tether', price: '$1.00', change: '+0.02%' },
]

const faqs = [
  { question: 'What is managed trading in this demo?', answer: 'This platform is a simulated managed trading experience for presentation and demo purposes only. It does not execute live trading or guarantee outcomes.' },
  { question: 'How do deposits work?', answer: 'Users deposit supported crypto to a configured wallet, submit the payment details, and wait for admin confirmation before the funds are credited to the managed balance.' },
  { question: 'How are tiers qualified?', answer: 'Tier qualification is based solely on the managed balance and uses configured thresholds defined by the platform administrator.' },
  { question: 'What is the difference between managed and pending balance?', answer: 'Pending balances represent submitted deposits or withdrawals that are awaiting review. They do not count toward the managed balance or tier qualification until approved.' },
  { question: 'Is the performance shown real?', answer: 'No. All performance figures and market data in the demo are simulated and clearly identified as such.' },
]

const testimonials = [
  { quote: 'The dashboard makes the workflow clear, elegant, and easy to present to prospective clients.', author: 'Demo Strategy Team' },
  { quote: 'This is the right balance of clean fintech UX and strict demo disclosure for a managed account presentation.', author: 'Growth Advisory Panel' },
]

function LandingPage() {
  const [strategies, setStrategies] = useState<PublicStrategiesResponse['data']>([])
  const [strategiesLoading, setStrategiesLoading] = useState(true)
  const [strategiesError, setStrategiesError] = useState<string | null>(null)
  const [strategyRefresh, setStrategyRefresh] = useState(0)

  useDocumentTitle('Home')

  useEffect(() => {
    let active = true

    void apiRequest<PublicStrategiesResponse>('/api/strategies')
      .then((response) => {
        if (!active) return
        if (!response || !Array.isArray(response.data)) {
          setStrategiesError('Strategy data is unavailable right now.')
          return
        }

        setStrategies(response.data)
      })
      .catch((error: unknown) => {
        if (active) setStrategiesError(error instanceof Error ? error.message : 'Unable to load strategies.')
      })
      .finally(() => {
        if (active) setStrategiesLoading(false)
      })

    return () => {
      active = false
    }
  }, [strategyRefresh])

  return (
    <div className="public-shell text-[var(--theme-heading)]">
      <header className="mx-auto flex max-w-7xl items-center justify-between px-6 py-6 lg:px-8">
        <div className="flex items-center gap-3">
          <div className="public-brand-mark">M</div>
          <div>
            <div className="text-lg font-semibold tracking-tight text-[var(--theme-heading)]">Mercury Managed</div>
            <div className="text-[10px] uppercase tracking-[0.24em] text-[var(--theme-muted)]">Demo Platform</div>
          </div>
        </div>

        <nav className="hidden items-center gap-7 text-sm text-[var(--theme-body)] md:flex">
          {navItems.map((item) => (
            item.href.startsWith('#')
              ? <a key={item.label} href={item.href} className="transition hover:text-[var(--theme-heading)]">{item.label}</a>
              : <Link key={item.label} to={item.href} className="transition hover:text-[var(--theme-heading)]">{item.label}</Link>
          ))}
        </nav>

        <div className="flex items-center gap-3">
          <Link to="/login" className="public-secondary-action hidden rounded-full px-4 py-2 text-sm transition sm:inline-flex">
            Login
          </Link>
          <Link to="/register" className="public-primary-action inline-flex rounded-full px-4 py-2 text-sm font-medium transition">
            Get Started
          </Link>
        </div>
      </header>

      <main>
        <section className="relative overflow-hidden">
          <div className="public-hero-light absolute inset-0" />
          <div className="relative mx-auto grid max-w-7xl gap-12 px-6 pb-20 pt-10 lg:grid-cols-[1.1fr_0.9fr] lg:px-8 lg:pb-28 lg:pt-16">
            <div className="max-w-xl">
              <div className="public-kicker mb-6 inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-medium uppercase tracking-[0.18em]">
                Demo Account · Simulated Performance
              </div>
              <h1 className="text-4xl font-semibold tracking-[-0.06em] text-[var(--theme-heading)] sm:text-5xl lg:text-6xl">
                Managed crypto exposure with clear visibility and controlled demo performance.
              </h1>
              <p className="mt-6 max-w-lg text-base text-[var(--theme-body)] sm:text-lg">
                A polished managed-trading presentation platform for account monitoring, tier qualification, simulated market insights, and secure account management.
              </p>

              <div className="mt-8 flex flex-col gap-4 sm:flex-row">
                <Link to="/register" className="public-primary-action inline-flex items-center justify-center rounded-full px-6 py-3.5 text-sm font-semibold transition">
                  Get Started
                </Link>
                <a href="#strategies" className="public-secondary-action inline-flex items-center justify-center rounded-full px-6 py-3.5 text-sm font-semibold transition">
                  Explore Strategies
                </a>
              </div>

              <div className="mt-10 grid max-w-md grid-cols-3 gap-4 text-left">
                <div>
                  <div className="text-2xl font-semibold text-[var(--theme-heading)]">$12.4M</div>
                  <div className="mt-1 text-xs uppercase tracking-[0.18em] text-[var(--theme-muted)]">Demo Volume</div>
                </div>
                <div>
                  <div className="text-2xl font-semibold text-[var(--theme-heading)]">4</div>
                  <div className="mt-1 text-xs uppercase tracking-[0.18em] text-[var(--theme-muted)]">Tiers</div>
                </div>
                <div>
                  <div className="text-2xl font-semibold text-[var(--theme-heading)]">5</div>
                  <div className="mt-1 text-xs uppercase tracking-[0.18em] text-[var(--theme-muted)]">Assets</div>
                </div>
              </div>
            </div>

            <div className="relative">
              <div className="rounded-[2rem] border border-slate-800 bg-slate-900/80 p-4 shadow-2xl shadow-slate-950/60 backdrop-blur-sm">
                <div className="rounded-[1.5rem] border border-slate-800 bg-slate-950 p-5">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Portfolio</p>
                      <h2 className="mt-2 text-3xl font-semibold text-white">$128,540</h2>
                    </div>
                    <div className="rounded-full bg-emerald-500/15 px-2.5 py-1 text-xs font-medium text-emerald-300">
                      +8.6% Demo
                    </div>
                  </div>

                  <div className="mt-6 grid gap-4 sm:grid-cols-2">
                    <div className="rounded-2xl border border-slate-800 bg-slate-900 p-4">
                      <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Managed Balance</div>
                      <div className="mt-3 text-2xl font-semibold text-white">$128.5k</div>
                    </div>
                    <div className="rounded-2xl border border-slate-800 bg-slate-900 p-4">
                      <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Total P/L</div>
                      <div className="mt-3 text-2xl font-semibold text-emerald-300">+$9.2k</div>
                    </div>
                  </div>

                  <div className="mt-6 rounded-2xl border border-slate-800 bg-slate-900 p-4">
                    <div className="flex items-center justify-between text-sm text-slate-400">
                      <span>Performance</span>
                      <span>Since Jan</span>
                    </div>
                    <div className="mt-4 flex items-end gap-2">
                      {[35, 52, 40, 68, 78, 92].map((height, index) => (
                        <div key={index} className="flex-1 rounded-t-xl bg-gradient-to-t from-[#2d7774] to-[#81c9c3]" style={{ height: `${height}px` }} />
                      ))}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className="mx-auto max-w-7xl px-6 py-12 lg:px-8">
          <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
            {highlights.map((item) => (
              <article key={item.title} className="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 transition hover:border-slate-700 hover:bg-slate-900">
                <div className="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-cyan-500/10 text-cyan-300 ring-1 ring-cyan-500/20">✦</div>
                <h3 className="text-xl font-semibold text-white">{item.title}</h3>
                <p className="mt-3 text-sm leading-6 text-slate-400">{item.description}</p>
              </article>
            ))}
          </div>
        </section>

        <section id="how-it-works" className="mx-auto max-w-7xl px-6 py-12 lg:px-8">
          <div className="mb-10 flex items-end justify-between gap-6">
            <div>
              <p className="text-xs uppercase tracking-[0.22em] text-cyan-300">How It Works</p>
              <h2 className="mt-3 text-3xl font-semibold text-white">Simple flow, clear oversight.</h2>
            </div>
          </div>

          <div className="grid gap-6 md:grid-cols-4">
            {steps.map((step, index) => (
              <div key={step} className="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
                <div className="mb-4 flex h-10 w-10 items-center justify-center rounded-full bg-cyan-500/15 text-sm font-semibold text-cyan-300 ring-1 ring-cyan-500/20">
                  {index + 1}
                </div>
                <h3 className="text-lg font-semibold text-white">{step}</h3>
                <p className="mt-3 text-sm text-slate-400">
                  {index === 0 && 'Open a profile and verify your email to unlock the account experience.'}
                  {index === 1 && 'Fund the account using supported assets and a configured deposit wallet address.'}
                  {index === 2 && 'Qualify for a strategy based on the managed balance configuration and tier rules.'}
                  {index === 3 && 'Monitor account performance, balances, and strategy status from a single dashboard.'}
                </p>
              </div>
            ))}
          </div>
        </section>

        <section id="strategies" className="mx-auto max-w-7xl px-6 py-12 lg:px-8">
          <div className="mb-10">
            <p className="public-kicker mb-3 inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-medium uppercase tracking-[0.2em]">
              Strategies
            </p>
            <h2 className="mt-3 text-3xl font-semibold text-[var(--theme-heading)]">Tiered profiles built for different account sizes.</h2>
          </div>

          {strategiesLoading && (
            <div className="grid gap-6 lg:grid-cols-2 xl:grid-cols-4" role="status" aria-label="Loading strategies">
              {[0, 1, 2, 3].map((item) => (
                <div key={item} className="public-card animate-pulse p-5">
                  <div className="h-4 w-28 rounded bg-[var(--theme-surface)]" />
                  <div className="mt-5 h-7 w-32 rounded bg-[var(--theme-surface)]" />
                  <div className="mt-5 h-16 rounded bg-[var(--theme-surface)]" />
                </div>
              ))}
              <span className="sr-only">Loading strategy information...</span>
            </div>
          )}

          {!strategiesLoading && strategiesError && (
            <div role="alert" className="public-card border border-[rgba(227,155,155,0.25)] bg-[rgba(127,29,29,0.10)] p-5 text-sm text-[var(--theme-heading)]">
              <p>{strategiesError}</p>
              <button
                type="button"
                onClick={() => {
                  setStrategiesError(null)
                  setStrategiesLoading(true)
                  setStrategyRefresh((current) => current + 1)
                }}
                className="mt-3 rounded-full border border-[rgba(227,155,155,0.28)] px-4 py-2 font-medium text-[var(--theme-heading)] transition hover:bg-[rgba(227,155,155,0.08)]"
              >
                Try again
              </button>
            </div>
          )}

          {!strategiesLoading && !strategiesError && strategies.length === 0 && (
            <p className="public-card p-5 text-sm text-[var(--theme-body)]">
              No strategy profiles are currently available.
            </p>
          )}

          {!strategiesLoading && !strategiesError && strategies.length > 0 && (
            <div className="grid gap-6 lg:grid-cols-2 xl:grid-cols-4">
              {strategies.map((strategy) => (
                <article key={strategy.id} className="public-card p-5 transition hover:-translate-y-1 hover:border-[rgba(129,201,195,0.3)]">
                  <div className="text-sm uppercase tracking-[0.2em] text-[var(--theme-accent)]">{strategy.name}</div>
                  <div className="mt-4 text-2xl font-semibold text-[var(--theme-heading)]">{formatMinimum(strategy.tiers[0]?.minimum_balance)}</div>
                  <p className="mt-3 text-sm text-[var(--theme-body)]">{strategy.description}</p>
                  <p className="mt-4 text-sm leading-6 text-[var(--theme-muted)]">{strategy.risk_profile} profile</p>
                  <Link to={`/strategies/${strategy.id}`} className="mt-5 inline-flex rounded-full border border-[var(--theme-border)] px-4 py-2 text-sm text-[var(--theme-heading)] transition hover:border-[rgba(129,201,195,0.25)] hover:bg-[rgba(129,201,195,0.05)]">
                    View Details
                  </Link>
                </article>
              ))}
            </div>
          )}
        </section>

        <section id="markets" className="mx-auto max-w-7xl px-6 py-12 lg:px-8">
          <div className="mb-10">
            <p className="public-kicker mb-3 inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-medium uppercase tracking-[0.22em]">Markets</p>
            <h2 className="mt-3 text-3xl font-semibold text-[var(--theme-heading)]">Simulated market overview.</h2>
          </div>

          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            {markets.map((market) => (
              <div key={market.symbol} className="public-card p-5">
                <div className="flex items-center justify-between">
                  <div>
                    <div className="text-lg font-semibold text-[var(--theme-heading)]">{market.symbol}</div>
                    <div className="text-xs uppercase tracking-[0.18em] text-[var(--theme-muted)]">{market.name}</div>
                  </div>
                  <span className="rounded-full bg-[rgba(155,211,179,0.10)] px-2 py-1 text-xs font-medium text-[var(--theme-success)]">{market.change}</span>
                </div>
                <div className="mt-6 text-3xl font-semibold text-[var(--theme-heading)]">{market.price}</div>
                <div className="mt-5 h-20 rounded-xl bg-gradient-to-r from-[rgba(24,31,34,0.95)] via-[rgba(18,25,28,0.92)] to-[rgba(129,201,195,0.18)]" />
              </div>
            ))}
          </div>
        </section>

        <section className="mx-auto max-w-7xl px-6 py-12 lg:px-8">
          <div className="public-card p-8 sm:p-10">
            <div className="grid gap-10 lg:grid-cols-[1fr_0.9fr] lg:items-center">
              <div>
                <p className="public-kicker inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-medium uppercase tracking-[0.22em]">Performance</p>
                <h2 className="mt-3 text-3xl font-semibold text-[var(--theme-heading)]">Clear, simulated growth tracking.</h2>
                <p className="mt-4 max-w-xl text-[var(--theme-body)]">
                  This presentation emphasizes account-level performance visibility without implying live or guaranteed returns. All values are demo-only and clearly labeled.
                </p>
              </div>

              <div className="public-surface rounded-2xl p-5">
                <div className="flex items-center justify-between text-sm text-[var(--theme-muted)]">
                  <span>Demo Account</span>
                  <span className="text-[var(--theme-success)]">+12.8%</span>
                </div>
                <div className="mt-5 flex items-end gap-2">
                  {[20, 32, 40, 54, 72, 88, 101].map((height) => (
                    <div key={height} className="flex-1 rounded-t-xl bg-gradient-to-t from-[rgba(155,211,179,0.75)] to-[var(--theme-accent)]" style={{ height: `${height}px` }} />
                  ))}
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className="mx-auto max-w-7xl px-6 py-12 lg:px-8">
          <div className="mb-10">
            <p className="public-kicker mb-3 inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-medium uppercase tracking-[0.22em]">Testimonials</p>
            <h2 className="mt-3 text-3xl font-semibold text-[var(--theme-heading)]">Demo-ready social proof for presentation use.</h2>
          </div>

          <div className="grid gap-6 md:grid-cols-2">
            {testimonials.map((item) => (
              <blockquote key={item.author} className="public-card p-6">
                <p className="text-lg leading-8 text-[var(--theme-body)]">“{item.quote}”</p>
                <footer className="mt-5 text-sm uppercase tracking-[0.18em] text-[var(--theme-muted)]">{item.author}</footer>
              </blockquote>
            ))}
          </div>
        </section>

        <section id="faq" className="mx-auto max-w-7xl px-6 py-12 lg:px-8">
          <div className="mb-10">
            <p className="public-kicker mb-3 inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-medium uppercase tracking-[0.22em]">FAQ</p>
            <h2 className="mt-3 text-3xl font-semibold text-[var(--theme-heading)]">Frequently asked questions.</h2>
          </div>

          <div className="space-y-4">
            {faqs.map((faq) => (
              <div key={faq.question} className="public-card p-5">
                <h3 className="text-lg font-semibold text-[var(--theme-heading)]">{faq.question}</h3>
                <p className="mt-2 text-sm leading-6 text-[var(--theme-muted)]">{faq.answer}</p>
              </div>
            ))}
          </div>
        </section>

        <section className="mx-auto max-w-7xl px-6 pb-20 pt-12 lg:px-8">
          <div className="public-info-card border-[rgba(129,201,195,0.24)] bg-[rgba(129,201,195,0.08)] p-8 text-center sm:p-12">
            <p className="text-xs uppercase tracking-[0.22em] text-[var(--theme-accent)]">Ready to begin</p>
            <h2 className="mt-3 text-3xl font-semibold text-[var(--theme-heading)] sm:text-4xl">Build a premium managed-trading presentation without real trading risk.</h2>
            <div className="mt-6 flex justify-center">
              <Link to="/register" className="public-primary-action inline-flex rounded-full px-6 py-3.5 text-sm font-semibold transition">
                Get Started
              </Link>
            </div>
          </div>
        </section>
      </main>

      <footer className="border-t border-[var(--theme-border)] bg-[rgba(10,14,17,0.78)]">
        <div className="mx-auto grid max-w-7xl gap-10 px-6 py-12 text-sm text-[var(--theme-muted)] md:grid-cols-5 lg:px-8">
          <div className="md:col-span-2">
            <div className="flex items-center gap-3">
              <div className="public-brand-mark h-9 w-9 text-sm">M</div>
              <div className="font-semibold text-[var(--theme-heading)]">Mercury Managed</div>
            </div>
            <p className="mt-4 max-w-sm leading-6 text-[var(--theme-muted)]">
              Demo-managed crypto presentation platform built to showcase secure account flows, tiered strategies, and simulated market activity.
            </p>
          </div>

          <div>
            <div className="font-semibold uppercase tracking-[0.18em] text-[var(--theme-body)]">Navigation</div>
            <ul className="mt-4 space-y-2">
              {navItems.map((item) => (
                <li key={item.label}>
                  {item.href.startsWith('#')
                    ? <a href={item.href} className="hover:text-[var(--theme-heading)]">{item.label}</a>
                    : <Link to={item.href} className="hover:text-[var(--theme-heading)]">{item.label}</Link>}
                </li>
              ))}
            </ul>
          </div>

          <div>
            <div className="font-semibold uppercase tracking-[0.18em] text-[var(--theme-body)]">Strategies</div>
            <ul className="mt-4 space-y-2">
              {strategies.map((strategy) => (
                <li key={strategy.id}><Link to={`/strategies/${strategy.id}`} className="hover:text-[var(--theme-heading)]">{strategy.name}</Link></li>
              ))}
            </ul>
          </div>

          <div>
            <div className="font-semibold uppercase tracking-[0.18em] text-[var(--theme-body)]">Legal</div>
            <ul className="mt-4 space-y-2">
              <li><Link to="/contact" className="hover:text-[var(--theme-heading)]">Contact</Link></li>
              <li><Link to="/terms" className="hover:text-[var(--theme-heading)]">Terms</Link></li>
              <li><Link to="/privacy" className="hover:text-[var(--theme-heading)]">Privacy</Link></li>
              <li className="mt-3 text-[11px] uppercase tracking-[0.18em] text-[var(--theme-accent)]">Demo Disclosure</li>
            </ul>
          </div>
        </div>
      </footer>
    </div>
  )
}

function formatMinimum(value: string | undefined): string {
  if (!value || !/^\d+(?:\.\d{1,2})?$/.test(value)) return 'Qualification threshold unavailable'
  const [whole, fraction] = value.split('.')
  const groupedWhole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',')
  const amount = fraction && Number(fraction) > 0 ? `${groupedWhole}.${fraction.padEnd(2, '0')}` : groupedWhole

  return `$${amount}+`
}

export default LandingPage
