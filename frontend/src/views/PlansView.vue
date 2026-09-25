<template>
  <div data-public-style class="public-plans-view plans-page">
    <AppHeader />
    <main class="plans-main">
      <section class="plans-hero container-page">
        <span class="plans-eyebrow"><span aria-hidden="true">◇</span> CDN PLANS</span>
        <h1>为你的业务，<br class="mobile-break" /><span>选择合适的加速方案</span></h1>
        <p>从日常访问到业务增长，找到适合你的资源配置。<br />清晰的价格，灵活的选择。</p>
        <div class="plans-highlights">
          <span>按需选择资源</span><span>支持 USDT 支付</span><span>自助管理服务</span>
        </div>
      </section>
      <section class="plans-content container-page" aria-label="可选套餐">
        <ProductCatalog
          :selected-slug="typeof route.query.plan === 'string' ? route.query.plan : ''"
        />
        <div class="plans-help">
          <div>
            <h2>需要更适合你的配置？</h2>
            <p>与我们聊聊带宽、流量和业务需求。</p>
          </div>
          <a class="button" :href="contact.telegramUrl" target="_blank" rel="noopener noreferrer"
            >联系咨询 <span aria-hidden="true">↗</span></a
          >
        </div>
      </section>
    </main>
    <AppFooter />
  </div>
</template>

<script setup>
import { useRoute } from 'vue-router'
import AppHeader from '../components/AppHeader.vue'
import AppFooter from '../components/AppFooter.vue'
import ProductCatalog from '../components/ProductCatalog.vue'
import { contact } from '../data/landing'
const route = useRoute()
</script>
