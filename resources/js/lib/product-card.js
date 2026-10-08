/**
 * Format angka unduhan gaya Play Store (25 rb+, 1,2 jt+).
 *
 * @param {number} value
 * @returns {string}
 */
export function formatCount(value) {
    const n = Number(value) || 0;

    if (n >= 1_000_000) {
        return `${(n / 1_000_000).toFixed(1).replace('.0', '')} jt+`;
    }

    if (n >= 1_000) {
        return `${Math.floor(n / 1000)} rb+`;
    }

    return String(n);
}

/**
 * Ubah `ProductResource` menjadi bentuk yang dipakai `AppCard`.
 *
 * @param {Record<string, any>} product
 * @returns {Record<string, any>}
 */
export function productCard(product) {
    return {
        id: product.id,
        name: product.title,
        dev: product.developer?.name ?? 'Developer',
        cat: product.category?.name ?? 'Lainnya',
        size: product.size ?? '—',
        rate: product.rate ?? null,
        dl: formatCount(product.downloads_count),
        reviews: formatCount(product.reviews_count ?? 0),
        emoji: product.emoji ?? '📦',
        grad: product.grad ?? 'linear-gradient(135deg, #3a3a3a, #111111)',
        icon: product.icon_url ?? null,
        desc: product.summary ?? product.description ?? '',
    };
}
