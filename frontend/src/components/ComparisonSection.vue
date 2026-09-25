<template>
  <section data-public-style id="compare" ref="sectionRef" class="public-comparison-section cmp-section" :class="{ 'is-visible': isVisible }">
    <div class="container-page">
      <div class="cmp-head">
        <span class="eyebrow">对比</span>
        <h2 class="cmp-title">你现在用的方案，和我们差在哪</h2>
        <p class="cmp-sub">不是我们说好，把关键指标放在一起看就很清楚。</p>
      </div>

      <!--
        Coloured pills per cell, not plain text in a highlighted column.
        This is the one idea worth taking from the reference site: the reader
        decides who wins from colour alone, before reading a single word, and
        only then reads the cells that matter to them.

        The pills are never colour-only, though — our column also carries a tick
        and a heavier weight, so the table still resolves for anyone who cannot
        separate the red from the green.
      -->
      <div class="cmp-table-wrap desktop-only">
        <table class="cmp-table">
          <caption class="visually-hidden">
            TyCDN 与其他 CDN 方案的关键指标对比
          </caption>
          <thead>
            <tr>
              <th scope="col" class="cmp-corner">对比维度</th>
              <th
                v-for="(col, ci) in comparison.columns"
                :key="col"
                scope="col"
                :class="['cmp-col-head', { 'is-us': ci === 0 }]"
              >
                <span v-if="ci === 0" class="cmp-us-mark">推荐</span>
                {{ col }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, ri) in comparison.rows" :key="row.label" :style="{ '--i': ri }">
              <th scope="row" class="cmp-row-head">{{ row.label }}</th>
              <td
                v-for="(cell, ci) in row.cells"
                :key="ci"
                :class="['cmp-cell', { 'is-us': ci === 0 }]"
              >
                <span :class="['pill', `pill-${cell.tone}`]">
                  <svg v-if="ci === 0" class="pill-check" viewBox="0 0 16 16" aria-hidden="true">
                    <path
                      d="M3.5 8.5l3 3 6-7"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2.2"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    />
                  </svg>
                  {{ cell.text }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- mobile: transposed, one capability per card -->
      <div class="cmp-cards mobile-only">
        <article v-for="row in comparison.rows" :key="`m-${row.label}`" class="cmp-card">
          <h3 class="cmp-card-title">{{ row.label }}</h3>
          <ul class="cmp-card-list">
            <li v-for="(cell, ci) in row.cells" :key="ci" class="cmp-card-row">
              <span class="cmp-card-col">{{ comparison.columns[ci] }}</span>
              <span :class="['pill', `pill-${cell.tone}`]">{{ cell.text }}</span>
            </li>
          </ul>
        </article>
      </div>

      <p class="cmp-foot">以上为结构性差异（计费方式、控制权、接入路径），不随机房与时段变化。</p>
    </div>
  </section>
</template>

<script setup>
import { comparison } from '../data/landing'
import { useReveal } from '../composables/useReveal'

const { sectionRef, isVisible } = useReveal(0.08)
</script>
