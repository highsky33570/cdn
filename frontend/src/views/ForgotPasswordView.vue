<script setup>
import { useTheme } from '../composables/useTheme'
import ThemeToggle from '../components/ThemeToggle.vue'
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Message } from '@arco-design/web-vue'
import { IconEmail } from '@arco-design/web-vue/es/icon'
import { requestPasswordResetLink } from '../api/auth'

const { theme } = useTheme()
const router = useRouter()

const recaptchaSiteKey = import.meta.env.VITE_RECAPTCHA_SITE_KEY || ''

const form = ref({ email: '' })
const loading = ref(false)
const emailTouched = ref(false)
const sent = ref(false)
const recaptchaToken = ref('')
const recaptchaWidgetId = ref(null)

watch(
  theme,
  () => {
    recaptchaToken.value = ''
    recaptchaWidgetId.value = null
    onRecaptchaLoad()
  },
  { flush: 'post' }
)

const emailError = computed(() => {
  const value = form.value.email.trim()
  if (!emailTouched.value || !value) return ''
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) ? '' : '请输入正确的邮箱格式'
})

const validateEmail = () => {
  emailTouched.value = true
}

function onRecaptchaLoad() {
  if (!recaptchaSiteKey || !window.grecaptcha?.render) return
  recaptchaWidgetId.value = window.grecaptcha.render('recaptcha-container', {
    sitekey: recaptchaSiteKey,
    theme: theme.value,
    callback: (token) => {
      recaptchaToken.value = token
    },
    'expired-callback': () => {
      recaptchaToken.value = ''
    },
    'error-callback': () => {
      recaptchaToken.value = ''
    }
  })
}

function resetRecaptcha() {
  recaptchaToken.value = ''
  if (window.grecaptcha && recaptchaWidgetId.value !== null) {
    window.grecaptcha.reset(recaptchaWidgetId.value)
  }
}

onMounted(() => {
  if (!recaptchaSiteKey) return
  if (window.grecaptcha && window.grecaptcha.render) {
    onRecaptchaLoad()
    return
  }
  window.__onRecaptchaLoad = onRecaptchaLoad
  const script = document.createElement('script')
  script.src = 'https://www.google.com/recaptcha/api.js?onload=__onRecaptchaLoad&render=explicit'
  script.async = true
  script.defer = true
  document.head.appendChild(script)
})

const handleSubmit = async () => {
  emailTouched.value = true
  const email = form.value.email.trim()

  if (!email) {
    Message.warning('请输入邮箱')
    return
  }
  if (emailError.value) {
    Message.warning('请先填写正确的邮箱地址')
    return
  }
  if (recaptchaSiteKey && !recaptchaToken.value) {
    Message.warning('请完成人机验证')
    return
  }

  try {
    loading.value = true
    const res = await requestPasswordResetLink({
      email,
      captcha: recaptchaToken.value || 'no-captcha'
    })
    sent.value = true
    Message.success(res.status || '重置密码邮件已发送，请检查邮箱')
  } catch (error) {
    Message.error(error instanceof Error ? error.message : '发送重置密码邮件失败')
  } finally {
    loading.value = false
    resetRecaptcha()
  }
}

const backToLogin = () => {
  router.push('/login')
}
</script>

<template>
  <div class="forgot-page">
    <div class="forgot-page__bg"></div>

    <header class="forgot-topbar">
      <RouterLink to="/" class="brand">
        <img src="/favicon.svg" alt="logo" class="brand__logo" />
        <span class="brand__name">TyCDN</span>
      </RouterLink>

      <ThemeToggle />
    </header>

    <main class="forgot-main">
      <section class="forgot-panel">
        <h1 class="forgot-panel__title">找回密码</h1>
        <p class="forgot-panel__subtitle">输入注册邮箱，我们会向你的邮箱发送一封重置密码邮件。</p>

        <div v-if="sent" class="forgot-notice">
          重置邮件已发送。如果几分钟内没有收到，请检查垃圾邮件箱，或稍后再试。
        </div>

        <div class="forgot-form">
          <div class="forgot-field">
            <a-input
              v-model="form.email"
              size="large"
              class="forgot-input"
              placeholder="请输入邮箱"
              @blur="validateEmail"
              @input="emailTouched && validateEmail()"
            >
              <template #prefix>
                <IconEmail />
              </template>
            </a-input>

            <div v-if="emailError" class="forgot-error">
              {{ emailError }}
            </div>
          </div>

          <div v-if="recaptchaSiteKey" class="forgot-field forgot-field--captcha">
            <div id="recaptcha-container" :key="theme"></div>
          </div>

          <a-button
            type="primary"
            size="large"
            long
            class="forgot-submit"
            :loading="loading"
            @click="handleSubmit"
          >
            发送重置邮件
          </a-button>

          <button class="text-link" type="button" @click="backToLogin">返回登录</button>
        </div>
      </section>
    </main>

    <footer class="forgot-footer">Copyright © 2023-2026 TyCDN LTD.</footer>
  </div>
</template>

<style scoped>
.forgot-page {
  position: relative;
  min-height: 100vh;
  overflow: hidden;
  background:
    radial-gradient(circle at 50% 10%, var(--accent-glow), transparent 26%),
    linear-gradient(180deg, var(--bg-grad-1), var(--bg-grad-2));
  color: var(--text-strong);
}

.forgot-page__bg {
  position: absolute;
  inset: 0;
  pointer-events: none;
  background: linear-gradient(
    90deg,
    var(--accent-glow) 0%,
    transparent 35%,
    transparent 65%,
    var(--accent-glow) 100%
  );
}

.forgot-topbar {
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
  color: var(--text-strong);
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

.forgot-main {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: calc(100vh - 140px);
  padding: 40px 20px 80px;
}

.forgot-panel {
  width: 100%;
  max-width: 380px;
}

.forgot-panel__title {
  margin: 0 0 10px;
  font-size: 32px;
  line-height: 1.2;
  font-weight: 800;
  color: var(--text-strong);
}

.forgot-panel__subtitle {
  margin: 0 0 24px;
  font-size: 15px;
  line-height: 1.7;
  color: var(--text-3);
}

.forgot-notice {
  margin-bottom: 18px;
  padding: 14px 16px;
  border: 1px solid var(--accent-border);
  border-radius: 12px;
  background: var(--accent-glow);
  color: var(--text);
  font-size: 14px;
  line-height: 1.6;
}

.forgot-form {
  width: 100%;
}

.forgot-field {
  opacity: 0;
  transform: translateY(14px);
  animation: auth-field-fade-in 0.48s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}

.forgot-field--captcha {
  margin-top: 18px;
  animation-delay: 0.08s;
}

.forgot-input {
  width: 100%;
}

:deep(.forgot-input.arco-input-wrapper),
:deep(.forgot-input > .arco-input-wrapper),
:deep(.forgot-input .arco-input-wrapper) {
  display: flex;
  align-items: center;
  width: 100%;
  min-height: 56px;
  padding: 0 16px;
  border: 1px solid var(--border) !important;
  border-radius: 8px !important;
  background: var(--surface) !important;
  box-shadow: none !important;
}

:deep(.forgot-input.arco-input-wrapper:hover),
:deep(.forgot-input > .arco-input-wrapper:hover),
:deep(.forgot-input .arco-input-wrapper:hover) {
  border-color: var(--accent-border) !important;
  background: var(--surface) !important;
}

:deep(.forgot-input.arco-input-wrapper.arco-input-focus),
:deep(.forgot-input > .arco-input-wrapper.arco-input-focus),
:deep(.forgot-input .arco-input-wrapper.arco-input-focus) {
  border-color: var(--accent-border) !important;
  background: var(--surface) !important;
  box-shadow: 0 0 0 2px var(--accent-glow) !important;
}

:deep(.forgot-input .arco-input-prefix) {
  margin-right: 12px;
  color: var(--text-3) !important;
}

:deep(.forgot-input .arco-input),
:deep(.forgot-input input.arco-input) {
  flex: 1;
  min-width: 0;
  height: auto !important;
  line-height: 1.4 !important;
  padding: 0 !important;
  border: 0 !important;
  font-size: 16px;
  color: var(--text-strong) !important;
  background: transparent !important;
}

:deep(.forgot-input .arco-input::placeholder),
:deep(.forgot-input input.arco-input::placeholder) {
  color: var(--text-3) !important;
}

.forgot-error {
  margin-top: 8px;
  padding-left: 2px;
  font-size: 13px;
  line-height: 1.4;
  color: var(--danger);
}

.forgot-submit {
  margin-top: 22px;
  height: 44px;
  border-radius: 6px;
  font-size: 16px;
  font-weight: 700;
}

.text-link {
  display: block;
  margin: 18px auto 0;
  border: 0;
  background: transparent;
  padding: 0;
  color: var(--accent-2);
  font-size: 14px;
  cursor: pointer;
}

.forgot-footer {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 18px;
  z-index: 2;
  text-align: center;
  font-size: 12px;
  color: var(--text-3);
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
  .forgot-field {
    opacity: 1;
    transform: none;
    animation: none;
  }
}
</style>
