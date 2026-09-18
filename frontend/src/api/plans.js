import { http } from './http'

export async function fetchProducts() {
  const res = await http('/api/products')

  if (!Array.isArray(res.data)) throw new Error('Invalid product catalog response')
  return res.data
}
