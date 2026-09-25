<template>
  <section data-public-style id="faq" ref="sectionRef" class="public-faq-section faq-section">
    <div class="container-page faq-inner">
      <div class="faq-lede">
        <span class="eyebrow">常见问题</span>
        <h2 class="faq-title">接入之前，<br />你大概会问这些</h2>
        <p class="faq-sub">
          没找到答案？直接在 Telegram 上问，通常几分钟内回复。
        </p>
        <a class="faq-contact" :href="contact.telegramUrl" target="_blank" rel="noopener">
          {{ contact.telegram }}
          <span aria-hidden="true">↗</span>
        </a>
      </div>

      <!--
        <details> rather than a hand-rolled accordion: keyboard support, screen
        reader semantics and find-in-page all work without JavaScript, and the
        first one can be open on load without a mounted hook.
      -->
      <div class="faq-list">
        <details
          v-for="(item, i) in faqs"
          :key="item.q"
          class="faq-item"
          :open="i === 0"
        >
          <summary class="faq-q">
            <span>{{ item.q }}</span>
            <span class="faq-icon" aria-hidden="true"></span>
          </summary>
          <p class="faq-a">{{ item.a }}</p>
        </details>
      </div>
    </div>
  </section>
</template>

<script setup>
import { contact, faqs } from '../data/landing'
import { useReveal } from '../composables/useReveal'

const { sectionRef } = useReveal()
</script>
