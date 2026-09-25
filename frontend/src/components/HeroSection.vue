<template>
  <section data-public-style id="hero" class="public-hero-section hero-section">
    <div class="hero-glow" aria-hidden="true"></div>

    <div class="container-hero hero-inner">
      <div class="hero-copy">
        <button class="hero-badge" type="button" @click="goPlans">
          <span class="hero-badge-dot"></span>
          <span>TyCDN · 香港边缘节点 · 面向中国优化</span>
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

        <dl class="hero-stats">
          <div v-for="stat in stats" :key="stat.label" class="hero-stat">
            <dd class="hero-stat-value" :class="{ 'is-pending': stat.pending }">
              {{ stat.value }}<small v-if="stat.unit">{{ stat.unit }}</small>
            </dd>
            <dt class="hero-stat-label">{{ stat.label }}</dt>
            <dd class="hero-stat-note">{{ stat.note }}</dd>
          </div>
        </dl>
        <p class="hero-monitor-note" :title="measurementExplanation">
          <span>{{ lastChecked ? `最近探测 ${lastChecked}` : '首轮探测完成后自动更新' }}</span>
          <span>大陆探针抽样 · 每 15 分钟更新</span>
        </p>

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
import { contact, scenarios } from '../data/landing'
import { useNetworkStats } from '../composables/useNetworkStats'

const {
  onlineNodes,
  registeredNodes,
  latency,
  availability,
  historyDays,
  windowComplete,
  status,
  lastChecked
} = useNetworkStats()

const measurementExplanation =
  '延迟为中国大陆探针至边缘节点的平均 ICMP 往返时间。可用性为有结果的探测中节点可达的比例，不等同于网站可用性或 SLA；探针服务异常不计为节点故障。'
const pendingText = computed(() =>
  ['stale', 'unavailable'].includes(status.value) ? '待更新' : '采集中'
)
const stats = computed(() => [
  {
    value:
      latency.value === null
        ? onlineNodes.value === 0
          ? '未响应'
          : pendingText.value
        : latency.value.toFixed(1),
    unit: latency.value === null ? '' : 'ms',
    label: '中国大陆平均延迟',
    note: '大陆探针 → 边缘节点',
    pending: latency.value === null
  },
  {
    value: String(onlineNodes.value ?? registeredNodes.value),
    label: onlineNodes.value === null ? '已接入边缘节点' : '在线边缘节点',
    note: onlineNodes.value === null ? '在线状态探测中' : `共 ${registeredNodes.value} 个接入节点`,
    pending: false
  },
  {
    value: availability.value === null ? pendingText.value : availability.value.toFixed(2),
    unit: availability.value === null ? '' : '%',
    label: windowComplete.value
      ? '近 30 天可用性'
      : historyDays.value >= 30
        ? '近 30 天抽样可用性'
        : '监测以来可用性',
    note:
      status.value === 'stale'
        ? '监测待更新 · 历史可达率'
        : windowComplete.value
          ? '探测可达率 · 滚动 30 天'
          : historyDays.value
            ? `已累计 ${historyDays.value} 天 · 探测可达率`
            : '监测不足 1 天 · 探测可达率',
    pending: availability.value === null
  }
])

const scrollTo = (id) => {
  document.querySelector(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const goPlans = () => scrollTo('#plans')
const goCompare = () => scrollTo('#compare')
</script>
