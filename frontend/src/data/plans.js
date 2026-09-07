export const planGroups = [
  {
    key: 'jpn',
    shortName: 'JPN',
    name: 'Japan CDN Plans',
    location: 'Tokyo COP',
    badge: 'Japan - Tokyo COP',
    status: 'online',
    description:
      '专为中国大陆访问优化的日本节点，加速内容分发。具备高速路由、CC防护与DDoS防护，99.9% SLA保证。',
  },
  {
    key: 'hkg',
    shortName: 'HKG',
    name: 'Hong Kong CDN Plans',
    location: 'Hong Kong COP',
    badge: 'Hong Kong COP',
    status: 'coming',
    description:
      '香港节点支持商务预咨询，适合中国优化访问、国际出海与低时延业务场景，后续将按套餐或定制方案开放。',
  },
  {
    key: 'sin',
    shortName: 'SIN',
    name: 'Singapore CDN Plans',
    location: 'Singapore COP',
    badge: 'Singapore COP',
    status: 'coming',
    description:
      '新加坡节点支持商务预咨询，适合东南亚覆盖、多国业务出海与国际内容分发，后续可直接补充套餐上线。',
  },
]

export const plans = [
  {
    id: 201,
    slug: 'jpn-mini',
    groupKey: 'jpn',
    title: 'JPN-Mini',
    price: 5,
    currency: 'USD',
    billingCycle: 'Monthly',
    traffic: '50 GiB',
    websites: '1',
    domains: '1',
    bandwidth: '100 Mbps',
    uploadSize: '50MiB',
    websocket: true,

  },
  {
    id: 202,
    slug: 'jpn-standard',
    groupKey: 'jpn',
    title: 'JPN-Standard',
    price: 10,
    currency: 'USD',
    billingCycle: 'Monthly',
    traffic: '100 GiB',
    websites: '5',
    domains: '5',
    bandwidth: '300 Mbps',
    uploadSize: '100MiB',
    websocket: true,
  },
  {
    id: 203,
    slug: 'jpn-plus',
    groupKey: 'jpn',
    title: 'JPN-Plus',
    price: 20,
    currency: 'USD',
    billingCycle: 'Monthly',
    traffic: '200 GiB',
    websites: '10',
    domains: '10',
    bandwidth: '1 Gbps',
    uploadSize: '200MiB',
    websocket: true,
  },
    {
    id: 204,
    slug: 'jpn-pro',
    groupKey: 'jpn',
    title: 'JPN-Pro',
    price: 30,
    currency: 'USD',
    billingCycle: 'Monthly',
    traffic: '300 GiB',
    websites: '20',
    domains: '20',
    bandwidth: '1 Gbps',
    uploadSize: '300MiB',
    websocket: true,
  },
]

export const getPlansByGroup = (groupKey) => plans.filter((item) => item.groupKey === groupKey)

export const getPlanBySlug = (slug) => plans.find((item) => item.slug === slug)

export const getGroupByKey = (groupKey) => planGroups.find((item) => item.key === groupKey)
