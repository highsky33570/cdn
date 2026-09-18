<template>
  <div class="network-map" :class="{ 'network-map--compact': compact }">
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

<style scoped>
.network-map {
  --network-accent: var(--map-accent);
  overflow: hidden;
  position: relative;
  border: 1px solid var(--border);
  border-radius: 24px;
  background: var(--map-bg);
  box-shadow: var(--shadow);
}
.network-heading {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 28px 32px 0;
}
.network-eyebrow {
  font:
    600 10px/1.5 'Cascadia Code',
    Consolas,
    monospace;
  letter-spacing: 0.16em;
  color: var(--text-3);
}
h3 {
  margin: 8px 0 0;
  color: var(--text-strong);
  font-size: 20px;
  letter-spacing: -0.03em;
}
.network-status {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--text-2);
  font-size: 11px;
  padding: 10px 12px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--map-badge);
  white-space: nowrap;
}
.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--warn);
}
.network-status.is-ready {
  color: var(--network-accent);
}
.is-ready .status-dot {
  background: var(--network-accent);
  box-shadow: 0 0 12px var(--network-accent);
}
.network-canvas {
  position: relative;
  aspect-ratio: 1.85;
  margin: 16px 24px 0;
  isolation: isolate;
}
.network-grid {
  position: absolute;
  inset: 0;
  z-index: -1;
  background-image:
    linear-gradient(var(--map-grid) 1px, transparent 1px),
    linear-gradient(90deg, var(--map-grid) 1px, transparent 1px);
  background-size: 40px 40px;
  mask-image: radial-gradient(ellipse at center, black 30%, transparent 75%);
}
.network-geography {
  position: absolute;
  width: 90%;
  aspect-ratio: 3933 / 2540;
  left: 5%;
  top: 50%;
  transform: translateY(-50%) scaleY(0.85);
}
.network-land {
  position: absolute;
  inset: 0;
  background: radial-gradient(circle, var(--map-land) 1.15px, transparent 1.4px);
  background-size: 6px 6px;
  mask: url('/images/world-map-japan.svg') center / 100% 100% no-repeat;
  -webkit-mask: url('/images/world-map-japan.svg') center / 100% 100% no-repeat;
}
.network-routes {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  overflow: visible;
}
.route-line {
  stroke: var(--map-route);
  stroke-width: 1.4;
  vector-effect: non-scaling-stroke;
}
.route-packet {
  stroke: var(--network-accent);
  stroke-width: 3;
  stroke-linecap: round;
  stroke-dasharray: 0.4 99.6;
  animation: travel 6s linear infinite;
  filter: drop-shadow(0 0 3px var(--network-accent));
  vector-effect: non-scaling-stroke;
}
.audience-ring {
  stroke: var(--map-route);
  stroke-width: 1;
  vector-effect: non-scaling-stroke;
}
.audience-dot {
  fill: var(--network-accent);
}
.network-marker {
  position: absolute;
  width: 10px;
  height: 10px;
  transform: translate(-50%, -50%) scaleY(1.176);
  color: var(--network-accent);
}
.marker-dot {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  background: currentColor;
  box-shadow: 0 0 18px var(--network-accent);
}
.marker-ring,
.marker-orbit {
  position: absolute;
  inset: -7px;
  border: 1px solid currentColor;
  border-radius: 50%;
}
.marker-orbit {
  inset: -15px;
  opacity: 0.25;
}
.marker-ring {
  animation: ping 3s ease-out infinite;
}
.marker-label {
  position: absolute;
  left: 21px;
  top: -14px;
  white-space: nowrap;
  color: var(--text-strong);
  font-size: 12px;
  font-weight: 700;
  text-shadow: 0 1px 6px var(--map-bg);
}
.marker-label small {
  display: block;
  margin-top: 2px;
  font-size: 10px;
  color: var(--text-2);
  font-weight: 400;
}
.network-coordinate {
  position: absolute;
  top: 8px;
  left: 10px;
  font:
    9px/1.5 Consolas,
    monospace;
  color: var(--text-3);
  letter-spacing: 0.06em;
}
.network-counter {
  position: absolute;
  bottom: 20px;
  left: 8px;
  display: grid;
  gap: 5px;
  padding: 13px 16px;
  border: 1px solid var(--border);
  background: var(--map-badge);
  border-radius: 8px;
  backdrop-filter: blur(12px);
}
.network-counter > span {
  color: var(--text-2);
  font-size: 10px;
}
.network-counter strong {
  color: var(--network-accent);
  font-size: 28px;
  line-height: 1.15;
  font-weight: 650;
  font-variant-numeric: tabular-nums;
}
.network-counter strong small {
  font-size: 13px;
  color: var(--text-3);
  font-weight: 400;
}
.network-counter .counter-note {
  color: var(--text-3);
  font-size: 9px;
}
.network-legend {
  position: absolute;
  bottom: 22px;
  right: 8px;
  color: var(--text-2);
  text-align: right;
  font-size: 10px;
  line-height: 2;
}
.network-legend span,
.network-legend i {
  display: inline-block;
  width: 6px;
  height: 6px;
  margin: 0 5px 1px 10px;
  border-radius: 50%;
  background: var(--network-accent);
}
.network-legend i {
  background: transparent;
  border: 1px solid var(--network-accent);
}
.network-legend small {
  color: var(--text-3);
  font-size: 9px;
}
.network-footer {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  padding: 16px 32px;
  border-top: 1px solid var(--border);
  color: var(--text-3);
  font-size: 11px;
}
.footer-dot {
  display: inline-block;
  width: 5px;
  height: 5px;
  margin: 0 7px 2px 0;
  border-radius: 50%;
  background: var(--network-accent);
}
.network-map--compact .network-heading {
  padding: 22px 22px 0;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
}
.network-map--compact h3 {
  font-size: 17px;
}
.network-map--compact .network-status {
  padding: 6px 9px;
  font-size: 10px;
}
.network-map--compact .network-canvas {
  aspect-ratio: 1.4;
  margin: 12px 12px 0;
}
.network-map--compact .network-geography {
  width: 96%;
  left: 2%;
  top: 42%;
}
.network-map--compact .network-land {
  background-size: 4px 4px;
  background-image: radial-gradient(circle, var(--map-land) 0.8px, transparent 1px);
}
.network-map--compact .network-coordinate {
  font-size: 8px;
}
.network-map--compact .network-counter {
  left: 10px;
  bottom: 16px;
  padding: 10px 12px;
}
.network-map--compact .network-counter strong {
  font-size: 24px;
}
.network-map--compact .network-footer {
  padding: 14px 22px;
  font-size: 10px;
  flex-wrap: wrap;
}
@keyframes travel {
  to {
    stroke-dashoffset: -100;
  }
}
@keyframes ping {
  from {
    transform: scale(0.7);
    opacity: 0.8;
  }
  to {
    transform: scale(1.6);
    opacity: 0;
  }
}
@media (max-width: 640px) {
  .network-heading {
    padding: 20px 20px 0;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 12px;
  }
  h3 {
    font-size: 17px;
  }
  .network-status {
    padding: 6px 9px;
    font-size: 10px;
  }
  .network-canvas {
    aspect-ratio: 1.2;
    margin: 12px 8px 0;
  }
  .network-map--compact .network-canvas {
    aspect-ratio: 1.2;
  }
  .network-geography {
    width: 98%;
    left: 1%;
    top: 40%;
  }
  .network-map--compact .network-geography {
    top: 40%;
  }
  .network-land {
    background-size: 4px 4px;
    background-image: radial-gradient(circle, var(--map-land) 0.8px, transparent 1px);
  }
  .marker-label {
    left: auto;
    right: 18px;
    top: 0;
    font-size: 11px;
    text-align: right;
  }
  .marker-label small {
    font-size: 9px;
  }
  .network-counter {
    bottom: 12px;
    padding: 10px;
  }
  .network-legend {
    bottom: 14px;
    font-size: 9px;
  }
  .network-footer {
    flex-wrap: wrap;
    padding: 14px 20px;
    font-size: 10px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .route-packet,
  .marker-ring {
    animation: none;
  }
}
</style>
