<script setup>
import ThemeToggle from '../components/ThemeToggle.vue'
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Message } from '@arco-design/web-vue'
import { IconLock, IconSafe } from '@arco-design/web-vue/es/icon'
import { submitTwoFactorChallenge } from '../api/auth'
import { resolvePostAuthRedirect } from '../config/runtime'

const route = useRoute()
const router = useRouter()

const mode = ref('code')
const code = ref('')
const recoveryCode = ref('')
const loading = ref(false)

const redirectTarget = computed(() =>
  typeof route.query.redirect === 'string' ? route.query.redirect : ''
)

const accountLabel = computed(() =>
  typeof route.query.account === 'string' && route.query.account
    ? route.query.account
    : '当前登录账号'
)

const currentValue = computed(() => (mode.value === 'code' ? code.value : recoveryCode.value))

const pageTitle = computed(() => (mode.value === 'code' ? '输入两步验证码' : '输入恢复代码'))

const pageSubtitle = computed(() =>
  mode.value === 'code'
    ? '当前账号已开启两步验证，请输入验证器中的 6 位动态码。'
    : '如果你当前无法访问验证器，可以改用恢复代码完成登录。'
)

const submitLabel = computed(() => (mode.value === 'code' ? '验证并登录' : '使用恢复代码登录'))

const handleSubmit = async () => {
  if (!currentValue.value.trim()) {
    Message.warning(mode.value === 'code' ? '请输入两步验证码' : '请输入恢复代码')
    return
  }

  try {
    loading.value = true

    const res = await submitTwoFactorChallenge({
      code: mode.value === 'code' ? code.value.trim() : '',
      recoveryCode: mode.value === 'recovery' ? recoveryCode.value.trim() : '',
      redirect: redirectTarget.value || undefined
    })

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
    Message.error(error instanceof Error ? error.message : '两步验证失败')
  } finally {
    loading.value = false
  }
}

const switchMode = (nextMode) => {
  mode.value = nextMode
}

const handleBackToLogin = () => {
  router.push({
    path: '/login',
    query: redirectTarget.value ? { redirect: redirectTarget.value } : {}
  })
}
</script>

<template>
  <div data-public-style class="public-two-factor-challenge-view challenge-page">
    <div class="challenge-page__bg"></div>

    <header class="challenge-topbar">
      <RouterLink to="/" class="brand">
        <img src="/favicon.svg" alt="logo" class="brand__logo" />
        <span class="brand__name">TyCDN</span>
      </RouterLink>

      <ThemeToggle />
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

          <button class="text-link" type="button" @click="handleBackToLogin">返回登录</button>
        </div>
      </section>
    </main>

    <footer class="challenge-footer">Copyright © 2023-2026 TyCDN LTD.</footer>
  </div>
</template>
