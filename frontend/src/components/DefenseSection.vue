<template>
  <section data-public-style id="defense" ref="sectionRef" class="public-defense-section def-section" :class="{ 'is-visible': isVisible }">
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
              <stop offset="0%" stop-color="var(--danger)" stop-opacity="0.55" />
              <stop offset="100%" stop-color="var(--accent)" stop-opacity="0.1" />
            </linearGradient>
            <linearGradient id="defClean" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="var(--ok)" stop-opacity="0.35" />
              <stop offset="100%" stop-color="var(--ok)" stop-opacity="0.05" />
            </linearGradient>
          </defs>

          <!-- incoming traffic, wide and hostile -->
          <path d="M20 26 L300 26 L232 120 L88 120 Z" fill="url(#defBeam)" />
          <text x="160" y="16" class="svg-cap" text-anchor="middle">混合流量</text>

          <!-- three filter bands -->
          <g class="def-band" style="--d: 0s">
            <rect x="78" y="120" width="164" height="52" rx="10" />
            <text x="160" y="144" class="svg-l" text-anchor="middle">L3 / L4</text>
            <text x="160" y="161" class="svg-s" text-anchor="middle">流量型攻击丢弃</text>
          </g>
          <g class="def-band" style="--d: 0.12s">
            <rect x="94" y="186" width="132" height="52" rx="10" />
            <text x="160" y="210" class="svg-l" text-anchor="middle">L7 WAF</text>
            <text x="160" y="227" class="svg-s" text-anchor="middle">漏洞特征匹配</text>
          </g>
          <g class="def-band" style="--d: 0.24s">
            <rect x="110" y="252" width="100" height="52" rx="10" />
            <text x="160" y="276" class="svg-l" text-anchor="middle">CC / Bot</text>
            <text x="160" y="293" class="svg-s" text-anchor="middle">刷量识别</text>
          </g>

          <!-- what survives -->
          <path d="M126 304 L194 304 L178 352 L142 352 Z" fill="url(#defClean)" />

          <rect x="104" y="352" width="112" height="36" rx="10" class="def-origin" />
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
