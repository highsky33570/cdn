<template>
  <div class="plans-page">
    <AppHeader transparent />

    <main class="plans-main">
      <section class="plans-hero">
        <div class="container-page">
          <div class="plans-hero__badge">
            <span class="plans-hero__dot"></span>
            <span>{{
              activeGroup.badge || `${activeGroup.shortName} COP`
            }}</span>
          </div>

          <div class="plans-hero__head">
            <div>
              <h1>{{ activeGroup.name }}</h1>
              <p>{{ activeGroup.description }}</p>
            </div>

            <div class="plans-hero__actions">
              <a-button size="large" shape="round" @click="goLogin"
                >登录账户</a-button
              >
              <a-button
                type="primary"
                size="large"
                shape="round"
                @click="goRegister"
              >
                快速注册
              </a-button>
            </div>
          </div>
        </div>
      </section>

      <section class="plans-section">
        <div class="container-page">
          <div class="plans-panel">
            <div class="plans-panel__bar">
              <div>
                <div class="panel-title">Regional Packages</div>
                <div class="panel-subtitle">
                  在线分组直接读取本地商品目录并创建 EPUSDT
                  订单，未上线分组继续作为预告展示。
                </div>
              </div>
              <div class="panel-pill">{{ panelPillText }}</div>
            </div>

            <div class="group-switcher" role="tablist" aria-label="节点分组">
              <button
                v-for="group in planGroups"
                :key="group.key"
                type="button"
                class="group-chip"
                :class="{ 'group-chip--active': activeGroupKey === group.key }"
                @click="goGroup(group.key)"
              >
                <span class="group-chip__name">{{ group.shortName }}</span>
                <span class="group-chip__meta">
                  {{ group.location }}
                  <em
                    :class="[
                      'group-chip__status',
                      group.status === 'online' ? 'is-online' : 'is-coming',
                    ]"
                  >
                    {{ group.status === "online" ? "在线" : "即将上线" }}
                  </em>
                </span>
              </button>
            </div>

            <div
              v-if="catalogLoading && activeGroup.status === 'online'"
              class="loading-state"
            >
              正在加载本地商品目录...
            </div>

            <template v-else-if="activePlans.length">
              <div class="plans-grid">
                <article
                  v-for="item in activePlans"
                  :key="item.id"
                  class="plan-card"
                >
                  <div class="plan-card__glow"></div>

                  <div class="plan-card__body">
                    <div class="plan-card__label">
                      {{ activeGroup.shortName }}
                    </div>
                    <h3>{{ item.title }}</h3>
                    <p class="plan-desc">{{ item.description }}</p>

                    <div class="plan-price">
                      <span class="plan-price__currency">$</span>
                      <span class="plan-price__value">{{
                        item.monthlyPrice
                      }}</span>
                      <span class="plan-price__usd">{{ item.currency }}</span>
                    </div>
                    <div class="plan-price__cycle">Monthly</div>

                    <ul class="plan-terms">
                      <li>
                        <span>Product ID</span>
                        <strong>#{{ item.id }}</strong>
                      </li>
                      <li>
                        <span>Quarterly</span>
                        <strong>{{ item.quarterlyPrice }}</strong>
                      </li>
                      <li>
                        <span>Yearly</span>
                        <strong>{{ item.yearlyPrice }}</strong>
                      </li>
                      <li>
                        <span>交付状态</span>
                        <strong>本地开通 / 待同步</strong>
                      </li>
                      <li>
                        <span>支付网关</span>
                        <strong>EPUSDT</strong>
                      </li>
                    </ul>

                    <div v-if="item.features.length" class="plan-tags">
                      <span v-for="feature in item.features" :key="feature">{{
                        feature
                      }}</span>
                    </div>
                  </div>

                  <div class="plan-card__actions">
                    <a-button
                      type="primary"
                      long
                      size="large"
                      @click="goCheckout(item.id)"
                    >
                      立即购买
                    </a-button>
                  </div>
                </article>
              </div>
            </template>

            <div v-else class="empty-panel">
              <div class="empty-panel__icon">{{ activeGroup.shortName }}</div>
              <div class="empty-panel__eyebrow">
                {{
                  activeGroup.status === "online"
                    ? "No Products"
                    : "Coming Soon"
                }}
              </div>
              <div class="empty-panel__title">
                {{
                  activeGroup.status === "online"
                    ? "当前分组暂时没有可售商品"
                    : `${activeGroup.shortName} 节点即将上线`
                }}
              </div>
              <div class="empty-panel__desc">
                <template v-if="activeGroup.status === 'online'">
                  本地商品目录里还没有启用商品，当前不会创建任何支付订单。
                </template>
                <template v-else>
                  当前区域支持商务预咨询。可按带宽、流量、回源策略、中国优化链路与防护需求，提供更贴合业务场景的定制方案。
                </template>
              </div>

              <div class="empty-panel__tags">
                <span>高速路由</span>
                <span>DDoS 防护</span>
                <span>CC 防护</span>
              </div>

              <div class="empty-panel__actions">
                <a-button type="primary" size="large" @click="contactSales"
                  >联系销售</a-button
                >
                <a-button size="large" @click="goGroup('jpn')"
                  >查看已上线套餐</a-button
                >
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>

    <AppFooter />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { Message } from "@arco-design/web-vue";
import { useRoute, useRouter } from "vue-router";
import { fetchProducts } from "../api/plans";
import AppFooter from "../components/AppFooter.vue";
import AppHeader from "../components/AppHeader.vue";
import { getGroupByKey, planGroups } from "../data/plans";
import {
  buildAuthPageUrl,
  buildConsoleCheckoutPath,
  dashboardLoginUrl,
  dashboardRegisterUrl,
} from "../config/runtime";

const route = useRoute();
const router = useRouter();

const initialGroup = planGroups.some((item) => item.key === route.query.group)
  ? route.query.group
  : "jpn";

const activeGroupKey = ref(initialGroup);
const catalogLoading = ref(false);
const catalogProducts = ref([]);

const activeGroup = computed(
  () => getGroupByKey(activeGroupKey.value) || planGroups[0],
);
const activePlans = computed(() => {
  if (activeGroup.value?.status !== "online") {
    return [];
  }

  return catalogProducts.value.map((product) => ({
    id: product.id,
    title: product.name,
    description:
      String(product.description || "").trim() ||
      "支付成功后先在本地生成服务实例，待后续同步到 CDNfly。",
    monthlyPrice: normalizeAmount(product.price_monthly),
    quarterlyPrice: formatMoney(product.price_quarterly, product.currency),
    yearlyPrice: formatMoney(product.price_yearly, product.currency),
    currency: product.currency || "USD",
    features: Array.isArray(product.features)
      ? product.features
          .filter((item) => typeof item === "string" && item.trim())
          .slice(0, 3)
      : [],
  }));
});

const panelPillText = computed(() => {
  if (catalogLoading.value && activeGroup.value?.status === "online") {
    return "Loading";
  }

  return activePlans.value.length
    ? `${activePlans.value.length} Plans`
    : "Coming Soon";
});

onMounted(async () => {
  await loadCatalog();
});

async function loadCatalog() {
  if (activeGroup.value?.status !== "online") {
    return;
  }

  try {
    catalogLoading.value = true;
    catalogProducts.value = await fetchProducts();
  } catch (error) {
    Message.error(error instanceof Error ? error.message : "加载商品目录失败");
  } finally {
    catalogLoading.value = false;
  }
}

const goGroup = (groupKey) => {
  const nextGroup = getGroupByKey(groupKey);
  activeGroupKey.value = groupKey;
  router.replace({ path: "/plans", query: { group: groupKey } });
  if (nextGroup?.status === "online" && catalogProducts.value.length === 0) {
    void loadCatalog();
  }
};

const goRegister = () => {
  window.location.href = dashboardRegisterUrl;
};

const goLogin = () => {
  window.location.href = dashboardLoginUrl;
};

const goCheckout = (productId) => {
  const redirectPath = buildConsoleCheckoutPath(productId);
  window.location.href = buildAuthPageUrl(dashboardLoginUrl, redirectPath);
};

const contactSales = () => {
  Message.info("这里替换成你的 Telegram、邮箱、工单页或企业微信入口。");
};

function normalizeAmount(value) {
  const amount = Number(value);

  return Number.isFinite(amount) ? amount.toFixed(2) : "0.00";
}

function formatMoney(value, currency) {
  if (value === null || value === undefined || value === "") {
    return "-";
  }

  const amount = Number(value);

  if (!Number.isFinite(amount)) {
    return String(value);
  }

  return `${amount.toFixed(2)} ${currency || "USD"}`;
}
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

@keyframes pulse-green {
  0% {
    transform: scale(0.95);
    box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
  }
  70% {
    transform: scale(1);
    box-shadow: 0 0 0 6px rgba(34, 197, 94, 0);
  }
  100% {
    transform: scale(0.95);
    box-shadow: 0 0 0 0 rgba(34, 197, 94, 0);
  }
}

.plans-page {
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
  color: #ffffff;
}

.plans-main {
  padding-top: var(--header-h);
}

.plans-hero {
  padding: 72px 0 36px;
}

.plans-hero__badge {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  height: 36px;
  padding: 0 16px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(255, 255, 255, 0.04);
  color: rgba(255, 255, 255, 0.92);
  font-size: 14px;
  font-weight: 500;
}

.plans-hero__dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #22c55e;
  box-shadow: 0 0 10px rgba(34, 197, 94, 0.4);
  flex: 0 0 auto;
  animation: pulse-green 2s infinite;
}

.plans-hero__head {
  margin-top: 26px;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;
}

.plans-hero__head h1 {
  margin: 0;
  font-size: clamp(32px, 4.6vw, 56px);
  line-height: 1.08;
  color: rgba(255, 255, 255, 0.96);
}

.plans-hero__head p {
  margin-top: 16px;
  max-width: 920px;
  font-size: 18px;
  line-height: 1.9;
  color: rgba(255, 255, 255, 0.66);
}

.plans-hero__actions {
  display: flex;
  gap: 12px;
  flex: 0 0 auto;
}

:deep(.plans-hero__actions .arco-btn) {
  min-width: 128px;
}

.plans-section {
  padding: 0 0 96px;
  opacity: 0;
  animation: fadeInUp 0.8s ease-out forwards;
}

.plans-panel {
  border-radius: 28px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(10, 16, 28, 0.78);
  backdrop-filter: blur(18px);
  box-shadow: 0 18px 60px rgba(0, 0, 0, 0.28);
  padding: 28px;
}

.plans-panel__bar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
}

.panel-title {
  font-size: 24px;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.94);
}

.panel-subtitle {
  margin-top: 10px;
  color: rgba(255, 255, 255, 0.58);
  font-size: 14px;
  line-height: 1.7;
}

.panel-pill {
  flex: 0 0 auto;
  padding: 8px 14px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.08);
  color: rgba(255, 255, 255, 0.82);
  font-size: 13px;
  font-weight: 600;
}

.group-switcher {
  margin-top: 22px;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.group-chip {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
  padding: 16px 18px;
  border-radius: 18px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(255, 255, 255, 0.03);
  color: rgba(255, 255, 255, 0.86);
  cursor: pointer;
  transition: 0.2s ease;
}

.group-chip:hover,
.group-chip--active {
  border-color: rgba(96, 165, 250, 0.48);
  background: rgba(37, 99, 235, 0.12);
}

.group-chip__name {
  font-size: 18px;
  font-weight: 700;
}

.group-chip__meta {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 8px;
  color: rgba(255, 255, 255, 0.58);
  font-size: 13px;
  line-height: 1.5;
}

.group-chip__status {
  font-style: normal;
  font-weight: 600;
}

.group-chip__status.is-online {
  color: #4ade80;
}

.group-chip__status.is-coming {
  color: #fbbf24;
}

.loading-state {
  margin-top: 22px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(255, 255, 255, 0.03);
  color: rgba(255, 255, 255, 0.76);
  font-size: 14px;
}

.plans-grid {
  margin-top: 22px;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 18px;
}

.plan-card {
  position: relative;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  min-height: 100%;
  border-radius: 24px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: linear-gradient(
    180deg,
    rgba(17, 24, 39, 0.86),
    rgba(8, 14, 25, 0.96)
  );
}

.plan-card__glow {
  position: absolute;
  inset: auto 18% 100% auto;
  width: 180px;
  height: 180px;
  border-radius: 50%;
  background: rgba(59, 130, 246, 0.18);
  filter: blur(60px);
  pointer-events: none;
}

.plan-card__body {
  position: relative;
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 16px;
  padding: 24px;
}

.plan-card__label {
  display: inline-flex;
  align-self: flex-start;
  padding: 7px 12px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.08);
  color: rgba(255, 255, 255, 0.82);
  font-size: 12px;
  font-weight: 700;
}

.plan-card h3 {
  margin: 0;
  font-size: 28px;
  line-height: 1.15;
}

.plan-desc {
  margin: 0;
  color: rgba(255, 255, 255, 0.62);
  line-height: 1.8;
  font-size: 14px;
}

.plan-price {
  display: flex;
  align-items: baseline;
  gap: 8px;
}

.plan-price__currency {
  font-size: 22px;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.78);
}

.plan-price__value {
  font-size: 50px;
  line-height: 1;
  font-weight: 800;
}

.plan-price__usd {
  font-size: 14px;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.66);
}

.plan-price__cycle {
  margin-top: -8px;
  color: rgba(255, 255, 255, 0.56);
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.plan-terms {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 10px;
}

.plan-terms li {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 16px;
  padding-bottom: 10px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.plan-terms span {
  color: rgba(255, 255, 255, 0.56);
  font-size: 13px;
}

.plan-terms strong {
  text-align: right;
  color: rgba(255, 255, 255, 0.92);
  font-size: 14px;
}

.plan-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.plan-tags span {
  display: inline-flex;
  padding: 7px 10px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.08);
  color: rgba(255, 255, 255, 0.72);
  font-size: 12px;
}

.plan-card__actions {
  padding: 0 24px 24px;
}

.empty-panel {
  margin-top: 22px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 46px 24px;
  border-radius: 24px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(255, 255, 255, 0.03);
}

.empty-panel__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.05);
  font-size: 24px;
  font-weight: 800;
}

.empty-panel__eyebrow {
  margin-top: 16px;
  color: #fbbf24;
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.empty-panel__title {
  margin-top: 12px;
  font-size: 28px;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.94);
}

.empty-panel__desc {
  margin-top: 14px;
  max-width: 720px;
  color: rgba(255, 255, 255, 0.62);
  line-height: 1.8;
}

.empty-panel__tags {
  margin-top: 22px;
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 10px;
}

.empty-panel__tags span {
  display: inline-flex;
  padding: 8px 12px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.08);
  color: rgba(255, 255, 255, 0.72);
  font-size: 13px;
}

.empty-panel__actions {
  margin-top: 24px;
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

@media (max-width: 1080px) {
  .plans-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 900px) {
  .plans-hero__head,
  .plans-panel__bar {
    flex-direction: column;
    align-items: flex-start;
  }

  .group-switcher,
  .plans-grid {
    grid-template-columns: 1fr;
  }

  .plans-panel {
    padding: 22px;
  }
}

@media (max-width: 640px) {
  .plans-hero {
    padding-top: 54px;
  }

  .plans-hero__actions,
  .empty-panel__actions {
    width: 100%;
    flex-direction: column;
  }

  :deep(.plans-hero__actions .arco-btn),
  :deep(.empty-panel__actions .arco-btn) {
    width: 100%;
  }
}
</style>
