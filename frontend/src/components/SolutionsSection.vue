<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const features = [
  {
    icon: 'CN2',
    title: '中国大陆优化',
    desc: '通过我们的亚太区域（非大陆地区） CDN 节点专门优化中国大陆访问路由，为您的中国用户提供快速内容分发和稳定连接保障。'
  },
  {
    icon: 'CDN',
    title: '极致性能 CDN',
    desc: '全球边缘节点、智能路由、HTTP/2、Brotli 压缩和高级缓存，最大化网站性能表现。'
  },
  {
    icon: 'SLA',
    title: '99.9% 可用性保障',
    desc: '您的访客及潜在客户将享受流畅的用户体验，无需担心意外宕机或服务中断。'
  },
  {
    icon: 'SEC',
    title: '高级 DDoS 防护',
    desc: '通过企业级安全防护系统抵御网络攻击和恶意威胁，保障业务稳定运行。'
  }
]

const sectionRef = ref(null)
const isVisible = ref(false)
let observer = null

onMounted(() => {
  observer = new IntersectionObserver(
    ([entry]) => {
      if (entry.isIntersecting) {
        isVisible.value = true
        if (sectionRef.value) observer.unobserve(sectionRef.value)
      }
    },
    { threshold: 0.15 }
  )

  if (sectionRef.value) {
    observer.observe(sectionRef.value)
  }
})

onUnmounted(() => {
  if (observer) observer.disconnect()
})
</script>

<template>
  <section data-public-style id="about" class="public-solutions-section why-cdn-section" ref="sectionRef" :class="{ 'is-visible': isVisible }">
    <div class="why-cdn-container">
      <div class="why-cdn-heading">
        <p class="why-cdn-kicker">WHY TY CDN</p>
        <h2>满足您所有业务需求的快速、可靠、安全 CDN 服务</h2>
        <p class="why-cdn-subtitle">
          基于高质量亚洲节点、智能调度与安全防护能力，为跨境访问、静态资源分发与高并发业务场景提供稳定支撑。
        </p>
      </div>

      <div class="why-cdn-grid">
        <article v-for="item in features" :key="item.title" class="why-cdn-card">
          <div class="why-cdn-card__icon">
            <span>{{ item.icon }}</span>
          </div>
          <h3>{{ item.title }}</h3>
          <p>{{ item.desc }}</p>
        </article>
      </div>
    </div>
  </section>
</template>
