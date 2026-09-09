<template>
  <section
    id="pain"
    ref="sectionRef"
    class="pain-section"
    :class="{ 'is-visible': isVisible }"
  >
    <div class="container-page pain-inner">
      <!--
        Deliberately not the reference site's six identical icon cards. A uniform
        grid gives every complaint the same weight and reads as filler; an
        editorial split puts the argument on the left and lets the list carry
        density on the right.
      -->
      <div class="pain-lede">
        <span class="eyebrow">现状</span>
        <h2 class="pain-title">你现在用的 CDN，<br />是不是这样？</h2>
        <p class="pain-sub">
          这六件事，几乎是每个从别家迁过来的客户开口就提的。
        </p>
      </div>

      <ol class="pain-list">
        <li
          v-for="(item, i) in painPoints"
          :key="item.n"
          class="pain-item"
          :style="{ '--i': i }"
        >
          <span class="pain-n">{{ item.n }}</span>
          <div class="pain-body">
            <h3 class="pain-item-title">{{ item.title }}</h3>
            <p class="pain-item-desc">{{ item.desc }}</p>
          </div>
        </li>
      </ol>
    </div>
  </section>
</template>

<script setup>
import { painPoints } from '../data/landing'
import { useReveal } from '../composables/useReveal'

const { sectionRef, isVisible } = useReveal()
</script>

<style scoped>
.pain-section {
  padding: 120px 0;
  position: relative;
}

.pain-section::before {
  /* a single soft light source, rather than a card-shaped glow behind each item */
  content: '';
  position: absolute;
  inset: 0 auto auto 0;
  width: 46%;
  height: 60%;
  background: radial-gradient(
    ellipse at top left,
    rgba(77, 140, 255, 0.09),
    transparent 70%
  );
  pointer-events: none;
}

.pain-inner {
  display: grid;
  grid-template-columns: minmax(0, 0.85fr) minmax(0, 1.15fr);
  gap: 72px;
  align-items: start;
  position: relative;
}

.pain-lede {
  position: sticky;
  top: calc(var(--header-h) + 48px);
}

.eyebrow {
  display: inline-block;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.18em;
  color: #4d8cff;
  margin-bottom: 20px;
}

.pain-title {
  font-size: clamp(30px, 3.4vw, 46px);
  line-height: 1.24;
  font-weight: 800;
  margin: 0;
  color: var(--text);
  letter-spacing: -0.02em;
}

.pain-sub {
  margin: 20px 0 0;
  font-size: 16px;
  line-height: 1.8;
  color: var(--text-2);
  max-width: 34ch;
}

.pain-list {
  list-style: none;
  margin: 0;
  padding: 0;
  border-top: 1px solid rgba(255, 255, 255, 0.07);
}

.pain-item {
  display: grid;
  grid-template-columns: 56px minmax(0, 1fr);
  gap: 20px;
  padding: 26px 0;
  border-bottom: 1px solid rgba(255, 255, 255, 0.07);
  opacity: 0;
  transform: translateY(14px);
}

.is-visible .pain-item {
  animation: pain-in 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
  animation-delay: calc(var(--i) * 70ms);
}

@keyframes pain-in {
  to {
    opacity: 1;
    transform: none;
  }
}

.pain-n {
  font-size: 13px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  color: rgba(255, 255, 255, 0.3);
  padding-top: 4px;
  letter-spacing: 0.06em;
}

.pain-item-title {
  margin: 0;
  font-size: 17px;
  font-weight: 700;
  line-height: 1.5;
  color: var(--text);
}

.pain-item-desc {
  margin: 8px 0 0;
  font-size: 15px;
  line-height: 1.75;
  color: var(--text-2);
}

/* the hairline brightens on hover — the only motion, kept cheap */
.pain-item {
  transition: border-color 0.25s;
}

.pain-item:hover {
  border-bottom-color: rgba(77, 140, 255, 0.5);
}

@media (max-width: 1024px) {
  .pain-inner {
    grid-template-columns: 1fr;
    gap: 44px;
  }

  .pain-lede {
    position: static;
  }

  .pain-sub {
    max-width: none;
  }
}

@media (max-width: 768px) {
  .pain-section {
    padding: 72px 0;
  }

  .pain-item {
    grid-template-columns: 40px minmax(0, 1fr);
    gap: 14px;
    padding: 20px 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .pain-item,
  .is-visible .pain-item {
    animation: none;
    opacity: 1;
    transform: none;
  }
}
</style>
