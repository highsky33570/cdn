import { http } from './http'

/**
 * Public network summary: online edge-node count + per-location breakdown.
 * Computed and cached server-side (see PublicNetworkController). Marketing pages
 * must render even if this is unreachable, so callers treat a failure as empty.
 */
export async function fetchNetwork() {
  const res = await http('/api/network')

  if (!res?.data || res.data.online_nodes == null) throw new Error('Invalid network response')
  return res.data
}
