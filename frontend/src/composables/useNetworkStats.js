import { computed, onMounted, onUnmounted, ref } from 'vue'
import { fetchNetwork } from '../api/network'
import { edgeLocations } from '../data/network'

const summary = ref(null)
const failed = ref(false)
let inflight = null
let timer = null
let subscribers = 0

const numberOrNull = (value) =>
  typeof value === 'number' && Number.isFinite(value) && value >= 0 ? value : null

async function refresh() {
  if (inflight) return inflight
  inflight = fetchNetwork()
    .then((data) => {
      summary.value = data
      failed.value = false
    })
    .catch(() => {
      failed.value = true
    })
    .finally(() => {
      inflight = null
    })
  return inflight
}

export function useNetworkStats() {
  onMounted(() => {
    subscribers++
    if (subscribers === 1) {
      refresh()
      timer = setInterval(refresh, 60_000)
    }
  })
  onUnmounted(() => {
    if (--subscribers === 0) {
      clearInterval(timer)
      timer = null
    }
  })

  const status = computed(() =>
    failed.value ? 'unavailable' : summary.value?.monitoring_status || 'collecting'
  )
  const onlineNodes = computed(() =>
    status.value === 'ready' ? numberOrNull(summary.value?.online_nodes) : null
  )
  const registeredNodes = computed(
    () =>
      numberOrNull(summary.value?.registered_nodes) ??
      edgeLocations.reduce((sum, item) => sum + item.count, 0)
  )
  const locations = computed(() =>
    summary.value?.locations?.length ? summary.value.locations : edgeLocations
  )
  const latency = computed(() =>
    status.value === 'ready' ? numberOrNull(summary.value?.latency_ms) : null
  )
  const availability = computed(() =>
    failed.value ? null : numberOrNull(summary.value?.availability_percent)
  )
  const historyDays = computed(() => numberOrNull(summary.value?.history_days) ?? 0)
  const windowComplete = computed(() => summary.value?.window_complete === true)
  const lastChecked = computed(() => {
    const date = new Date(summary.value?.last_checked_at || '')
    return Number.isNaN(date.getTime())
      ? ''
      : date.toLocaleString('zh-CN', {
          month: 'numeric',
          day: 'numeric',
          hour: '2-digit',
          minute: '2-digit',
          hour12: false
        })
  })
  const statusLabel = computed(() => {
    if (status.value === 'ready')
      return onlineNodes.value === registeredNodes.value
        ? '全部节点可达'
        : onlineNodes.value === 0
          ? '节点暂不可达'
          : '部分节点可达'
    return (
      {
        stale: '监测待更新',
        unavailable: '监测暂不可用',
        collecting: '正在采集监测数据'
      }[status.value] || '正在采集监测数据'
    )
  })
  return {
    summary,
    status,
    statusLabel,
    onlineNodes,
    registeredNodes,
    locations,
    latency,
    availability,
    historyDays,
    windowComplete,
    lastChecked
  }
}
