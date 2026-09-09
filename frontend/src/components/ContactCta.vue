<template>
  <section id="contact" ref="sectionRef" class="cta-section">
    <div class="container-page">
      <div class="cta-panel" :class="{ 'is-visible': isVisible }">
        <div class="cta-copy">
          <h2 class="cta-title">先用一个非核心域名试试</h2>
          <p class="cta-sub">
            接入只需要改一条 CNAME。线路和回源确认没问题，再迁主站也不迟。
          </p>
        </div>

        <div class="cta-actions">
          <a-button type="primary" size="large" class="cta-btn" @click="goRegister">
            注册并选购
          </a-button>
          <a
            class="cta-tg"
            :href="contact.telegramUrl"
            target="_blank"
            rel="noopener"
          >
            Telegram 咨询
            <span aria-hidden="true">↗</span>
          </a>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { contact } from '../data/landing'
import { useReveal } from '../composables/useReveal'

const { sectionRef, isVisible } = useReveal(0.25)

const goRegister = () => {
  window.location.href = '/register'
}
</script>

<style scoped>
.cta-section {
  padding: 40px 0 120px;
}

.cta-panel {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 40px;
  flex-wrap: wrap;
  padding: 52px 56px;
  border-radius: 22px;
  border: 1px solid rgba(77, 140, 255, 0.28);
  background:
    radial-gradient(
      ellipse at 12% 0%,
      rgba(77, 140, 255, 0.22),
      transparent 60%
    ),
    #0b1224;
  opacity: 0;
  transform: translateY(16px);
  transition:
    opacity 0.6s cubic-bezier(0.2, 0.8, 0.2, 1),
    transform 0.6s cubic-bezier(0.2, 0.8, 0.2, 1);
}

.cta-panel.is-visible {
  opacity: 1;
  transform: none;
}

.cta-title {
  margin: 0;
  font-size: clamp(24px, 2.6vw, 34px);
  font-weight: 800;
  color: var(--text);
  letter-spacing: -0.02em;
}

.cta-sub {
  margin: 12px 0 0;
  font-size: 16px;
  line-height: 1.8;
  color: var(--text-2);
  max-width: 46ch;
}

.cta-actions {
  display: flex;
  align-items: center;
  gap: 22px;
  flex-wrap: wrap;
}

:deep(.cta-btn.arco-btn) {
  height: 52px;
  min-width: 168px;
  border-radius: 12px;
  font-size: 16px;
  font-weight: 700;
}

.cta-tg {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 15px;
  font-weight: 700;
  color: #7fb0ff;
  transition: color 0.2s;
}

.cta-tg:hover {
  color: #a9caff;
}

@media (max-width: 768px) {
  .cta-section {
    padding: 24px 0 72px;
  }

  .cta-panel {
    padding: 34px 24px;
  }

  .cta-actions {
    width: 100%;
  }

  :deep(.cta-btn.arco-btn) {
    flex: 1;
  }
}

@media (prefers-reduced-motion: reduce) {
  .cta-panel {
    opacity: 1;
    transform: none;
    transition: none;
  }
}
</style>
