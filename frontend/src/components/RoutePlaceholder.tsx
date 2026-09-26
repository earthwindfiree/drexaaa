import { useDocumentTitle } from '../hooks/useDocumentTitle'

interface RoutePlaceholderProps {
  title: string
}

function RoutePlaceholder({ title }: RoutePlaceholderProps) {
  useDocumentTitle(title)

  return (
    <section className="mx-auto w-full max-w-5xl px-6 py-16 lg:px-8">
      <p className="text-xs font-medium uppercase tracking-[0.2em] text-cyan-300">Application foundation</p>
      <h1 className="mt-3 text-3xl font-semibold text-white">{title}</h1>
      <p className="mt-4 max-w-xl text-slate-300">This page is not implemented yet.</p>
    </section>
  )
}

export default RoutePlaceholder