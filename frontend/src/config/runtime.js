const trimTrailingSlash = (value, fallback) => {
  const target = (value || fallback || "").trim();

  return target.replace(/\/+$/, "");
};

const resolveApiBaseUrl = () => {
  if (import.meta.env.PROD && !import.meta.env.VITE_API_BASE_URL) {
    throw new Error("VITE_API_BASE_URL is required for production builds");
  }

  return trimTrailingSlash(
    import.meta.env.VITE_API_BASE_URL,
    "http://127.0.0.1:8013",
  );
};

export const apiBaseUrl = resolveApiBaseUrl();

export const dashboardBaseUrl = trimTrailingSlash(
  import.meta.env.VITE_DASHBOARD_BASE_URL,
  apiBaseUrl,
);

const authBaseUrl = trimTrailingSlash(import.meta.env.VITE_AUTH_BASE_URL, "");

const authUrl = (path) => (authBaseUrl ? `${authBaseUrl}${path}` : path);

export const dashboardHomeUrl = `${dashboardBaseUrl}/console`;
export const dashboardLoginUrl = authUrl("/login");
export const dashboardRegisterUrl = authUrl("/register");
export const emailVerificationNoticeUrl = authUrl("/verify-email");

function normalizePath(path, fallback = "/") {
  const value = String(path || "").trim();

  if (!value) {
    return fallback;
  }

  return value.startsWith("/") ? value : `/${value}`;
}

export function buildConsoleCheckoutPath(productId) {
  const search = new URLSearchParams();
  const numericProductId = Number(productId);

  if (Number.isFinite(numericProductId) && numericProductId > 0) {
    search.set("product_id", String(numericProductId));
  }

  const suffix = search.toString();

  return suffix
    ? `/console/billing/packages?${suffix}`
    : "/console/billing/packages";
}

export function buildAuthPageUrl(baseUrl, redirectPath = "") {
  const target = String(baseUrl || "").trim();
  const redirect = String(redirectPath || "").trim();

  if (!redirect) {
    return target;
  }

  const separator = target.includes("?") ? "&" : "?";

  return `${target}${separator}redirect=${encodeURIComponent(redirect)}`;
}

export function resolvePostAuthRedirect(rawRedirect) {
  const redirect = String(rawRedirect || "").trim();

  if (!redirect) {
    return dashboardHomeUrl;
  }

  if (redirect.startsWith("/console") || redirect.startsWith("/dashboard")) {
    return `${dashboardBaseUrl}${redirect}`;
  }

  if (redirect.startsWith("/")) {
    return `${window.location.origin}${redirect}`;
  }

  try {
    const parsed = new URL(redirect);
    const dashboardOrigin = new URL(dashboardHomeUrl).origin;
    const allowedOrigins = new Set([window.location.origin, dashboardOrigin]);

    if (allowedOrigins.has(parsed.origin)) {
      return parsed.toString();
    }
  } catch {
    return dashboardHomeUrl;
  }

  return dashboardHomeUrl;
}

export function resolvePortalPath(path = "/") {
  return normalizePath(path);
}
