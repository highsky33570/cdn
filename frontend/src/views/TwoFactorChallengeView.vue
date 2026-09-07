<script setup>
import { computed, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { Message } from "@arco-design/web-vue";
import { IconLanguage, IconLock, IconSafe } from "@arco-design/web-vue/es/icon";
import { submitTwoFactorChallenge } from "../api/auth";
import { resolvePostAuthRedirect } from "../config/runtime";

const route = useRoute();
const router = useRouter();

const mode = ref("code");
const code = ref("");
const recoveryCode = ref("");
const loading = ref(false);

const redirectTarget = computed(() =>
  typeof route.query.redirect === "string" ? route.query.redirect : "",
);

const accountLabel = computed(() =>
  typeof route.query.account === "string" && route.query.account
    ? route.query.account
    : "当前登录账号",
);

const currentValue = computed(() =>
  mode.value === "code" ? code.value : recoveryCode.value,
);

const pageTitle = computed(() =>
  mode.value === "code" ? "输入两步验证码" : "输入恢复代码",
);

const pageSubtitle = computed(() =>
  mode.value === "code"
    ? "当前账号已开启两步验证，请输入验证器中的 6 位动态码。"
    : "如果你当前无法访问验证器，可以改用恢复代码完成登录。",
);

const submitLabel = computed(() =>
  mode.value === "code" ? "验证并登录" : "使用恢复代码登录",
);

const handleSubmit = async () => {
  if (!currentValue.value.trim()) {
    Message.warning(
      mode.value === "code" ? "请输入两步验证码" : "请输入恢复代码",
    );
    return;
  }

  try {
    loading.value = true;

    const res = await submitTwoFactorChallenge({
      code: mode.value === "code" ? code.value.trim() : "",
      recoveryCode: mode.value === "recovery" ? recoveryCode.value.trim() : "",
      redirect: redirectTarget.value || undefined,
    });

    if (res.data?.email_verified) {
      Message.success("登录成功");
      window.location.href = res.data?.redirect || resolvePostAuthRedirect(redirectTarget.value);
      return;
    }

    Message.warning("请先完成邮箱验证");
    router.push({
      path: "/verify-email",
      query: {
        ...(res.data?.user?.email ? { email: res.data.user.email } : {}),
        ...(redirectTarget.value ? { redirect: redirectTarget.value } : {}),
      },
    });
  } catch (error) {
    Message.error(error instanceof Error ? error.message : "两步验证失败");
  } finally {
    loading.value = false;
  }
};

const switchMode = (nextMode) => {
  mode.value = nextMode;
};

const handleBackToLogin = () => {
  router.push({
    path: "/login",
    query: redirectTarget.value ? { redirect: redirectTarget.value } : {},
  });
};
</script>

<template>
  <div class="challenge-page">
    <div class="challenge-page__bg"></div>

    <header class="challenge-topbar">
      <RouterLink to="/" class="brand">
        <img src="/favicon.svg" alt="logo" class="brand__logo" />
        <span class="brand__name">TyCDN</span>
      </RouterLink>

      <button class="lang-btn" type="button" aria-label="language">
        <IconLanguage />
      </button>
    </header>

    <main class="challenge-main">
      <section class="challenge-panel">
        <div class="challenge-badge">
          <IconSafe />
          <span>2FA Required</span>
        </div>

        <h1 class="challenge-panel__title">{{ pageTitle }}</h1>
        <p class="challenge-panel__subtitle">{{ pageSubtitle }}</p>

        <div class="challenge-card">
          <div class="challenge-card__label">登录账号</div>
          <div class="challenge-card__value">{{ accountLabel }}</div>
          <p class="challenge-card__hint">
            这一步由 Fortify 挑战流程处理，验证通过后才会建立最终登录态。
          </p>
        </div>

        <div class="challenge-switcher">
          <button
            class="challenge-switcher__item"
            :class="{ 'challenge-switcher__item--active': mode === 'code' }"
            type="button"
            @click="switchMode('code')"
          >
            动态码
          </button>
          <button
            class="challenge-switcher__item"
            :class="{ 'challenge-switcher__item--active': mode === 'recovery' }"
            type="button"
            @click="switchMode('recovery')"
          >
            恢复代码
          </button>
        </div>

        <div class="challenge-form">
          <a-input
            v-if="mode === 'code'"
            v-model="code"
            size="large"
            class="challenge-input"
            placeholder="请输入 6 位验证码"
          >
            <template #prefix>
              <IconLock />
            </template>
          </a-input>

          <a-textarea
            v-else
            v-model="recoveryCode"
            :auto-size="{ minRows: 3, maxRows: 4 }"
            class="challenge-textarea"
            placeholder="请输入恢复代码"
          />

          <a-button
            type="primary"
            size="large"
            long
            class="challenge-submit"
            :loading="loading"
            @click="handleSubmit"
          >
            {{ submitLabel }}
          </a-button>

          <button class="text-link" type="button" @click="handleBackToLogin">
            返回登录
          </button>
        </div>
      </section>
    </main>

    <footer class="challenge-footer">Copyright © 2023-2026 TyCDN LTD.</footer>
  </div>
</template>

<style scoped>
.challenge-page {
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

.challenge-page__bg {
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

.challenge-topbar {
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

.challenge-main {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: calc(100vh - 140px);
  padding: 40px 20px 80px;
}

.challenge-panel {
  width: 100%;
  max-width: 420px;
}

.challenge-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 30px;
  padding: 0 12px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  background: rgba(255, 255, 255, 0.08);
  color: rgba(255, 255, 255, 0.82);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.challenge-panel__title {
  margin: 18px 0 10px;
  font-size: 32px;
  line-height: 1.2;
  font-weight: 800;
  color: rgba(255, 255, 255, 0.96);
}

.challenge-panel__subtitle {
  margin: 0 0 24px;
  font-size: 15px;
  line-height: 1.7;
  color: rgba(255, 255, 255, 0.54);
}

.challenge-card {
  padding: 18px 18px 16px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.06);
  box-shadow: 0 24px 60px rgba(8, 12, 22, 0.28);
}

.challenge-card__label {
  margin-bottom: 10px;
  color: rgba(255, 255, 255, 0.48);
  font-size: 13px;
}

.challenge-card__value {
  font-size: 20px;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.96);
  word-break: break-word;
}

.challenge-card__hint {
  margin: 10px 0 0;
  font-size: 14px;
  line-height: 1.6;
  color: rgba(255, 255, 255, 0.46);
}

.challenge-switcher {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
  margin-top: 18px;
}

.challenge-switcher__item {
  height: 42px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.05);
  color: rgba(255, 255, 255, 0.72);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}

.challenge-switcher__item--active {
  border-color: rgba(72, 120, 255, 0.35);
  background: rgba(72, 120, 255, 0.18);
  color: #fff;
}

.challenge-form {
  display: grid;
  gap: 14px;
  margin-top: 18px;
}

.challenge-input,
.challenge-textarea {
  width: 100%;
}

.challenge-form > .challenge-input,
.challenge-form > .challenge-textarea {
  opacity: 0;
  transform: translateY(14px);
  animation: auth-field-fade-in 0.48s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}

:deep(.challenge-input.arco-input-wrapper),
:deep(.challenge-textarea.arco-textarea-wrapper) {
  border: 1px solid rgba(255, 255, 255, 0.08) !important;
  border-radius: 10px !important;
  background: rgba(255, 255, 255, 0.08) !important;
  box-shadow: none !important;
}

:deep(.challenge-input .arco-input),
:deep(.challenge-textarea .arco-textarea) {
  color: rgba(255, 255, 255, 0.92) !important;
  background: transparent !important;
}

:deep(.challenge-input .arco-input::placeholder),
:deep(.challenge-textarea .arco-textarea::placeholder) {
  color: rgba(255, 255, 255, 0.34) !important;
}

:deep(.challenge-input .arco-input-prefix) {
  color: rgba(255, 255, 255, 0.34) !important;
}

.challenge-submit {
  height: 46px;
  border-radius: 10px;
  font-size: 16px;
  font-weight: 700;
}

.text-link {
  border: 0;
  background: transparent;
  padding: 0;
  color: #6ea8ff;
  font-size: 14px;
  cursor: pointer;
}

.challenge-footer {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 18px;
  z-index: 2;
  text-align: center;
  font-size: 12px;
  color: rgba(255, 255, 255, 0.3);
}

@keyframes auth-field-fade-in {
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
  .challenge-form > .challenge-input,
  .challenge-form > .challenge-textarea {
    opacity: 1;
    transform: none;
    animation: none;
  }
}
</style>
