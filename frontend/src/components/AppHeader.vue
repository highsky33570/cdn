<template>
  <header class="site-header">
    <div class="container-hero header-inner">
      <div class="header-left">
        <router-link to="/" class="brand">TyCDN</router-link>

        <nav class="desktop-nav">
          <router-link to="/" class="nav-link">首页</router-link>
          <a href="/#why" class="nav-link">为什么选我们</a>
          <router-link to="/plans" class="nav-link">套餐价格</router-link>
          <a href="/#compare" class="nav-link">方案对比</a>
          <a href="/#faq" class="nav-link">常见问题</a>
        </nav>
      </div>

      <div class="header-right">
        <button
          type="button"
          class="theme-toggle"
          :aria-label="isDark ? '切换到浅色模式' : '切换到深色模式'"
          :title="isDark ? '浅色模式' : '深色模式'"
          @click="toggleTheme"
        >
          <svg
            v-if="isDark"
            class="theme-icon"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <circle cx="12" cy="12" r="4" />
            <path
              d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"
            />
          </svg>
          <svg
            v-else
            class="theme-icon"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
          </svg>
        </button>
        <a-button type="text" shape="round" class="header-action">文A</a-button>
        <a-button type="text" @click="goLogin" class="header-action"
          >登录</a-button
        >
        <a-button type="primary" @click="goRegister" size="medium"
          >注册</a-button
        >
      </div>
    </div>
  </header>
</template>

<script setup>
import { useTheme } from '../composables/useTheme'

const { isDark, toggleTheme } = useTheme()

const goLogin = () => {
  window.location.href = '/login'
}

const goRegister = () => {
  window.location.href = '/register'
}
</script>

<style scoped>
.site-header {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 1000;
  height: var(--header-h);
  background: var(--header-bg);
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
  border-bottom: 1px solid var(--header-border);
}

.header-inner {
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 24px;
  min-width: 0;
}

.brand {
  font-size: 18px;
  font-weight: 800;
  white-space: nowrap;
  color: var(--text-strong);
}

.desktop-nav {
  display: flex;
  align-items: center;
  gap: 18px;
  flex-wrap: nowrap;
}

.nav-link {
  font-size: 15px;
  color: var(--text-2);
  white-space: nowrap;
  transition: color 0.2s;
}

.nav-link:hover {
  color: var(--text-strong);
}

.header-right {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: nowrap;
}

.theme-toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  padding: 0;
  border-radius: 10px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--text-2);
  cursor: pointer;
  transition:
    color 0.2s,
    border-color 0.2s,
    background 0.2s;
}

.theme-toggle:hover {
  color: var(--text-strong);
  border-color: var(--border-strong);
  background: var(--surface-strong);
}

.theme-icon {
  width: 18px;
  height: 18px;
}

:deep(.header-action.arco-btn) {
  font-size: 14px;
  padding: 0 8px;
}

:deep(.arco-btn) {
  height: 36px;
  border-radius: 10px;
}

@media (max-width: 768px) {
  .header-left {
    gap: 16px;
  }

  .desktop-nav {
    gap: 12px;
  }

  .nav-link {
    font-size: 14px;
  }

  .header-right {
    gap: 4px;
  }
}
</style>
