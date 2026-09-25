<script setup>
import { useTheme } from '../composables/useTheme'
import ThemeToggle from '../components/ThemeToggle.vue'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Message } from '@arco-design/web-vue'
import { IconEmail, IconUser, IconLock } from '@arco-design/web-vue/es/icon'
import { register } from '../api/auth'

const { theme } = useTheme()
const route = useRoute()
const router = useRouter()

const recaptchaSiteKey = import.meta.env.VITE_RECAPTCHA_SITE_KEY || ''

const form = ref({
  username: '',
  email: '',
  password: '',
  confirmPassword: '',
  agree: true
})

const loading = ref(false)
const showPassword = ref(true)
const showConfirmPassword = ref(true)
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

const redirectTarget = computed(() =>
  typeof route.query.redirect === 'string' ? route.query.redirect : ''
)

const usernameTouched = ref(false)
const emailTouched = ref(false)
const passwordTouched = ref(false)
const confirmTouched = ref(false)

const usernameError = computed(() => {
  const value = form.value.username.trim()
  if (!usernameTouched.value || !value) return ''
  return /^[A-Za-z0-9\u4e00-\u9fff]+$/.test(value) ? '' : '用户名只允许中文、英文字符及数字'
})

const emailError = computed(() => {
  const value = form.value.email.trim()
  if (!emailTouched.value || !value) return ''
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) ? '' : '请输入正确的邮箱格式'
})

const passwordError = computed(() => {
  const value = form.value.password
  if (!passwordTouched.value || !value) return ''
  if (value.length < 8) return '密码长度至少 8 位'
  if (!/(?=.*[a-zA-Z])(?=.*[0-9])/.test(value)) return '密码必须包含字母和数字'
  return ''
})

const confirmError = computed(() => {
  const value = form.value.confirmPassword
  if (!confirmTouched.value || !value) return ''
  return value === form.value.password ? '' : '两次输入的密码不一致'
})

const validateUsername = () => {
  usernameTouched.value = true
}

const validateEmail = () => {
  emailTouched.value = true
}

const validatePassword = () => {
  passwordTouched.value = true
}

const validateConfirmPassword = () => {
  confirmTouched.value = true
}

function onRecaptchaLoad() {
  if (!recaptchaSiteKey || !window.grecaptcha?.render) return
  recaptchaWidgetId.value = window.grecaptcha.render('register-recaptcha', {
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

const handleRegister = async () => {
  usernameTouched.value = true
  emailTouched.value = true
  passwordTouched.value = true
  confirmTouched.value = true

  const username = form.value.username.trim()
  const email = form.value.email.trim()

  if (!username) {
    Message.warning('请输入用户名')
    return
  }

  if (!email) {
    Message.warning('请输入邮箱')
    return
  }

  if (usernameError.value || emailError.value || passwordError.value || confirmError.value) {
    Message.warning('请先完善注册信息')
    return
  }

  if (!form.value.agree) {
    Message.warning('请先同意服务协议')
    return
  }

  if (recaptchaSiteKey && !recaptchaToken.value) {
    Message.warning('请完成人机验证')
    return
  }

  try {
    loading.value = true

    await register({
      username,
      email,
      password: form.value.password,
      confirmPassword: form.value.confirmPassword,
      captcha: recaptchaToken.value || undefined
    })

    Message.success('注册成功，请查收邮箱完成验证')
    router.push({
      path: '/verify-email',
      query: {
        email,
        ...(redirectTarget.value ? { redirect: redirectTarget.value } : {})
      }
    })
  } catch (err) {
    Message.error(err.message || '注册失败')
  } finally {
    loading.value = false
    resetRecaptcha()
  }
}

const goLogin = () => {
  router.push({
    path: '/login',
    query: redirectTarget.value ? { redirect: redirectTarget.value } : {}
  })
}
</script>

<template>
  <div data-public-style class="public-register-view register-page">
    <div class="register-page__bg"></div>

    <header class="register-topbar">
      <RouterLink to="/" class="brand">
        <img src="/favicon.svg" alt="logo" class="brand__logo" />
        <span class="brand__name">TyCDN</span>
      </RouterLink>

      <ThemeToggle />
    </header>

    <main class="register-main">
      <section class="register-panel">
        <h1 class="register-panel__title">创建您的账户</h1>
        <p class="register-panel__subtitle">欢迎加入，请填写以下信息完成注册。</p>

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
              <a-checkbox v-model="form.agree"> 我已阅读并同意服务协议 </a-checkbox>
            </label>
          </div>

          <div v-if="recaptchaSiteKey" class="register-field register-field--captcha">
            <div id="register-recaptcha" :key="theme"></div>
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
            <button class="text-link" type="button" @click="goLogin">立即登录</button>
          </div>
        </div>
      </section>
    </main>

    <footer class="register-footer">Copyright © 2023-2026 TyCDN LTD.</footer>
  </div>
</template>
