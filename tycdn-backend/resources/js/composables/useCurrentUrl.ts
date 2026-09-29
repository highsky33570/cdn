import type { InertiaLinkProps } from '@inertiajs/vue3';
import { router, usePage } from '@inertiajs/vue3';
import type { ComputedRef, DeepReadonly } from 'vue';
import { computed, readonly, ref, watch } from 'vue';
import { toUrl } from '@/lib/utils';

export type UseCurrentUrlReturn = {
    currentUrl: DeepReadonly<ComputedRef<string>>;
    isCurrentUrl: (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
        startsWith?: boolean,
    ) => boolean;
    isCurrentOrParentUrl: (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
    ) => boolean;
    whenCurrentUrl: <T, F = null>(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        ifTrue: T,
        ifFalse?: F,
    ) => T | F;
};

function pathnameOf(url: unknown): string {
    if (typeof window !== 'undefined' && (url === null || url === undefined || url === '')) {
        return window.location.pathname;
    }

    if (typeof URL !== 'undefined' && url instanceof URL) {
        return url.pathname;
    }

    if (typeof url === 'string' && url !== '') {
        try {
            return new URL(
                url,
                typeof window !== 'undefined'
                    ? window.location.origin
                    : 'http://localhost',
            ).pathname;
        } catch {
            return url.split('?')[0] || '/';
        }
    }

    if (url && typeof url === 'object' && 'pathname' in url) {
        return String((url as { pathname: unknown }).pathname || '/');
    }

    return typeof window !== 'undefined' ? window.location.pathname : '/';
}

const page = usePage();

/**
 * Layout chrome can miss soft-navigation URL updates. Keep an explicit path
 * ref synced from router visit events and window.location.
 */
const pathRef = ref(pathnameOf(null));

function syncPath(url?: unknown): void {
    const next = pathnameOf(url);

    if (pathRef.value !== next) {
        pathRef.value = next;
    }
}

watch(
    () => page.url as unknown,
    (url) => {
        syncPath(url);
    },
    { immediate: true },
);

if (typeof window !== 'undefined') {
    router.on('before', (event) => {
        syncPath(event.detail.visit.url);
    });
    router.on('navigate', (event) => {
        syncPath(event.detail.page.url);
    });
    router.on('success', (event) => {
        syncPath(event.detail.page.url);
    });
    router.on('finish', () => {
        syncPath();
    });
    window.addEventListener('popstate', () => syncPath());
}

const currentUrlReactive = computed(() => pathRef.value);

export function useCurrentUrl(): UseCurrentUrlReturn {
    function isCurrentUrl(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
        startsWith: boolean = false,
    ) {
        const urlToCompare = currentUrl ?? currentUrlReactive.value;
        const urlString = toUrl(urlToCheck);

        const comparePath = (path: string): boolean =>
            startsWith ? urlToCompare.startsWith(path) : path === urlToCompare;

        if (!urlString.startsWith('http')) {
            return comparePath(urlString);
        }

        try {
            const absoluteUrl = new URL(urlString);

            return comparePath(absoluteUrl.pathname);
        } catch {
            return false;
        }
    }

    function isCurrentOrParentUrl(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
    ) {
        return isCurrentUrl(urlToCheck, currentUrl, true);
    }

    function whenCurrentUrl(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        ifTrue: any,
        ifFalse: any = null,
    ) {
        return isCurrentUrl(urlToCheck) ? ifTrue : ifFalse;
    }

    return {
        currentUrl: readonly(currentUrlReactive),
        isCurrentUrl,
        isCurrentOrParentUrl,
        whenCurrentUrl,
    };
}
