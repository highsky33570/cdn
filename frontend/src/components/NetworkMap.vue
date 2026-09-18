<template>
  <div class="network-map" :class="{ 'network-map--compact': compact }">
    <div class="network-heading">
      <div>
        <span class="eyebrow">TYCDN NETWORK</span>
        <h3>边缘网络</h3>
      </div>
      <span class="network-total">{{ edgeNodes.length }} 个节点 IP</span>
    </div>
    <div class="network-canvas" role="img" :aria-label="mapLabel">
      <div class="network-land" aria-hidden="true"></div>
      <div
        v-for="location in mappedLocations"
        :key="location.key"
        class="network-marker"
        :style="{ left: `${location.x}%`, top: `${location.y}%` }"
      >
        <span class="marker-ring" aria-hidden="true"></span
        ><span class="marker-dot" aria-hidden="true"></span
        ><span class="marker-label">{{ location.label }}</span>
      </div>
      <div class="network-caption">
        <span class="caption-line" aria-hidden="true"></span>边缘接入 · 内容分发 · 安全防护
      </div>
    </div>
    <div class="network-inventory">
      <div class="inventory-heading">
        <span>节点 IP</span
        ><span v-if="onlineNodes !== null" class="network-status">全网在线 {{ onlineNodes }}</span
        ><span v-else class="network-status is-unknown">在线状态未获取</span>
      </div>
      <ul>
        <li v-for="(node, index) in edgeNodes" :key="node.ip">
          <span class="node-number">{{ String(index + 1).padStart(2, '0') }}</span
          ><code>{{ node.ip }}</code
          ><span v-if="nodeLocation(node)" class="node-location">{{ nodeLocation(node) }}</span>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { edgeNodes, edgeLocations } from '../data/network'
import { useNetworkStats, locationCount } from '../composables/useNetworkStats'
defineProps({ compact: Boolean })
const { onlineNodes, locations } = useNetworkStats()
const mappedLocations = computed(() =>
  edgeLocations.filter(
    (location) =>
      edgeNodes.some((node) => node.locationKey === location.key) ||
      locationCount(locations.value, location.key) > 0
  )
)
const nodeLocation = (node) =>
  edgeLocations.find((location) => location.key === node.locationKey)?.label
const mapLabel = computed(
  () =>
    `TyCDN 边缘网络地图${mappedLocations.value.length ? '：' + mappedLocations.value.map((location) => location.label).join('、') : ''}`
)
</script>

<style scoped>
.network-map {
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 22px;
  background: var(--panel);
  box-shadow: var(--shadow);
}
.network-heading {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding: 24px 28px 0;
}
.eyebrow {
  font-size: 10px;
  letter-spacing: 0.15em;
}
h3 {
  margin: 6px 0 0;
  font-size: 18px;
  color: var(--text-strong);
}
.network-total {
  padding: 6px 10px;
  border: 1px solid var(--border);
  border-radius: 7px;
  color: var(--text-2);
  font-size: 12px;
  white-space: nowrap;
}
.network-canvas {
  position: relative;
  aspect-ratio: 3933 / 2540;
  margin: 12px 28px 0;
  background-image:
    linear-gradient(var(--map-grid) 1px, transparent 1px),
    linear-gradient(90deg, var(--map-grid) 1px, transparent 1px);
  background-size: 32px 32px;
}
.network-land {
  position: absolute;
  inset: 0;
  background: var(--map-land);
  -webkit-mask: url('/images/world-map-japan.svg') center / 100% 100% no-repeat;
  mask: url('/images/world-map-japan.svg') center / 100% 100% no-repeat;
}
.network-marker {
  position: absolute;
  width: 12px;
  height: 12px;
  transform: translate(-50%, -50%);
}
.marker-dot {
  display: block;
  width: 12px;
  height: 12px;
  border: 2px solid var(--panel);
  border-radius: 50%;
  background: var(--accent-fill);
  box-shadow: 0 0 0 5px var(--accent-glow);
}
.marker-ring {
  position: absolute;
  inset: -7px;
  border: 1px solid var(--accent);
  border-radius: 50%;
  animation: ping 2.8s ease-out infinite;
}
.marker-label {
  position: absolute;
  bottom: 20px;
  right: -12px;
  padding: 5px 8px;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--panel);
  color: var(--text);
  white-space: nowrap;
  font-size: 11px;
  box-shadow: var(--shadow);
}
.network-caption {
  position: absolute;
  bottom: 8px;
  left: 0;
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--text-3);
  font-size: 11px;
}
.caption-line {
  width: 20px;
  height: 2px;
  background: var(--accent);
}
.network-inventory {
  padding: 20px 28px 26px;
  border-top: 1px solid var(--border);
}
.inventory-heading {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-2);
}
.network-status {
  color: var(--ok);
}
.network-status.is-unknown {
  color: var(--text-3);
}
ul {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  list-style: none;
  margin: 0;
  padding: 0;
}
li {
  min-width: 0;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  padding: 12px;
  border: 1px solid var(--border);
  border-radius: 9px;
  background: var(--panel-2);
}
.node-number {
  color: var(--text-3);
  font-size: 10px;
}
code {
  color: var(--text);
  font:
    12px/1.5 'Cascadia Code',
    Consolas,
    monospace;
}
.node-location {
  margin-left: auto;
  color: var(--text-3);
  font-size: 10px;
}
.network-map--compact ul {
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}
.network-map--compact .node-location {
  width: 100%;
  margin-left: 22px;
}
.network-map--compact .network-inventory {
  padding: 18px 22px 22px;
}
.network-map--compact .network-heading {
  padding: 22px 22px 0;
}
.network-map--compact .network-canvas {
  margin-inline: 22px;
}
@keyframes ping {
  from {
    transform: scale(0.65);
    opacity: 0.6;
  }
  to {
    transform: scale(1.6);
    opacity: 0;
  }
}
@media (max-width: 640px) {
  ul {
    grid-template-columns: 1fr;
  }
  .network-heading {
    padding: 20px 20px 0;
  }
  .network-canvas {
    margin-inline: 20px;
  }
  .network-inventory {
    padding: 20px;
  }
  .network-caption {
    font-size: 9px;
  }
}
</style>
