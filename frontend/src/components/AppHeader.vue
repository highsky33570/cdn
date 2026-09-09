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
        <a-button type="text" shape="round" class="header-action">文A</a-button>
          <a-button type="text" @click="goLogin" class="header-action">登录</a-button>
          <a-button type="primary" @click="goRegister" size="medium">注册</a-button>
      </div>
    </div>
  </header>
</template>

<script setup>
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
  background: rgba(10, 16, 32, 0.42);
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
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
}

.desktop-nav {
  display: flex;
  align-items: center;
  gap: 18px;
  flex-wrap: nowrap;
}

.nav-link {
  font-size: 15px;
  color: rgba(255, 255, 255, 0.84);
  white-space: nowrap;
  transition: color 0.2s;
}

.nav-link:hover {
  color: #fff;
}

.header-right {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: nowrap;
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