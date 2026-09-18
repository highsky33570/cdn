<template>
  <div class="plans-page">
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

<style scoped>
.plans-page {
  min-height: 100vh;
  background: radial-gradient(ellipse at 50% 0%, var(--accent-glow), transparent 44%), var(--bg);
}
.plans-main {
  padding-top: var(--header-h);
}
.plans-hero {
  padding-top: 76px;
  padding-bottom: 56px;
  text-align: center;
}
.plans-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 7px 12px;
  border: 1px solid var(--accent-border);
  border-radius: 8px;
  background: var(--accent-soft);
  color: var(--accent);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.12em;
}
h1 {
  margin: 24px 0 0;
  font-size: clamp(30px, 3.7vw, 48px);
  line-height: 1.4;
  letter-spacing: -0.04em;
  color: var(--text-strong);
}
h1 span {
  color: var(--accent);
}
.plans-hero p {
  color: var(--text-2);
  font-size: 16px;
  line-height: 1.9;
  margin: 20px auto 0;
}
.plans-highlights {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  gap: 12px 24px;
  margin-top: 28px;
  color: var(--text-2);
  font-size: 13px;
}
.plans-highlights span::before {
  content: '✓';
  margin-right: 7px;
  color: var(--ok);
}
.plans-content {
  padding-bottom: 88px;
}
.plans-help {
  margin-top: 32px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  padding: 28px 32px;
  border: 1px solid var(--border);
  border-radius: 18px;
  background: var(--panel);
}
.plans-help h2 {
  font-size: 18px;
  margin: 0;
  color: var(--text-strong);
}
.plans-help p {
  color: var(--text-2);
  margin: 8px 0 0;
}
.mobile-break {
  display: none;
}
@media (max-width: 640px) {
  .plans-hero {
    padding-top: 48px;
    padding-bottom: 36px;
  }
  .plans-help {
    flex-direction: column;
    align-items: stretch;
    padding: 24px;
  }
  .mobile-break {
    display: inline;
  }
}
</style>
