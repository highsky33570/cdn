import { http } from './http'

export async function fetchNetwork() {
  const controller = new AbortController()
  const timeout = setTimeout(() => controller.abort(), 10_000)
  try {
    const res = await http('/api/network', { signal: controller.signal, cache: 'no-store' })
    if (!res?.data || !Array.isArray(res.data.locations)) {
      throw new Error('Invalid network response')
    }
    return res.data
  } finally {
    clearTimeout(timeout)
  }
}
