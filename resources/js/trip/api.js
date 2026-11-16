/* JSON client for the Laravel API. Uses the session cookie plus the CSRF token. */

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
let base = '/api';

export function setApiBase(url) {
    base = url.replace(/\/$/, '');
}

export class ApiError extends Error {
    /**
     * @param {number} status 0 means the request never reached the server.
     * @param {any} body
     */
    constructor(status, body) {
        super(body?.message || `Request failed (${status})`);
        this.status = status;
        this.body = body;
    }

    /** Worth retrying later: offline, timeout or server trouble. */
    get retryable() {
        return this.status === 0 || this.status === 408 || this.status === 429 || this.status >= 500;
    }

    /** First validation message, if any. */
    get firstError() {
        const errors = this.body?.errors;
        if (errors) {
            const first = Object.values(errors)[0];
            return Array.isArray(first) ? first[0] : String(first);
        }
        return this.body?.message || null;
    }
}

export async function api(method, path, body) {
    let response;
    try {
        response = await fetch(base + path, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: body === undefined ? undefined : JSON.stringify(body),
        });
    } catch {
        throw new ApiError(0, null);
    }

    if (response.status === 401) {
        window.location.href = '/login';
        throw new ApiError(401, null);
    }
    if (response.status === 419) {
        window.location.reload();
        throw new ApiError(419, null);
    }
    if (response.status === 204) {
        return null;
    }

    const data = await response.json().catch(() => null);
    if (!response.ok) {
        throw new ApiError(response.status, data);
    }
    return data;
}
