import { ref } from "vue";
import { fetchProducts } from "../api/plans";

/**
 * Single source of truth for plan prices on the marketing pages.
 *
 * Prices AND the enforced limits are read from /api/products and merged in by
 * slug. The limits come from the CDNfly package that enforces them, so raising
 * a real limit cannot leave a card advertising the old number.
 *
 * src/data/plans.js still owns the copy, and its numbers remain as the fallback
 * for when the catalogue cannot be reached -- a marketing page has to render
 * even with the API down.
 */

const priceBySlug = ref({});
const limitsBySlug = ref({});
let inflight = null;

export function useLivePricing() {
  if (!inflight) {
    inflight = fetchProducts()
      .then((products) => {
        const map = {};
        const limits = {};

        for (const product of products) {
          if (!product || !product.slug) continue;

          // A product with no CDNfly package behind it has no enforced
          // limits; its static values stay in charge.
          if (product.limits) {
            limits[product.slug] = product.limits;
          }

          const price = Number(product.price_monthly);

          if (!Number.isFinite(price)) continue;

          map[product.slug] = {
            price,
            currency: product.currency || "USD",
          };
        }

        priceBySlug.value = map;
        limitsBySlug.value = limits;
      })
      .catch(() => {
        // Marketing pages must still render if the API is down; the static
        // prices in data/plans.js remain as the fallback.
        priceBySlug.value = {};
        limitsBySlug.value = {};
      });
  }

  return { priceBySlug, limitsBySlug, ready: inflight };
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

/**
 * The spec rows for a plan card, preferring the limits the package actually
 * enforces and falling back to the static entry per field.
 *
 * Per field rather than all-or-nothing: a package that defines bandwidth but
 * not upload size should still show the static upload size.
 */
export function resolvePlanSpecs(plan, limitsMap) {
  const live = (plan && plan.slug ? limitsMap[plan.slug] : null) || {};

  const sites = live.sites ?? plan?.websites;
  const domains = live.domains ?? plan?.domains;
  const websocket =
    live.websocket !== undefined ? live.websocket : plan?.websocket;

  const rows = [
    { label: "峰值带宽", value: live.bandwidth ?? plan?.bandwidth },
    {
      label: "月流量",
      value: live.traffic ? formatTraffic(live.traffic) : plan?.traffic,
    },
    { label: "站点数", value: sites },
    { label: "域名数", value: domains },
    { label: "上传限制", value: plan?.uploadSize },
    { label: "WebSocket", value: websocket ? "支持" : "不支持" },
  ];

  if (live.custom_cc_rule) {
    rows.push({ label: "自定义 CC", value: "支持" });
  }

  if (live.ddos) {
    rows.push({ label: "DDoS 防护", value: live.ddos });
  }

  return rows.filter(
    (row) => row.value !== undefined && row.value !== null && row.value !== "",
  );
}

/** CDNfly stores traffic as a bare number of GB. */
function formatTraffic(value) {
  return value === "不限" ? "不限" : `${value} GB`;
}
