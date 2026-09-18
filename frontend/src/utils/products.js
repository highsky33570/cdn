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

export function productSpecs(product) {
  const limits = product?.limits || {}
  const rows = [
    ['峰值带宽', limits.bandwidth],
    [
      '月流量',
      limits.traffic == null ? null : limits.traffic === '不限' ? '不限' : `${limits.traffic} GB`
    ],
    ['站点数', limits.sites],
    ['域名数', limits.domains],
    ['WebSocket', limits.websocket == null ? null : limits.websocket ? '支持' : '不支持'],
    ['自定义 CC', limits.custom_cc_rule == null ? null : limits.custom_cc_rule ? '支持' : '不支持'],
    ['DDoS 防护', limits.ddos]
  ]
  return rows
    .filter(([, value]) => value !== null && value !== undefined && value !== '')
    .map(([label, value]) => ({ label, value }))
}

export function productFeatures(product) {
  return Array.isArray(product?.features)
    ? product.features.filter((text) => typeof text === 'string' && text.trim())
    : []
}
