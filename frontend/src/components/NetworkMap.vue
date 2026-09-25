<template>
  <div data-public-style class="public-network-map network-map" :class="{ 'network-map--compact': compact }">
    <div class="network-heading">
      <div>
        <span class="network-eyebrow">TYCDN / EDGE NETWORK</span>
        <h3>让每一次连接，更近一步</h3>
      </div>
      <div
        class="network-status"
        :class="{
          'is-ready': status === 'ready' && onlineNodes === registeredNodes
        }"
      >
        <span class="status-dot" aria-hidden="true"></span>
        <span>{{ statusLabel }}</span>
      </div>
    </div>
    <div class="network-canvas" role="img" :aria-label="mapLabel">
      <div class="network-grid" aria-hidden="true"></div>
      <div class="network-geography" aria-hidden="true">
        <div class="network-land"></div>
        <svg class="network-routes" viewBox="0 0 1000 646" fill="none">
          <g v-for="(route, index) in routes" :key="route.key">
            <path class="route-line" :d="route.path" />
            <path
              class="route-packet"
              :d="route.path"
              pathLength="100"
              :style="{
                animationDelay: `-${index * 1.1}s`,
                animationDuration: `${5 + index * 0.7}s`
              }"
            />
            <circle class="audience-ring" :cx="route.x" :cy="route.y" r="8" />
            <circle class="audience-dot" :cx="route.x" :cy="route.y" r="2.5" />
          </g>
        </svg>
        <div
          v-for="location in mappedLocations"
          :key="location.key"
          class="network-marker"
          :style="{ left: `${location.x}%`, top: `${location.y}%` }"
        >
          <span class="marker-orbit"></span><span class="marker-ring"></span
          ><span class="marker-dot"></span>
          <span class="marker-label"
            >{{ location.label }}<small>{{ location.count }} 个边缘节点</small></span
          >
        </div>
      </div>
      <div class="network-coordinate" aria-hidden="true">22.28° N &nbsp; 114.17° E</div>
      <div class="network-counter">
        <span>{{ onlineNodes === null ? '已接入边缘节点' : '在线边缘节点' }}</span>
        <strong
          >{{ onlineNodes ?? registeredNodes }}<small> / {{ registeredNodes }}</small></strong
        >
        <span class="counter-note">{{
          onlineNodes === null ? '等待最新探测结果' : '中国大陆探针 · 定时监测'
        }}</span>
      </div>
      <div class="network-legend">
        <span></span>边缘节点<i></i>访问区域<br /><small>连线为访问路径示意</small>
      </div>
    </div>
    <div class="network-footer">
      <span><span class="footer-dot"></span>香港边缘接入 · 内容分发 · 安全防护</span>
      <span>{{ lastChecked ? `最近探测 ${lastChecked}` : '网络监测启动中' }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { audienceLocations, edgeLocations } from '../data/network'
import { useNetworkStats } from '../composables/useNetworkStats'
defineProps({ compact: Boolean })
const { locations, status, statusLabel, onlineNodes, registeredNodes, lastChecked } =
  useNetworkStats()
const mappedLocations = computed(() =>
  locations.value
    .map((location) => ({
      ...edgeLocations.find((item) => item.key === location.key),
      ...location
    }))
    .filter((location) => Number.isFinite(location.x) && Number.isFinite(location.y))
)
const routes = computed(() => {
  const hub = mappedLocations.value[0]
  if (!hub) return []
  const hx = hub.x * 10,
    hy = hub.y * 6.46
  return audienceLocations.map((point) => {
    const x = point.x * 10,
      y = point.y * 6.46
    const lift = Math.min(170, Math.max(32, Math.abs(hx - x) * 0.4))
    return {
      key: point.key,
      x,
      y,
      path: `M ${hx} ${hy} Q ${(hx + x) / 2} ${Math.min(hy, y) - lift} ${x} ${y}`
    }
  })
})
const mapLabel = computed(
  () =>
    `TyCDN 边缘网络：${mappedLocations.value.map((location) => location.label + '，' + location.count + ' 个节点').join('；')}。连线表示访问路径示意。`
)
</script>
