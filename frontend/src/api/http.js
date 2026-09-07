import { apiBaseUrl } from '../config/runtime'

const apiFetchBase = import.meta.env.DEV && !import.meta.env.VITE_API_BASE_URL ? '' : apiBaseUrl

let csrfReady = false

function requiresCsrf(method) {
  return !['GET', 'HEAD', 'OPTIONS'].includes(String(method || 'GET').toUpperCase())
}

async function ensureCsrf() {
  if (csrfReady) return

  await fetch(`${apiFetchBase}/sanctum/csrf-cookie`, {
    credentials: 'include',
  })

  csrfReady = true
}

function getXsrfToken() {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)

  return match ? decodeURIComponent(match[1]) : ''
}

function firstValidationError(errors) {
  if (!errors || typeof errors !== 'object') return ''

  for (const value of Object.values(errors)) {
    if (Array.isArray(value) && typeof value[0] === 'string' && value[0]) {
      return value[0]
    }
  }

  return ''
}

function resolveErrorMessage(data) {
  if (typeof data?.status === 'string' && data.status.trim()) {
    return normalizeAuthMessage(data.status)
  }

  if (typeof data?.message === 'string' && data.message.trim()) {
    return normalizeAuthMessage(data.message)
  }

  return normalizeAuthMessage(firstValidationError(data?.errors) || '请求失败')
}

function resolveFetchError(error) {
  const message = String(error?.message || '').trim()

  if (message === 'Failed to fetch') {
    return new Error('当前无法连接服务，请稍后重试')
  }

  if (error instanceof Error) {
    return error
  }

  return new Error('请求失败')
}

function normalizeAuthMessage(message) {
  const normalized = String(message || '').trim()

  if (!normalized) return '请求失败'

  if (normalized === 'These credentials do not match our records.') {
    return '账号或密码错误'
  }

  if (normalized === 'The provided two factor authentication code was invalid.') {
    return '两步验证码错误'
  }

  if (normalized === 'The provided two factor recovery code was invalid.') {
    return '恢复代码错误'
  }

  if (normalized.startsWith('Too many login attempts.')) {
    return '登录尝试过于频繁，请稍后再试'
  }

  if (normalized === 'We have emailed your password reset link.') {
    return '重置密码邮件已发送，请检查邮箱'
  }

  if (normalized === 'This password reset token is invalid.') {
    return '重置密码链接无效，请重新发起找回密码'
  }

  if (normalized === 'This password reset token has expired.') {
    return '重置密码链接已过期，请重新发起找回密码'
  }

  if (normalized === 'Your password has been reset.') {
    return '密码已重置，请使用新密码登录'
  }

  return normalized
}

export async function http(url, options = {}) {
  const method = String(options.method || 'GET').toUpperCase()
  const send = () => fetch(`${apiFetchBase}${url}`, {
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      'X-XSRF-TOKEN': getXsrfToken(),
      Accept: 'application/json',
      ...(options.headers || {}),
    },
    ...options,
  })
  let res

  try {
    if (requiresCsrf(method)) {
      await ensureCsrf()
    }

    res = await send()

    if (res.status === 419 && requiresCsrf(method)) {
      csrfReady = false
      await ensureCsrf()
      res = await send()
    }
  } catch (error) {
    throw resolveFetchError(error)
  }

  const data = await res.json().catch(() => ({}))

  if (!res.ok || data.ok === false) {
    throw new Error(resolveErrorMessage(data))
  }

  return data
}
