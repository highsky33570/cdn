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
  <div data-public-style class="public-forgot-password-view forgot-page">
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
