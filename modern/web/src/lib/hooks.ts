import { useEffect, useSyncExternalStore } from 'react'

/** Set the browser tab title for a page. */
export function useTitle(title: string | undefined) {
  useEffect(() => {
    document.title = title ? `${title} · Makan & Co.` : 'Makan & Co. — Buffet catering, made effortless'
  }, [title])
}

/** Live result of a CSS media query, e.g. useMediaQuery('(min-width: 1024px)'). */
export function useMediaQuery(query: string) {
  return useSyncExternalStore(
    (onChange) => {
      const mql = window.matchMedia(query)
      mql.addEventListener('change', onChange)
      return () => mql.removeEventListener('change', onChange)
    },
    () => window.matchMedia(query).matches,
    () => false,
  )
}

let webgl: boolean | undefined
/** Whether this browser can draw WebGL (the 3D buffet preview needs it). */
export function hasWebGL() {
  if (webgl === undefined) {
    try {
      const canvas = document.createElement('canvas')
      webgl = !!(canvas.getContext('webgl2') || canvas.getContext('webgl'))
    } catch {
      webgl = false
    }
  }
  return webgl
}
