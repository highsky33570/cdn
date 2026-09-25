<template>
  <div data-public-style class="public-product-catalog catalog" :aria-busy="loading">
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
    <div v-else class="catalog-grid" :style="{ '--catalog-columns': Math.min(products.length, 5) }">
      <PlanCard
        v-for="(product, index) in products"
        :key="product.id"
        :product="product"
        :number="index + 1"
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
