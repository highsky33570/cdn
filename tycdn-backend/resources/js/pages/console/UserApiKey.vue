<script setup lang="ts">
import { Copy, RefreshCw } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
import { Spinner } from '@/components/ui/spinner';
import Switch from '@/components/ui/switch/Switch.vue';
import { getErrorMessage, textValue } from '@/lib/cdnRecord';
import {
    createUserApiKey,
    deleteUserApiKey,
    extractCdnflyRecord,
    getUserApiKey,
    updateUserApiKey,
} from '@/lib/cdnUserApi';
import type { CdnflyRecord } from '@/lib/cdnUserApi';

const record = ref<CdnflyRecord | null>(null),
    loading = ref(false),
    saving = ref(false),
    loaded = ref(false),
    error = ref(''),
    ip = ref('');
let disposed = false;
function field(keys: string[]) {
    for (const name of keys) {
        const value = textValue(record.value?.[name]);

        if (value && value !== '-') {
            return value;
        }
    }

    return '';
}
const key = computed(() => field(['api_key', 'apiKey', 'key', 'access_key']));
const secret = computed(() =>
    field(['api_secret', 'apiSecret', 'secret', 'access_secret']),
);
const savedIp = computed(() => field(['api_ip', 'ip', 'white_ip', 'whiteIp']));
const enabled = computed(() => Boolean(key.value));
const dirty = computed(() => ip.value.trim() !== savedIp.value);
const busy = computed(() => loading.value || saving.value);
function accept(value: unknown) {
    record.value = extractCdnflyRecord(value);
    ip.value = savedIp.value;
    loaded.value = true;
}
async function load() {
    if (busy.value) {
        return;
    }

    loading.value = true;
    error.value = '';

    try {
        const result = await getUserApiKey();

        if (!disposed) {
            accept(result);
        }
    } catch (cause) {
        if (!disposed) {
            error.value = getErrorMessage(cause);
        }
    } finally {
        loading.value = false;
    }
}
async function mutate(action: 'enable' | 'disable' | 'reset' | 'ip') {
    if (busy.value || !loaded.value) {
        return;
    }

    saving.value = true;
    error.value = '';
    let committed = false;

    try {
        if (action === 'disable') {
            await deleteUserApiKey();

            if (!disposed) {
                accept(null);
            }
        } else {
            const result =
                action === 'enable'
                    ? await createUserApiKey()
                    : await updateUserApiKey(
                          action === 'reset'
                              ? { reset: true }
                              : { ip: ip.value.trim() },
                      );
            committed = true;
            const data = extractCdnflyRecord(result);
            // Some native writes return no data; confirm the current configuration.
            const complete =
                data &&
                (data.api_key || data.apiKey || data.key || data.access_key) &&
                (data.api_secret ||
                    data.apiSecret ||
                    data.secret ||
                    data.access_secret);
            const current = complete ? result : await getUserApiKey();

            if (!disposed) {
                accept(current);

                if (!key.value || !secret.value) {
                    throw new Error('未能读取完整密钥');
                }
            }
        }

        if (!disposed) {
            toast.success(
                action === 'ip'
                    ? 'IP白名单已保存'
                    : action === 'reset'
                      ? '密钥已重置'
                      : action === 'enable'
                        ? '密钥已启用'
                        : '密钥已停用',
            );
        }
    } catch (cause) {
        if (!disposed) {
            if (committed) {
                record.value = null;
                loaded.value = false;
                error.value = '设置已提交，但读取最新密钥失败，请刷新重试。';
            } else {
                error.value = getErrorMessage(cause);
            }
        }
    } finally {
        saving.value = false;
    }
}
function toggle() {
    if (!dirty.value) {
        void mutate(enabled.value ? 'disable' : 'enable');
    }
}
function saveIp() {
    if (dirty.value && enabled.value) {
        void mutate('ip');
    }
}
async function copy(value: string, label: string) {
    try {
        await navigator.clipboard.writeText(value);
        toast.success(`${label}已复制`);
    } catch {
        toast.error('复制失败，请手动选择文本');
    }
}
onMounted(load);
onUnmounted(() => {
    disposed = true;
});
</script>

<template>
    <section class="user-api-key" aria-label="API密钥管理" :aria-busy="busy">
        <Alert v-if="error" variant="destructive" class="key-error"
            ><AlertDescription
                >{{ error
                }}<Button
                    v-if="!loaded"
                    variant="outline"
                    :disabled="busy"
                    @click="load"
                    ><RefreshCw class="size-4" />重试</Button
                ></AlertDescription
            ></Alert
        >
        <div class="key-row">
            <span id="key-status-label" class="key-label">密钥状态</span>
            <div class="key-value">
                <Switch
                    class="h-[26px] w-[54px] [&_[data-slot=switch-thumb]]:size-[22px] [&_[data-slot=switch-thumb][data-state=checked]]:translate-x-7"
                    aria-labelledby="key-status-label"
                    :checked="enabled"
                    :disabled="busy || !loaded || dirty"
                    @update:checked="toggle"
                /><Spinner v-if="busy" class="size-4" />
            </div>
        </div>
        <div class="key-row">
            <span class="key-label">api_key</span>
            <div class="key-value">
                <span class="credential">{{
                    loaded ? key || '—' : loading ? '加载中…' : '—'
                }}</span
                ><button
                    v-if="key"
                    type="button"
                    class="copy-key"
                    aria-label="复制 api_key"
                    title="复制 api_key"
                    :disabled="busy"
                    @click="copy(key, 'api_key')"
                >
                    <Copy :size="14" />
                </button>
            </div>
        </div>
        <div class="key-row">
            <span class="key-label">api_secret</span>
            <div class="key-value">
                <span class="credential">{{
                    loaded ? secret || '—' : loading ? '加载中…' : '—'
                }}</span
                ><button
                    v-if="secret"
                    type="button"
                    class="copy-key"
                    aria-label="复制 api_secret"
                    title="复制 api_secret"
                    :disabled="busy"
                    @click="copy(secret, 'api_secret')"
                >
                    <Copy :size="14" />
                </button>
            </div>
        </div>
        <form class="key-row whitelist-row" @submit.prevent="saveIp">
            <label class="key-label" for="key-whitelist">IP白名单</label>
            <div class="whitelist-control">
                <Input
                    id="key-whitelist"
                    v-model="ip"
                    maxlength="2000"
                    :disabled="busy || !loaded || !enabled"
                    placeholder="多个IP以,分隔"
                    autocomplete="off"
                    spellcheck="false"
                /><Button
                    v-if="dirty"
                    type="submit"
                    class="save-ip"
                    :disabled="busy"
                    >保存</Button
                >
            </div>
        </form>
        <div class="key-actions">
            <Button
                class="reset-key"
                :disabled="busy || !loaded || !enabled || dirty"
                @click="mutate('reset')"
                >重置密钥</Button
            >
        </div>
    </section>
</template>

<style scoped>
.user-api-key {
    min-width: 0;
    margin: 16px;
    padding: 0 14px 38px;
    background: #fff;
    color: #526078;
    font-size: 14px;
}
.key-row {
    display: flex;
    align-items: center;
    min-height: 72px;
    gap: 16px;
}
.key-label {
    width: 74px;
    flex-shrink: 0;
    text-align: right;
}
.key-value {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}
.credential {
    overflow-wrap: anywhere;
    min-width: 0;
}
.copy-key {
    flex-shrink: 0;
    color: #308cff;
    cursor: pointer;
    padding: 4px;
}
.copy-key:disabled {
    opacity: 0.5;
    cursor: wait;
}
.copy-key:focus-visible {
    outline: 2px solid #308cff;
    outline-offset: 3px;
}
.whitelist-control {
    display: flex;
    min-width: 0;
    flex: 1;
    gap: 10px;
}
.whitelist-control input {
    min-width: 0;
    width: 100%;
    height: 40px;
    border: 1px solid #dcdfe6;
    border-radius: 4px;
    padding: 0 10px;
    background: transparent;
    outline: none;
}
.whitelist-control input:focus {
    border-color: #308cff;
}
.whitelist-control input::placeholder {
    color: #bfc5ce;
}
.whitelist-control input:disabled {
    background: #f7f7f9;
    opacity: 0.7;
}
.key-actions {
    margin-left: 90px;
    margin-top: 16px;
}
.user-api-key :deep(.reset-key),
.user-api-key :deep(.save-ip) {
    height: 40px;
    padding: 0 20px;
    background: #308cf0;
    color: white;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 400;
    box-shadow: none;
}
.key-error {
    margin-top: 14px;
}
:global(.dark) .user-api-key {
    background: #18181b;
    color: #cbd5e1;
}
:global(.dark) .whitelist-control input {
    border-color: #3f3f46;
}
:global(.dark) .whitelist-control input:disabled {
    background: #27272a;
}
@media (max-width: 640px) {
    .user-api-key {
        margin: 8px;
        padding-inline: 8px;
    }
    .key-row {
        gap: 10px;
    }
    .key-label {
        width: 74px;
    }
    .key-actions {
        margin-left: 84px;
    }
    .whitelist-control {
        flex-wrap: wrap;
    }
}
</style>
