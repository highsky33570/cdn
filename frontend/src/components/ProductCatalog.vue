<template>
  <div class="catalog" :aria-busy="loading">
    <div v-if="products.length" class="catalog-toolbar">
      <span class="catalog-count">{{ products.length }} 款可选套餐</span>
      <div class="billing-switch" role="group" aria-label="计费周期">
        <button
          v-for="cycle in availablePeriods"
          :key="cycle.key"
          type="button"
          :aria-pressed="period === cycle.key"
          @click="period = cycle.key"
        >
          {{ cycle.label }}
        </button>
      </div>
    </div>
    <div v-if="loading" class="catalog-state" role="status">
      <span class="catalog-spinner" aria-hidden="true"></span>
      <h3>正在加载套餐</h3>
      <p>即将为你呈现可选方案。</p>
    </div>
    <div v-else-if="error" class="catalog-state" role="alert">
      <span class="state-icon" aria-hidden="true">↻</span>
      <h3>套餐加载失败</h3>
      <p>{{ error }}</p>
      <button class="button" type="button" @click="reload">重新加载</button>
    </div>
    <div v-else-if="!products.length" class="catalog-state" role="status">
      <span class="state-icon" aria-hidden="true">◇</span>
      <h3>暂无可选套餐</h3>
      <p>如需了解适合你的方案，请联系我们。</p>
      <a class="button" :href="contact.telegramUrl" target="_blank" rel="noopener noreferrer"
        >联系咨询 ↗</a
      >
    </div>
    <div v-else class="catalog-grid">
      <PlanCard
        v-for="product in products"
        :key="product.id"
        :product="product"
        :period="period"
        :selected="selectedSlug === product.slug"
      />
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import PlanCard from './PlanCard.vue'
import { useProductCatalog } from '../composables/useProductCatalog'
import { billingPeriods, productPrice } from '../utils/products'
import { contact } from '../data/landing'
defineProps({ selectedSlug: { type: String, default: '' } })
const { products, loading, error, reload } = useProductCatalog()
const period = ref('monthly')
const availablePeriods = computed(() =>
  billingPeriods.filter((cycle) =>
    products.value.some((product) => productPrice(product, cycle.key) !== null)
  )
)
watch(
  availablePeriods,
  (cycles) => {
    if (cycles.length && !cycles.some((cycle) => cycle.key === period.value))
      period.value = cycles[0].key
  },
  { immediate: true }
)
</script>

<style scoped>
.catalog-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 28px;
}
.catalog-count {
  color: var(--text-2);
  font-size: 14px;
}
.billing-switch {
  display: flex;
  gap: 4px;
  padding: 5px;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: var(--surface);
}
.billing-switch button {
  border: 0;
  border-radius: 8px;
  padding: 9px 22px;
  background: transparent;
  color: var(--text-2);
  cursor: pointer;
  font-weight: 600;
}
.billing-switch button[aria-pressed='true'] {
  background: var(--panel);
  color: var(--accent);
  box-shadow: var(--shadow);
}
.catalog-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr));
  gap: 22px;
}
.catalog-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  min-height: 320px;
  padding: 56px 24px;
  background: var(--panel);
  border: 1px solid var(--border);
  border-radius: 20px;
}
.catalog-state h3 {
  margin: 20px 0 0;
  color: var(--text-strong);
  font-size: 21px;
}
.catalog-state p {
  margin: 12px 0 24px;
  color: var(--text-2);
}
.state-icon {
  display: grid;
  place-items: center;
  width: 48px;
  height: 48px;
  border-radius: 14px;
  background: var(--accent-soft);
  color: var(--accent);
  font-size: 28px;
}
.catalog-spinner {
  width: 28px;
  height: 28px;
  border: 3px solid var(--border);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
@media (max-width: 480px) {
  .catalog-toolbar {
    align-items: flex-start;
    flex-direction: column;
  }
  .billing-switch {
    width: 100%;
  }
  .billing-switch button {
    flex: 1;
  }
}
</style>
