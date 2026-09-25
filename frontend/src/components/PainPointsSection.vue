<template>
  <section data-public-style id="pain" ref="sectionRef" class="public-pain-points-section pain-section" :class="{ 'is-visible': isVisible }">
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
        <p class="pain-sub">这六件事，几乎是每个从别家迁过来的客户开口就提的。</p>
      </div>

      <ol class="pain-list">
        <li v-for="(item, i) in painPoints" :key="item.n" class="pain-item" :style="{ '--i': i }">
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
