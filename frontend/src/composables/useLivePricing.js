import { ref } from "vue";
import { fetchProducts } from "../api/plans";

/**
 * Single source of truth for plan prices on the marketing pages.
 *
 * src/data/plans.js still owns presentation metadata (bandwidth, traffic, copy),
 * but it also used to hardcode prices -- so the homepage and plan detail pages
 * could advertise one number while checkout charged whatever was in the products
 * table. Prices are now read from /api/products and merged in by slug, with the
 * static value kept only as a fallback when the catalogue cannot be reached.
 */

const priceBySlug = ref({});
let inflight = null;

export function useLivePricing() {
  if (!inflight) {
    inflight = fetchProducts()
      .then((products) => {
        const map = {};

        for (const product of products) {
          if (!product || !product.slug) continue;

          const price = Number(product.price_monthly);

          if (!Number.isFinite(price)) continue;

          map[product.slug] = {
            price,
            currency: product.currency || "USD",
          };
        }

        priceBySlug.value = map;
      })
      .catch(() => {
        // Marketing pages must still render if the API is down; the static
        // prices in data/plans.js remain as the fallback.
        priceBySlug.value = {};
      });
  }

  return { priceBySlug, ready: inflight };
}

/**
 * Resolve the price to display for a static plan entry, preferring the live
 * catalogue value.
 */
export function resolvePlanPrice(plan, map) {
  const live = plan && plan.slug ? map[plan.slug] : null;

  return {
    price: live ? live.price : plan?.price,
    currency: live ? live.currency : plan?.currency || "USD",
    isLive: Boolean(live),
  };
}

/**
 * Currency-aware formatting. The detail page previously hardcoded a yuan sign
 * while every plan is priced in USD.
 */
export function formatPlanPrice(plan, map, suffix = "") {
  const { price, currency } = resolvePlanPrice(plan, map);

  if (price === null || price === undefined || price === "") return "-";

  const amount = Number(price);
  const shown = Number.isFinite(amount) ? amount.toFixed(2) : price;
  const symbol = currency === "CNY" ? "¥" : "$";

  return `${symbol}${shown}${suffix}`;
}
