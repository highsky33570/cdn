export const billingPeriods = [
  { key: 'monthly', label: '月付', unit: '月' },
  { key: 'quarterly', label: '季付', unit: '季' },
  { key: 'yearly', label: '年付', unit: '年' }
]

export function productPrice(product, period = 'monthly') {
  const value = product?.[`price_${period}`]
  if (value === null || value === undefined || value === '') return null
  const amount = Number(value)
  return Number.isFinite(amount) && amount >= 0 ? amount : null
}

// The catalog's legacy USD code represents the operator's USDT-denominated plans.
export function displayCurrency(currency = 'USDT') {
  const code = String(currency || 'USDT').toUpperCase()
  return ['US', 'USD', 'USDT'].includes(code) ? 'USDT' : code
}

export function formatAmount(value) {
  if (value === null) return '暂未提供'
  return new Intl.NumberFormat('zh-CN', { maximumFractionDigits: 2 }).format(value)
}

export function formatMoney(value, currency = 'USDT') {
  return value === null ? '暂未提供' : `${formatAmount(value)} ${displayCurrency(currency)}`
}

function highlightValue(value) {
  if (value == null) return false
  const text = String(value)
  return text === '不限' || text === '支持' || text.includes('99.99%')
}

function highlightFeature(text, badge) {
  if (!text) return false
  if (text.includes('：支持') || text.includes(':支持')) return true
  if (badge === 'custom') {
    return (
      text.includes('独立节点') ||
      text.includes('屏蔽') ||
      text.includes('顶级') ||
      text.includes('不限')
    )
  }
  if (badge === 'recommend') {
    return text.includes('支持')
  }
  return false
}

/**
 * Storefront rows aligned with the sample pricing cards: domains, bandwidth,
 * traffic, DDoS, CC. Extra CDNfly fields stay available via `limits` for detail pages.
 */
export function productSpecs(product) {
  const limits = product?.limits || {}
  const count = (value) =>
    value == null || value === ''
      ? null
      : value === '不限'
        ? '不限'
        : /^\d+$/.test(String(value))
          ? `${value}个`
          : value
  const rows = [
    ['域名数', count(limits.domains)],
    ['峰值带宽', limits.bandwidth],
    [
      '月流量',
      limits.traffic == null
        ? null
        : limits.traffic === '不限'
          ? '不限'
          : `${limits.traffic} GB`
    ],
    ['DDoS防护', limits.ddos],
    ['CC防护', limits.custom_cc_rule ? '99.99% CC防护' : null]
  ]
  return rows
    .filter(([, value]) => value !== null && value !== undefined && value !== '')
    .map(([label, value]) => ({
      label,
      value,
      highlight: highlightValue(value)
    }))
}

export function productFeatures(product) {
  const badge = product?.badge || null
  return Array.isArray(product?.features)
    ? product.features
        .filter((text) => typeof text === 'string' && text.trim())
        .map((text) => ({
          text: text.trim(),
          highlight: highlightFeature(text.trim(), badge)
        }))
    : []
}

export function productBadgeLabel(badge) {
  if (badge === 'recommend') return '推荐'
  if (badge === 'custom') return '定制'
  return null
}
