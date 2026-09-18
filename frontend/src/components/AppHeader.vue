<template>
  <header class="site-header" @keydown.esc="closeMenu">
    <div class="container-hero header-inner">
      <router-link to="/" class="brand" aria-label="TyCDN 首页"
        ><span class="brand-mark" aria-hidden="true">T</span>TyCDN</router-link
      >
      <nav class="desktop-nav" aria-label="主导航">
        <router-link
          v-for="link in links"
          :key="link.to"
          :to="link.to"
          class="nav-link"
          :class="{ 'is-active': isActive(link.to) }"
          :aria-current="isActive(link.to) ? 'page' : undefined"
          >{{ link.label }}</router-link
        >
      </nav>
      <div class="header-right">
        <ThemeToggle />
        <a :href="dashboardLoginUrl" class="header-login">登录</a>
        <a :href="dashboardRegisterUrl" class="button button--primary header-register"
          >注册 <span aria-hidden="true">↗</span></a
        >
        <button
          ref="menuButton"
          type="button"
          class="menu-toggle"
          :aria-expanded="menuOpen"
          aria-controls="mobile-navigation"
          :aria-label="menuOpen ? '关闭导航' : '打开导航'"
          @click="menuOpen = !menuOpen"
        >
          <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            aria-hidden="true"
          >
            <path v-if="menuOpen" d="m6 6 12 12M18 6 6 18" />
            <path v-else d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
      </div>
    </div>
    <nav v-if="menuOpen" id="mobile-navigation" class="mobile-nav" aria-label="移动端导航">
      <router-link
        v-for="link in links"
        :key="link.to"
        :to="link.to"
        class="nav-link"
        :class="{ 'is-active': isActive(link.to) }"
        @click="menuOpen = false"
        >{{ link.label }}</router-link
      >
    </nav>
  </header>
</template>
<script setup>
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import ThemeToggle from './ThemeToggle.vue'
import { dashboardLoginUrl, dashboardRegisterUrl } from '../config/runtime'
const route = useRoute()
const menuOpen = ref(false)
const menuButton = ref(null)
const links = [
  { to: '/', label: '首页' },
  { to: '/#why', label: '为什么选我们' },
  { to: '/plans', label: '套餐价格' },
  { to: '/#compare', label: '方案对比' },
  { to: '/#faq', label: '常见问题' }
]
const isActive = (to) =>
  to === '/plans'
    ? route.path.startsWith('/plans')
    : to === '/'
      ? route.path === '/' && !route.hash
      : route.fullPath === to
const closeMenu = () => {
  menuOpen.value = false
  menuButton.value?.focus()
}
watch(
  () => route.fullPath,
  () => {
    menuOpen.value = false
  }
)
</script>
<style scoped>
.site-header {
  position: fixed;
  inset: 0 0 auto;
  z-index: 1000;
  height: var(--header-h);
  background: var(--header-bg);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--header-border);
}
.header-inner {
  display: flex;
  height: 100%;
  align-items: center;
  gap: 36px;
}
.brand {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-size: 21px;
  font-weight: 800;
  color: var(--text-strong);
  letter-spacing: -0.04em;
  white-space: nowrap;
}
.brand-mark {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 10px;
  color: var(--accent-ink);
  background: var(--accent-fill);
  font-size: 21px;
}
.desktop-nav {
  display: flex;
  align-items: center;
  gap: 4px;
}
.nav-link {
  padding: 9px 12px;
  border-radius: 8px;
  color: var(--text-2);
  font-size: 14px;
  font-weight: 500;
  white-space: nowrap;
  transition:
    background 0.2s,
    color 0.2s;
}
.nav-link:hover {
  color: var(--text);
  background: var(--surface);
}
.nav-link.is-active {
  color: var(--accent);
  background: var(--accent-soft);
}
.header-right {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-left: auto;
}
.header-login {
  padding: 10px 12px;
  color: var(--text);
  font-weight: 600;
  border-radius: 9px;
}
.header-login:hover {
  color: var(--accent);
  background: var(--surface);
}
.header-register {
  min-height: 40px;
  padding: 9px 17px;
}
.menu-toggle {
  display: none;
  place-items: center;
  width: 40px;
  height: 40px;
  padding: 0;
  border: 1px solid var(--border);
  border-radius: 10px;
  color: var(--text);
  background: var(--panel);
  cursor: pointer;
}
.menu-toggle svg {
  width: 20px;
  height: 20px;
}
.mobile-nav {
  display: none;
}
@media (max-width: 1060px) {
  .desktop-nav {
    display: none;
  }
  .menu-toggle {
    display: grid;
  }
  .mobile-nav {
    display: grid;
    gap: 6px;
    padding: 16px 20px 20px;
    background: var(--panel);
    border-bottom: 1px solid var(--border);
    box-shadow: var(--shadow);
  }
}
@media (max-width: 480px) {
  .header-inner {
    padding-inline: 16px;
    gap: 12px;
  }
  .header-right {
    gap: 7px;
  }
  .brand {
    font-size: 18px;
    gap: 7px;
  }
  .brand-mark {
    width: 30px;
    height: 30px;
    font-size: 18px;
  }
  .header-login {
    padding-inline: 5px;
    font-size: 13px;
  }
  .header-register {
    padding-inline: 10px;
    font-size: 13px;
  }
  .header-register span {
    display: none;
  }
}
</style>
