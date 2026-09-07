type ApiEnvelope<T> = {
    ok?: boolean;
    data?: T;
    message?: string;
    errors?: Record<string, string[]>;
};

let csrfReady = false;

export async function apiRequest<T>(
    url: string,
    options: RequestInit = {},
    retried = false,
): Promise<T> {
    const method = (options.method ?? 'GET').toUpperCase();

    if (method !== 'GET' && method !== 'HEAD') {
        await ensureCsrfCookie();
    }

    const headers = new Headers(options.headers);
    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');

    if (options.body && !headers.has('Content-Type')) {
        headers.set('Content-Type', 'application/json');
    }

    const xsrfToken = readCookie('XSRF-TOKEN');

    if (xsrfToken) {
        headers.set('X-XSRF-TOKEN', decodeURIComponent(xsrfToken));
    }

    const response = await fetch(url, {
        ...options,
        credentials: 'include',
        headers,
    });

    if (response.status === 419 && !retried) {
        csrfReady = false;
        await ensureCsrfCookie();

        return apiRequest<T>(url, options, true);
    }

    const json = await parseJson<ApiEnvelope<T>>(response);

    if (!response.ok || json?.ok === false) {
        throw new Error(resolveErrorMessage(json, response.status));
    }

    if (json && typeof json === 'object' && 'data' in json) {
        return json.data as T;
    }

    return json as T;
}

async function ensureCsrfCookie(): Promise<void> {
    if (csrfReady) {
        return;
    }

    const response = await fetch('/sanctum/csrf-cookie', {
        credentials: 'include',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        throw new Error('无法初始化 CSRF Cookie');
    }

    csrfReady = true;
}

async function parseJson<T>(response: Response): Promise<T> {
    const text = await response.text();

    if (text === '') {
        return {} as T;
    }

    try {
        return JSON.parse(text) as T;
    } catch {
        return {
            message: text,
        } as T;
    }
}

function resolveErrorMessage(
    body: ApiEnvelope<unknown>,
    status: number,
): string {
    if (body?.errors) {
        const firstErrors = Object.values(body.errors).find(
            (messages) => messages.length > 0,
        );

        if (firstErrors?.[0]) {
            return firstErrors[0];
        }
    }

    if (body?.message) {
        return body.message;
    }

    return `请求失败 (${status})`;
}

function readCookie(name: string): string | null {
    const encodedName = `${encodeURIComponent(name)}=`;
    const cookie = document.cookie
        .split(';')
        .map((item) => item.trim())
        .find((item) => item.startsWith(encodedName));

    return cookie ? cookie.slice(encodedName.length) : null;
}
