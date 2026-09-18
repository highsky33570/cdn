<template>
  <section id="hero" class="hero-section">
    <div class="hero-glow" aria-hidden="true"></div>

    <div class="container-hero hero-inner">
      <div class="hero-copy">
        <button class="hero-badge" type="button" @click="goPlans">
          <span class="hero-badge-dot"></span>
          <span>TyCDN · 日本东京节点 · CN2 优化线路</span>
        </button>

        <p class="hero-kicker">别家一被打就<span class="hero-kicker-mark">绕路卡顿</span>？</p>

        <h1 class="hero-h1">
          攻击时不绕路，<br />
          <span class="hero-h1-accent">业务照常跑</span>
        </h1>

        <p class="hero-lede">
          亚太边缘就近清洗，流量不绕美西、路由不切换。用户无感知，
          你的接口不超时、下载不断流、站点不掉线。
        </p>

        <div class="hero-actions">
          <a-button type="primary" size="large" class="hero-btn" @click="goPlans">
            查看套餐价格
          </a-button>
          <a-button size="large" class="hero-btn hero-btn-ghost" @click="goCompare">
            为什么我们不绕路
          </a-button>
          <a class="hero-tg" :href="contact.telegramUrl" target="_blank" rel="noopener">
            Telegram 咨询
            <span aria-hidden="true">↗</span>
          </a>
        </div>

        <!--
          The reference site opens with hard numbers, and leading with facts is
          the part worth copying. What we must not copy is *their* numbers:
          these stay as dashes until real measurements exist, because inventing
          throughput figures for a network with no nodes deployed would be
          fabricating performance data. See PENDING_FACTS in data/landing.js.
        -->
        <dl class="hero-stats">
          <div v-for="stat in stats" :key="stat.label" class="hero-stat">
            <dd class="hero-stat-value" :class="{ 'is-pending': stat.pending }">
              {{ stat.value }}<small v-if="stat.unit">{{ stat.unit }}</small>
            </dd>
            <dt class="hero-stat-label">{{ stat.label }}</dt>
          </div>
        </dl>

        <div class="hero-scenarios">
          <span class="hero-scenarios-label">适配场景</span>
          <ul class="hero-scenario-list">
            <li v-for="item in scenarios" :key="item" class="hero-scenario">
              {{ item }}
            </li>
          </ul>
        </div>
      </div>

      <div class="hero-visual"><NetworkMap compact /></div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import NetworkMap from './NetworkMap.vue'
import { contact, PENDING_FACTS, scenarios } from '../data/landing'
import { useNetworkStats } from '../composables/useNetworkStats'

const { onlineNodes } = useNetworkStats()

// 在线边缘节点 is real now that nodes are deployed — pull the live count from
// /api/network. Latency and uptime stay pending: publishing them without real
// probe/monitoring data would be fabricating performance figures.
const stats = computed(() => [
  PENDING_FACTS.latency,
  {
    ...PENDING_FACTS.nodes,
    value: onlineNodes.value != null ? String(onlineNodes.value) : '—',
    pending: onlineNodes.value == null
  },
  PENDING_FACTS.uptime
])

const scrollTo = (id) => {
  document.querySelector(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const goPlans = () => scrollTo('#plans')
const goCompare = () => scrollTo('#compare')
</script>

<style scoped>
.hero-section {
  position: relative;
  overflow: hidden;
  padding: calc(var(--header-h) + 72px) 0 96px;
}

.hero-glow {
  position: absolute;
  inset: -20% -10% auto auto;
  width: 70%;
  height: 90%;
  pointer-events: none;
  background: radial-gradient(ellipse at 70% 30%, var(--accent-glow), transparent 62%);
}

.hero-inner {
  position: relative;
  z-index: 2;
  display: grid;
  grid-template-columns: minmax(0, 1.02fr) minmax(0, 0.98fr);
  gap: 40px;
  align-items: center;
}

/* staged entrance */
.hero-copy > * {
  opacity: 0;
  animation: rise 0.75s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
}

.hero-copy > .hero-badge {
  animation-delay: 0.05s;
}
.hero-copy > .hero-kicker {
  animation-delay: 0.15s;
}
.hero-copy > .hero-h1 {
  animation-delay: 0.25s;
}
.hero-copy > .hero-lede {
  animation-delay: 0.35s;
}
.hero-copy > .hero-actions {
  animation-delay: 0.45s;
}
.hero-copy > .hero-stats {
  animation-delay: 0.58s;
}
.hero-copy > .hero-scenarios {
  animation-delay: 0.7s;
}

@keyframes rise {
  from {
    opacity: 0;
    transform: translateY(18px);
  }
  to {
    opacity: 1;
    transform: none;
  }
}

.hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  height: 34px;
  padding: 0 16px;
  border-radius: 999px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--text);
  font-size: 13px;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
}

.hero-badge-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--ok);
  animation: pulse-dot 2.2s infinite;
}

@keyframes pulse-dot {
  0% {
    box-shadow: 0 0 0 0 var(--ok-border);
  }
  70% {
    box-shadow: 0 0 0 7px transparent;
  }
  100% {
    box-shadow: 0 0 0 0 transparent;
  }
}

/*
  The reference site's two-tier headline — a small provocation above the claim —
  is a good device: it names the reader's problem before selling. Kept, in a
  restrained accent rather than orange-on-cream.
*/
.hero-kicker {
  margin: 26px 0 0;
  font-size: clamp(15px, 1.5vw, 18px);
  font-weight: 700;
  color: var(--text-2);
}

.hero-kicker-mark {
  color: var(--warn);
  padding: 0 2px;
}

.hero-h1 {
  margin: 12px 0 0;
  font-size: clamp(34px, 4.4vw, 58px);
  line-height: 1.15;
  font-weight: 800;
  color: var(--text);
  letter-spacing: -0.03em;
}

.hero-h1-accent {
  background: linear-gradient(96deg, var(--accent-2), var(--ok));
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}

.hero-lede {
  margin: 22px 0 0;
  max-width: 46ch;
  font-size: 16px;
  line-height: 1.85;
  color: var(--text-2);
}

.hero-actions {
  margin-top: 34px;
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
}

:deep(.hero-btn.arco-btn) {
  height: 50px;
  min-width: 156px;
  border-radius: 12px;
  font-size: 16px;
  font-weight: 700;
}

:deep(.hero-btn-ghost.arco-btn) {
  background: var(--surface);
  border: 1px solid var(--border);
  color: var(--text);
}

.hero-tg {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  font-size: 15px;
  font-weight: 700;
  color: var(--accent-2);
  transition: color 0.2s;
}

.hero-tg:hover {
  color: var(--accent-3);
}

/* ── stats ────────────────────────────────────────────── */
.hero-stats {
  display: flex;
  flex-wrap: wrap;
  margin: 40px 0 0;
  padding: 24px 0;
  border-top: 1px solid var(--border);
  border-bottom: 1px solid var(--border);
}

.hero-stat {
  padding-right: 40px;
  margin-right: 40px;
  border-right: 1px solid var(--border);
}

.hero-stat:last-child {
  border-right: none;
  margin-right: 0;
  padding-right: 0;
}

.hero-stat-value {
  margin: 0;
  font-size: 30px;
  font-weight: 800;
  color: var(--text);
  line-height: 1.1;
  letter-spacing: -0.02em;
  font-variant-numeric: tabular-nums;
}

.hero-stat-value small {
  font-size: 15px;
  font-weight: 700;
  margin-left: 2px;
}

/* dashes are deliberate — never a fabricated figure */
.hero-stat-value.is-pending {
  color: var(--text-3);
}

.hero-stat-label {
  margin-top: 7px;
  font-size: 13px;
  color: var(--text-3);
}

/* ── scenarios ────────────────────────────────────────── */
.hero-scenarios {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
  margin-top: 22px;
}

.hero-scenarios-label {
  font-size: 13px;
  color: var(--text-3);
  white-space: nowrap;
}

.hero-scenario-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  list-style: none;
  margin: 0;
  padding: 0;
}

.hero-scenario {
  padding: 6px 13px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--surface);
  font-size: 13px;
  color: var(--text-2);
  transition:
    border-color 0.2s,
    color 0.2s;
}

.hero-scenario:hover {
  border-color: var(--accent-border);
  color: var(--accent-3);
}

/* ── visual ───────────────────────────────────────────── */
.hero-visual {
  opacity: 0;
  animation: rise 0.9s cubic-bezier(0.2, 0.8, 0.2, 1) 0.35s forwards;
}

@media (max-width: 1024px) {
  .hero-inner {
    grid-template-columns: 1fr;
    gap: 48px;
  }

  .hero-lede {
    max-width: none;
  }

  .hero-visual {
    width: 100%;
    max-width: 620px;
    margin-inline: auto;
  }
}

@media (max-width: 768px) {
  .hero-section {
    padding: calc(var(--header-h) + 44px) 0 64px;
  }

  .hero-stat {
    padding-right: 22px;
    margin-right: 22px;
  }

  .hero-stat-value {
    font-size: 24px;
  }

  :deep(.hero-btn.arco-btn) {
    flex: 1 1 100%;
  }
}

@media (prefers-reduced-motion: reduce) {
  .hero-copy > *,
  .hero-visual {
    animation: none;
    opacity: 1;
    transform: none;
  }

  .hero-badge-dot {
    animation: none;
  }
}
</style>
