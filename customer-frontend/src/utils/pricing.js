export function pickPrice(prices = [], { tierId = null, tierCode = null, minQty = 1 } = {}) {
  if (!Array.isArray(prices)) return null;

  const normalizedTierCode = tierCode ? String(tierCode).toUpperCase() : null;

  return (
    prices.find((p) => {
      if (Number(p?.min_quantity) !== Number(minQty)) return false;

      const byId = tierId != null && String(p?.tier_id) === String(tierId);
      const pCode =
        p?.tier?.code != null
          ? String(p.tier.code).toUpperCase()
          : p?.tier_code != null
            ? String(p.tier_code).toUpperCase()
            : p?.tierCode != null
              ? String(p.tierCode).toUpperCase()
              : null;

      const byCode = normalizedTierCode != null && pCode === normalizedTierCode;
      return byId || byCode;
    }) || null
  );
}

export function pickFirstMinQtyRow(prices = [], minQty = 1) {
  if (!Array.isArray(prices)) return null;
  return prices.find((p) => Number(p?.min_quantity) === Number(minQty)) || null;
}

export function getUserTierId(currentUser) {
  return currentUser?.tier_id ?? currentUser?.profile?.tier ?? null;
}

export function getUserTierRow(prices, currentUser) {
  const userTierId = getUserTierId(currentUser);
  if (userTierId == null) return null;
  return pickPrice(prices, { tierId: userTierId, minQty: 1 });
}

export function getRetailRow(prices, currentUser) {
  const byRetailCode = pickPrice(prices, { tierCode: "RETAIL", minQty: 1 });
  if (byRetailCode) return byRetailCode;

  const userRow = getUserTierRow(prices, currentUser);
  if (!Array.isArray(prices)) return null;

  if (userRow) {
    const anotherTierRow = prices.find(
      (p) => Number(p?.min_quantity) === 1 && String(p?.tier_id) !== String(userRow?.tier_id),
    );
    if (anotherTierRow) return anotherTierRow;
  }

  return pickFirstMinQtyRow(prices, 1);
}

export function resolvePrimaryTierRows(prices = [], currentUser = null) {
  if (!Array.isArray(prices) || prices.length === 0) return [];

  const userTierId = getUserTierId(currentUser);
  const userRows =
    userTierId == null
      ? []
      : prices.filter((p) => String(p?.tier_id) === String(userTierId));
  if (userRows.length > 0) {
    return [...userRows].sort((a, b) => Number(a?.min_quantity || 0) - Number(b?.min_quantity || 0));
  }

  const retailRows = prices.filter((p) => {
    const code =
      p?.tier?.code != null
        ? String(p.tier.code).toUpperCase()
        : p?.tier_code != null
          ? String(p.tier_code).toUpperCase()
          : p?.tierCode != null
            ? String(p.tierCode).toUpperCase()
            : null;
    return code === "RETAIL";
  });
  if (retailRows.length > 0) {
    return [...retailRows].sort((a, b) => Number(a?.min_quantity || 0) - Number(b?.min_quantity || 0));
  }

  const minRow = pickFirstMinQtyRow(prices, 1) || prices[0];
  if (!minRow) return [];
  const fallbackTierRows = prices.filter((p) => String(p?.tier_id) === String(minRow?.tier_id));
  return [...fallbackTierRows].sort((a, b) => Number(a?.min_quantity || 0) - Number(b?.min_quantity || 0));
}

export function getAppliedPriceRow(prices = [], currentUser = null, quantity = 1) {
  const rows = resolvePrimaryTierRows(prices, currentUser);
  if (rows.length === 0) return null;

  const qty = Math.max(1, Number(quantity || 1));
  let selected = rows[0];

  for (const row of rows) {
    if (Number(row?.min_quantity || 0) <= qty) selected = row;
  }

  return selected;
}

