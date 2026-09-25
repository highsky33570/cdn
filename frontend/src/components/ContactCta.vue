<template>
  <section data-public-style id="contact" ref="sectionRef" class="public-contact-cta cta-section">
    <div class="container-page">
      <div class="cta-panel" :class="{ 'is-visible': isVisible }">
        <div class="cta-copy">
          <h2 class="cta-title">先用一个非核心域名试试</h2>
          <p class="cta-sub">接入只需要改一条 CNAME。线路和回源确认没问题，再迁主站也不迟。</p>
        </div>

        <div class="cta-actions">
          <a-button type="primary" size="large" class="cta-btn" @click="goRegister">
            注册并选购
          </a-button>
          <a class="cta-tg" :href="contact.telegramUrl" target="_blank" rel="noopener">
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
