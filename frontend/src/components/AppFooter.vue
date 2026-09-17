<template>
  <footer class="site-footer">
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
            <router-link to="/plans?group=jpn" class="footer-link">JPN</router-link>
          </div>

          <div class="footer-column">
            <div class="footer-title">资源与支持</div>
            <a href="/#about" class="footer-link">关于我们</a>
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

<style scoped>
.site-footer {
  padding: 72px 0 34px;
  border-top: 1px solid var(--surface);
  background:
    linear-gradient(180deg, var(--surface) 0%, var(--surface) 100%),
    var(--panel);
}

.footer-top {
  display: flex;
  justify-content: space-between;
  gap: 56px;
  padding-bottom: 42px;
}

.footer-brand {
  max-width: 520px;
}

.footer-brand__logo {
  display: inline-flex;
  align-items: center;
  gap: 14px;
}

.footer-brand__mark {
  width: 44px;
  height: 44px;
  display: grid;
  place-items: center;
  border-radius: 14px;
  background: linear-gradient(180deg, var(--accent) 0%, var(--accent) 100%);
  color: var(--text);
  font-size: 22px;
  font-weight: 800;
  box-shadow: 0 12px 28px rgba(35, 88, 227, 0.28);
}

.footer-brand__name {
  font-size: 20px;
  font-weight: 800;
  color: var(--text);
}

.footer-brand__desc {
  margin: 24px 0 0;
  color: var(--text-2);
  font-size: 16px;
  line-height: 1.9;
}

.footer-links {
  display: grid;
  /* 合并后恢复为 2 列 */
  grid-template-columns: repeat(2, minmax(140px, 1fr));
  gap: 64px;
  flex: 0 0 auto;
}

.footer-column {
  min-width: 0;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.footer-title {
  margin-bottom: 22px;
  color: var(--text);
  font-size: 18px;
  font-weight: 700;
}

.footer-link {
  display: inline-flex;
  align-items: center;
  min-height: 32px;
  color: var(--text-3);
  font-size: 16px;
  transition: color 0.2s ease;
  margin-bottom: 12px; /* 与下方的按钮拉开距离 */
}

.footer-link:hover {
  color: var(--text);
}

.footer-bottom {
  display: flex;
  justify-content: space-between;
  gap: 18px;
  padding-top: 24px;
  border-top: 1px solid var(--surface);
  color: var(--text-3);
  font-size: 14px;
  line-height: 1.8;
}

@media (max-width: 900px) {
  .footer-top {
    flex-direction: column;
    gap: 36px;
  }
  .footer-links {
    gap: 36px;
  }
  .footer-bottom {
    flex-direction: column;
  }
}

@media (max-width: 560px) {
  .footer-links {
    grid-template-columns: 1fr;
  }
}
</style>