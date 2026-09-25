<template>
  <article data-public-style class="public-plan-card plan-card" :class="{ 'is-selected': selected }">
    <div class="plan-card__heading">
      <span v-if="number !== null" class="plan-number" aria-hidden="true">{{
        String(number).padStart(2, '0')
      }}</span>
      <span class="plan-heading-line" aria-hidden="true"></span>
      <span v-if="selected" class="plan-selected">已选套餐</span>
      <span v-else class="plan-cycle">{{ cycle.label }}</span>
    </div>
    <h3>{{ product.name }}</h3>
    <div class="plan-price">
      <strong>{{ priceText }}</strong
      ><span v-if="amount !== null"
        >{{ displayCurrency(product.currency) }} / {{ cycle.unit }}</span
      >
    </div>
    <ul v-if="specs.length || features.length" class="plan-inclusions">
      <li
        v-for="spec in specs"
        :key="spec.label"
        :class="{ 'is-unavailable': spec.value === '不支持' }"
      >
        <span class="inclusion-mark" aria-hidden="true">{{
          spec.value === '不支持' ? '−' : '✓'
        }}</span>
        <span
          ><span class="inclusion-label">{{ spec.label }}：</span
          ><strong :class="{ 'is-unlimited': spec.value === '不限' }">{{
            spec.value
          }}</strong></span
        >
      </li>
      <li v-for="(feature, index) in features" :key="`feature-${index}`">
        <span class="inclusion-mark" aria-hidden="true">✓</span
        ><span class="inclusion-feature">{{ feature }}</span>
      </li>
    </ul>
    <div class="plan-actions">
      <a v-if="amount !== null" :href="checkoutUrl" class="button button--primary"
        >立即使用 <span aria-hidden="true">↗</span></a
      >
      <button v-else class="button" disabled>当前周期不可用</button>
      <router-link
        v-if="product.slug"
        :to="`/plans/${encodeURIComponent(product.slug)}`"
        class="plan-details"
        >查看套餐详情 <span aria-hidden="true">→</span></router-link
      >
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue'
import {
  billingPeriods,
  productPrice,
  formatAmount,
  displayCurrency,
  productSpecs,
  productFeatures
} from '../utils/products'
import { buildAuthPageUrl, buildConsoleCheckoutPath, dashboardLoginUrl } from '../config/runtime'
const props = defineProps({
  product: { type: Object, required: true },
  period: { type: String, default: 'monthly' },
  number: { type: Number, default: null },
  selected: Boolean
})
const cycle = computed(
  () => billingPeriods.find((item) => item.key === props.period) || billingPeriods[0]
)
const amount = computed(() => productPrice(props.product, cycle.value.key))
const priceText = computed(() => formatAmount(amount.value))
const specs = computed(() => productSpecs(props.product))
const features = computed(() => {
  // Older catalog responses may provide prose specs without structured limits.
  const fallback =
    !specs.value.length && Array.isArray(props.product.specs)
      ? props.product.specs.filter((item) => typeof item === 'string' && item.trim())
      : []
  return [...new Set([...fallback, ...productFeatures(props.product)])]
})
const checkoutUrl = computed(() =>
  buildAuthPageUrl(dashboardLoginUrl, buildConsoleCheckoutPath(props.product.id))
)
</script>
