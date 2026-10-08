/**
 * Inisial untuk avatar bulat, mengikuti desain (mis. "Rina Dewi" → "RD").
 *
 * @param {string} [name]
 * @returns {string}
 */
export function initials(name = '') {
    const parts = name.trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return '?';
    }

    return parts.slice(0, 2).map((part) => part[0].toUpperCase()).join('');
}

/**
 * Waktu relatif singkat dalam bahasa Indonesia.
 *
 * @param {string | null | undefined} iso
 * @returns {string}
 */
export function relativeTime(iso) {
    if (!iso) {
        return '';
    }

    const diff = Date.now() - new Date(iso).getTime();
    const minutes = Math.floor(diff / 60000);

    if (minutes < 1) {
        return 'Baru saja';
    }
    if (minutes < 60) {
        return `${minutes} menit lalu`;
    }

    const hours = Math.floor(minutes / 60);

    if (hours < 24) {
        return `${hours} jam lalu`;
    }

    const days = Math.floor(hours / 24);

    if (days < 7) {
        return `${days} hari lalu`;
    }

    return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

/**
 * Nama peran yang ditampilkan di kartu postingan.
 *
 * @param {string} [role]
 * @returns {string}
 */
export function roleLabel(role) {
    return role === 'developer' || role === 'admin' ? 'Developer' : 'Pengguna';
}

/**
 * Harga rupiah tanpa desimal (mis. 15000 → "Rp15.000").
 *
 * @param {number | null | undefined} value
 * @returns {string}
 */
export function rupiah(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value ?? 0);
}

const MEDIA_BASE = import.meta.env.VITE_MEDIA_URL ?? '';

/**
 * Ubah path objek penyimpanan (mis. `posts/1.jpg`) menjadi URL yang bisa
 * dimuat browser. Path absolut dibiarkan apa adanya.
 *
 * @param {string | null | undefined} path
 * @returns {string}
 */
export function mediaUrl(path) {
    if (!path) {
        return '';
    }

    if (/^https?:\/\//.test(path)) {
        return path;
    }

    return `${MEDIA_BASE.replace(/\/$/, '')}/${String(path).replace(/^\//, '')}`;
}
