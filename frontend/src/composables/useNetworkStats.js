import { ref } from 'vue'
import { fetchNetwork } from '../api/network'

/**
 * Live network facts shared across the marketing page (single fetch).
 *
 * `onlineNodes` is null until loaded and on failure, so tiles can fall back to a
 * pending "—" rather than a fake 0. `locations` is [{ key, count }] keyed by the
 * node-name prefix, which the map maps to coordinates.
 */
const onlineNodes = ref(null)
const locations = ref([])
let inflight = null

export function useNetworkStats() {
  if (!inflight) {
    inflight = fetchNetwork()
      .then((data) => {
        const n = Number(data.online_nodes)
        onlineNodes.value = Number.isFinite(n) ? n : null
        locations.value = Array.isArray(data.locations) ? data.locations : []
      })
      .catch(() => {
        onlineNodes.value = null
        locations.value = []
      })
  }

  return { onlineNodes, locations, ready: inflight }
}

/** Online count for one location key (0 when unknown). */
export function locationCount(locationList, key) {
  const hit = locationList.find((l) => l.key === key)

  return hit ? Number(hit.count) || 0 : 0
}
