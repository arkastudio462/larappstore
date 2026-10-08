/**
 * Validasi isian di sisi kirim (client) agar pengguna mendapat umpan balik
 * bahasa Indonesia tanpa menunggu respons server.
 */

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const USERNAME_PATTERN = /^[A-Za-z0-9_-]+$/;

export function isEmail(value) {
    return EMAIL_PATTERN.test(value);
}

export function isUsername(value) {
    return USERNAME_PATTERN.test(value);
}

export function required(value) {
    return typeof value === 'string' ? value.trim().length > 0 : value !== null && value !== undefined;
}
