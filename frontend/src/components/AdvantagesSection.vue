<template>
  <section data-public-style id="why" ref="sectionRef" class="public-advantages-section adv-section" :class="{ 'is-visible': isVisible }">
    <div class="container-page">
      <div class="adv-head">
        <span class="eyebrow">解法</span>
        <h2 class="section-title adv-title">同样是 CDN，为什么选 TyCDN</h2>
        <p class="section-desc">上面每一条，下面都有对应的答案。</p>
      </div>

      <div class="adv-grid">
        <article
          v-for="(item, i) in advantages"
          :key="item.key"
          class="adv-card"
          :style="{ '--i': i }"
        >
          <span class="adv-tag">{{ item.tag }}</span>
          <h3 class="adv-card-title">{{ item.title }}</h3>
          <p class="adv-card-desc">{{ item.desc }}</p>
          <span class="adv-rule" aria-hidden="true"></span>
        </article>
      </div>
    </div>
  </section>
</template>

<script setup>
import { advantages } from '../data/landing'
import { useReveal } from '../composables/useReveal'

const { sectionRef, isVisible } = useReveal()
</script>
