import axios from 'axios';

export const api = axios.create({
    baseURL: '/api',
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    withXSRFToken: true,
});

/**
 * Pesan singkat dari respons server, untuk ditampilkan lewat toast.
 *
 * @param {unknown} error
 * @param {string} fallback
 * @returns {string}
 */
export function errorMessage(error, fallback = 'Terjadi kesalahan, coba lagi.') {
    if (!axios.isAxiosError(error) || !error.response) {
        return fallback;
    }

    if (error.response.status === 429) {
        return 'Terlalu banyak percobaan. Coba lagi dalam beberapa menit.';
    }

    return error.response.data?.message ?? fallback;
}

/**
 * Peta `field => pesan[]` dari respons validasi 422.
 *
 * @param {unknown} error
 * @returns {Record<string, string>}
 */
export function validationErrors(error) {
    if (!axios.isAxiosError(error) || !error.response?.data?.errors) {
        return {};
    }

    /** @type {Record<string, string>} */
    const result = {};

    for (const [field, messages] of Object.entries(error.response.data.errors)) {
        result[field] = Array.isArray(messages) ? messages[0] : String(messages);
    }

    return result;
}
