<template>
  <article class="plan-card" :class="{ 'is-selected': selected }">
    <div class="plan-card__heading">
      <span class="plan-icon" aria-hidden="true"
        ><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
          <rect x="3" y="3" width="18" height="7" rx="2" />
          <rect x="3" y="14" width="18" height="7" rx="2" />
          <path d="M7 6.5h.01M7 17.5h.01M11 6.5h6M11 17.5h6" /></svg
      ></span>
      <span v-if="selected" class="plan-selected">已选套餐</span>
      <span v-else class="plan-cycle">{{ cycle.label }}</span>
    </div>
    <h3>{{ product.name }}</h3>
    <p v-if="product.description" class="plan-description">{{ product.description }}</p>
    <div class="plan-price">
      <strong>{{ priceText }}</strong
      ><span v-if="amount !== null">/ {{ cycle.unit }}</span>
    </div>
    <p class="plan-currency">{{ product.currency || 'USD' }} · {{ cycle.label }}</p>
    <dl v-if="specs.length" class="plan-specs">
      <div v-for="spec in specs" :key="spec.label">
        <dt>{{ spec.label }}</dt>
        <dd>{{ spec.value }}</dd>
      </div>
    </dl>
    <ul v-if="features.length" class="plan-features">
      <li v-for="(feature, index) in features" :key="index">
        <span aria-hidden="true">✓</span>{{ feature }}
      </li>
    </ul>
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
  formatMoney,
  productSpecs,
  productFeatures
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
const priceText = computed(() => formatMoney(amount.value, props.product.currency || 'USD'))
const specs = computed(() => productSpecs(props.product))
const features = computed(() => productFeatures(props.product))
const checkoutUrl = computed(() =>
  buildAuthPageUrl(dashboardLoginUrl, buildConsoleCheckoutPath(props.product.id))
)
</script>

<style scoped>
.plan-card {
  min-width: 0;
  display: flex;
  flex-direction: column;
  padding: 28px;
  border: 1px solid var(--border);
  border-radius: 20px;
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
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}
.plan-icon {
  display: grid;
  place-items: center;
  width: 42px;
  height: 42px;
  border-radius: 12px;
  background: var(--accent-soft);
  color: var(--accent);
}
.plan-icon svg {
  width: 24px;
  height: 24px;
}
.plan-cycle,
.plan-selected {
  font-size: 12px;
  color: var(--text-2);
  background: var(--surface);
  border-radius: 6px;
  padding: 4px 9px;
}
.plan-selected {
  color: var(--accent);
  background: var(--accent-soft);
}
h3 {
  margin: 24px 0 0;
  font-size: 21px;
  color: var(--text-strong);
  overflow-wrap: anywhere;
}
.plan-description {
  margin: 10px 0 0;
  line-height: 1.8;
  color: var(--text-2);
  overflow-wrap: anywhere;
}
.plan-price {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 6px;
  margin-top: 26px;
}
.plan-price strong {
  color: var(--text-strong);
  font-size: clamp(28px, 3vw, 40px);
  letter-spacing: -0.045em;
  line-height: 1.2;
  overflow-wrap: anywhere;
}
.plan-price span,
.plan-currency {
  color: var(--text-3);
  font-size: 13px;
}
.plan-currency {
  margin: 8px 0 24px;
}
.plan-specs {
  display: grid;
  gap: 13px;
  margin: 0;
  padding: 22px 0;
  border-top: 1px solid var(--border);
}
.plan-specs div {
  display: flex;
  justify-content: space-between;
  gap: 16px;
}
dt {
  color: var(--text-2);
}
dd {
  margin: 0;
  text-align: right;
  font-weight: 600;
  overflow-wrap: anywhere;
}
.plan-features {
  list-style: none;
  margin: 0 0 24px;
  padding: 0;
  display: grid;
  gap: 10px;
  color: var(--text-2);
}
.plan-features li {
  display: flex;
  gap: 10px;
  overflow-wrap: anywhere;
}
.plan-features span {
  color: var(--ok);
}
.plan-actions {
  display: grid;
  gap: 14px;
  padding-top: 12px;
  margin-top: auto;
  text-align: center;
}
.plan-details {
  color: var(--text-2);
  font-size: 13px;
}
.plan-details:hover {
  color: var(--accent);
}
</style>
