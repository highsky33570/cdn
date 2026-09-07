<template>
  <section id="hero" class="hero-section">
    <div class="hero-distribution-bg"></div>

    <div class="container-hero hero-inner">
      <div class="hero-copy">
        <button class="hero-badge hero-badge-link" @click="goPlans">
          <span class="hero-badge-dot"></span>
          <span>Japan-Tokyo COP</span>
        </button>

        <h1>
          <span class="hero-brand-strong">TyCDN</span>
          <span class="hero-title-rest">专注中国优化加速的<br/>日本 CDN 服务</span>
        </h1>

        <div class="hero-tags">
          <div class="hero-tag-line">
            <span class="hero-tag-icon" aria-hidden="true">
              <img src="/images/tag-cyber-crystal.svg" alt="Security" class="hero-tag-svg" />
            </span>
            <span class="tag-box">高速内容分发，极致DDoS防御</span>
          </div>
        </div>

        <div class="hero-actions">
          <a-button type="primary" size="medium" shape="square" @click="goPlans">立即购买</a-button>
          <a-button size="medium" shape="square" @click="goAbout">了解我们</a-button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
const goPlans = () => {
  document.querySelector('#plans')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const goAbout = () => {
  document.querySelector('#about')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
</script>

<style scoped>
.hero-section {
  min-height: 100vh;
  padding-top: var(--header-h);
  display: flex;
  align-items: center;
  position: relative;
  overflow: hidden;
  background-color: transparent; 
}

/* --- 进场动画 --- */
@keyframes slideInFade {
  0% { opacity: 0; transform: translateX(-40px); }
  100% { opacity: 1; transform: translateX(0); }
}

@keyframes fadeInBg {
  0% { opacity: 0; }
  100% { opacity: 0.95; }
}

/* --- 背景资产引用 --- */
.hero-distribution-bg {
  position: absolute;
  top: 0; right: 0; bottom: 0; left: 10%;
  z-index: 0;
  pointer-events: none;
  opacity: 0; 
  animation: fadeInBg 1.5s ease-in-out forwards;
  background-image: 
    url('/images/cdn-network.svg'),
    radial-gradient(circle at 65% 50%, rgba(77, 140, 255, 0.05) 0%, transparent 60%);
  background-repeat: no-repeat;
  background-position: center right; 
  background-size: contain, cover; 
}

/* --- 文案流式入场 --- */
.hero-inner { position: relative; z-index: 2; width: 100%; }
.hero-copy { max-width: 650px; }
.hero-copy > * {
  opacity: 0;
  animation: slideInFade 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
}
.hero-copy > .hero-badge { animation-delay: 0.1s; }
.hero-copy > h1 { animation-delay: 0.25s; }
.hero-copy > .hero-tags { animation-delay: 0.4s; }
.hero-copy > .hero-actions { animation-delay: 0.55s; }

/* --- 样式细节 --- */
.hero-badge {
  display: inline-flex; align-items: center; gap: 10px; height: 34px; padding: 0 16px;
  border-radius: 999px; border: 1px solid rgba(255, 255, 255, 0.08); background: rgba(255, 255, 255, 0.03);
  color: rgba(255, 255, 255, 0.92); font-size: 14px; font-weight: 500; margin-bottom: 24px;
}
.hero-badge-dot { width: 7px; height: 7px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 8px rgba(34, 197, 94, 0.35); animation: pulse-green 2s infinite; }
@keyframes pulse-green { 0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); } 70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(34, 197, 94, 0); } 100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); } }

.hero-copy h1 { font-size: clamp(32px, 4.5vw, 56px); line-height: 1.16; margin: 0; font-weight: 500; }
.hero-brand-strong { font-weight: 800; color: #fff; }
.hero-title-rest { color: rgba(255, 255, 255, 0.72); margin-left: 12px; display: block; }

.hero-tags { margin-top: 40px; }
.hero-tag-line { display: flex; align-items: center; gap: 14px; }
.hero-tag-icon { 
  width: 46px; 
  height: 46px; 
  display: inline-flex; 
  align-items: center; 
  justify-content: center; 
}
.hero-tag-svg { width: 34px; height: 34px; display: block; }

.tag-box { font-size: 20px; line-height: 1.9; color: #b2b8cd; }
.hero-actions { margin-top: 40px; display: flex; gap: 12px; }
:deep(.hero-actions .arco-btn) { min-width: 140px; height: 54px; border-radius: 12px; font-size: 18px; }

@media (max-width: 980px) { .hero-distribution-bg { left: 0; opacity: 0.3; } }
</style>