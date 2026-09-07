<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { Message } from "@arco-design/web-vue";
import { IconLanguage, IconLock, IconUser } from "@arco-design/web-vue/es/icon";
import { login } from "../api/auth";
import { resolvePostAuthRedirect } from "../config/runtime";

const route = useRoute();
const router = useRouter();

const recaptchaSiteKey = import.meta.env.VITE_RECAPTCHA_SITE_KEY || "";

const form = ref({
  account: "",
  password: "",
});

const loading = ref(false);
const showPassword = ref(true);
const accountTouched = ref(false);
const passwordTouched = ref(false);
const recaptchaToken = ref("");
const recaptchaWidgetId = ref(null);

const passwordError = computed(() => {
  const value = form.value.password;
  if (!passwordTouched.value || !value) return "";

  return value.length >= 6 ? "" : "密码长度至少 6 位";
});

const redirectTarget = computed(() =>
  typeof route.query.redirect === "string" ? route.query.redirect : "",
);

const validateAccount = () => {
  accountTouched.value = true;
};

const validatePassword = () => {
  passwordTouched.value = true;
};

function onRecaptchaLoad() {
  if (!recaptchaSiteKey || !window.grecaptcha) return;
  recaptchaWidgetId.value = window.grecaptcha.render("login-recaptcha", {
    sitekey: recaptchaSiteKey,
    theme: "dark",
    callback: (token) => { recaptchaToken.value = token; },
    "expired-callback": () => { recaptchaToken.value = ""; },
    "error-callback": () => { recaptchaToken.value = ""; },
  });
}

function resetRecaptcha() {
  recaptchaToken.value = "";
  if (window.grecaptcha && recaptchaWidgetId.value !== null) {
    window.grecaptcha.reset(recaptchaWidgetId.value);
  }
}

onMounted(() => {
  if (!recaptchaSiteKey) return;
  if (window.grecaptcha && window.grecaptcha.render) {
    onRecaptchaLoad();
    return;
  }
  window.__onRecaptchaLoad = onRecaptchaLoad;
  const script = document.createElement("script");
  script.src = "https://www.google.com/recaptcha/api.js?onload=__onRecaptchaLoad&render=explicit";
  script.async = true;
  script.defer = true;
  document.head.appendChild(script);
});

const handleLogin = async () => {
  if (loading.value) return;

  accountTouched.value = true;
  passwordTouched.value = true;

  const account = form.value.account.trim();

  if (!account) {
    Message.warning("请输入用户名或邮箱");
    return;
  }

  if (!form.value.password) {
    Message.warning("请输入密码");
    return;
  }

  if (passwordError.value) return;

  if (recaptchaSiteKey && !recaptchaToken.value) {
    Message.warning("请完成人机验证");
    return;
  }

  try {
    loading.value = true;
    const res = await login({
      account,
      password: form.value.password,
      captcha: recaptchaToken.value || undefined,
      redirect: redirectTarget.value || undefined,
    });

    if (res.data?.two_factor) {
      Message.info("请输入两步验证码继续登录");
      router.push({
        path: "/two-factor-challenge",
        query: {
          account,
          ...(typeof route.query.redirect === "string"
            ? { redirect: route.query.redirect }
            : {}),
        },
      });
      return;
    }

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
    Message.error(error instanceof Error ? error.message : "登录失败");
  } finally {
    loading.value = false;
    resetRecaptcha();
  }
};

const goRegister = () => {
  router.push({
    path: "/register",
    query: redirectTarget.value ? { redirect: redirectTarget.value } : {},
  });
};

const goForgotPassword = () => {
  router.push("/forgot-password");
};

</script>

<template>
  <div class="login-page">
    <div class="login-page__bg"></div>

    <header class="login-topbar">
      <RouterLink to="/" class="brand">
        <img src="/favicon.svg" alt="logo" class="brand__logo" />
        <span class="brand__name">TyCDN</span>
      </RouterLink>

      <button class="lang-btn" type="button" aria-label="language">
        <IconLanguage />
      </button>
    </header>

    <main class="login-main">
      <section class="login-panel">
        <h1 class="login-panel__title">欢迎回来</h1>
        <p class="login-panel__subtitle">请输入您的账户信息以登录控制面板。</p>

        <form class="login-form" @submit.prevent="handleLogin">
          <div class="login-field">
            <a-input
              v-model="form.account"
              size="large"
              class="login-input"
              placeholder="请输入用户名或邮箱"
              @blur="validateAccount"
              @input="accountTouched && validateAccount()"
              @press-enter="handleLogin"
            >
              <template #prefix>
                <IconUser />
              </template>
            </a-input>

            <div
              v-if="accountTouched && !form.account.trim()"
              class="login-error"
            >
              请输入用户名或邮箱
            </div>
          </div>

          <div class="login-field">
            <a-input-password
              v-model="form.password"
              size="large"
              class="login-input"
              :default-visibility="true"
              placeholder="请输入密码"
              :visibility="showPassword"
              @visibility-change="showPassword = $event"
              @blur="validatePassword"
              @input="passwordTouched && validatePassword()"
              @press-enter="handleLogin"
            >
              <template #prefix>
                <IconLock />
              </template>
            </a-input-password>

            <div v-if="passwordError" class="login-error">
              {{ passwordError }}
            </div>
          </div>

          <div class="login-actions">
            <button
              class="text-link text-link--inline"
              type="button"
              @click="goForgotPassword"
            >
              忘记密码？
            </button>
          </div>

          <div v-if="recaptchaSiteKey" class="login-field login-field--captcha">
            <div id="login-recaptcha"></div>
          </div>

          <a-button
            type="primary"
            size="large"
            long
            class="login-submit"
            :loading="loading"
            html-type="submit"
          >
            登录
          </a-button>

          <div class="register-row">
            <span>没有账号?</span>
            <button class="text-link" type="button" @click="goRegister">
              立即注册
            </button>
          </div>
        </form>
      </section>
    </main>

    <footer class="login-footer">Copyright © 2023-2026 TyCDN LTD.</footer>
  </div>
</template>

<style scoped>
.login-page {
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

.login-page__bg {
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

.login-topbar {
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

.login-main {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: calc(100vh - 140px);
  padding: 40px 20px 80px;
}

.login-panel {
  width: 100%;
  max-width: 360px;
}

.login-panel__title {
  margin: 0 0 10px;
  font-size: 32px;
  line-height: 1.2;
  font-weight: 800;
  color: rgba(255, 255, 255, 0.96);
}

.login-panel__subtitle {
  margin: 0 0 28px;
  font-size: 15px;
  line-height: 1.6;
  color: rgba(255, 255, 255, 0.44);
}

.login-form {
  width: 100%;
}

.login-field + .login-field {
  margin-top: 20px;
}

.login-field {
  opacity: 0;
  transform: translateY(14px);
  animation: auth-field-fade-in 0.48s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}

.login-field:nth-of-type(2) {
  animation-delay: 0.08s;
}

.login-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 12px;
}

.login-field--captcha {
  margin-top: 18px;
}

.login-input {
  width: 100%;
}

:deep(.login-input.arco-input-wrapper),
:deep(.login-input > .arco-input-wrapper),
:deep(.login-input .arco-input-wrapper) {
  display: flex;
  align-items: center;
  width: 100%;
  min-height: 56px;
  padding: 0 16px;
  border: 1px solid rgba(255, 255, 255, 0.06) !important;
  border-radius: 8px !important;
  background: rgba(255, 255, 255, 0.08) !important;
  box-shadow: none !important;
}

:deep(.login-input.arco-input-wrapper:hover),
:deep(.login-input > .arco-input-wrapper:hover),
:deep(.login-input .arco-input-wrapper:hover) {
  border-color: rgba(63, 110, 255, 0.22) !important;
  background: rgba(255, 255, 255, 0.1) !important;
}

:deep(.login-input.arco-input-wrapper.arco-input-focus),
:deep(.login-input > .arco-input-wrapper.arco-input-focus),
:deep(.login-input .arco-input-wrapper.arco-input-focus) {
  border-color: rgba(63, 110, 255, 0.34) !important;
  background: rgba(255, 255, 255, 0.1) !important;
  box-shadow: 0 0 0 2px rgba(39, 98, 255, 0.08) !important;
}

:deep(.login-input .arco-input-prefix),
:deep(.login-input .arco-input-suffix) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  height: 100%;
  color: rgba(255, 255, 255, 0.34) !important;
}

:deep(.login-input .arco-input-prefix) {
  margin-right: 12px;
}

:deep(.login-input .arco-input-suffix) {
  margin-left: 12px;
}

:deep(.login-input .arco-icon) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  color: rgba(255, 255, 255, 0.34) !important;
}

:deep(.login-input .arco-input),
:deep(.login-input input.arco-input) {
  flex: 1;
  min-width: 0;
  height: auto !important;
  line-height: 1.4 !important;
  padding: 0 !important;
  border: 0 !important;
  font-size: 16px;
  white-space: nowrap !important;
  color: rgba(255, 255, 255, 0.92) !important;
  background: transparent !important;
}

:deep(.login-input .arco-input::placeholder),
:deep(.login-input input.arco-input::placeholder) {
  white-space: nowrap !important;
  color: rgba(255, 255, 255, 0.32) !important;
}

:deep(.login-input input.arco-input::-ms-reveal),
:deep(.login-input input.arco-input::-ms-clear) {
  display: none;
}

:deep(.login-input .arco-input-password-icon) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  color: rgba(255, 255, 255, 0.34) !important;
}

.login-error {
  margin-top: 8px;
  padding-left: 2px;
  font-size: 13px;
  line-height: 1.4;
  color: #ff6b6b;
}

.login-submit {
  margin-top: 22px;
  height: 44px;
  border-radius: 6px;
  font-size: 16px;
  font-weight: 700;
}

.register-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: 24px;
  color: rgba(255, 255, 255, 0.5);
  font-size: 14px;
}

.text-link {
  border: 0;
  background: transparent;
  padding: 0;
  color: #2468ff;
  font-size: 14px;
  cursor: pointer;
}

.text-link--inline {
  color: #6ea8ff;
}

.login-footer {
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
  .login-field {
    opacity: 1;
    transform: none;
    animation: none;
  }
}
</style>
