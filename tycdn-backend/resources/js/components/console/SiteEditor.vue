<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ArrowLeft, RefreshCw, Save } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import Switch from '@/components/ui/switch/Switch.vue';
import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRecord, extractCdnflyRows } from '@/lib/cdnflyResponse';
import { configRecord, configPatch } from '@/lib/configEditor';
import type { ConfigObject, Field } from '@/lib/configEditor';
import type { CdnflyRecord } from '@/lib/sharedTypes';
import { siteSections, siteTabs, editableSiteKeys } from '@/lib/siteSettings';
import ConfigFields from './ConfigFields.vue';
import ConsoleTabs from './ConsoleTabs.vue';

const props = withDefaults(
    defineProps<{ siteId: string; scope?: 'admin' | 'user' }>(),
    { scope: 'user' },
);
const endpoint = computed(
    () =>
        `/api/${props.scope === 'admin' ? 'admin' : 'cdn'}/sites/${encodeURIComponent(props.siteId)}`,
);
const back = computed(() =>
    props.scope === 'admin' ? '/console/admin/sites' : '/console/sites',
);
const original = ref<ConfigObject>({});
const draft = ref<ConfigObject>({});
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const referenceError = ref('');
const tab = ref('basic');
const packages = ref<CdnflyRecord[]>([]);
const certs = ref<CdnflyRecord[]>([]);
const ccRules = ref<CdnflyRecord[]>([]);
const wafOriginal = ref<ConfigObject>({ libraries: [] }),
    wafDraft = ref<ConfigObject>({ libraries: [] }),
    libraries = ref<CdnflyRecord[]>([]),
    wafReady = ref(false);
const wafEndpoint = computed(() =>
    props.scope === 'admin'
        ? `${endpoint.value}/waf-rules`
        : `/api/cdn/proxy/v1/sites/${props.siteId}/waf-rules`,
);
const wafDirty = computed(
    () => JSON.stringify(wafOriginal.value) !== JSON.stringify(wafDraft.value),
);
const wafFields = computed<Field[]>(() => [
    {
        key: 'libraries',
        label: '已应用规则库',
        type: 'array',
        fields: [
            {
                key: 'rule_id',
                label: '规则库',
                type: 'select',
                options: libraries.value.map((row) => ({
                    value: Number(row.id),
                    label: `${row.name} · #${row.id}`,
                })),
            },
            { key: 'enable', label: '启用', type: 'toggle' },
        ],
        initial: { type: 'library', rule_id: 0, enable: 1 },
    },
]);
const listenerEnabled = (key: string) => {
    const value = draft.value[key];

    return (
        !!value &&
        typeof value === 'object' &&
        !Array.isArray(value) &&
        Object.keys(value).length > 0
    );
};
function toggleListener(key: string, enabled: boolean) {
    draft.value = {
        ...draft.value,
        [key]: enabled
            ? original.value[key] &&
              typeof original.value[key] === 'object' &&
              Object.keys(original.value[key] as object).length
                ? original.value[key]
                : key === 'http_listen'
                  ? { enable: 1, port: '80' }
                  : { port: '443', cert: 0 }
            : {},
    };
}
const patch = computed(() =>
    configPatch(original.value, draft.value, editableSiteKeys),
);
const dirty = computed(
    () => Object.keys(patch.value).length > 0 || wafDirty.value,
);
const title = computed(() =>
    String(draft.value.domain ?? `网站 #${props.siteId}`),
);
const sections = computed(() =>
    (tab.value === 'https' && !listenerEnabled('https_listen')
        ? []
        : siteSections[tab.value]
    ).map((section) => ({
        ...section,
        fields: section.fields.map((field) => referenceField(field)),
    })),
);
function referenceField(field: Field, prefix = ''): Field {
    const path = prefix ? `${prefix}.${field.key}` : field.key;
    const records =
        field.key === 'user_package'
            ? packages.value
            : field.key === 'https_listen.cert'
              ? certs.value
              : ['cc_default_rule', 'cc_switch.rule'].includes(path)
                ? ccRules.value
                : null;

    if (!records) {
        return {
            ...field,
            fields: field.fields?.map((child) => referenceField(child, path)),
        };
    }

    return {
        ...field,
        type: 'select',
        options: records.map((row) => ({
            value: Number(row.id),
            label: `${row.package_name ?? row.name ?? row.domain ?? '配置'} · #${row.id}`,
        })),
    };
}
async function load() {
    loading.value = true;
    error.value = '';

    try {
        const record = extractCdnflyRecord(await apiRequest(endpoint.value));

        if (!record?.id) {
            throw new Error('未找到网站配置');
        }

        original.value = configRecord(record ?? {});
        draft.value = configRecord(record ?? {});
        const admin = props.scope === 'admin';
        const uid = Number(record.uid);
        const urls = admin
            ? [
                  `/api/admin/cdnfly-user-packages?limit=200&uid=${uid}`,
                  '/api/admin/all-certs?limit=200',
                  '/api/admin/cc/rule?limit=200',
              ]
            : [
                  '/api/cdn/proxy/v1/user-packages?limit=200',
                  '/api/cdn/certs?limit=200',
                  '/api/cdn/proxy/v1/cc-rules?limit=200',
              ];
        urls.push(
            wafEndpoint.value,
            admin
                ? '/api/admin/all-acls?limit=200'
                : '/api/cdn/proxy/v1/waf-rules?limit=200',
        );
        const results = await Promise.allSettled(
            urls.map((url) => apiRequest(url)),
        );
        wafReady.value = results[3].status === 'fulfilled';

        if (results[3].status === 'fulfilled') {
            wafOriginal.value = {
                libraries: extractCdnflyRows(results[3].value).map((row) =>
                    configRecord(row),
                ),
            };
            wafDraft.value = JSON.parse(JSON.stringify(wafOriginal.value));
        }

        if (results[4].status === 'fulfilled') {
            libraries.value = extractCdnflyRows(results[4].value);
        }

        [packages, certs, ccRules].forEach((target, index) => {
            const result = results[index];
            target.value =
                result.status === 'fulfilled'
                    ? extractCdnflyRows(result.value)
                    : [];
        });
        referenceError.value = results.some((r) => r.status === 'rejected')
            ? '部分套餐、证书或规则选项未能加载，当前配置已保留。请刷新重试。'
            : '';
    } catch (e) {
        error.value = e instanceof Error ? e.message : '加载失败';
    } finally {
        loading.value = false;
    }
}
function validate() {
    if ('domain' in patch.value && !String(draft.value.domain ?? '').trim()) {
        throw new Error('请填写访问域名');
    }

    if ('backend' in patch.value) {
        const rows = draft.value.backend;

        if (!Array.isArray(rows) || !rows.length) {
            throw new Error('至少保留一个源站');
        }

        for (const row of rows) {
            if (
                !row ||
                typeof row !== 'object' ||
                Array.isArray(row) ||
                !String(row.addr ?? '').trim()
            ) {
                throw new Error('请填写每个源站的地址');
            }

            if (Number(row.weight) < 1) {
                throw new Error('源站权重必须大于零');
            }
        }
    }

    if ('https_listen' in patch.value) {
        const https = draft.value.https_listen;

        if (
            https &&
            typeof https === 'object' &&
            !Array.isArray(https) &&
            Object.keys(https).length &&
            !Number(https.cert)
        ) {
            throw new Error('开启 HTTPS 前请选择证书');
        }
    }
}
async function save() {
    saving.value = true;
    error.value = '';

    try {
        validate();

        if (
            wafDirty.value &&
            Array.isArray(wafDraft.value.libraries) &&
            wafDraft.value.libraries.some(
                (row) =>
                    !row ||
                    typeof row !== 'object' ||
                    Array.isArray(row) ||
                    !Number(row.rule_id),
            )
        ) {
            throw new Error('请选择 WAF 规则库');
        }

        if (Object.keys(patch.value).length) {
            const payload = JSON.parse(JSON.stringify(patch.value));

            for (const key of ['req_header', 'resp_header']) {
                if (Array.isArray(payload[key])) {
                    for (const header of payload[key]) {
                        if (header.action === 'del') {
                            header.value = '1';
                            header.allow_repeat = false;
                        }
                    }
                }
            }

            if (Array.isArray(payload.waf_allow_rule)) {
                for (const rule of payload.waf_allow_rule) {
                    for (const group of rule.matcher_groups ?? []) {
                        for (const matcher of group.matcher ?? []) {
                            if (
                                ['in', '!in', 'ip_range', '!ip_range'].includes(
                                    matcher.op,
                                ) &&
                                typeof matcher.value === 'string'
                            ) {
                                matcher.value = matcher.value
                                    .split(/\r?\n/)
                                    .map((v: string) => v.trim())
                                    .filter(Boolean);
                            }
                        }
                    }
                }
            }

            await apiRequest(endpoint.value, {
                method: 'PUT',
                body: JSON.stringify(payload),
            });
            original.value = JSON.parse(JSON.stringify(draft.value));
        }

        if (wafDirty.value) {
            const rows = Array.isArray(wafDraft.value.libraries)
                ? wafDraft.value.libraries
                : [];
            await apiRequest(wafEndpoint.value, {
                method: 'PUT',
                body: JSON.stringify(
                    rows.map((row, index) => ({
                        ...(row as ConfigObject),
                        sort: index + 1,
                    })),
                ),
            });
            wafOriginal.value = JSON.parse(JSON.stringify(wafDraft.value));
        }

        toast.success('网站配置已保存，正在同步到节点');
        await load();
    } catch (e) {
        error.value = e instanceof Error ? e.message : '保存失败';
    } finally {
        saving.value = false;
    }
}

function discard() {
    wafDraft.value = JSON.parse(JSON.stringify(wafOriginal.value));
    draft.value = JSON.parse(JSON.stringify(original.value));
    error.value = '';
}
const unload = (event: BeforeUnloadEvent) => {
    if (dirty.value) {
        event.preventDefault();
        event.returnValue = '';
    }
};
let removeBefore: (() => void) | undefined;
onMounted(() => {
    void load();
    window.addEventListener('beforeunload', unload);
    removeBefore = router.on(
        'before',
        () => !dirty.value || window.confirm('网站配置尚未保存，确定离开？'),
    );
});
onBeforeUnmount(() => {
    removeBefore?.();
    window.removeEventListener('beforeunload', unload);
});
</script>

<template>
    <div class="site-editor console-page flex flex-1 flex-col gap-5 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <Button variant="ghost" size="icon" as-child
                    ><Link :href="back" aria-label="返回网站列表"
                        ><ArrowLeft class="size-5" /></Link
                ></Button>
                <h1 data-typography="page-title" class="truncate font-semibold">
                    {{ title }}
                </h1>
                <Badge
                    :variant="
                        Number(draft.enable) === 1 ? 'secondary' : 'outline'
                    "
                    >{{
                        Number(draft.enable) === 1 ? '正常运行' : '已停用'
                    }}</Badge
                >
            </div>
            <Button
                variant="outline"
                :disabled="loading || saving || dirty"
                @click="load"
                ><RefreshCw class="size-4" />刷新</Button
            >
        </div>
        <div
            v-if="error"
            role="alert"
            class="rounded-lg border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive"
        >
            {{ error }}
        </div>
        <div
            v-if="referenceError"
            role="status"
            class="rounded-lg border p-3 text-sm text-muted-foreground"
        >
            {{ referenceError }}
        </div>
        <p
            data-typography="body"
            v-if="loading"
            role="status"
            class="p-10 text-center text-muted-foreground"
        >
            正在加载网站配置…
        </p>
        <template v-else-if="original.id">
            <div class="console-panel rounded-xl border bg-card p-5">
                <div
                    class="mb-5 flex flex-wrap gap-x-8 gap-y-2 border-b pb-4 text-sm text-muted-foreground"
                >
                    <span
                        >网站 ID
                        <strong class="ml-2 text-foreground">{{
                            original.id
                        }}</strong></span
                    ><span
                        >CNAME
                        <strong class="ml-2 text-foreground">{{
                            [original.cname_hostname, original.cname_domain]
                                .filter(Boolean)
                                .join('.') || '—'
                        }}</strong></span
                    ><span
                        >同步状态
                        <strong class="ml-2 text-foreground">{{
                            original.sync_state || '—'
                        }}</strong></span
                    >
                </div>
                <ConsoleTabs v-model="tab" :tabs="siteTabs" />
                <form class="mt-5 grid gap-5" @submit.prevent="save">
                    <div
                        v-if="tab === 'basic' || tab === 'https'"
                        class="flex items-center justify-between rounded-lg border p-4"
                    >
                        <span>{{
                            tab === 'basic' ? 'HTTP 访问' : 'HTTPS 访问'
                        }}</span
                        ><Switch
                            role="switch"
                            :aria-label="
                                tab === 'basic' ? '启用 HTTP' : '启用 HTTPS'
                            "
                            :checked="
                                listenerEnabled(
                                    tab === 'basic'
                                        ? 'http_listen'
                                        : 'https_listen',
                                )
                            "
                            @update:checked="
                                toggleListener(
                                    tab === 'basic'
                                        ? 'http_listen'
                                        : 'https_listen',
                                    $event,
                                )
                            "
                        />
                    </div>
                    <section
                        v-for="section in sections"
                        :key="section.title"
                        class="rounded-lg border p-4 md:p-5"
                    >
                        <h2
                            data-typography="section-title"
                            class="mb-1 font-semibold"
                        >
                            {{ section.title }}
                        </h2>
                        <p
                            data-typography="description"
                            v-if="section.help"
                            class="mb-4 text-muted-foreground"
                        >
                            {{ section.help }}
                        </p>
                        <ConfigFields
                            v-model="draft"
                            :fields="section.fields"
                            :disabled="saving"
                            class="mt-4"
                        />
                    </section>
                    <section v-if="tab === 'waf'" class="rounded-lg border p-5">
                        <ConfigFields
                            v-if="wafReady"
                            v-model="wafDraft"
                            :fields="wafFields"
                            :disabled="saving"
                        />
                        <p
                            data-typography="body"
                            v-else
                            class="text-destructive"
                        >
                            规则库加载失败，请刷新重试。
                        </p>
                    </section>
                    <div
                        class="sticky bottom-0 z-10 flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-card p-4 shadow-sm"
                    >
                        <span class="text-sm text-muted-foreground">{{
                            dirty
                                ? `${Object.keys(patch).length} 项配置尚未保存`
                                : '所有更改已保存'
                        }}</span>
                        <div class="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="!dirty || saving"
                                @click="discard"
                                >取消更改</Button
                            ><Button type="submit" :disabled="!dirty || saving"
                                ><Save class="size-4" />{{
                                    saving ? '保存中…' : '保存配置'
                                }}</Button
                            >
                        </div>
                    </div>
                </form>
            </div>
        </template>
    </div>
</template>
