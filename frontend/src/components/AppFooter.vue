<template>
  <footer data-public-style class="public-app-footer site-footer">
    <div class="container-page">
      <div class="footer-top">
        <div class="footer-brand">
          <router-link to="/" class="footer-brand__logo" aria-label="TyCDN 首页">
            <span class="footer-brand__mark">T</span>
            <span class="footer-brand__name">TyCDN</span>
          </router-link>

          <p class="footer-brand__desc">
            面向跨境访问与中国优化链路场景，提供稳定、快速、易扩展的 CDN 分发服务。
          </p>
        </div>

        <div class="footer-links">
          <div class="footer-column">
            <div class="footer-title">产品</div>
            <router-link to="/plans" class="footer-link">CDN 套餐</router-link>
          </div>

          <div class="footer-column">
            <div class="footer-title">资源与支持</div>
            <a href="/#why" class="footer-link">关于我们</a>
          </div>
        </div>
      </div>

      <div class="footer-bottom">
        <div>Copyright © 2023-2026 TyCDN. All rights reserved.</div>
        <div>本程序界面、源代码受相关法律保护，未经授权，严禁使用。</div>
      </div>
    </div>
  </footer>
</template>
