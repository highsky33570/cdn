import { http } from "./http";

export async function fetchProducts() {
  const res = await http("/api/products");

  return Array.isArray(res.data) ? res.data : [];
}
