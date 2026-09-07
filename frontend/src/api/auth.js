import { http } from './http'

export async function login(payload) {
  return http('/api/auth/login', {
    method: 'POST',
    body: JSON.stringify({
      account: payload.account,
      password: payload.password,
      captcha: payload.captcha || undefined,
      redirect: payload.redirect || undefined,
    }),
  })
}

export async function submitTwoFactorChallenge(payload) {
  return http('/api/auth/two-factor-challenge', {
    method: 'POST',
    body: JSON.stringify({
      code: payload.code || undefined,
      recovery_code: payload.recoveryCode || undefined,
      redirect: payload.redirect || undefined,
    }),
  })
}

export async function register(payload) {
  return http('/api/auth/register', {
    method: 'POST',
    body: JSON.stringify({
      name: payload.username,
      email: payload.email,
      password: payload.password,
      password_confirmation: payload.confirmPassword,
      captcha: payload.captcha || undefined,
    }),
  })
}

export async function requestPasswordResetLink(payload) {
  return http('/api/auth/forgot-password', {
    method: 'POST',
    body: JSON.stringify({
      email: payload.email,
      captcha: payload.captcha,
    }),
  })
}

export async function resetPassword(payload) {
  return http('/api/auth/reset-password', {
    method: 'POST',
    body: JSON.stringify({
      email: payload.email,
      token: payload.token,
      password: payload.password,
      password_confirmation: payload.confirmPassword,
    }),
  })
}

export async function logout() {
  return http('/api/auth/logout', { method: 'POST' })
}

export async function getMe() {
  return http('/api/auth/me')
}

export async function resendVerification() {
  return http('/api/auth/resend-verification', { method: 'POST' })
}

export async function verifyEmailUrl(verifyUrl) {
  let res

  try {
    res = await fetch(verifyUrl, {
      method: 'GET',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
      },
    })
  } catch (error) {
    if (String(error?.message || '').trim() === 'Failed to fetch') {
      throw new Error('当前无法连接服务，请稍后重试')
    }

    throw error instanceof Error ? error : new Error('请求失败')
  }

  const data = await res.json().catch(() => ({}))

  if (!res.ok || data.ok === false) {
    throw new Error(data.message || '验证失败')
  }

  return data
}
