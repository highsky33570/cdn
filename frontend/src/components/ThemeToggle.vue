<template>
  <button
    type="button"
    class="theme-toggle"
    :aria-label="isDark ? '切换到浅色模式' : '切换到深色模式'"
    :title="isDark ? '浅色模式' : '深色模式'"
    @click="toggleTheme"
  >
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="1.7"
      stroke-linecap="round"
      stroke-linejoin="round"
      aria-hidden="true"
    >
      <template v-if="isDark">
        <circle cx="12" cy="12" r="4" />
        <path
          d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"
        />
      </template>
      <path v-else d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
    </svg>
  </button>
</template>
<script setup>
import { useTheme } from '../composables/useTheme'
const { isDark, toggleTheme } = useTheme()
</script>
<style scoped>
.theme-toggle {
  display: inline-grid;
  place-items: center;
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  padding: 0;
  border: 1px solid var(--border);
  border-radius: 11px;
  background: var(--panel);
  color: var(--text-2);
  cursor: pointer;
  transition:
    background 0.2s,
    color 0.2s;
}
.theme-toggle:hover {
  background: var(--surface);
  color: var(--accent);
  border-color: var(--accent-border);
}
svg {
  width: 19px;
  height: 19px;
}
</style>
