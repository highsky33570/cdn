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

function pathnameOf(url: string): string {
    try {
        return new URL(
            url,
            typeof window !== 'undefined'
                ? window.location.origin
                : 'http://localhost',
        ).pathname;
    } catch {
        return String(url).split('?')[0] || '/';
    }
}

const page = usePage();

/**
 * Layout chrome (sidebar, nav active state) can miss Inertia page.url updates
 * across soft navigations — the switch button stayed on the previous console
 * until a full refresh. Keep an explicit path ref in sync with router events
 * and the page URL so scope flips immediately.
 */
const pathRef = ref(
    pathnameOf(
        typeof window !== 'undefined' ? window.location.pathname : page.url || '/',
    ),
);

function syncPath(url: string): void {
    const next = pathnameOf(url);

    if (pathRef.value !== next) {
        pathRef.value = next;
    }
}

watch(
    () => page.url,
    (url) => {
        if (typeof url === 'string' && url !== '') {
            syncPath(url);
        }
    },
    { immediate: true },
);

if (typeof window !== 'undefined') {
    router.on('navigate', (event) => {
        syncPath(event.detail.page.url);
    });
    router.on('success', (event) => {
        syncPath(event.detail.page.url);
    });
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
