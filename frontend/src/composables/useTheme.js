import { computed, ref, watch } from 'vue'

/*
 * Light/dark theme switch.
 *
 * One source of truth shared across every component (module-level state).
 * `theme` is always the resolved value — 'light' or 'dark'. On first visit with
 * no saved choice we follow the OS (prefers-color-scheme) and keep following it
 * live; the first manual toggle makes the choice explicit and stops tracking
 * the OS. Applying a theme stamps:
 *   - data-theme on <html>  -> our own CSS custom properties (global.css)
 *   - arco-theme on <body>  -> Arco Design components' dark tokens
 * An inline script in index.html does the same before first paint to avoid a
 * flash; this composable stays consistent with it.
 */

const STORAGE_KEY = 'tycdn-theme'

function readStored() {
  try {
    const value = localStorage.getItem(STORAGE_KEY)

    return value === 'light' || value === 'dark' ? value : null
  } catch {
    return null
  }
}

function systemPrefersDark() {
  return (
    typeof window !== 'undefined' &&
    typeof window.matchMedia === 'function' &&
    window.matchMedia('(prefers-color-scheme: dark)').matches
  )
}

function applyTheme(value) {
  if (typeof document === 'undefined') {
    return
  }

  document.documentElement.setAttribute('data-theme', value)

  if (value === 'dark') {
    document.body.setAttribute('arco-theme', 'dark')
  } else {
    document.body.removeAttribute('arco-theme')
  }
}

const stored = readStored()
// Explicit = the user has picked a side; until then we mirror the OS.
const explicit = ref(stored !== null)
const theme = ref(stored ?? (systemPrefersDark() ? 'dark' : 'light'))

applyTheme(theme.value)

watch(theme, (value) => {
  applyTheme(value)

  if (explicit.value) {
    try {
      localStorage.setItem(STORAGE_KEY, value)
    } catch {
      // storage may be unavailable (private mode); the in-memory value still works
    }
  }
})

// While the choice is implicit, follow the OS live.
if (
  typeof window !== 'undefined' &&
  typeof window.matchMedia === 'function'
) {
  const media = window.matchMedia('(prefers-color-scheme: dark)')
  const onChange = (event) => {
    if (!explicit.value) {
      theme.value = event.matches ? 'dark' : 'light'
    }
  }

  if (typeof media.addEventListener === 'function') {
    media.addEventListener('change', onChange)
  } else if (typeof media.addListener === 'function') {
    media.addListener(onChange)
  }
}

export function useTheme() {
  const isDark = computed(() => theme.value === 'dark')

  function setTheme(value) {
    explicit.value = true
    theme.value = value === 'dark' ? 'dark' : 'light'
  }

  function toggleTheme() {
    setTheme(theme.value === 'dark' ? 'light' : 'dark')
  }

  return { theme, isDark, setTheme, toggleTheme }
}
