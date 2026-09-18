import { readonly, ref } from 'vue'
import { fetchProducts } from '../api/plans'

const products = ref([])
const loading = ref(false)
const error = ref('')
let loaded = false
let inflight = null

// All storefront pages use published products, with no static fallback.
function loadCatalog() {
  if (inflight) return inflight
  loading.value = true
  error.value = ''
  inflight = fetchProducts()
    .then((data) => {
      products.value = data.filter((product) => product && product.id != null)
      loaded = true
    })
    .catch(() => {
      products.value = []
      error.value = '暂时无法加载套餐，请稍后重试。'
      loaded = false
    })
    .finally(() => {
      loading.value = false
      inflight = null
    })
  return inflight
}

export function useProductCatalog() {
  if (!loaded && !inflight) void loadCatalog()
  return {
    products: readonly(products),
    loading: readonly(loading),
    error: readonly(error),
    reload: loadCatalog
  }
}
