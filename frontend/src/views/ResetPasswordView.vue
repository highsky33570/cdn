<script setup>
import ThemeToggle from '../components/ThemeToggle.vue'
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Message } from '@arco-design/web-vue'
import { IconLock } from '@arco-design/web-vue/es/icon'
import { resetPassword } from '../api/auth'

const route = useRoute()
const router = useRouter()

const form = ref({
  password: '',
  confirmPassword: ''
})

const loading = ref(false)
const showPassword = ref(true)
const showConfirmPassword = ref(true)
const passwordTouched = ref(false)
const confirmTouched = ref(false)

const email = computed(() =>
  typeof route.query.email === 'string' ? route.query.email.trim() : ''
)

const token = computed(() =>
  typeof route.params.token === 'string' ? route.params.token.trim() : ''
)

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

const validatePassword = () => {
  passwordTouched.value = true
}

const validateConfirmPassword = () => {
  confirmTouched.value = true
}

const handleSubmit = async () => {
  passwordTouched.value = true
  confirmTouched.value = true

  if (!email.value) {
    Message.error('重置链接缺少邮箱参数，请重新发起找回密码')
    return
  }

  if (!token.value) {
    Message.error('重置链接无效，请重新发起找回密码')
    return
  }

  if (passwordError.value || confirmError.value) {
    Message.warning('请先填写正确的新密码')
    return
  }

  try {
    loading.value = true
    const res = await resetPassword({
      email: email.value,
      token: token.value,
      password: form.value.password,
      confirmPassword: form.value.confirmPassword
    })
    Message.success(res.status || '密码已重置，请使用新密码登录')
    router.push('/login')
  } catch (error) {
    Message.error(error instanceof Error ? error.message : '重置密码失败')
  } finally {
    loading.value = false
  }
}

const backToLogin = () => {
  router.push('/login')
}
</script>

<template>
  <div data-public-style class="public-reset-password-view reset-page">
    <div class="reset-page__bg"></div>

    <header class="reset-topbar">
      <RouterLink to="/" class="brand">
        <img src="/favicon.svg" alt="logo" class="brand__logo" />
        <span class="brand__name">TyCDN</span>
      </RouterLink>

      <ThemeToggle />
    </header>

    <main class="reset-main">
      <section class="reset-panel">
        <h1 class="reset-panel__title">设置新密码</h1>
        <p class="reset-panel__subtitle">为账号设置一个新的登录密码。修改后，旧密码将立即失效。</p>

        <div class="reset-card">
          <div class="reset-card__label">目标邮箱</div>
          <div class="reset-card__value">{{ email || '缺少邮箱参数' }}</div>
        </div>

        <div class="reset-form">
          <div class="reset-field">
            <a-input-password
              v-model="form.password"
              size="large"
              class="reset-input"
              :default-visibility="true"
              :visibility="showPassword"
              placeholder="请输入新密码"
              @visibility-change="showPassword = $event"
              @blur="validatePassword"
              @input="passwordTouched && validatePassword()"
            >
              <template #prefix>
                <IconLock />
              </template>
            </a-input-password>

            <div v-if="passwordError" class="reset-error">
              {{ passwordError }}
            </div>
          </div>

          <div class="reset-field">
            <a-input-password
              v-model="form.confirmPassword"
              size="large"
              class="reset-input"
              :default-visibility="true"
              :visibility="showConfirmPassword"
              placeholder="请再次输入新密码"
              @visibility-change="showConfirmPassword = $event"
              @blur="validateConfirmPassword"
              @input="confirmTouched && validateConfirmPassword()"
            >
              <template #prefix>
                <IconLock />
              </template>
            </a-input-password>

            <div v-if="confirmError" class="reset-error">
              {{ confirmError }}
            </div>
          </div>

          <a-button
            type="primary"
            size="large"
            long
            class="reset-submit"
            :loading="loading"
            @click="handleSubmit"
          >
            保存新密码
          </a-button>

          <button class="text-link" type="button" @click="backToLogin">返回登录</button>
        </div>
      </section>
    </main>

    <footer class="reset-footer">Copyright © 2023-2026 TyCDN LTD.</footer>
  </div>
</template>
