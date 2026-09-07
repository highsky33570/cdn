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
  observer = new IntersectionObserver(([entry]) => {
    if (entry.isIntersecting) {
      isVisible.value = true
      if (sectionRef.value) observer.unobserve(sectionRef.value)
    }
  }, { threshold: 0.15 })

  if (sectionRef.value) {
    observer.observe(sectionRef.value)
  }
})

onUnmounted(() => {
  if (observer) observer.disconnect()
})
</script>

<template>
  <section 
    id="about" 
    class="why-cdn-section" 
    ref="sectionRef" 
    :class="{ 'is-visible': isVisible }"
  >
    <div class="why-cdn-container">
      <div class="why-cdn-heading">
        <p class="why-cdn-kicker">WHY TY CDN</p>
        <h2>满足您所有业务需求的快速、可靠、安全 CDN 服务</h2>
        <p class="why-cdn-subtitle">
          基于高质量亚洲节点、智能调度与安全防护能力，为跨境访问、静态资源分发与高并发业务场景提供稳定支撑。
        </p>
      </div>

      <div class="why-cdn-grid">
        <article
          v-for="item in features"
          :key="item.title"
          class="why-cdn-card"
        >
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

<style scoped>
.why-cdn-section {
  position: relative;
  overflow: hidden;
  padding: 110px 0 120px;
  background: none;
  opacity: 0;
}

.why-cdn-section.is-visible {
  animation: fadeInUp 0.8s ease-out forwards;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.why-cdn-container {
  position: relative;
  z-index: 1;
  width: min(1280px, calc(100% - 48px));
  margin: 0 auto;
}

.why-cdn-heading {
  max-width: 920px;
  margin: 0 auto 52px;
  text-align: center;
}

.why-cdn-kicker {
  margin: 0 0 14px;
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.24em;
  color: #6ea8ff;
}

.why-cdn-heading h2 {
  margin: 0;
  font-size: clamp(34px, 4.2vw, 58px);
  line-height: 1.18;
  font-weight: 800;
  color: #f4f7ff;
}

.why-cdn-subtitle {
  margin: 18px auto 0;
  max-width: 860px;
  font-size: 18px;
  line-height: 1.9;
  color: rgba(220, 230, 255, 0.72);
}

.why-cdn-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 48px;
}

.why-cdn-card {
  position: relative;
  min-height: 240px;
  padding: 26px 28px 24px;
  border: 1px solid rgba(120, 150, 220, 0.08);
  border-radius: 8px;
  background: linear-gradient(
    180deg,
    rgba(24, 34, 60, 0.68) 0%,
    rgba(16, 24, 42, 0.74) 100%
  );
  box-shadow: none;
  overflow: hidden;
}

.why-cdn-card::after {
  content: '';
  position: absolute;
  inset: 0;
  background:
    linear-gradient(90deg, rgba(92, 132, 255, 0.14) 0%, transparent 34%, transparent 66%, rgba(92, 132, 255, 0.08) 100%);
  pointer-events: none;
}

.why-cdn-card__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 64px;
  height: 64px;
  margin-bottom: 24px;
  border-radius: 50%;
  border: 1px solid rgba(138, 168, 255, 0.12);
  background: rgba(78, 103, 165, 0.18);
  box-shadow: none;
}

.why-cdn-card__icon span {
  font-size: 14px;
  font-weight: 700;
  color: rgba(235, 240, 250, 0.88);
}

.why-cdn-card h3 {
  margin: 0 0 18px;
  font-size: 26px;
  line-height: 1.3;
  font-weight: 800;
  color: rgba(240, 244, 252, 0.9);
}

.why-cdn-card p {
  margin: 0;
  font-size: 17px;
  line-height: 1.9;
  color: rgba(205, 215, 232, 0.62);
}

@media (max-width: 991px) {
  .why-cdn-grid {
    grid-template-columns: 1fr;
  }

  .why-cdn-card {
    min-height: auto;
  }
}

@media (max-width: 767px) {
  .why-cdn-section {
    padding: 84px 0 92px;
  }

  .why-cdn-container {
    width: min(100% - 28px, 1280px);
  }

  .why-cdn-heading {
    margin-bottom: 34px;
  }

  .why-cdn-subtitle {
    font-size: 15px;
    line-height: 1.8;
  }

  .why-cdn-card {
    padding: 26px 22px 24px;
    border-radius: 22px;
  }

  .why-cdn-card h3 {
    font-size: 22px;
  }

  .why-cdn-card p {
    font-size: 15px;
    line-height: 1.85;
  }
}
</style>