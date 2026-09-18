<template>
  <article class="plan-card" :class="{ 'is-selected': selected }">
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

<style scoped>
.plan-card {
  position: relative;
  min-width: 0;
  display: flex;
  flex-direction: column;
  padding: 24px;
  border: 1px solid var(--border);
  border-radius: 16px;
  background: var(--panel);
  box-shadow: var(--shadow);
  transition:
    border-color 0.2s,
    transform 0.2s;
}
.plan-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 24px;
  right: 24px;
  height: 2px;
  background: linear-gradient(90deg, var(--accent), var(--accent-border));
  border-radius: 0 0 2px 2px;
}
.plan-card:hover {
  transform: translateY(-4px);
  border-color: var(--accent-border);
}
.plan-card.is-selected {
  border-color: var(--accent);
  box-shadow: 0 0 0 1px var(--accent);
}
.plan-card__heading {
  display: flex;
  align-items: center;
  gap: 12px;
}
.plan-number {
  color: var(--accent);
  font:
    600 15px/1.5 'Cascadia Code',
    Consolas,
    monospace;
  letter-spacing: 0.04em;
}
.plan-heading-line {
  flex: 1;
  height: 1px;
  background: var(--border);
}
.plan-cycle,
.plan-selected {
  font-size: 11px;
  color: var(--text-2);
  background: var(--surface);
  border-radius: 6px;
  padding: 4px 9px;
  white-space: nowrap;
}
.plan-selected {
  color: var(--accent);
  background: var(--accent-soft);
}
h3 {
  margin: 18px 0 0;
  font-size: 23px;
  line-height: 1.4;
  color: var(--text-strong);
  overflow-wrap: anywhere;
}
.plan-price {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 7px;
  margin: 16px 0 22px;
}
.plan-price strong {
  color: var(--text-strong);
  font-size: 44px;
  letter-spacing: -0.045em;
  line-height: 1.2;
  overflow-wrap: anywhere;
}
.plan-price span {
  color: var(--text-3);
  font-size: 12px;
  white-space: nowrap;
}
.plan-inclusions {
  display: grid;
  gap: 9px;
  list-style: none;
  margin: 0;
  padding: 20px 0 0;
  border-top: 1px solid var(--border);
}
.plan-inclusions li {
  display: grid;
  grid-template-columns: 14px minmax(0, 1fr);
  align-content: start;
  gap: 8px;
  min-width: 0;
  font-size: 13px;
  line-height: 1.65;
  overflow-wrap: anywhere;
}
.inclusion-mark {
  color: var(--ok);
  font-size: 12px;
  font-weight: 600;
}
.inclusion-label {
  color: var(--text-2);
}
.plan-inclusions strong {
  color: var(--text);
  font-weight: 600;
}
.plan-inclusions strong.is-unlimited {
  color: var(--accent);
}
.inclusion-feature {
  color: var(--text);
}
.is-unavailable .inclusion-mark,
.is-unavailable strong {
  color: var(--text-3);
  font-weight: 400;
}
.plan-actions {
  display: grid;
  gap: 10px;
  padding-top: 26px;
  margin-top: auto;
  text-align: center;
}
.plan-actions .button {
  min-height: 44px;
  padding: 11px 14px;
  font-size: 14px;
  border-radius: 9px;
}
.plan-details {
  color: var(--text-2);
  font-size: 12px;
}
.plan-details:hover {
  color: var(--accent);
}
@media (min-width: 1200px) {
  .plan-card {
    padding-inline: 20px;
  }
  .plan-card::before {
    left: 20px;
    right: 20px;
  }
}
</style>
