<script setup lang="ts">
import { Check, CircleX, RefreshCw } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import PackagePagination from '@/components/console/PackagePagination.vue';
import { Button } from '@/components/ui/button';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import SelectField from '@/components/ui/select/SelectField.vue';
import SelectOption from '@/components/ui/select/SelectOption.vue';
import { Spinner } from '@/components/ui/spinner';
import { formatDate, getErrorMessage } from '@/lib/cdnRecord';
import {
    listBillingOrders,
    provisionBillingOrder,
} from '@/lib/localBillingApi';
import type { LocalBillingOrder } from '@/lib/localBillingApi';

const type = ref('all'),
    status = ref('all'),
    start = ref(''),
    end = ref('');
const page = ref(1),
    size = ref(10),
    total = ref(0);
const rows = ref<LocalBillingOrder[]>([]),
    loading = ref(false),
    error = ref('');
const selected = ref<LocalBillingOrder | null>(null),
    detailOpen = ref(false),
    submitting = ref(false),
    actionError = ref('');
const types: Record<string, string> = {
    new: '购买',
    renew: '续费',
    recharge: '充值',
};
const statuses: Record<string, string> = {
    pending: '待支付',
    paid: '已支付',
    provisioning: '开通中',
    active: '已完成',
    failed: '开通失败',
    expired: '已过期',
    cancelled: '已取消',
};
const hasFilters = computed(
    () =>
        type.value !== 'all' ||
        status.value !== 'all' ||
        start.value ||
        end.value,
);
let requestId = 0;
async function load() {
    const id = ++requestId;
    loading.value = true;
    error.value = '';
    rows.value = [];

    try {
        const params: Record<string, string | number> = {
            page: page.value,
            limit: size.value,
        };

        if (type.value !== 'all') {
            params.order_type = type.value;
        }

        if (status.value !== 'all') {
            params.status = status.value;
        }

        if (start.value && end.value) {
            params.start = start.value.slice(0, 10);
            params.end = end.value.slice(0, 10);
        }

        const result = await listBillingOrders(params);

        if (id !== requestId) {
            return;
        }

        rows.value = result.items;
        total.value = result.total;
    } catch (cause) {
        if (id === requestId) {
            error.value = getErrorMessage(cause);
            total.value = 0;
        }
    } finally {
        if (id === requestId) {
            loading.value = false;
        }
    }
}
watch([type, status, start, end, size], () => {
    if (page.value !== 1) {
        page.value = 1;
    } else {
        void load();
    }
});
watch(page, load);
onMounted(load);
onUnmounted(() => {
    requestId++;
});
function clear() {
    type.value = 'all';
    status.value = 'all';
    start.value = '';
    end.value = '';
}
function paid(order: LocalBillingOrder) {
    return (
        Boolean(order.paid_at) ||
        ['paid', 'provisioning', 'active'].includes(order.status)
    );
}
function money(value: unknown, currency: string | null | undefined): string {
    if (
        value === null ||
        value === undefined ||
        value === '' ||
        !Number.isFinite(Number(value))
    ) {
        return '—';
    }

    return `${Number(value).toLocaleString('zh-CN', { maximumFractionDigits: 8 })}${currency === 'CNY' ? '元' : ` ${currency || 'USDT'}`}`;
}
function original(order: LocalBillingOrder) {
    return order.fiat_amount != null
        ? money(order.fiat_amount, order.fiat_currency)
        : money(order.amount_usdt, 'USDT');
}
function actual(order: LocalBillingOrder) {
    return money(order.actual_paid_amount, order.pay_currency);
}
function note(order: LocalBillingOrder) {
    return (
        order.service_instance?.queue_reason ||
        order.service_instance?.last_error ||
        ''
    );
}
function more(order: LocalBillingOrder) {
    return (
        order.product_name ||
        (order.order_type === 'recharge' ? '账户充值' : '订单详情')
    );
}
function showDetails(order: LocalBillingOrder) {
    selected.value = order;
    actionError.value = '';
    detailOpen.value = true;
}
async function retry() {
    if (!selected.value || submitting.value) {
        return;
    }

    submitting.value = true;
    actionError.value = '';

    try {
        await provisionBillingOrder(selected.value.order_no);
        toast.success('已提交处理请求');
        detailOpen.value = false;
        await load();
    } catch (cause) {
        actionError.value = getErrorMessage(cause);
    } finally {
        submitting.value = false;
    }
}
function pay() {
    if (selected.value?.gateway_payment_url) {
        window.location.assign(selected.value.gateway_payment_url);
    }
}
</script>

<template>
    <section class="console-user-orders user-orders" aria-label="消费记录">
        <div class="order-filters">
            <SelectField v-model="type" aria-label="订单类型">
                <SelectOption value="all">所有类型</SelectOption>
                <SelectOption
                    v-for="(label, value) in types"
                    :key="value"
                    :value="value"
                >
                    {{ label }}
                </SelectOption>
            </SelectField>
            <SelectField v-model="status" aria-label="订单状态">
                <SelectOption value="all">所有状态</SelectOption>
                <SelectOption
                    v-for="(label, value) in statuses"
                    :key="value"
                    :value="value"
                >
                    {{ label }}
                </SelectOption>
            </SelectField>
            <DateRangePicker
                :key="`${!start && !end}`"
                v-model:start="start"
                v-model:end="end"
                placeholder="请选择时间范围"
                trigger-class="order-date"
            />
            <Button
                variant="link"
                size="inline"
                type="button"
                data-slot="console-link"
                v-if="hasFilters"
                class="text-link"
                @click="clear"
            >
                清除
            </Button>
        </div>
        <div v-if="error" class="order-error" role="alert">
            {{ error }}
            <Button variant="outline" @click="load"
                ><RefreshCw class="size-4" />重试</Button
            >
        </div>
        <div
            class="order-scroll"
            tabindex="0"
            aria-label="订单表格，可横向滚动"
            :aria-busy="loading"
        >
            <table>
                <colgroup>
                    <col style="width: 80px" />
                    <col style="width: 110px" />
                    <col style="width: 140px" />
                    <col style="width: 135px" />
                    <col style="width: 145px" />
                    <col style="width: 140px" />
                    <col style="width: 130px" />
                    <col style="width: 190px" />
                    <col style="width: 215px" />
                    <col style="width: 100px" />
                </colgroup>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>类型</th>
                        <th>备注</th>
                        <th>原价</th>
                        <th>实际支付</th>
                        <th>更多</th>
                        <th>支付方式</th>
                        <th>订单号</th>
                        <th>创建时间</th>
                        <th>已付款</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="10" class="empty">
                            <span
                                ><Spinner class="inline size-4" /> 加载中…</span
                            >
                        </td>
                    </tr>
                    <tr v-else-if="!rows.length">
                        <td colspan="10" class="empty">
                            <span>{{
                                error ? '加载失败，请重试' : '暂无数据'
                            }}</span>
                        </td>
                    </tr>
                    <tr v-for="order in rows" v-else :key="order.id">
                        <td>{{ order.id }}</td>
                        <td>
                            {{
                                types[order.order_type || ''] ||
                                order.order_type ||
                                '—'
                            }}
                        </td>
                        <td>
                            <span class="truncate-cell" :title="note(order)">{{
                                note(order)
                            }}</span>
                        </td>
                        <td>{{ original(order) }}</td>
                        <td>{{ actual(order) }}</td>
                        <td>
                            <Button
                                variant="link"
                                size="inline"
                                type="button"
                                data-slot="console-link"
                                class="text-link truncate-cell"
                                :aria-label="`查看订单 ${order.order_no}`"
                                :title="more(order)"
                                @click="showDetails(order)"
                            >
                                {{ more(order) }}
                            </Button>
                        </td>
                        <td>
                            {{ order.gateway_provider?.toUpperCase() || '—' }}
                        </td>
                        <td>
                            <span
                                class="truncate-cell"
                                :title="order.order_no"
                                >{{ order.order_no }}</span
                            >
                        </td>
                        <td>{{ formatDate(order.created_at) }}</td>
                        <td>
                            <span
                                v-if="paid(order)"
                                class="paid-icon"
                                role="img"
                                aria-label="已付款"
                                :title="statuses[order.status] || order.status"
                                ><Check :size="11" /></span
                            ><CircleX
                                v-else
                                :size="16"
                                class="unpaid-icon"
                                role="img"
                                :aria-label="statuses[order.status] || '未付款'"
                                :title="statuses[order.status] || '未付款'"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <PackagePagination
            v-model:page="page"
            v-model:page-size="size"
            :total="total"
            :disabled="loading"
            numbered
            edge-links
            class="order-pagination"
        />
        <Dialog v-model:open="detailOpen">
            <DialogScrollContent
                v-if="selected"
                class="console-user-orders sm:max-w-xl"
            >
                <DialogHeader
                    ><DialogTitle>订单详情</DialogTitle
                    ><DialogDescription class="break-all">{{
                        selected.order_no
                    }}</DialogDescription></DialogHeader
                >
                <dl class="order-detail">
                    <dt>类型</dt>
                    <dd>
                        {{
                            types[selected.order_type || ''] ||
                            selected.order_type ||
                            '—'
                        }}
                    </dd>
                    <dt>状态</dt>
                    <dd>{{ statuses[selected.status] || selected.status }}</dd>
                    <dt>商品</dt>
                    <dd>{{ selected.product_name || '—' }}</dd>
                    <dt>计费周期</dt>
                    <dd>{{ selected.billing_cycle || '—' }}</dd>
                    <dt>原价</dt>
                    <dd>{{ original(selected) }}</dd>
                    <dt>实际支付</dt>
                    <dd>{{ actual(selected) }}</dd>
                    <dt>支付方式</dt>
                    <dd>{{ selected.gateway_provider || '—' }}</dd>
                    <dt>交易号</dt>
                    <dd>{{ selected.gateway_trade_id || '—' }}</dd>
                    <dt>创建时间</dt>
                    <dd>{{ formatDate(selected.created_at) }}</dd>
                    <dt>付款时间</dt>
                    <dd>{{ formatDate(selected.paid_at) }}</dd>
                    <dt>备注</dt>
                    <dd>{{ note(selected) || '—' }}</dd>
                </dl>
                <p
                    data-typography="body"
                    v-if="actionError"
                    role="alert"
                    class="text-destructive"
                >
                    {{ actionError }}
                </p>
                <DialogFooter
                    ><Button
                        variant="outline"
                        :disabled="submitting"
                        @click="detailOpen = false"
                        >关闭</Button
                    ><Button
                        v-if="
                            selected.status === 'pending' &&
                            selected.gateway_payment_url
                        "
                        @click="pay"
                        >继续支付</Button
                    ><Button
                        v-else-if="
                            selected.status === 'paid' &&
                            !selected.provisioned_at
                        "
                        :disabled="submitting"
                        @click="retry"
                        ><Spinner v-if="submitting" />重试处理</Button
                    ></DialogFooter
                >
            </DialogScrollContent>
        </Dialog>
    </section>
</template>
