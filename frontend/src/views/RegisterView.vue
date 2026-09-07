<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { Message } from "@arco-design/web-vue";
import {
  IconEmail,
  IconUser,
  IconLock,
  IconLanguage,
} from "@arco-design/web-vue/es/icon";
import { register } from "../api/auth";

const route = useRoute();
const router = useRouter();

const recaptchaSiteKey = import.meta.env.VITE_RECAPTCHA_SITE_KEY || "";

const form = ref({
  username: "",
  email: "",
  password: "",
  confirmPassword: "",
  agree: true,
});

const loading = ref(false);
const showPassword = ref(true);
const showConfirmPassword = ref(true);
const recaptchaToken = ref("");
const recaptchaWidgetId = ref(null);

const redirectTarget = computed(() =>
  typeof route.query.redirect === "string" ? route.query.redirect : "",
);

const usernameTouched = ref(false);
const emailTouched = ref(false);
const passwordTouched = ref(false);
const confirmTouched = ref(false);

const usernameError = computed(() => {
  const value = form.value.username.trim();
  if (!usernameTouched.value || !value) return "";
  return /^[A-Za-z0-9\u4e00-\u9fff]+$/.test(value)
    ? ""
    : "用户名只允许中文、英文字符及数字";
});

const emailError = computed(() => {
  const value = form.value.email.trim();
  if (!emailTouched.value || !value) return "";
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) ? "" : "请输入正确的邮箱格式";
});

const passwordError = computed(() => {
  const value = form.value.password;
  if (!passwordTouched.value || !value) return "";
  if (value.length < 8) return "密码长度至少 8 位";
  if (!/(?=.*[a-zA-Z])(?=.*[0-9])/.test(value)) return "密码必须包含字母和数字";
  return "";
});

const confirmError = computed(() => {
  const value = form.value.confirmPassword;
  if (!confirmTouched.value || !value) return "";
  return value === form.value.password ? "" : "两次输入的密码不一致";
});

const validateUsername = () => {
  usernameTouched.value = true;
};

const validateEmail = () => {
  emailTouched.value = true;
};

const validatePassword = () => {
  passwordTouched.value = true;
};

const validateConfirmPassword = () => {
  confirmTouched.value = true;
};

function onRecaptchaLoad() {
  if (!recaptchaSiteKey || !window.grecaptcha) return;
  recaptchaWidgetId.value = window.grecaptcha.render("register-recaptcha", {
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

const handleRegister = async () => {
  usernameTouched.value = true;
  emailTouched.value = true;
  passwordTouched.value = true;
  confirmTouched.value = true;

  const username = form.value.username.trim();
  const email = form.value.email.trim();

  if (!username) {
    Message.warning("请输入用户名");
    return;
  }

  if (!email) {
    Message.warning("请输入邮箱");
    return;
  }

  if (
    usernameError.value ||
    emailError.value ||
    passwordError.value ||
    confirmError.value
  ) {
    Message.warning("请先完善注册信息");
    return;
  }

  if (!form.value.agree) {
    Message.warning("请先同意服务协议");
    return;
  }

  if (recaptchaSiteKey && !recaptchaToken.value) {
    Message.warning("请完成人机验证");
    return;
  }

  try {
    loading.value = true;

    await register({
      username,
      email,
      password: form.value.password,
      confirmPassword: form.value.confirmPassword,
      captcha: recaptchaToken.value || undefined,
    });

    Message.success("注册成功，请查收邮箱完成验证");
    router.push({
      path: "/verify-email",
      query: {
        email,
        ...(redirectTarget.value ? { redirect: redirectTarget.value } : {}),
      },
    });
  } catch (err) {
    Message.error(err.message || "注册失败");
  } finally {
    loading.value = false;
    resetRecaptcha();
  }
};

const goLogin = () => {
  router.push({
    path: "/login",
    query: redirectTarget.value ? { redirect: redirectTarget.value } : {},
  });
};

</script>

<template>
  <div class="register-page">
    <div class="register-page__bg"></div>

    <header class="register-topbar">
      <RouterLink to="/" class="brand">
        <img src="/favicon.svg" alt="logo" class="brand__logo" />
        <span class="brand__name">TyCDN</span>
      </RouterLink>

      <button class="lang-btn" type="button" aria-label="language">
        <IconLanguage />
      </button>
    </header>

    <main class="register-main">
      <section class="register-panel">
        <h1 class="register-panel__title">创建您的账户</h1>
        <p class="register-panel__subtitle">
          欢迎加入，请填写以下信息完成注册。
        </p>

        <div class="register-form">
          <div class="register-field">
            <a-input
              v-model="form.username"
              size="large"
              class="register-input"
              placeholder="请输入用户名"
              @blur="validateUsername"
              @input="usernameTouched && validateUsername()"
            >
              <template #prefix>
                <IconUser />
              </template>
            </a-input>

            <div v-if="usernameError" class="register-error">
              {{ usernameError }}
            </div>
          </div>

          <div class="register-field">
            <a-input
              v-model="form.email"
              size="large"
              class="register-input"
              placeholder="请输入邮箱"
              @blur="validateEmail"
              @input="emailTouched && validateEmail()"
            >
              <template #prefix>
                <IconEmail />
              </template>
            </a-input>

            <div v-if="emailError" class="register-error">
              {{ emailError }}
            </div>
          </div>

          <div class="register-field">
            <a-input-password
              v-model="form.password"
              size="large"
              class="register-input"
              :default-visibility="true"
              placeholder="请输入密码"
              :visibility="showPassword"
              @visibility-change="showPassword = $event"
              @blur="validatePassword"
              @input="passwordTouched && validatePassword()"
            >
              <template #prefix>
                <IconLock />
              </template>
            </a-input-password>

            <div v-if="passwordError" class="register-error">
              {{ passwordError }}
            </div>
          </div>

          <div class="register-field">
            <a-input-password
              v-model="form.confirmPassword"
              size="large"
              class="register-input"
              :default-visibility="true"
              placeholder="请再次输入密码"
              :visibility="showConfirmPassword"
              @visibility-change="showConfirmPassword = $event"
              @blur="validateConfirmPassword"
              @input="confirmTouched && validateConfirmPassword()"
            >
              <template #prefix>
                <IconLock />
              </template>
            </a-input-password>

            <div v-if="confirmError" class="register-error">
              {{ confirmError }}
            </div>
          </div>

          <div class="register-row">
            <label class="agree-wrap">
              <a-checkbox v-model="form.agree">
                我已阅读并同意服务协议
              </a-checkbox>
            </label>
          </div>

          <div v-if="recaptchaSiteKey" class="register-field register-field--captcha">
            <div id="register-recaptcha"></div>
          </div>

          <a-button
            type="primary"
            size="large"
            long
            class="register-submit"
            :loading="loading"
            @click="handleRegister"
          >
            注册
          </a-button>

          <div class="login-row">
            <span>已有帐号?</span>
            <button class="text-link" type="button" @click="goLogin">
              立即登录
            </button>
          </div>
        </div>
      </section>
    </main>

    <footer class="register-footer">Copyright © 2023-2026 TyCDN LTD.</footer>
  </div>
</template>

<style scoped>
.register-page {
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

.register-page__bg {
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

.register-topbar {
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

.register-main {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: calc(100vh - 140px);
  padding: 40px 20px 80px;
}

.register-panel {
  width: 100%;
  max-width: 360px;
}

.register-panel__title {
  margin: 0 0 10px;
  font-size: 32px;
  line-height: 1.2;
  font-weight: 800;
  color: rgba(255, 255, 255, 0.96);
}

.register-panel__subtitle {
  margin: 0 0 28px;
  font-size: 15px;
  line-height: 1.6;
  color: rgba(255, 255, 255, 0.44);
}

.register-form {
  width: 100%;
}

.register-field + .register-field {
  margin-top: 20px;
}

.register-field {
  opacity: 0;
  transform: translateY(14px);
  animation: auth-field-fade-in 0.48s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}

.register-field:nth-of-type(2) {
  animation-delay: 0.08s;
}

.register-field:nth-of-type(3) {
  animation-delay: 0.16s;
}

.register-field:nth-of-type(4) {
  animation-delay: 0.24s;
}

.register-field--captcha {
  margin-top: 18px;
}

.register-input {
  width: 100%;
}

:deep(.register-input.arco-input-wrapper),
:deep(.register-input > .arco-input-wrapper),
:deep(.register-input .arco-input-wrapper) {
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

:deep(.register-input.arco-input-wrapper:hover),
:deep(.register-input > .arco-input-wrapper:hover),
:deep(.register-input .arco-input-wrapper:hover) {
  border-color: rgba(63, 110, 255, 0.22) !important;
  background: rgba(255, 255, 255, 0.1) !important;
}

:deep(.register-input.arco-input-wrapper.arco-input-focus),
:deep(.register-input > .arco-input-wrapper.arco-input-focus),
:deep(.register-input .arco-input-wrapper.arco-input-focus) {
  border-color: rgba(63, 110, 255, 0.34) !important;
  background: rgba(255, 255, 255, 0.1) !important;
  box-shadow: 0 0 0 2px rgba(39, 98, 255, 0.08) !important;
}

:deep(.register-input .arco-input-prefix),
:deep(.register-input .arco-input-suffix) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  height: 100%;
  color: rgba(255, 255, 255, 0.34) !important;
}

:deep(.register-input .arco-input-prefix) {
  margin-right: 12px;
}

:deep(.register-input .arco-input-suffix) {
  margin-left: 12px;
}

:deep(.register-input .arco-icon) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  color: rgba(255, 255, 255, 0.34) !important;
}

:deep(.register-input .arco-input),
:deep(.register-input input.arco-input) {
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

:deep(.register-input .arco-input::placeholder),
:deep(.register-input input.arco-input::placeholder) {
  white-space: nowrap !important;
  color: rgba(255, 255, 255, 0.32) !important;
}

:deep(.register-input input.arco-input::-ms-reveal),
:deep(.register-input input.arco-input::-ms-clear) {
  display: none;
}

:deep(.register-input .arco-input-password-icon) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  color: rgba(255, 255, 255, 0.34) !important;
}

.register-error {
  margin-top: 8px;
  padding-left: 2px;
  font-size: 13px;
  line-height: 1.4;
  color: #ff6b6b;
}

.register-row {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  margin: 18px 0 22px;
}

.agree-wrap {
  color: rgba(255, 255, 255, 0.68);
}

:deep(.agree-wrap .arco-checkbox-label) {
  color: rgba(255, 255, 255, 0.62);
}

.text-link {
  border: 0;
  background: transparent;
  padding: 0;
  color: #2468ff;
  font-size: 14px;
  cursor: pointer;
}

.register-submit {
  height: 44px;
  border-radius: 6px;
  font-size: 16px;
  font-weight: 700;
}

.login-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: 24px;
  color: rgba(255, 255, 255, 0.5);
  font-size: 14px;
}

.register-footer {
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
  .register-field {
    opacity: 1;
    transform: none;
    animation: none;
  }
}
</style>
