<template>
  <article class="plan-card" :class="{ 'is-selected': selected }">
    <div class="plan-card__heading">
      <span class="plan-icon" aria-hidden="true"
        ><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
          <rect x="3" y="3" width="18" height="7" rx="2" />
          <rect x="3" y="14" width="18" height="7" rx="2" />
          <path d="M7 6.5h.01M7 17.5h.01M11 6.5h6M11 17.5h6" /></svg
      ></span>
      <h3>{{ product.name }}</h3>
      <span v-if="selected" class="plan-selected">已选套餐</span>
      <span v-else class="plan-cycle">{{ cycle.label }}</span>
    </div>
    <div class="plan-price">
      <strong>{{ priceText }}</strong
      ><span v-if="amount !== null">{{ displayCurrency(product.currency) }} / {{ cycle.unit }}</span>
    </div>
    <dl v-if="specs.length" class="plan-specs">
      <div v-for="spec in specs" :key="spec.label">
        <dt>{{ spec.label }}</dt>
        <dd>{{ spec.value }}</dd>
      </div>
    </dl>
    <div class="plan-actions">
      <a v-if="amount !== null" :href="checkoutUrl" class="button button--primary"
        >选择套餐 <span aria-hidden="true">↗</span></a
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
  productSpecs
} from '../utils/products'
import { buildAuthPageUrl, buildConsoleCheckoutPath, dashboardLoginUrl } from '../config/runtime'
const props = defineProps({
  product: { type: Object, required: true },
  period: { type: String, default: 'monthly' },
  selected: Boolean
})
const cycle = computed(
  () => billingPeriods.find((item) => item.key === props.period) || billingPeriods[0]
)
const amount = computed(() => productPrice(props.product, cycle.value.key))
const priceText = computed(() => formatAmount(amount.value))
// Keep cards easy to compare; the detail page includes all specs and features.
const specs = computed(() => productSpecs(props.product).slice(0, 4))
const checkoutUrl = computed(() =>
  buildAuthPageUrl(dashboardLoginUrl, buildConsoleCheckoutPath(props.product.id))
)
</script>

<style scoped>
.plan-card {
  min-width: 0;
  display: flex;
  flex-direction: column;
  padding: 22px;
  border: 1px solid var(--border);
  border-radius: 16px;
  background: var(--panel);
  box-shadow: var(--shadow);
  transition:
    border-color 0.2s,
    transform 0.2s;
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
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 10px;
}
.plan-icon {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: var(--accent-soft);
  color: var(--accent);
}
.plan-icon svg {
  width: 20px;
  height: 20px;
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
  margin: 0;
  font-size: 17px;
  line-height: 1.4;
  color: var(--text-strong);
  overflow-wrap: anywhere;
}
.plan-price {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 8px;
  margin: 20px 0;
}
.plan-price strong {
  color: var(--text-strong);
  font-size: 34px;
  letter-spacing: -0.045em;
  line-height: 1.2;
  overflow-wrap: anywhere;
}
.plan-price span {
  color: var(--text-3);
  font-size: 13px;
}
.plan-specs {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px 12px;
  margin: 0;
  padding: 16px 0;
  border-block: 1px solid var(--border);
}
.plan-specs div {
  display: grid;
  align-content: start;
  gap: 3px;
  min-width: 0;
}
dt {
  color: var(--text-3);
  font-size: 11px;
}
dd {
  margin: 0;
  color: var(--text-strong);
  font-size: 14px;
  font-weight: 600;
  overflow-wrap: anywhere;
}
.plan-actions {
  display: grid;
  gap: 10px;
  padding-top: 18px;
  margin-top: auto;
  text-align: center;
}
.plan-actions .button {
  min-height: 40px;
  padding: 9px 14px;
  font-size: 13px;
  border-radius: 9px;
}
.plan-details {
  color: var(--text-2);
  font-size: 12px;
}
.plan-details:hover {
  color: var(--accent);
}
</style>
