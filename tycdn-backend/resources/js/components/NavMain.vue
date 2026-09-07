<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

const props = withDefaults(
    defineProps<{
        items: NavItem[];
        label?: string;
    }>(),
    {
        label: 'Console',
    },
);

const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
const { isMobile, openMobile, setOpen, setOpenMobile, state } = useSidebar();

function isChildActive(item: NavItem) {
    return toUrl(item.href) === '/console/sites'
        ? isCurrentOrParentUrl(item.href)
        : isCurrentUrl(item.href);
}

function hasActiveChild(item: NavItem) {
    return item.children?.some((child) => isChildActive(child));
}

function isActive(item: NavItem) {
    if (item.children?.length) {
        return isCurrentUrl(item.href) || hasActiveChild(item);
    }

    return isCurrentUrl(item.href);
}

const openMenuTitles = ref<Set<string>>(
    new Set(
        isMobile.value
            ? []
            : props.items
                  .filter((item) => item.children?.length && isActive(item))
                  .map((item) => item.title),
    ),
);

function isMenuOpen(item: NavItem) {
    return openMenuTitles.value.has(item.title);
}

function setMenuOpen(item: NavItem, open: boolean) {
    const next = new Set(openMenuTitles.value);

    if (open) {
        next.add(item.title);
    } else {
        next.delete(item.title);
    }

    openMenuTitles.value = next;
}

function expandDesktopSidebar() {
    if (!isMobile.value && state.value === 'collapsed') {
        setOpen(true);
    }
}

function resetOpenMenus() {
    openMenuTitles.value = new Set();
}

function collapseMobileSidebar() {
    if (isMobile.value) {
        setOpenMobile(false);
        resetOpenMenus();
    }
}

function handleLeafNavigation() {
    expandDesktopSidebar();
    collapseMobileSidebar();
}

watch(state, (value) => {
    if (!isMobile.value && value === 'collapsed') {
        resetOpenMenus();
    }
});

watch(openMobile, (value) => {
    if (isMobile.value && !value) {
        resetOpenMenus();
    }
});
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>{{ props.label }}</SidebarGroupLabel>
        <SidebarMenu>
            <Collapsible
                v-for="item in items"
                :key="item.title"
                as-child
                :open="isMenuOpen(item)"
                class="group/collapsible"
                @update:open="(open) => setMenuOpen(item, open)"
            >
                <SidebarMenuItem v-if="item.children?.length">
                    <CollapsibleTrigger as-child>
                        <SidebarMenuButton
                            :is-active="isActive(item)"
                            :tooltip="item.title"
                            @click="expandDesktopSidebar"
                        >
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                            <ChevronRight
                                class="ml-auto transition-transform group-data-[collapsible=icon]:hidden group-data-[state=open]/collapsible:rotate-90"
                            />
                        </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarMenuSub>
                            <SidebarMenuSubItem
                                v-for="child in item.children"
                                :key="child.title"
                            >
                                <SidebarMenuSubButton
                                    as-child
                                    :is-active="isChildActive(child)"
                                >
                                    <Link
                                        :href="child.href"
                                        @click="collapseMobileSidebar"
                                    >
                                        <span>{{ child.title }}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        </SidebarMenuSub>
                    </CollapsibleContent>
                </SidebarMenuItem>

                <SidebarMenuItem v-else>
                    <SidebarMenuButton
                        as-child
                        :is-active="isActive(item)"
                        :tooltip="item.title"
                    >
                        <Link :href="item.href" @click="handleLeafNavigation">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </Collapsible>
        </SidebarMenu>
    </SidebarGroup>
</template>
