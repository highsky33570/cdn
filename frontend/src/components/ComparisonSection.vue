<template>
  <section
    id="compare"
    ref="sectionRef"
    class="cmp-section"
    :class="{ 'is-visible': isVisible }"
  >
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
            <tr
              v-for="(row, ri) in comparison.rows"
              :key="row.label"
              :style="{ '--i': ri }"
            >
              <th scope="row" class="cmp-row-head">{{ row.label }}</th>
              <td
                v-for="(cell, ci) in row.cells"
                :key="ci"
                :class="['cmp-cell', { 'is-us': ci === 0 }]"
              >
                <span :class="['pill', `pill-${cell.tone}`]">
                  <svg
                    v-if="ci === 0"
                    class="pill-check"
                    viewBox="0 0 16 16"
                    aria-hidden="true"
                  >
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
        <article
          v-for="row in comparison.rows"
          :key="`m-${row.label}`"
          class="cmp-card"
        >
          <h3 class="cmp-card-title">{{ row.label }}</h3>
          <ul class="cmp-card-list">
            <li v-for="(cell, ci) in row.cells" :key="ci" class="cmp-card-row">
              <span class="cmp-card-col">{{ comparison.columns[ci] }}</span>
              <span :class="['pill', `pill-${cell.tone}`]">{{ cell.text }}</span>
            </li>
          </ul>
        </article>
      </div>

      <p class="cmp-foot">
        以上为结构性差异（计费方式、控制权、接入路径），不随机房与时段变化。
      </p>
    </div>
  </section>
</template>

<script setup>
import { comparison } from '../data/landing'
import { useReveal } from '../composables/useReveal'

const { sectionRef, isVisible } = useReveal(0.08)
</script>

<style scoped>
.cmp-section {
  padding: 120px 0;
}

.cmp-head {
  text-align: center;
  margin-bottom: 48px;
}

.eyebrow {
  display: inline-block;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.18em;
  color: #4d8cff;
  margin-bottom: 16px;
}

.cmp-title {
  font-size: clamp(28px, 3.2vw, 44px);
  font-weight: 800;
  margin: 0;
  color: var(--text);
  letter-spacing: -0.02em;
}

.cmp-sub {
  margin: 14px 0 0;
  font-size: 16px;
  color: var(--text-2);
}

.cmp-table-wrap {
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 20px;
  overflow: hidden;
  background: #0b1120;
}

.cmp-table {
  width: 100%;
  border-collapse: collapse;
}

.cmp-corner {
  width: 15%;
  padding: 20px 22px;
  text-align: left;
  font-size: 13px;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.42);
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.cmp-col-head {
  padding: 20px 18px;
  font-size: 15px;
  font-weight: 700;
  color: var(--text-2);
  text-align: left;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  vertical-align: bottom;
  width: 21.25%;
}

/* a continuous tinted spine down our column, so the eye follows one line */
.cmp-col-head.is-us {
  color: #fff;
  font-size: 17px;
  background: linear-gradient(
    180deg,
    rgba(74, 222, 128, 0.16),
    rgba(74, 222, 128, 0.04)
  );
  border-bottom-color: rgba(74, 222, 128, 0.4);
}

.cmp-us-mark {
  display: block;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.14em;
  color: #4ade80;
  margin-bottom: 7px;
}

.cmp-row-head {
  padding: 14px 22px;
  text-align: left;
  font-size: 14px;
  font-weight: 600;
  color: var(--text);
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  white-space: nowrap;
}

.cmp-cell {
  padding: 14px 18px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  vertical-align: middle;
}

.cmp-cell.is-us {
  background: rgba(74, 222, 128, 0.045);
}

.cmp-table tbody tr:last-child .cmp-row-head,
.cmp-table tbody tr:last-child .cmp-cell {
  border-bottom: none;
}

.cmp-table tbody tr:hover .cmp-cell,
.cmp-table tbody tr:hover .cmp-row-head {
  background: rgba(255, 255, 255, 0.02);
}

.cmp-table tbody tr:hover .cmp-cell.is-us {
  background: rgba(74, 222, 128, 0.08);
}

/* ── pills ────────────────────────────────────────────── */
.pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 13px;
  border-radius: 999px;
  font-size: 13px;
  line-height: 1.45;
  border: 1px solid transparent;
}

.pill-good {
  color: #86efac;
  background: rgba(74, 222, 128, 0.12);
  border-color: rgba(74, 222, 128, 0.32);
  font-weight: 700;
}

.pill-bad {
  color: #fca5a5;
  background: rgba(248, 113, 113, 0.1);
  border-color: rgba(248, 113, 113, 0.24);
}

.pill-warn {
  color: #fcd34d;
  background: rgba(251, 191, 36, 0.1);
  border-color: rgba(251, 191, 36, 0.24);
}

.pill-muted {
  color: rgba(255, 255, 255, 0.38);
  background: rgba(255, 255, 255, 0.04);
  border-color: rgba(255, 255, 255, 0.1);
}

.pill-check {
  width: 13px;
  height: 13px;
  flex: none;
}

.cmp-table tbody tr {
  opacity: 0;
}

.is-visible .cmp-table tbody tr {
  animation: cmp-in 0.45s ease forwards;
  animation-delay: calc(var(--i) * 50ms);
}

@keyframes cmp-in {
  to {
    opacity: 1;
  }
}

.cmp-foot {
  margin: 20px 0 0;
  text-align: center;
  font-size: 13px;
  color: rgba(255, 255, 255, 0.38);
}

/* ── mobile ───────────────────────────────────────────── */
.cmp-cards {
  display: grid;
  gap: 14px;
}

.cmp-card {
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
  background: #0b1120;
  padding: 20px;
}

.cmp-card-title {
  margin: 0 0 14px;
  font-size: 16px;
  font-weight: 700;
  color: var(--text);
}

.cmp-card-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 10px;
}

.cmp-card-row {
  display: grid;
  grid-template-columns: 84px minmax(0, 1fr);
  gap: 12px;
  align-items: center;
}

.cmp-card-col {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.42);
}

.cmp-card-row:first-child .cmp-card-col {
  color: #86efac;
  font-weight: 700;
}

.visually-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}

.desktop-only {
  display: block;
}

.mobile-only {
  display: none;
}

@media (max-width: 1080px) {
  .desktop-only {
    display: none;
  }

  .mobile-only {
    display: grid;
  }

  .cmp-section {
    padding: 72px 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .cmp-table tbody tr,
  .is-visible .cmp-table tbody tr {
    animation: none;
    opacity: 1;
  }
}
</style>
