import { useEffect } from 'react'

function useDocumentTitle(title: string) {
  useEffect(() => {
    document.title = `${title} | Mercury Managed`
  }, [title])
}

export { useDocumentTitle }