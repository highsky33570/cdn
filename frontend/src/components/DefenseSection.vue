<template>
  <section
    id="defense"
    ref="sectionRef"
    class="def-section"
    :class="{ 'is-visible': isVisible }"
  >
    <div class="container-page def-inner">
      <div class="def-copy">
        <span class="eyebrow">防护</span>
        <h2 class="def-title">攻击在边缘停下，<br />不在你的源站停下</h2>
        <p class="def-sub">
          三层过滤依次收窄流量：网络层丢弃、应用层匹配规则、行为层识别刷量。
          到达源站的只剩正常请求。
        </p>

        <ul class="def-list">
          <li v-for="item in defenseLayers" :key="item.layer" class="def-item">
            <span class="def-layer">{{ item.layer }}</span>
            <div>
              <h3 class="def-item-title">{{ item.title }}</h3>
              <p class="def-item-desc">{{ item.desc }}</p>
            </div>
          </li>
        </ul>
      </div>

      <!--
        An inline SVG rather than an image: it has to stay legible on a dark
        background at any width, and the funnel is the argument — three filters
        narrowing, source stays clean. Decorative only, so aria-hidden; the list
        on the left already states the same thing in text.
      -->
      <figure class="def-figure" aria-hidden="true">
        <svg viewBox="0 0 320 400" class="def-svg" role="presentation">
          <defs>
            <linearGradient id="defBeam" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#ff5f6d" stop-opacity="0.55" />
              <stop offset="100%" stop-color="#4d8cff" stop-opacity="0.1" />
            </linearGradient>
            <linearGradient id="defClean" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#4ade80" stop-opacity="0.35" />
              <stop offset="100%" stop-color="#4ade80" stop-opacity="0.05" />
            </linearGradient>
          </defs>

          <!-- incoming traffic, wide and hostile -->
          <path d="M20 26 L300 26 L232 120 L88 120 Z" fill="url(#defBeam)" />
          <text x="160" y="16" class="svg-cap" text-anchor="middle">
            混合流量
          </text>

          <!-- three filter bands -->
          <g class="def-band" style="--d: 0s">
            <rect x="78" y="120" width="164" height="52" rx="10" />
            <text x="160" y="144" class="svg-l" text-anchor="middle">L3 / L4</text>
            <text x="160" y="161" class="svg-s" text-anchor="middle">
              流量型攻击丢弃
            </text>
          </g>
          <g class="def-band" style="--d: 0.12s">
            <rect x="94" y="186" width="132" height="52" rx="10" />
            <text x="160" y="210" class="svg-l" text-anchor="middle">L7 WAF</text>
            <text x="160" y="227" class="svg-s" text-anchor="middle">
              漏洞特征匹配
            </text>
          </g>
          <g class="def-band" style="--d: 0.24s">
            <rect x="110" y="252" width="100" height="52" rx="10" />
            <text x="160" y="276" class="svg-l" text-anchor="middle">CC / Bot</text>
            <text x="160" y="293" class="svg-s" text-anchor="middle">刷量识别</text>
          </g>

          <!-- what survives -->
          <path d="M126 304 L194 304 L178 352 L142 352 Z" fill="url(#defClean)" />

          <rect
            x="104"
            y="352"
            width="112"
            height="36"
            rx="10"
            class="def-origin"
          />
          <text x="160" y="375" class="svg-l" text-anchor="middle">你的源站</text>
        </svg>
        <figcaption class="def-cap">正常请求</figcaption>
      </figure>
    </div>
  </section>
</template>

<script setup>
import { defenseLayers } from '../data/landing'
import { useReveal } from '../composables/useReveal'

const { sectionRef, isVisible } = useReveal()
</script>

<style scoped>
.def-section {
  padding: 120px 0;
  background:
    radial-gradient(
      ellipse at 78% 40%,
      rgba(77, 140, 255, 0.1),
      transparent 62%
    );
}

.def-inner {
  display: grid;
  grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
  gap: 72px;
  align-items: center;
}

.eyebrow {
  display: inline-block;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.18em;
  color: #4d8cff;
  margin-bottom: 18px;
}

.def-title {
  font-size: clamp(28px, 3.2vw, 42px);
  line-height: 1.26;
  font-weight: 800;
  margin: 0;
  color: var(--text);
  letter-spacing: -0.02em;
}

.def-sub {
  margin: 20px 0 0;
  font-size: 16px;
  line-height: 1.85;
  color: var(--text-2);
  max-width: 46ch;
}

.def-list {
  list-style: none;
  margin: 40px 0 0;
  padding: 0;
  display: grid;
  gap: 4px;
}

.def-item {
  display: grid;
  grid-template-columns: 78px minmax(0, 1fr);
  gap: 18px;
  padding: 18px 0;
  border-top: 1px solid rgba(255, 255, 255, 0.07);
}

.def-layer {
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.08em;
  color: #7fb0ff;
  padding-top: 3px;
}

.def-item-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: var(--text);
}

.def-item-desc {
  margin: 6px 0 0;
  font-size: 14px;
  line-height: 1.75;
  color: var(--text-2);
}

/* ── figure ───────────────────────────────────────────── */
.def-figure {
  margin: 0;
  text-align: center;
}

.def-svg {
  width: 100%;
  max-width: 360px;
  height: auto;
  display: block;
  margin: 0 auto;
}

.def-band rect {
  fill: #0e1830;
  stroke: rgba(127, 176, 255, 0.35);
  stroke-width: 1;
  opacity: 0;
  transform: translateY(10px);
}

.is-visible .def-band rect {
  animation: band-in 0.55s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
  animation-delay: var(--d);
}

@keyframes band-in {
  to {
    opacity: 1;
    transform: none;
  }
}

.def-origin {
  fill: #0e1830;
  stroke: rgba(74, 222, 128, 0.5);
  stroke-width: 1;
}

.svg-l {
  fill: #eaf1ff;
  font-size: 13px;
  font-weight: 700;
}

.svg-s {
  fill: rgba(245, 247, 255, 0.55);
  font-size: 11px;
}

.svg-cap {
  fill: rgba(245, 247, 255, 0.5);
  font-size: 11px;
  letter-spacing: 0.1em;
}

.def-cap {
  margin-top: 14px;
  font-size: 13px;
  color: rgba(74, 222, 128, 0.85);
  letter-spacing: 0.08em;
}

@media (max-width: 1024px) {
  .def-inner {
    grid-template-columns: 1fr;
    gap: 48px;
  }

  .def-sub {
    max-width: none;
  }
}

@media (max-width: 768px) {
  .def-section {
    padding: 72px 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .def-band rect,
  .is-visible .def-band rect {
    animation: none;
    opacity: 1;
    transform: none;
  }
}
</style>
