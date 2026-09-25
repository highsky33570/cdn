<template>
  <section data-public-style id="network" class="public-map-section map-section">
    <div class="container-page">
      <div class="map-heading">
        <span class="eyebrow">EDGE NETWORK</span>
        <h2 class="section-title">连接你的业务与用户</h2>
        <p class="section-desc">
          以香港为边缘接入点，连接各地用户。节点状态与中国大陆访问延迟，持续监测。
        </p>
      </div>
      <NetworkMap />
    </div>
  </section>
</template>
<script setup>
import NetworkMap from './NetworkMap.vue'
</script>
