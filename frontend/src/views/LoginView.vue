<script setup>
import { useTheme } from '../composables/useTheme'
import ThemeToggle from '../components/ThemeToggle.vue'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Message } from '@arco-design/web-vue'
import { IconLock, IconUser } from '@arco-design/web-vue/es/icon'
import { login } from '../api/auth'
import { resolvePostAuthRedirect } from '../config/runtime'

const { theme } = useTheme()
const route = useRoute()
const router = useRouter()

const recaptchaSiteKey = import.meta.env.VITE_RECAPTCHA_SITE_KEY || ''

const form = ref({
  account: '',
  password: ''
})

const loading = ref(false)
const showPassword = ref(true)
const accountTouched = ref(false)
const passwordTouched = ref(false)
const recaptchaToken = ref('')
const recaptchaWidgetId = ref(null)

// reCAPTCHA resolves its skin at render time, so remount it after a theme change.
watch(
  theme,
  () => {
    recaptchaToken.value = ''
    recaptchaWidgetId.value = null
    onRecaptchaLoad()
  },
  { flush: 'post' }
)

const passwordError = computed(() => {
  const value = form.value.password
  if (!passwordTouched.value || !value) return ''

  return value.length >= 6 ? '' : '密码长度至少 6 位'
})

const redirectTarget = computed(() =>
  typeof route.query.redirect === 'string' ? route.query.redirect : ''
)

const validateAccount = () => {
  accountTouched.value = true
}

const validatePassword = () => {
  passwordTouched.value = true
}

function onRecaptchaLoad() {
  if (!recaptchaSiteKey || !window.grecaptcha?.render) return
  recaptchaWidgetId.value = window.grecaptcha.render('login-recaptcha', {
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

const handleLogin = async () => {
  if (loading.value) return

  accountTouched.value = true
  passwordTouched.value = true

  const account = form.value.account.trim()

  if (!account) {
    Message.warning('请输入用户名或邮箱')
    return
  }

  if (!form.value.password) {
    Message.warning('请输入密码')
    return
  }

  if (passwordError.value) return

  if (recaptchaSiteKey && !recaptchaToken.value) {
    Message.warning('请完成人机验证')
    return
  }

  try {
    loading.value = true
    const res = await login({
      account,
      password: form.value.password,
      captcha: recaptchaToken.value || undefined,
      redirect: redirectTarget.value || undefined
    })

    if (res.data?.two_factor) {
      Message.info('请输入两步验证码继续登录')
      router.push({
        path: '/two-factor-challenge',
        query: {
          account,
          ...(typeof route.query.redirect === 'string' ? { redirect: route.query.redirect } : {})
        }
      })
      return
    }

    if (res.data?.email_verified) {
      Message.success('登录成功')
      window.location.href = res.data?.redirect || resolvePostAuthRedirect(redirectTarget.value)
      return
    }

    Message.warning('请先完成邮箱验证')
    router.push({
      path: '/verify-email',
      query: {
        ...(res.data?.user?.email ? { email: res.data.user.email } : {}),
        ...(redirectTarget.value ? { redirect: redirectTarget.value } : {})
      }
    })
  } catch (error) {
    Message.error(error instanceof Error ? error.message : '登录失败')
  } finally {
    loading.value = false
    resetRecaptcha()
  }
}

const goRegister = () => {
  router.push({
    path: '/register',
    query: redirectTarget.value ? { redirect: redirectTarget.value } : {}
  })
}

const goForgotPassword = () => {
  router.push('/forgot-password')
}
</script>

<template>
  <div data-public-style class="public-login-view login-page">
    <div class="login-page__bg"></div>

    <header class="login-topbar">
      <RouterLink to="/" class="brand">
        <img src="/favicon.svg" alt="logo" class="brand__logo" />
        <span class="brand__name">TyCDN</span>
      </RouterLink>

      <ThemeToggle />
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

            <div v-if="accountTouched && !form.account.trim()" class="login-error">
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
            <button class="text-link text-link--inline" type="button" @click="goForgotPassword">
              忘记密码？
            </button>
          </div>

          <div v-if="recaptchaSiteKey" class="login-field login-field--captcha">
            <div id="login-recaptcha" :key="theme"></div>
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
            <button class="text-link" type="button" @click="goRegister">立即注册</button>
          </div>
        </form>
      </section>
    </main>

    <footer class="login-footer">Copyright © 2023-2026 TyCDN LTD.</footer>
  </div>
</template>
