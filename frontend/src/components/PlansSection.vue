<template>
  <section id="plans" class="plans-section" ref="sectionRef" :class="{ 'is-visible': isVisible }">
    <div class="container-page">
      <h2 class="section-title">热门 CDN 套餐</h2>
      <p class="section-desc">
        更多套餐点击套餐页面查看，支持按需定制，满足不同业务需求。
      </p>

      <div class="region-bar">
        <button class="region-btn active">
          <span class="flag">JP</span>
          <span>日本</span>
        </button>
      </div>

      <div class="table-wrapper desktop-only">
        <div class="table-glow"></div>
        
        <div class="table-panel">
          <div class="table-header">
            <div>套餐</div>
            <div>带宽</div>
            <div>流量</div>
            <div>总网站数</div>
            <div>总域名数</div>
            <div>价格</div>
          </div>

          <div v-for="item in plans" :key="item.slug" class="table-row">
            <div class="plan-col">
              <div class="plan-name">{{ item.title }}</div>
            </div>
            <div><div class="value-main">{{ item.bandwidth }}</div></div>
            <div><div class="value-main">{{ item.traffic }}</div></div>
            <div><div class="value-main">{{ item.websites }}</div></div>
            <div><div class="value-main">{{ item.domains }}</div></div>
            <div><div class="price">{{ formatPrice(item) }}</div></div>
            <div class="action-col">
              <a-button type="primary" @click="goPlan(item.slug)">立即购买</a-button>
            </div>
          </div>
        </div>
      </div>

      <div class="table-wrapper mobile-only">
        <div class="table-glow"></div>
        
        <div class="mobile-list">
          <div v-for="item in plans" :key="`${item.slug}-m`" class="mobile-card">
            <div class="mobile-title">{{ item.title }}</div>
            <div class="mobile-grid">
              <div class="mobile-item"><span>带宽</span><span>{{ item.bandwidth }}</span></div>
              <div class="mobile-item"><span>流量</span><span>{{ item.traffic }}</span></div>
              <div class="mobile-item"><span>网站</span><span>{{ item.websites }}</span></div>
              <div class="mobile-item"><span>域名</span><span>{{ item.domains }}</span></div>
            </div>
            <div class="mobile-price">{{ formatPrice(item) }}</div>
            <a-button type="primary" long size="large" @click="goPlan(item.slug)">立即购买</a-button>
          </div>
        </div>
      </div>

      <div class="cta-wrapper">
        <h3 class="cta-title">立即注册，选购更多套餐</h3>
        <a-button type="primary" size="large" class="cta-btn" @click="goRegister">
          开始选购
        </a-button>
      </div>
    </div>
  </section>
</template>

<script setup>
import { useRouter, useRoute } from 'vue-router'
import { getPlansByGroup } from '../data/plans'
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { Message } from '@arco-design/web-vue'
import { dashboardRegisterUrl } from '../config/runtime'
import { useLivePricing, formatPlanPrice } from '../composables/useLivePricing'

const router = useRouter()
const plans = getPlansByGroup('jpn')
const sectionRef = ref(null) 
const isVisible = ref(false) 
let observer = null

// Prices come from /api/products so the homepage cannot drift from checkout;
// data/plans.js values are only a fallback when the catalogue is unreachable.
const { priceBySlug } = useLivePricing()

const formatPrice = (item) => formatPlanPrice(item, priceBySlug.value, ' / 月')

const goPlan = () => {
  router.push({ path: '/plans', query: { group: 'jpn' } })
}

const goRegister = () => {
  window.location.href = dashboardRegisterUrl
}

onMounted(() => {
  observer = new IntersectionObserver((entries) => {
    if (entries[0].isIntersecting) {
      isVisible.value = true 
      if (sectionRef.value) {
        observer.unobserve(sectionRef.value)
      }
    }
  }, {
    threshold: 0.15 
  })

  if (sectionRef.value) {
    observer.observe(sectionRef.value)
  }
})

onUnmounted(() => {
  if (observer && sectionRef.value) {
    observer.unobserve(sectionRef.value)
  }
})
</script>

<style scoped>
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.plans-section {
  padding: 90px 0 110px;
  opacity: 0;
}

.plans-section.is-visible {
  animation: fadeInUp 0.8s ease-out forwards;
}

.region-bar {
  display: flex;
  justify-content: center;
  margin: 36px 0 28px;
}

.region-btn {
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: rgba(255, 255, 255, 0.04);
  color: #fff;
  height: 54px;
  padding: 0 24px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-size: 18px;
}

/* ====== 环境光特效核心 CSS ====== */
.table-wrapper {
  position: relative;
  z-index: 1;
  border-radius: 26px;
}

.table-glow {
  position: absolute;
  inset: 0;
  z-index: -1;
  border-radius: 36px; /* 比内容圆角稍微大一点，光晕更柔和 */
  /* 左侧科技蓝，右上角朱红，右下角深紫，复刻参考图的光效 */
  background:
    radial-gradient(circle at 10% 50%, rgba(30, 100, 255, 0.35), transparent 45%),
    radial-gradient(circle at 90% 20%, rgba(255, 70, 70, 0.25), transparent 45%),
    radial-gradient(circle at 85% 85%, rgba(140, 40, 255, 0.25), transparent 45%);
  filter: blur(40px);
  opacity: 1.2; /* 基础状态微光 */
  transition: all 0.5s cubic-bezier(0.2, 0.8, 0.2, 1);
}

/* 鼠标悬浮在表格外框时：光晕爆发 */
.table-wrapper:hover .table-glow {
  opacity: 1;
  filter: blur(45px);
  transform: scale(1.02); /* 光晕往外轻微扩散 */
  background:
    radial-gradient(circle at 10% 50%, rgba(30, 100, 255, 0.55), transparent 50%),
    radial-gradient(circle at 90% 20%, rgba(255, 70, 70, 0.45), transparent 50%),
    radial-gradient(circle at 85% 85%, rgba(140, 40, 255, 0.45), transparent 50%);
}
/* ====== 环境光特效结束 ====== */

.table-panel {
  position: relative;
  z-index: 2; /* 确保表格在光晕上方 */
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 26px;
  overflow: hidden;
  /* 稍微增强表格自身的深色背景，让光晕主要从四周透出 */
  background: linear-gradient(135deg, rgba(18, 27, 46, 0.95), rgba(15, 21, 39, 0.98));
  backdrop-filter: blur(10px);
}

.table-header,
.table-row {
  display: grid;
  grid-template-columns: 1.75fr 1fr 1fr 1fr 1fr 1.1fr 132px;
  gap: 18px;
  align-items: center;
  padding: 0 24px;
}

.table-header {
  min-height: 84px;
  color: rgba(255, 255, 255, 0.56);
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  font-size: 15px;
}

.table-row {
  min-height: 116px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  position: relative;
  transition: all 0.3s ease;
}

.table-row:last-child {
  border-bottom: none;
}

/* 鼠标悬浮具体某一行时：背景高亮 */
.table-row:hover {
  background-color: rgba(255, 255, 255, 0.05);
  border-bottom-color: transparent; /* 悬浮时隐藏底部边框，看起来更像独立的卡片 */
  z-index: 10;
}

.plan-col {
  min-width: 0;
}

.plan-name {
  font-size: 24px;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.96);
}

.value-main {
  font-size: 22px;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.96);
  white-space: nowrap;
}

.price {
  font-size: 28px;
  font-weight: 800;
  color: rgba(255, 255, 255, 0.96);
  white-space: nowrap;
}

.action-col {
  display: flex;
  justify-content: flex-end;
}

:deep(.action-col .arco-btn) {
  min-width: 104px;
}

.desktop-only {
  display: block;
}

.mobile-only {
  display: none;
}

.mobile-list {
  display: flex;
  flex-direction: column;
  gap: 18px;
  position: relative;
  z-index: 2;
}

.mobile-card {
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 22px;
  padding: 28px 20px 20px;
  background: linear-gradient(90deg, rgba(18, 27, 46, 0.95), rgba(15, 21, 39, 0.98));
  backdrop-filter: blur(10px);
}

.mobile-title {
  font-size: 32px;
  font-weight: 800;
  margin-bottom: 24px;
  color: #fff;
}

.mobile-grid {
  display: grid;
  gap: 14px;
  margin-bottom: 28px;
}

.mobile-item {
  display: grid;
  grid-template-columns: 72px 1fr;
  gap: 16px;
}

.mobile-item span:first-child {
  color: rgba(255, 255, 255, 0.48);
  font-size: 18px;
}

.mobile-item span:last-child {
  color: rgba(255, 255, 255, 0.84);
  font-size: 18px;
  font-weight: 600;
}

.mobile-price {
  font-size: 48px;
  font-weight: 800;
  color: #fff;
  margin-bottom: 24px;
}

.cta-wrapper {
  margin-top: 72px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
}

.cta-title {
  font-size: 32px;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.9);
  margin: 0 0 32px 0;
  letter-spacing: 0.02em;
}

:deep(.cta-btn.arco-btn) {
  min-width: 180px;
  height: 48px;
  font-size: 18px;
  font-weight: 500;
  border-radius: 6px;
  background-color: #4063ff;
  border-color: #4063ff;
}

:deep(.cta-btn.arco-btn:hover) {
  background-color: #2b4deb;
  border-color: #2b4deb;
}

@media (max-width: 1180px) {
  .table-header,
  .table-row {
    grid-template-columns: 1.55fr 0.9fr 0.9fr 0.9fr 0.9fr 1fr 120px;
    gap: 14px;
    padding: 0 18px;
  }

  .plan-name { font-size: 20px; }
  .value-main { font-size: 18px; }
  .price { font-size: 22px; }
}

@media (max-width: 900px) {
  .desktop-only { display: none; }
  .mobile-only { display: block; }
}

@media (max-width: 520px) {
  .mobile-card { padding: 22px 16px 18px; }
  .mobile-title { font-size: 28px; }
  .mobile-item { grid-template-columns: 64px 1fr; gap: 12px; }
  .mobile-item span:first-child,
  .mobile-item span:last-child { font-size: 16px; }
  .mobile-price { font-size: 40px; }
  .cta-wrapper { margin-top: 56px; }
  .cta-title { font-size: 24px; margin-bottom: 24px; }
}
</style>
