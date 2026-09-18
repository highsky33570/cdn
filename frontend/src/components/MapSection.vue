<template>
  <section 
    class="map-section"
    ref="sectionRef"
    :class="{ 'is-visible': isVisible }"
  >
    <div class="container-page">
      <h2 class="section-title">亚太节点</h2>
      <p class="section-desc">{{ mapDesc }}</p>

      <div class="map-wrap">
        <img
          class="map-image"
          src="/images/world-map-japan.svg"
          alt="world map"
        />

        <button
          v-for="loc in activeRegions"
          :key="loc.key"
          class="japan-hotspot"
          :style="{ left: loc.x + '%', top: loc.y + '%' }"
          :aria-label="loc.label + ' 节点'"
          @click="goDetail"
        >
          <span class="pulse"></span>
          <span class="dot"></span>
          <span class="label">{{ markerLabel(loc) }}</span>
        </button>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { edgeLocations } from '../data/landing'
import { useNetworkStats, locationCount } from '../composables/useNetworkStats'

const router = useRouter()
const sectionRef = ref(null)
const isVisible = ref(false)
let observer = null

const { onlineNodes, locations } = useNetworkStats()

// Once the live data loads, show only regions that actually have online nodes;
// before it loads, fall back to the full curated list.
const activeRegions = computed(() => {
  const withNodes = edgeLocations.filter(
    (l) => locationCount(locations.value, l.key) > 0,
  )

  return withNodes.length ? withNodes : edgeLocations
})

const mapDesc = computed(() => {
  const names = activeRegions.value.map((l) => l.label).join('、')
  const count =
    onlineNodes.value != null ? `${onlineNodes.value} 个在线边缘节点，` : ''

  return `TyCDN 已接入 ${names} 边缘节点，${count}CN2 优化线路，就近接入、边缘清洗全程自动。`
})

const markerLabel = (loc) => {
  const c = locationCount(locations.value, loc.key)

  return c > 0 ? `${loc.label} · ${c}` : loc.label
}

const goDetail = () => {
  router.push('/plans?group=jpn')
}

onMounted(() => {
  observer = new IntersectionObserver(([entry]) => {
    if (entry.isIntersecting) {
      isVisible.value = true
      if (sectionRef.value) observer.unobserve(sectionRef.value)
    }
  }, { threshold: 0.15 })

  if (sectionRef.value) observer.observe(sectionRef.value)
})

onUnmounted(() => {
  if (observer) observer.disconnect()
})
</script>

<style scoped>
.map-section {
  padding: 110px 0 90px;
  opacity: 0; 
}

.map-section.is-visible {
  animation: fadeIn 1s ease-out forwards;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.map-wrap {
  position: relative;
  margin-top: 44px;
  min-height: 720px;
  border-radius: 24px;
  overflow: hidden;
  background: transparent;
  border: none;
  box-shadow: none;
}

.map-image {
  position: relative;
  z-index: 1;
  display: block;
  width: 100%;
  height: 100%;
  min-height: 720px;
  object-fit: contain;
  opacity: 0.24;
  filter: brightness(0.78) contrast(1.02);
}

.japan-hotspot {
  position: absolute;
  z-index: 2;
  border: none;
  background: transparent;
  cursor: pointer;
  padding: 0;
  width: 38px;
  height: 38px;
  transform: translate(-50%, -50%);
  left: 85.6%;
  top: 54.3%;
}

.dot,
.pulse {
  position: absolute;
  inset: 0;
  margin: auto;
  border-radius: 50%;
}

.dot {
  width: 12px;
  height: 12px;
  background: var(--accent-2);
  box-shadow:
    0 0 0 6px rgba(95, 143, 255, 0.14),
    0 0 14px rgba(95, 143, 255, 0.45),
    0 0 24px rgba(95, 143, 255, 0.14);
}

.pulse {
  width: 34px;
  height: 34px;
  border: 2px solid rgba(145, 186, 255, 0.36);
  animation: pulse 1.9s infinite;
}

.label {
  position: absolute;
  top: 28px;
  left: 50%;
  transform: translateX(-50%);
  white-space: nowrap;
  padding: 6px 12px;
  border-radius: 999px;
  background: none;
  color: var(--text);
  font-size: 13px;
  line-height: 1;
}

@keyframes pulse {
  0% {
    transform: scale(0.72);
    opacity: 1;
  }
  100% {
    transform: scale(1.9);
    opacity: 0;
  }
}

@media (max-width: 1200px) {
  .map-wrap,
  .map-image {
    min-height: 620px;
  }
}

@media (max-width: 768px) {
  .map-section {
    padding: 90px 0 70px;
  }

  .map-wrap,
  .map-image {
    min-height: 420px;
    border-radius: 18px;
  }

  .label {
    top: 24px;
    font-size: 12px;
    padding: 5px 10px;
  }
}
</style>