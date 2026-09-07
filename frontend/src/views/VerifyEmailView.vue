<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { Message } from "@arco-design/web-vue";
import { IconLanguage } from "@arco-design/web-vue/es/icon";
import { getMe, resendVerification, verifyEmailUrl } from "../api/auth";
import {
  buildAuthPageUrl,
  emailVerificationNoticeUrl,
  resolvePostAuthRedirect,
} from "../config/runtime";

const route = useRoute();
const router = useRouter();
const redirectTarget = ref(
  typeof route.query.redirect === "string" ? route.query.redirect : "",
);

const loading = ref(true);
const sending = ref(false);
const hasSession = ref(false);
const emailVerified = ref(false);
const verificationAttempted = ref(false);
const email = ref("");
const statusMessage = ref("");

const verificationSuccessMessage = "邮箱验证成功";
const expiredVerificationMessage = "验证链接已过期或无效，请重新发送验证邮件。";
const expiredVerificationLoginMessage = "验证链接已过期，请重新登录后再继续。";

const verificationUrl = computed(() => {
  const hash =
    typeof route.hash === "string" ? route.hash.replace(/^#/, "") : "";

  if (hash) {
    const hashParams = new URLSearchParams(hash);
    const hashVerificationUrl = hashParams.get("verify_url");

    if (hashVerificationUrl) {
      return hashVerificationUrl;
    }
  }

  return typeof route.query.verify_url === "string"
    ? route.query.verify_url
    : "";
});

const hasExpiredVerificationState = computed(
  () =>
    verificationAttempted.value &&
    !emailVerified.value &&
    statusMessage.value === expiredVerificationMessage,
);

const pageTitle = computed(() => {
  if (loading.value) {
    return verificationUrl.value ? "正在验证邮箱" : "正在检查验证状态";
  }

  if (emailVerified.value) {
    return "邮箱已验证";
  }

  if (hasExpiredVerificationState.value) {
    return "验证链接已过期";
  }

  return "请完成邮箱验证";
});

const pageSubtitle = computed(() => {
  if (loading.value) {
    return verificationUrl.value
      ? "正在处理邮件中的验证链接，请稍候。"
      : "正在读取当前账号的验证状态，请稍候。";
  }

  if (hasExpiredVerificationState.value) {
    return "该验证链接已经失效，请重新发送验证邮件后使用新的验证链接。";
  }

  if (verificationAttempted.value && emailVerified.value) {
    return "验证已完成，你现在可以继续访问控制台。";
  }

  if (emailVerified.value) {
    return "当前邮箱已经验证完成。";
  }

  if (hasSession.value && email.value) {
    return `验证邮件已发送至 ${email.value}，请打开邮件中的链接完成验证。`;
  }

  if (hasSession.value) {
    return "验证邮件已发送，请打开邮件中的链接完成验证。";
  }

  return "当前登录状态已失效，请重新登录后继续。";
});

const statusLabel = computed(() => {
  if (loading.value) return verificationUrl.value ? "Checking" : "Loading";
  if (emailVerified.value) return "Ready";
  if (hasExpiredVerificationState.value) return "Expired";
  return "Pending";
});

const emailLabel = computed(() => email.value || "当前账号");

const primaryActionLabel = computed(() => {
  if (loading.value) return "";

  if (emailVerified.value) {
    return hasSession.value ? "进入控制台" : "返回登录";
  }

  return hasSession.value ? "打开验证中心" : "返回登录";
});

const showStatusNote = computed(() => {
  if (!statusMessage.value) return false;
  if (emailVerified.value) return false;

  return true;
});

function cleanVerifyRoute() {
  return router.replace({
    path: "/verify-email",
    query: redirectTarget.value ? { redirect: redirectTarget.value } : {},
  });
}

onMounted(async () => {
  const verificationLink = verificationUrl.value;

  if (verificationLink) {
    await cleanVerifyRoute();
    await handleVerificationLink(verificationLink);
    return;
  }

  if (route.hash || Object.keys(route.query).length > 0) {
    await cleanVerifyRoute();
  }

  await loadCurrentUserState();
});

async function hydrateSessionState() {
  try {
    const res = await getMe();
    hasSession.value = true;
    emailVerified.value = Boolean(res.data.email_verified);
    email.value = res.data.user?.email || "";
  } catch (error) {
    hasSession.value = false;
    email.value = "";

    if (!verificationAttempted.value) {
      statusMessage.value = error.message || "";
    }
  }
}

async function loadCurrentUserState() {
  try {
    await hydrateSessionState();
  } finally {
    loading.value = false;
  }
}

function normalizeVerificationError(error) {
  const message = String(error?.message || "").trim();

  if (
    message.includes("已过期") ||
    message.includes("无效") ||
    message.includes("权限执行该操作")
  ) {
    return expiredVerificationMessage;
  }

  if (message.includes("先登录") || message.includes("登录状态")) {
    return expiredVerificationLoginMessage;
  }

  return message || "邮箱验证失败";
}

async function handleVerificationLink(url) {
  try {
    verificationAttempted.value = true;
    const res = await verifyEmailUrl(url);
    emailVerified.value = Boolean(res.data.email_verified);
    statusMessage.value = "";
    Message.success(verificationSuccessMessage);
    await hydrateSessionState();
  } catch (error) {
    verificationAttempted.value = true;
    statusMessage.value = normalizeVerificationError(error);
    await hydrateSessionState();

    if (hasSession.value && emailVerified.value) {
      Message.success(verificationSuccessMessage);
      handleOpenDashboard();
      return;
    }

    if (!hasSession.value) {
      Message.warning(expiredVerificationLoginMessage);
      await router.push({
        path: "/login",
        query: redirectTarget.value ? { redirect: redirectTarget.value } : {},
      });
      return;
    }

    Message.warning(statusMessage.value);
  } finally {
    loading.value = false;
  }
}

async function handleResend() {
  if (!hasSession.value) {
    Message.warning("请先登录后再重发验证邮件");
    router.push({
      path: "/login",
      query: redirectTarget.value ? { redirect: redirectTarget.value } : {},
    });
    return;
  }

  try {
    sending.value = true;
    const res = await resendVerification();
    statusMessage.value = res.message || "验证邮件已重新发送";
    Message.success(statusMessage.value);
  } catch (error) {
    statusMessage.value = error.message || "重发验证邮件失败";
    Message.error(statusMessage.value);
  } finally {
    sending.value = false;
  }
}

function handleOpenVerificationCenter() {
  window.location.href = buildAuthPageUrl(
    emailVerificationNoticeUrl,
    redirectTarget.value,
  );
}

function handleOpenDashboard() {
  window.location.href = resolvePostAuthRedirect(redirectTarget.value);
}

function handleBackToLogin() {
  router.push({
    path: "/login",
    query: redirectTarget.value ? { redirect: redirectTarget.value } : {},
  });
}

function handlePrimaryAction() {
  if (emailVerified.value) {
    if (hasSession.value) {
      handleOpenDashboard();
      return;
    }

    handleBackToLogin();
    return;
  }

  if (hasSession.value) {
    handleOpenVerificationCenter();
    return;
  }

  handleBackToLogin();
}
</script>

<template>
  <div class="verify-page">
    <div class="verify-page__bg"></div>

    <header class="verify-topbar">
      <RouterLink to="/" class="brand">
        <img src="/favicon.svg" alt="logo" class="brand__logo" />
        <span class="brand__name">TyCDN</span>
      </RouterLink>

      <button class="lang-btn" type="button" aria-label="language">
        <IconLanguage />
      </button>
    </header>

    <main class="verify-main">
      <section class="verify-panel">
        <div
          class="verify-badge"
          :class="{ 'verify-badge--success': emailVerified && !loading }"
        >
          {{ statusLabel }}
        </div>

        <h1 class="verify-panel__title">{{ pageTitle }}</h1>
        <p class="verify-panel__subtitle">{{ pageSubtitle }}</p>

        <div class="verify-card">
          <div class="verify-card__label">验证邮箱</div>
          <div class="verify-card__value">{{ emailLabel }}</div>
        </div>

        <div v-if="showStatusNote" class="verify-note">
          {{ statusMessage }}
        </div>

        <div class="verify-actions">
          <a-button
            v-if="!loading"
            type="primary"
            size="large"
            long
            @click="handlePrimaryAction"
          >
            {{ primaryActionLabel }}
          </a-button>

          <a-button
            v-if="!loading && !emailVerified && hasSession"
            size="large"
            long
            class="verify-secondary"
            :loading="sending"
            @click="handleResend"
          >
            重发验证邮件
          </a-button>
        </div>
      </section>
    </main>

    <footer class="verify-footer">Copyright © 2023-2026 TyCDN LTD.</footer>
  </div>
</template>

<style scoped>
.verify-page {
  position: relative;
  min-height: 100vh;
  overflow: hidden;
  background:
    radial-gradient(
      circle at 50% 10%,
      rgba(77, 113, 255, 0.14),
      transparent 26%
    ),
    linear-gradient(180deg, #171b28 0%, #151a28 35%, #121723 100%);
  color: #fff;
}

.verify-page__bg {
  position: absolute;
  inset: 0;
  pointer-events: none;
  background: linear-gradient(
    90deg,
    rgba(102, 129, 255, 0.06) 0%,
    transparent 35%,
    transparent 65%,
    rgba(102, 129, 255, 0.04) 100%
  );
}

.verify-topbar {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 26px 28px 0;
}

.brand {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: #f5f7ff;
  text-decoration: none;
  font-weight: 700;
  font-size: 18px;
}

.brand__logo {
  width: 28px;
  height: 28px;
  object-fit: contain;
}

.brand__name {
  letter-spacing: -0.01em;
}

.lang-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border: 0;
  background: transparent;
  color: rgba(255, 255, 255, 0.78);
  font-size: 18px;
  cursor: pointer;
}

.verify-main {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: calc(100vh - 140px);
  padding: 40px 20px 80px;
}

.verify-panel {
  width: 100%;
  max-width: 420px;
}

.verify-badge {
  display: inline-flex;
  align-items: center;
  height: 30px;
  padding: 0 12px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  background: rgba(255, 255, 255, 0.08);
  color: rgba(255, 255, 255, 0.78);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  opacity: 0;
  transform: translateY(14px);
  animation: verify-fade-in 0.48s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}

.verify-badge--success {
  border-color: rgba(34, 197, 94, 0.28);
  background: rgba(34, 197, 94, 0.16);
  color: #bbf7d0;
}

.verify-panel__title {
  margin: 18px 0 10px;
  font-size: 32px;
  line-height: 1.2;
  font-weight: 800;
  color: rgba(255, 255, 255, 0.96);
  opacity: 0;
  transform: translateY(14px);
  animation: verify-fade-in 0.48s cubic-bezier(0.22, 1, 0.36, 1) 0.05s forwards;
}

.verify-panel__subtitle {
  margin: 0 0 24px;
  font-size: 15px;
  line-height: 1.7;
  color: rgba(255, 255, 255, 0.54);
  opacity: 0;
  transform: translateY(14px);
  animation: verify-fade-in 0.48s cubic-bezier(0.22, 1, 0.36, 1) 0.1s forwards;
}

.verify-card {
  padding: 18px 18px 16px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.06);
  box-shadow: 0 24px 60px rgba(8, 12, 22, 0.28);
  opacity: 0;
  transform: translateY(14px);
  animation: verify-fade-in 0.48s cubic-bezier(0.22, 1, 0.36, 1) 0.15s forwards;
}

.verify-card__label {
  margin-bottom: 10px;
  color: rgba(255, 255, 255, 0.48);
  font-size: 13px;
}

.verify-card__value {
  font-size: 20px;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.96);
  word-break: break-word;
}

.verify-note {
  margin-top: 14px;
  padding: 12px 14px;
  border-radius: 12px;
  background: rgba(36, 104, 255, 0.12);
  color: rgba(255, 255, 255, 0.8);
  font-size: 14px;
  line-height: 1.6;
  opacity: 0;
  transform: translateY(14px);
  animation: verify-fade-in 0.48s cubic-bezier(0.22, 1, 0.36, 1) 0.2s forwards;
}

.verify-actions {
  display: grid;
  gap: 12px;
  margin-top: 22px;
  opacity: 0;
  transform: translateY(14px);
  animation: verify-fade-in 0.48s cubic-bezier(0.22, 1, 0.36, 1) 0.24s forwards;
}

.verify-secondary {
  background: rgba(255, 255, 255, 0.08);
  color: rgba(255, 255, 255, 0.92);
}

.verify-footer {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 18px;
  z-index: 2;
  text-align: center;
  font-size: 12px;
  color: rgba(255, 255, 255, 0.3);
}

@keyframes verify-fade-in {
  from {
    opacity: 0;
    transform: translateY(14px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .verify-badge,
  .verify-panel__title,
  .verify-panel__subtitle,
  .verify-card,
  .verify-note,
  .verify-actions {
    opacity: 1;
    transform: none;
    animation: none;
  }
}
</style>
