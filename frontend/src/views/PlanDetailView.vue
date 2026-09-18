<template>
  <div class="detail-page">
    <AppHeader />
    <main class="detail-main container-page" :aria-busy="loading">
      <router-link to="/plans" class="back-link">← 返回套餐列表</router-link>
      <div v-if="loading" class="detail-state" role="status">正在加载套餐…</div>
      <div v-else-if="error" class="detail-state" role="alert">
        <h1>套餐加载失败</h1>
        <p>{{ error }}</p>
        <button class="button" @click="reload">重新加载</button>
      </div>
      <div v-else-if="product" class="detail-grid">
        <section class="detail-panel">
          <span class="eyebrow">PLAN DETAILS</span>
          <h1>{{ product.name }}</h1>
          <h2 v-if="specs.length">资源配置</h2>
          <dl class="detail-specs">
            <div v-for="spec in specs" :key="spec.label">
              <dt>{{ spec.label }}</dt>
              <dd>{{ spec.value }}</dd>
            </div>
          </dl>
          <template v-if="features.length"
            ><h2>套餐特性</h2>
            <ul>
              <li v-for="(feature, index) in features" :key="index">
                {{ feature }}
              </li>
            </ul></template
          >
          <div class="detail-prices">
            <h2>计费方案</h2>
            <div v-for="cycle in availablePeriods" :key="cycle.key">
              <span>{{ cycle.label }}</span
              ><strong>{{
                formatMoney(productPrice(product, cycle.key), product.currency)
              }}</strong>
            </div>
          </div>
        </section>
        <PlanCard
          :product="product"
          :number="products.indexOf(product) + 1"
          :period="availablePeriods[0]?.key || 'monthly'"
        />
      </div>
      <div v-else class="detail-state" role="status">
        <h1>未找到该套餐</h1>
        <p>该套餐可能已下架，请查看其他可选方案。</p>
        <router-link to="/plans" class="button button--primary">查看可选套餐</router-link>
      </div>
    </main>
    <AppFooter />
  </div>
</template>
<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppHeader from '../components/AppHeader.vue'
import AppFooter from '../components/AppFooter.vue'
import PlanCard from '../components/PlanCard.vue'
import { useProductCatalog } from '../composables/useProductCatalog'
import {
  billingPeriods,
  productSpecs,
  productFeatures,
  productPrice,
  formatMoney
} from '../utils/products'
const route = useRoute()
const { products, loading, error, reload } = useProductCatalog()
const product = computed(() => products.value.find((item) => item.slug === route.params.slug))
const specs = computed(() => productSpecs(product.value))
const features = computed(() => productFeatures(product.value))
const availablePeriods = computed(() =>
  billingPeriods.filter((cycle) => productPrice(product.value, cycle.key) !== null)
)
</script>
<style scoped>
.detail-page {
  min-height: 100vh;
  background: radial-gradient(ellipse at 20% 0%, var(--accent-glow), transparent 50%), var(--bg);
}
.detail-main {
  padding-top: calc(var(--header-h) + 44px);
  padding-bottom: 88px;
  min-height: 65vh;
}
.back-link {
  display: inline-block;
  margin-bottom: 28px;
  color: var(--text-2);
}
.back-link:hover {
  color: var(--accent);
}
.detail-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(300px, 1fr);
  gap: 28px;
  align-items: start;
}
.detail-panel {
  min-width: 0;
  padding: 36px;
  background: var(--panel);
  border: 1px solid var(--border);
  border-radius: 20px;
  box-shadow: var(--shadow);
}
h1 {
  margin: 18px 0;
  color: var(--text-strong);
  font-size: clamp(28px, 3vw, 40px);
  overflow-wrap: anywhere;
}
h2 {
  margin: 30px 0 16px;
  font-size: 17px;
  color: var(--text-strong);
}
li {
  color: var(--text-2);
  line-height: 1.9;
  overflow-wrap: anywhere;
}
.detail-specs {
  margin: 0;
}
.detail-specs div,
.detail-prices > div {
  display: flex;
  justify-content: space-between;
  gap: 20px;
  padding: 14px 0;
  border-bottom: 1px solid var(--border);
}
dt,
.detail-prices span {
  color: var(--text-2);
}
dd {
  margin: 0;
  font-weight: 600;
  text-align: right;
}
ul {
  padding-left: 20px;
}
li + li {
  margin-top: 8px;
}
.detail-state {
  text-align: center;
  padding: 64px 24px;
  border: 1px solid var(--border);
  border-radius: 20px;
  background: var(--panel);
}
.detail-state p {
  margin-bottom: 24px;
  color: var(--text-2);
}
@media (max-width: 850px) {
  .detail-grid {
    grid-template-columns: 1fr;
  }
  .detail-panel {
    padding: 24px;
  }
}
</style>
