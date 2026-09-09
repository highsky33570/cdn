<template>
  <section id="faq" ref="sectionRef" class="faq-section">
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

<style scoped>
.faq-section {
  padding: 120px 0;
}

.faq-inner {
  display: grid;
  grid-template-columns: minmax(0, 0.8fr) minmax(0, 1.2fr);
  gap: 72px;
  align-items: start;
}

.faq-lede {
  position: sticky;
  top: calc(var(--header-h) + 48px);
}

.eyebrow {
  display: inline-block;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.18em;
  color: #4d8cff;
  margin-bottom: 18px;
}

.faq-title {
  font-size: clamp(28px, 3.2vw, 42px);
  line-height: 1.26;
  font-weight: 800;
  margin: 0;
  color: var(--text);
  letter-spacing: -0.02em;
}

.faq-sub {
  margin: 18px 0 0;
  font-size: 15px;
  line-height: 1.8;
  color: var(--text-2);
  max-width: 30ch;
}

.faq-contact {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-top: 20px;
  font-size: 15px;
  font-weight: 700;
  color: #7fb0ff;
  transition: color 0.2s;
}

.faq-contact:hover {
  color: #a9caff;
}

.faq-list {
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.faq-item {
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.faq-q {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 22px 0;
  font-size: 16px;
  font-weight: 700;
  color: var(--text);
  cursor: pointer;
  list-style: none;
  transition: color 0.2s;
}

.faq-q::-webkit-details-marker {
  display: none;
}

.faq-q:hover {
  color: #a9caff;
}

.faq-q:focus-visible {
  outline: 2px solid #4d8cff;
  outline-offset: 4px;
  border-radius: 4px;
}

/* a plus that becomes a minus — cheaper and calmer than a rotating chevron */
.faq-icon {
  position: relative;
  flex: none;
  width: 14px;
  height: 14px;
}

.faq-icon::before,
.faq-icon::after {
  content: '';
  position: absolute;
  background: #7fb0ff;
  border-radius: 1px;
  transition: transform 0.25s cubic-bezier(0.2, 0.8, 0.2, 1);
}

.faq-icon::before {
  inset: 6px 0 6px 0;
  height: 2px;
}

.faq-icon::after {
  inset: 0 6px 0 6px;
  width: 2px;
}

.faq-item[open] .faq-icon::after {
  transform: scaleY(0);
}

.faq-a {
  margin: 0;
  padding: 0 40px 24px 0;
  font-size: 15px;
  line-height: 1.85;
  color: var(--text-2);
}

@media (max-width: 1024px) {
  .faq-inner {
    grid-template-columns: 1fr;
    gap: 40px;
  }

  .faq-lede {
    position: static;
  }

  .faq-sub {
    max-width: none;
  }
}

@media (max-width: 768px) {
  .faq-section {
    padding: 72px 0;
  }

  .faq-a {
    padding-right: 0;
  }
}
</style>
