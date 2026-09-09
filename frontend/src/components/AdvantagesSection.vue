<template>
  <section
    id="why"
    ref="sectionRef"
    class="adv-section"
    :class="{ 'is-visible': isVisible }"
  >
    <div class="container-page">
      <div class="adv-head">
        <span class="eyebrow">解法</span>
        <h2 class="section-title adv-title">同样是 CDN，为什么选 TyCDN</h2>
        <p class="section-desc">
          上面每一条，下面都有对应的答案。
        </p>
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

<style scoped>
.adv-section {
  padding: 120px 0;
}

.adv-head {
  text-align: center;
  margin-bottom: 56px;
}

.eyebrow {
  display: inline-block;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.18em;
  color: #4d8cff;
  margin-bottom: 16px;
}

.adv-title {
  letter-spacing: -0.02em;
}

.adv-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1px;
  background: rgba(255, 255, 255, 0.07);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 18px;
  overflow: hidden;
}

/*
  A hairline grid rather than four floating cards with drop shadows. The
  reference site's soft-shadowed card grid is exactly the look the client called
  dated; a shared 1px lattice reads as one object instead of four stickers.
*/
.adv-card {
  position: relative;
  background: #0c1426;
  padding: 40px 36px 44px;
  opacity: 0;
  transform: translateY(16px);
  transition: background 0.3s;
}

.is-visible .adv-card {
  animation: adv-in 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
  animation-delay: calc(var(--i) * 90ms);
}

@keyframes adv-in {
  to {
    opacity: 1;
    transform: none;
  }
}

.adv-card:hover {
  background: #101a30;
}

.adv-tag {
  display: inline-block;
  font-size: 12px;
  font-weight: 700;
  color: #7fb0ff;
  background: rgba(77, 140, 255, 0.12);
  border: 1px solid rgba(77, 140, 255, 0.28);
  border-radius: 999px;
  padding: 4px 12px;
  margin-bottom: 22px;
}

.adv-card-title {
  margin: 0;
  font-size: 21px;
  font-weight: 800;
  line-height: 1.4;
  color: var(--text);
  letter-spacing: -0.01em;
}

.adv-card-desc {
  margin: 14px 0 0;
  font-size: 15px;
  line-height: 1.8;
  color: var(--text-2);
}

/* a short accent rule that grows on hover — a small signal of liveness */
.adv-rule {
  position: absolute;
  left: 36px;
  bottom: 0;
  height: 2px;
  width: 28px;
  background: #4d8cff;
  border-radius: 2px 2px 0 0;
  transition: width 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
}

.adv-card:hover .adv-rule {
  width: 72px;
}

@media (max-width: 860px) {
  .adv-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .adv-section {
    padding: 72px 0;
  }

  .adv-card {
    padding: 32px 24px 36px;
  }

  .adv-rule {
    left: 24px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .adv-card,
  .is-visible .adv-card {
    animation: none;
    opacity: 1;
    transform: none;
  }
}
</style>
