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
  <div class="reset-page">
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

<style scoped>
.reset-page {
  position: relative;
  min-height: 100vh;
  overflow: hidden;
  background:
    radial-gradient(circle at 50% 10%, var(--accent-glow), transparent 26%),
    linear-gradient(180deg, var(--bg-grad-1), var(--bg-grad-2));
  color: var(--text-strong);
}

.reset-page__bg {
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

.reset-topbar {
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

.reset-main {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: calc(100vh - 140px);
  padding: 40px 20px 80px;
}

.reset-panel {
  width: 100%;
  max-width: 400px;
}

.reset-panel__title {
  margin: 0 0 10px;
  font-size: 32px;
  line-height: 1.2;
  font-weight: 800;
  color: var(--text-strong);
}

.reset-panel__subtitle {
  margin: 0 0 24px;
  font-size: 15px;
  line-height: 1.7;
  color: var(--text-3);
}

.reset-card {
  margin-bottom: 18px;
  padding: 18px 18px 16px;
  border: 1px solid var(--border);
  border-radius: 16px;
  background: var(--surface);
  box-shadow: var(--shadow);
}

.reset-card__label {
  margin-bottom: 10px;
  color: var(--text-3);
  font-size: 13px;
}

.reset-card__value {
  font-size: 18px;
  font-weight: 700;
  color: var(--text-strong);
  word-break: break-word;
}

.reset-form {
  width: 100%;
}

.reset-field + .reset-field {
  margin-top: 20px;
}

.reset-field {
  opacity: 0;
  transform: translateY(14px);
  animation: auth-field-fade-in 0.48s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}

.reset-field:nth-of-type(2) {
  animation-delay: 0.08s;
}

.reset-input {
  width: 100%;
}

:deep(.reset-input.arco-input-wrapper),
:deep(.reset-input > .arco-input-wrapper),
:deep(.reset-input .arco-input-wrapper) {
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

:deep(.reset-input.arco-input-wrapper:hover),
:deep(.reset-input > .arco-input-wrapper:hover),
:deep(.reset-input .arco-input-wrapper:hover) {
  border-color: var(--accent-border) !important;
  background: var(--surface) !important;
}

:deep(.reset-input.arco-input-wrapper.arco-input-focus),
:deep(.reset-input > .arco-input-wrapper.arco-input-focus),
:deep(.reset-input .arco-input-wrapper.arco-input-focus) {
  border-color: var(--accent-border) !important;
  background: var(--surface) !important;
  box-shadow: 0 0 0 2px var(--accent-glow) !important;
}

:deep(.reset-input .arco-input-prefix),
:deep(.reset-input .arco-input-suffix) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: var(--text-3) !important;
}

:deep(.reset-input .arco-input-prefix) {
  margin-right: 12px;
}

:deep(.reset-input .arco-input-suffix) {
  margin-left: 12px;
}

:deep(.reset-input .arco-input),
:deep(.reset-input input.arco-input) {
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

:deep(.reset-input .arco-input::placeholder),
:deep(.reset-input input.arco-input::placeholder) {
  color: var(--text-3) !important;
}

:deep(.reset-input input.arco-input::-ms-reveal),
:deep(.reset-input input.arco-input::-ms-clear) {
  display: none;
}

:deep(.reset-input .arco-input-password-icon) {
  color: var(--text-3) !important;
}

.reset-error {
  margin-top: 8px;
  padding-left: 2px;
  font-size: 13px;
  line-height: 1.4;
  color: var(--danger);
}

.reset-submit {
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

.reset-footer {
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
  .reset-field {
    opacity: 1;
    transform: none;
    animation: none;
  }
}
</style>
