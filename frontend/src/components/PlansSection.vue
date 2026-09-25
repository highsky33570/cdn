<template>
  <section data-public-style id="plans" class="public-plans-section plans-section">
    <div class="container-page">
      <div class="plans-head">
        <span class="eyebrow">FLEXIBLE PRICING</span>
        <h2 class="section-title">选择适合你的套餐</h2>
        <p class="section-desc">清晰的价格与资源配置，让每一步增长都有合适的选择。</p>
      </div>
      <ProductCatalog />
      <div class="plans-note">
        <p>
          需要更高带宽、更多域名或独立线路？<a
            :href="contact.telegramUrl"
            target="_blank"
            rel="noopener noreferrer"
            >联系客服定制 ↗</a
          >
        </p>
        <router-link to="/plans">查看套餐页面 →</router-link>
      </div>
    </div>
  </section>
</template>

<script setup>
import ProductCatalog from './ProductCatalog.vue'
import { contact } from '../data/landing'
</script>
