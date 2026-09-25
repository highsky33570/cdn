<script setup lang="ts">
import { RefreshCw } from 'lucide-vue-next';
import { onMounted, onUnmounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import CheckboxField from '@/components/ui/checkbox/CheckboxField.vue';
import { Spinner } from '@/components/ui/spinner';
import { getErrorMessage } from '@/lib/cdnRecord';
import {
    extractCdnflyRows,
    getUserMessageSubscriptions,
    updateUserMessageSubscription,
} from '@/lib/cdnUserApi';

type Channel = 'phone' | 'email';
type Subscription = { type: string; phone: boolean; email: boolean };
type SaveError = { message: string; channel: Channel; value: boolean };
const labels: Record<string, string> = {
    'package-expire': '套餐到期',
    'traffic-exceed': '流量超限',
    'connection-exceed': '连接数超限',
    'bandwidth-exceed': '带宽超限',
    'cc-switch': '防护规则切换',
    'cert-expire': '证书到期',
    'package-expiring': '套餐即将到期',
    'traffic-exceeding': '流量即将用尽',
    'cert-expiring': '证书即将到期',
    'account-auth2': '二次实名',
    announcement: '公告',
    notice: '系统通知',
};
const order = Object.keys(labels);
const rows = ref<Subscription[]>([]),
    loading = ref(false),
    error = ref('');
const saving = reactive<Record<string, boolean>>({});
const saveErrors = reactive<Record<string, SaveError>>({});
const saved = ref('');
let token = 0,
    disposed = false;
const enabled = (value: unknown) =>
    value === true || value === 1 || value === '1';
async function load() {
    const current = ++token;
    loading.value = true;
    error.value = '';

    try {
        const result = await getUserMessageSubscriptions();

        if (disposed || current !== token) {
            return;
        }

        rows.value = extractCdnflyRows(result)
            .map((row) => ({
                type: String(row.msg_type ?? row.type ?? row.name ?? ''),
                phone: enabled(row.phone),
                email: enabled(row.email),
            }))
            .filter((row) => row.type !== '')
            .sort((a, b) => {
                const aIndex = order.indexOf(a.type),
                    bIndex = order.indexOf(b.type);

                return (
                    (aIndex < 0 ? order.length : aIndex) -
                    (bIndex < 0 ? order.length : bIndex)
                );
            });
    } catch (cause) {
        if (!disposed && current === token) {
            error.value = getErrorMessage(cause);
        }
    } finally {
        if (!disposed && current === token) {
            loading.value = false;
        }
    }
}
async function save(row: Subscription, channel: Channel, value: boolean) {
    if (saving[row.type] || loading.value || row[channel] === value) {
        return;
    }

    const previous = row[channel];
    row[channel] = value;
    saving[row.type] = true;
    delete saveErrors[row.type];
    saved.value = '';

    try {
        await updateUserMessageSubscription({
            msg_type: row.type,
            phone: row.phone,
            email: row.email,
        });

        if (!disposed) {
            saved.value = `${labels[row.type] || row.type}的${channel === 'phone' ? '手机提醒' : '邮件提醒'}已保存`;
            toast.success(saved.value);
        }
    } catch (cause) {
        row[channel] = previous;

        if (!disposed) {
            saveErrors[row.type] = {
                message: getErrorMessage(cause),
                channel,
                value,
            };
        }
    } finally {
        saving[row.type] = false;
    }
}
function change(row: Subscription, channel: Channel, event: Event) {
    void save(row, channel, (event.target as HTMLInputElement).checked);
}
function retry(row: Subscription) {
    const attempt = saveErrors[row.type];

    if (attempt) {
        void save(row, attempt.channel, attempt.value);
    }
}
onMounted(load);
onUnmounted(() => {
    disposed = true;
    token++;
});
</script>

<template>
    <section class="message-subscriptions" aria-label="消息订阅">
        <Alert v-if="error" variant="destructive" class="mb-4"
            ><AlertDescription
                >{{ error
                }}<Button variant="outline" @click="load"
                    ><RefreshCw class="size-4" />重试</Button
                ></AlertDescription
            ></Alert
        >
        <div class="subscription-scroll" :aria-busy="loading">
            <table>
                <colgroup>
                    <col style="width: 33.333%" />
                    <col style="width: 33.333%" />
                    <col style="width: 33.333%" />
                </colgroup>
                <thead>
                    <tr>
                        <th scope="col">消息类型</th>
                        <th scope="col">手机提醒</th>
                        <th scope="col">邮件提醒</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="3" class="empty">
                            <Spinner class="inline size-4" /> 加载中…
                        </td>
                    </tr>
                    <tr v-else-if="!rows.length">
                        <td colspan="3" class="empty">
                            {{ error ? '加载失败，请重试' : '暂无订阅记录' }}
                        </td>
                    </tr>
                    <template v-else
                        ><tr
                            v-for="row in rows"
                            :key="row.type"
                            :aria-busy="Boolean(saving[row.type])"
                        >
                            <td>
                                <span>{{ labels[row.type] || row.type }}</span>
                                <div
                                    v-if="saveErrors[row.type]"
                                    class="save-error"
                                    role="alert"
                                >
                                    <span>{{
                                        saveErrors[row.type].message
                                    }}</span
                                    ><button
                                        type="button"
                                        :aria-label="`重试保存${labels[row.type] || row.type}`"
                                        @click="retry(row)"
                                    >
                                        重试
                                    </button>
                                </div>
                            </td>
                            <td>
                                <CheckboxField
                                    :checked="row.phone"
                                    :disabled="saving[row.type]"
                                    :aria-label="`${labels[row.type] || row.type}手机提醒`"
                                    @change="change(row, 'phone', $event)"
                                />
                            </td>
                            <td>
                                <CheckboxField
                                    :checked="row.email"
                                    :disabled="saving[row.type]"
                                    :aria-label="`${labels[row.type] || row.type}邮件提醒`"
                                    @change="change(row, 'email', $event)"
                                />
                            </td></tr
                    ></template>
                </tbody>
            </table>
        </div>
        <p class="sr-only" role="status" aria-live="polite">{{ saved }}</p>
    </section>
</template>

<style scoped>
.message-subscriptions {
    min-width: 0;
    margin: 16px;
    padding: 10px 8px 0;
    background: var(--card);
    color: var(--foreground);
    font-size: 14px;
}
.subscription-scroll {
    border-bottom: 1px solid var(--input);
}
table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}
th,
td {
    padding: 0 22px;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
    text-align: left;
}
th {
    height: 48px;
    background: var(--muted);
    font-weight: 600;
}
td {
    height: 60px;
    overflow-wrap: anywhere;
}
tbody tr:hover {
    background: var(--accent);
}
:deep([data-slot='checkbox']) {
    display: block;
    width: 20px;
    height: 20px;
    accent-color: var(--primary);
    cursor: pointer;
}
:deep([data-slot='checkbox']):disabled {
    cursor: wait;
    opacity: 0.6;
}
:deep([data-slot='checkbox']):focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 3px;
}
.empty {
    text-align: center;
}
.save-error {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding-block: 6px;
    color: var(--destructive);
    font-size: 12px;
}
.save-error button {
    color: var(--primary);
    cursor: pointer;
}
@media (max-width: 640px) {
    .message-subscriptions {
        margin: 8px;
        padding: 10px 8px 0;
    }
    th,
    td {
        padding-inline: 10px;
    }
}
</style>
