<script setup>
import ThemeToggle from '../components/ThemeToggle.vue'
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Message } from '@arco-design/web-vue'
import { getMe, resendVerification, verifyEmailUrl } from '../api/auth'
import {
  buildAuthPageUrl,
  emailVerificationNoticeUrl,
  resolvePostAuthRedirect
} from '../config/runtime'

const route = useRoute()
const router = useRouter()
const redirectTarget = ref(typeof route.query.redirect === 'string' ? route.query.redirect : '')

const loading = ref(true)
const sending = ref(false)
const hasSession = ref(false)
const emailVerified = ref(false)
const verificationAttempted = ref(false)
const email = ref('')
const statusMessage = ref('')

const verificationSuccessMessage = '邮箱验证成功'
const expiredVerificationMessage = '验证链接已过期或无效，请重新发送验证邮件。'
const expiredVerificationLoginMessage = '验证链接已过期，请重新登录后再继续。'

const verificationUrl = computed(() => {
  const hash = typeof route.hash === 'string' ? route.hash.replace(/^#/, '') : ''

  if (hash) {
    const hashParams = new URLSearchParams(hash)
    const hashVerificationUrl = hashParams.get('verify_url')

    if (hashVerificationUrl) {
      return hashVerificationUrl
    }
  }

  return typeof route.query.verify_url === 'string' ? route.query.verify_url : ''
})

const verificationResult = computed(() =>
  typeof route.query.verification === 'string' ? route.query.verification : ''
)

const hasExpiredVerificationState = computed(
  () =>
    verificationAttempted.value &&
    !emailVerified.value &&
    statusMessage.value === expiredVerificationMessage
)

const pageTitle = computed(() => {
  if (loading.value) {
    return verificationUrl.value ? '正在验证邮箱' : '正在检查验证状态'
  }

  if (emailVerified.value) {
    return '邮箱已验证'
  }

  if (hasExpiredVerificationState.value) {
    return '验证链接已过期'
  }

  return '请完成邮箱验证'
})

const pageSubtitle = computed(() => {
  if (loading.value) {
    return verificationUrl.value
      ? '正在处理邮件中的验证链接，请稍候。'
      : '正在读取当前账号的验证状态，请稍候。'
  }

  if (hasExpiredVerificationState.value) {
    return '该验证链接已经失效，请重新发送验证邮件后使用新的验证链接。'
  }

  if (verificationAttempted.value && emailVerified.value) {
    return '验证已完成，你现在可以继续访问控制台。'
  }

  if (emailVerified.value) {
    return '当前邮箱已经验证完成。'
  }

  if (hasSession.value && email.value) {
    return `验证邮件已发送至 ${email.value}，请打开邮件中的链接完成验证。`
  }

  if (hasSession.value) {
    return '验证邮件已发送，请打开邮件中的链接完成验证。'
  }

  return '当前登录状态已失效，请重新登录后继续。'
})

const statusLabel = computed(() => {
  if (loading.value) return verificationUrl.value ? 'Checking' : 'Loading'
  if (emailVerified.value) return 'Ready'
  if (hasExpiredVerificationState.value) return 'Expired'
  return 'Pending'
})

const emailLabel = computed(() => email.value || '当前账号')

const primaryActionLabel = computed(() => {
  if (loading.value) return ''

  if (emailVerified.value) {
    return hasSession.value ? '进入控制台' : '返回登录'
  }

  return hasSession.value ? '打开验证中心' : '返回登录'
})

const showStatusNote = computed(() => {
  if (!statusMessage.value) return false
  if (emailVerified.value) return false

  return true
})

function cleanVerifyRoute() {
  return router.replace({
    path: '/verify-email',
    query: redirectTarget.value ? { redirect: redirectTarget.value } : {}
  })
}

onMounted(async () => {
  const verificationLink = verificationUrl.value

  if (verificationLink) {
    await cleanVerifyRoute()
    await handleVerificationLink(verificationLink)
    return
  }

  if (verificationResult.value) {
    verificationAttempted.value = true
    emailVerified.value = verificationResult.value === 'verified'
    statusMessage.value = emailVerified.value ? '' : expiredVerificationMessage
    await cleanVerifyRoute()
    await hydrateSessionState()
    loading.value = false

    if (emailVerified.value) {
      Message.success(verificationSuccessMessage)
    } else {
      Message.warning(statusMessage.value)
    }

    return
  }

  if (route.hash || Object.keys(route.query).length > 0) {
    await cleanVerifyRoute()
  }

  await loadCurrentUserState()
})

async function hydrateSessionState() {
  try {
    const res = await getMe()
    hasSession.value = true
    emailVerified.value = Boolean(res.data.email_verified)
    email.value = res.data.user?.email || ''
  } catch (error) {
    hasSession.value = false
    email.value = ''

    if (!verificationAttempted.value) {
      statusMessage.value = error.message || ''
    }
  }
}

async function loadCurrentUserState() {
  try {
    await hydrateSessionState()
  } finally {
    loading.value = false
  }
}

function normalizeVerificationError(error) {
  const message = String(error?.message || '').trim()

  if (
    message.includes('已过期') ||
    message.includes('无效') ||
    message.includes('权限执行该操作')
  ) {
    return expiredVerificationMessage
  }

  if (message.includes('先登录') || message.includes('登录状态')) {
    return expiredVerificationLoginMessage
  }

  return message || '邮箱验证失败'
}

async function handleVerificationLink(url) {
  try {
    verificationAttempted.value = true
    const res = await verifyEmailUrl(url)
    emailVerified.value = Boolean(res.data.email_verified)
    statusMessage.value = ''
    Message.success(verificationSuccessMessage)
    await hydrateSessionState()
  } catch (error) {
    verificationAttempted.value = true
    statusMessage.value = normalizeVerificationError(error)
    await hydrateSessionState()

    if (hasSession.value && emailVerified.value) {
      Message.success(verificationSuccessMessage)
      handleOpenDashboard()
      return
    }

    if (!hasSession.value) {
      Message.warning(expiredVerificationLoginMessage)
      await router.push({
        path: '/login',
        query: redirectTarget.value ? { redirect: redirectTarget.value } : {}
      })
      return
    }

    Message.warning(statusMessage.value)
  } finally {
    loading.value = false
  }
}

async function handleResend() {
  if (!hasSession.value) {
    Message.warning('请先登录后再重发验证邮件')
    router.push({
      path: '/login',
      query: redirectTarget.value ? { redirect: redirectTarget.value } : {}
    })
    return
  }

  try {
    sending.value = true
    const res = await resendVerification()
    statusMessage.value = res.message || '验证邮件已重新发送'
    Message.success(statusMessage.value)
  } catch (error) {
    statusMessage.value = error.message || '重发验证邮件失败'
    Message.error(statusMessage.value)
  } finally {
    sending.value = false
  }
}

function handleOpenVerificationCenter() {
  window.location.href = buildAuthPageUrl(emailVerificationNoticeUrl, redirectTarget.value)
}

function handleOpenDashboard() {
  window.location.href = resolvePostAuthRedirect(redirectTarget.value)
}

function handleBackToLogin() {
  router.push({
    path: '/login',
    query: redirectTarget.value ? { redirect: redirectTarget.value } : {}
  })
}

function handlePrimaryAction() {
  if (emailVerified.value) {
    if (hasSession.value) {
      handleOpenDashboard()
      return
    }

    handleBackToLogin()
    return
  }

  if (hasSession.value) {
    handleOpenVerificationCenter()
    return
  }

  handleBackToLogin()
}
</script>

<template>
  <div data-public-style class="public-verify-email-view verify-page">
    <div class="verify-page__bg"></div>

    <header class="verify-topbar">
      <RouterLink to="/" class="brand">
        <img src="/favicon.svg" alt="logo" class="brand__logo" />
        <span class="brand__name">TyCDN</span>
      </RouterLink>

      <ThemeToggle />
    </header>

    <main class="verify-main">
      <section class="verify-panel">
        <div class="verify-badge" :class="{ 'verify-badge--success': emailVerified && !loading }">
          {{ statusLabel }}
        </div>

        <h1 class="verify-panel__title">{{ pageTitle }}</h1>
        <p class="verify-panel__subtitle">{{ pageSubtitle }}</p>

        <div class="verify-card">
          <div class="verify-card__label">验证邮箱</div>
          <div class="verify-card__value">{{ emailLabel }}</div>
        </div>

        <div v-if="showStatusNote" class="verify-note">
          {{ statusMessage }}
        </div>

        <div class="verify-actions">
          <a-button v-if="!loading" type="primary" size="large" long @click="handlePrimaryAction">
            {{ primaryActionLabel }}
          </a-button>

          <a-button
            v-if="!loading && !emailVerified && hasSession"
            size="large"
            long
            class="verify-secondary"
            :loading="sending"
            @click="handleResend"
          >
            重发验证邮件
          </a-button>
        </div>
      </section>
    </main>

    <footer class="verify-footer">Copyright © 2023-2026 TyCDN LTD.</footer>
  </div>
</template>
