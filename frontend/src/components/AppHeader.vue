<template>
  <header data-public-style class="public-app-header site-header" @keydown.esc="closeMenu">
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
