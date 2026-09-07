<template>
  <div class="detail-page">
    <AppHeader transparent />

    <main class="detail-main section-inner">
      <div class="container-page">
        <template v-if="plan">
          <div class="detail-topbar">
            <router-link to="/plans" class="back-link"
              >← 返回 JPN 套餐页</router-link
            >
          </div>

          <div class="detail-grid">
            <section class="detail-panel detail-panel--main">
              <div class="detail-badge">{{ group?.shortName }} 套餐</div>
              <h1>{{ plan.title }}</h1>
              <p class="detail-desc">{{ plan.description }}</p>

              <a-descriptions :column="1" bordered class="detail-descriptions">
                <a-descriptions-item label="套餐名称">{{
                  plan.title
                }}</a-descriptions-item>
                <a-descriptions-item label="地区">{{
                  group?.name
                }}</a-descriptions-item>
                <a-descriptions-item label="适用场景">{{
                  plan.scene
                }}</a-descriptions-item>
                <a-descriptions-item label="带宽">{{
                  plan.bandwidth
                }}</a-descriptions-item>
                <a-descriptions-item label="流量">{{
                  plan.traffic
                }}</a-descriptions-item>
                <a-descriptions-item label="站点数">{{
                  plan.websites
                }}</a-descriptions-item>
                <a-descriptions-item label="域名数">{{
                  plan.domains
                }}</a-descriptions-item>
                <a-descriptions-item label="上传限制">{{
                  plan.uploadSize
                }}</a-descriptions-item>
                <a-descriptions-item label="WebSocket">
                  {{ plan.websocket ? "Supported" : "Not Supported" }}
                </a-descriptions-item>
              </a-descriptions>
            </section>

            <aside class="detail-panel detail-panel--side">
              <div class="summary-title">套餐价格</div>
              <div class="summary-price">
                <span>{{ displayPrice }}</span>
                <small>{{ plan.currency }}</small>
              </div>
              <div class="summary-cycle">{{ plan.billingCycle }}</div>

              <div class="summary-note">适合：{{ plan.scene }}</div>

              <a-space direction="vertical" fill>
                <a-button type="primary" long size="large" @click="goLogin"
                  >立即订购</a-button
                >
                <a-button long size="large" @click="goPlans"
                  >返回套餐列表</a-button
                >
              </a-space>
            </aside>
          </div>
        </template>

        <a-empty v-else description="未找到该套餐">
          <a-button type="primary" @click="goPlans">返回套餐页</a-button>
        </a-empty>
      </div>
    </main>

    <AppFooter />
  </div>
</template>

<script setup>
import { computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import AppHeader from "../components/AppHeader.vue";
import AppFooter from "../components/AppFooter.vue";
import { getGroupByKey, getPlanBySlug } from "../data/plans";
import {
  useLivePricing,
  formatPlanPrice,
} from "../composables/useLivePricing";
import {
  buildAuthPageUrl,
  buildConsoleCheckoutPath,
  dashboardLoginUrl,
} from "../config/runtime";

const route = useRoute();
const router = useRouter();

const plan = computed(() => getPlanBySlug(route.params.slug));

// Live catalogue price, falling back to the static entry.
const { priceBySlug } = useLivePricing();
const displayPrice = computed(() =>
  plan.value ? formatPlanPrice(plan.value, priceBySlug.value) : "-",
);
const group = computed(() =>
  plan.value ? getGroupByKey(plan.value.groupKey) : null,
);

const goPlans = () => {
  router.push("/plans");
};

const goLogin = () => {
  window.location.href = buildAuthPageUrl(
    dashboardLoginUrl,
    buildConsoleCheckoutPath(),
  );
};
</script>

<style scoped>
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.detail-page {
  min-height: 100vh;
  background:
    radial-gradient(
      circle at top left,
      rgba(29, 78, 216, 0.18),
      transparent 26%
    ),
    radial-gradient(
      circle at top right,
      rgba(22, 163, 74, 0.12),
      transparent 16%
    ),
    linear-gradient(180deg, #09101d 0%, #0d1322 46%, #111827 100%);
}

.detail-main {
  padding-top: calc(var(--header-h) + 40px);
  padding-bottom: 80px;
}

.detail-topbar {
  margin-bottom: 20px;
}

.back-link {
  color: rgba(255, 255, 255, 0.72);
  font-size: 15px;
}

.detail-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  gap: 24px;
  opacity: 0;
  animation: fadeInUp 0.8s ease-out forwards;
}

.detail-panel {
  border-radius: 24px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  background: linear-gradient(
    180deg,
    rgba(255, 255, 255, 0.04),
    rgba(255, 255, 255, 0.02)
  );
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.2);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
}

.detail-panel--main {
  padding: 32px;
}

.detail-panel--side {
  padding: 28px;
  align-self: start;
}

.detail-badge {
  display: inline-flex;
  padding: 8px 12px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.06);
  color: rgba(255, 255, 255, 0.84);
  font-weight: 700;
  margin-bottom: 18px;
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.detail-panel h1 {
  margin: 0;
  font-size: clamp(34px, 4vw, 52px);
  line-height: 1.1;
  color: rgba(255, 255, 255, 0.96);
}

.detail-desc {
  margin-top: 14px;
  margin-bottom: 24px;
  color: rgba(255, 255, 255, 0.6);
  line-height: 1.8;
  font-size: 16px;
}

.summary-title {
  color: rgba(255, 255, 255, 0.56);
  margin-bottom: 12px;
}

.summary-price {
  display: flex;
  align-items: baseline;
  gap: 8px;
  color: rgba(255, 255, 255, 0.96);
}

.summary-price span {
  font-size: 42px;
  font-weight: 800;
}

.summary-price small {
  font-size: 16px;
  font-weight: 700;
}

.summary-cycle,
.summary-note {
  color: rgba(255, 255, 255, 0.56);
}

.summary-cycle {
  margin-top: 4px;
}

.summary-note {
  margin: 22px 0 24px;
  line-height: 1.8;
}

.detail-descriptions {
  overflow: hidden;
}

:deep(.detail-descriptions .arco-descriptions) {
  border-color: rgba(255, 255, 255, 0.08);
}

:deep(.detail-descriptions .arco-descriptions-item-label) {
  background: rgba(255, 255, 255, 0.04);
  color: rgba(255, 255, 255, 0.52);
  border-color: rgba(255, 255, 255, 0.08);
}

:deep(.detail-descriptions .arco-descriptions-item-value) {
  background: rgba(255, 255, 255, 0.02);
  color: rgba(255, 255, 255, 0.88);
  border-color: rgba(255, 255, 255, 0.08);
}

@media (max-width: 900px) {
  .detail-grid {
    grid-template-columns: 1fr;
  }

  .detail-panel--main,
  .detail-panel--side {
    padding: 22px;
  }
}
</style>
