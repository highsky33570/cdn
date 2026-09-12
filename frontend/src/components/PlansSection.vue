<template>
  <section
    id="plans"
    ref="sectionRef"
    class="plans-section"
    :class="{ 'is-visible': isVisible }"
  >
    <div class="container-page">
      <div class="plans-head">
        <span class="eyebrow">价格</span>
        <h2 class="plans-title">选择适合你的套餐</h2>
        <p class="plans-sub">价格透明，按套餐计费，被攻击不额外收费。</p>

        <div class="region-bar">
          <button class="region-btn is-active" type="button">
            <span class="region-dot"></span>
            日本 · 东京
          </button>
        </div>
      </div>

      <!--
        Cards, not the table this used to be. The client pointed at the
        reference site's pricing grid as worth copying, and the reason it works
        is that the price is the largest thing on screen — a visitor gets the
        number before they read a single feature. A table row buries it.
      -->
      <div class="plan-grid">
        <article
          v-for="(item, i) in plans"
          :key="item.slug"
          class="plan-card"
          :class="{ 'is-featured': item.slug === featuredSlug }"
          :style="{ '--i': i }"
        >
          <span v-if="item.slug === featuredSlug" class="plan-badge">推荐</span>

          <h3 class="plan-name">{{ item.title }}</h3>

          <p class="plan-price">
            <span class="plan-cur">{{ priceOf(item).symbol }}</span>
            <span class="plan-amount">{{ priceOf(item).amount }}</span>
            <span class="plan-per">/ 月</span>
          </p>

          <ul class="plan-specs">
            <li v-for="spec in specsOf(item)" :key="spec.label" class="plan-spec">
              <span class="spec-dot" aria-hidden="true"></span>
              <span class="spec-label">{{ spec.label }}</span>
              <span class="spec-value">{{ spec.value }}</span>
            </li>
          </ul>

          <a-button
            class="plan-cta"
            :type="item.slug === featuredSlug ? 'primary' : 'outline'"
            long
            @click="goPlan(item.slug)"
          >
            立即使用
          </a-button>
        </article>
      </div>

      <p class="plans-note">
        <strong>提示：</strong>
        支持 USDT 支付，链上确认后自动开通。需要更高带宽、更多域名或独立线路，
        <a :href="contact.telegramUrl" target="_blank" rel="noopener">联系客服定制</a>。
      </p>
    </div>
  </section>
</template>

<script setup>
import { useRouter } from 'vue-router'
import { getPlansByGroup } from '../data/plans'
import { contact } from '../data/landing'
import {
  useLivePricing,
  formatPlanPrice,
  resolvePlanSpecs,
} from '../composables/useLivePricing'
import { useReveal } from '../composables/useReveal'

const router = useRouter()
const plans = getPlansByGroup('jpn')
const { sectionRef, isVisible } = useReveal()

/** The tier we steer people to. Kept as a slug so reordering plans is safe. */
const featuredSlug = 'jpn-plus'

// Prices and limits come from /api/products so the homepage cannot drift from
// checkout or from the package that enforces them; data/plans.js values are
// only a fallback when the catalogue is unreachable.
const { priceBySlug, limitsBySlug } = useLivePricing()

/**
 * The card sets the symbol, the number and the period at three different sizes,
 * so it needs them apart rather than as one formatted string.
 *
 * Trailing .00 is dropped: at 46px "5.00" reads as clutter, while a genuine
 * 12.50 keeps its decimals. The symbol comes from the formatter rather than
 * being hardcoded, so a CNY-priced product still renders ¥.
 */
const priceOf = (item) => {
  const formatted = formatPlanPrice(item, priceBySlug.value)
  const match = String(formatted).match(/^(\D*)([\d.,]+)$/)

  if (!match) return { symbol: '$', amount: String(item.price) }

  return {
    symbol: match[1] || '$',
    amount: match[2].replace(/\.00$/, ''),
  }
}

const specsOf = (item) => resolvePlanSpecs(item, limitsBySlug.value)

const goPlan = (slug) => {
  router.push({ path: '/plans', query: { group: 'jpn', plan: slug } })
}
</script>

<style scoped>
.plans-section {
  padding: 120px 0;
  position: relative;
}

.plans-head {
  text-align: center;
  margin-bottom: 44px;
}

.eyebrow {
  display: inline-block;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.18em;
  color: #4d8cff;
  margin-bottom: 16px;
}

.plans-title {
  font-size: clamp(28px, 3.2vw, 44px);
  font-weight: 800;
  margin: 0;
  color: var(--text);
  letter-spacing: -0.02em;
}

.plans-sub {
  margin: 14px 0 0;
  font-size: 16px;
  color: var(--text-2);
}

.region-bar {
  margin-top: 26px;
}

.region-btn {
  display: inline-flex;
  align-items: center;
  gap: 9px;
  height: 38px;
  padding: 0 20px;
  border-radius: 999px;
  border: 1px solid rgba(77, 140, 255, 0.32);
  background: rgba(77, 140, 255, 0.1);
  color: #cfe0ff;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
}

.region-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #4ade80;
  box-shadow: 0 0 8px rgba(74, 222, 128, 0.6);
}

.plan-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 18px;
  align-items: stretch;
}

.plan-card {
  position: relative;
  display: flex;
  flex-direction: column;
  padding: 32px 26px 28px;
  border-radius: 18px;
  border: 1px solid rgba(255, 255, 255, 0.09);
  background: #0b1120;
  opacity: 0;
  transform: translateY(18px);
  transition:
    border-color 0.25s,
    transform 0.25s;
}

.is-visible .plan-card {
  animation: plan-in 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
  animation-delay: calc(var(--i) * 80ms);
}

@keyframes plan-in {
  to {
    opacity: 1;
    transform: none;
  }
}

.plan-card:hover {
  border-color: rgba(77, 140, 255, 0.4);
}

/*
  The featured tier is lifted with a tinted edge and a glow rather than being
  scaled up — scaling knocks the price baselines out of alignment across the
  row, which is the one thing a pricing grid has to keep.
*/
.plan-card.is-featured {
  border-color: rgba(77, 140, 255, 0.55);
  background:
    radial-gradient(
      ellipse at 50% 0%,
      rgba(77, 140, 255, 0.16),
      transparent 62%
    ),
    #0b1120;
  box-shadow: 0 18px 50px -24px rgba(77, 140, 255, 0.65);
}

.plan-badge {
  position: absolute;
  top: -1px;
  right: 20px;
  padding: 5px 12px;
  border-radius: 0 0 8px 8px;
  background: #4d8cff;
  color: #06122c;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.08em;
}

.plan-name {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: var(--text-2);
  letter-spacing: 0.02em;
}

.plan-price {
  display: flex;
  align-items: baseline;
  gap: 3px;
  margin: 14px 0 0;
  color: #fff;
}

.plan-cur {
  font-size: 18px;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.55);
  align-self: flex-start;
  padding-top: 8px;
}

.plan-amount {
  font-size: 46px;
  font-weight: 800;
  line-height: 1;
  letter-spacing: -0.03em;
  font-variant-numeric: tabular-nums;
}

.plan-per {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.5);
  margin-left: 3px;
}

.plan-specs {
  list-style: none;
  margin: 26px 0 0;
  padding: 22px 0 0;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  display: grid;
  gap: 12px;
  flex: 1;
}

.plan-spec {
  display: grid;
  grid-template-columns: 7px minmax(0, 1fr) auto;
  align-items: center;
  gap: 9px;
  font-size: 14px;
}

.spec-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: rgba(127, 176, 255, 0.7);
}

.spec-label {
  color: rgba(255, 255, 255, 0.5);
}

.spec-value {
  color: #eaf1ff;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

:deep(.plan-cta.arco-btn) {
  margin-top: 26px;
  height: 44px;
  border-radius: 11px;
  font-size: 15px;
  font-weight: 700;
}

.plans-note {
  margin: 26px 0 0;
  padding: 16px 22px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.07);
  background: rgba(255, 255, 255, 0.02);
  font-size: 14px;
  line-height: 1.75;
  color: var(--text-2);
}

.plans-note strong {
  color: #eaf1ff;
}

.plans-note a {
  color: #7fb0ff;
  font-weight: 700;
}

.plans-note a:hover {
  text-decoration: underline;
}

@media (max-width: 1100px) {
  .plan-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 620px) {
  .plans-section {
    padding: 72px 0;
  }

  .plan-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .plan-amount {
    font-size: 40px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .plan-card,
  .is-visible .plan-card {
    animation: none;
    opacity: 1;
    transform: none;
  }
}
</style>
