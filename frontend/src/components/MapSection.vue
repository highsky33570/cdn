<template>
  <section 
    class="map-section"
    ref="sectionRef"
    :class="{ 'is-visible': isVisible }"
  >
    <div class="container-page">
      <h2 class="section-title">亚太节点</h2>
      <p class="section-desc">
        TyCDN目前接入日本东京高速节点，三网双程CN2线路，提供高达12Tbps+的DDoS防护能力
      </p>

      <div class="map-wrap">
        <img
          class="map-image"
          src="/images/world-map-japan.svg"
          alt="world map"
        />

        <button
          class="japan-hotspot"
          @click="goDetail"
          aria-label="日本节点"
        >
          <span class="pulse"></span>
          <span class="dot"></span>
          <span class="label">Japan-tokyo</span>
        </button>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const sectionRef = ref(null)
const isVisible = ref(false)
let observer = null

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
  background: #90b8ff;
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
  color: #fff;
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