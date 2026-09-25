<script setup lang="ts">
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import CheckboxField from '@/components/ui/checkbox/CheckboxField.vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { apiRequest } from '@/lib/apiRequest';
import { extractCdnflyRows } from '@/lib/cdnflyResponse';
import type { CdnflyRecord } from '@/lib/sharedTypes';
const props = defineProps<{ kind: 'mapping' | 'service'; id: number }>();
const emit = defineEmits<{ updated: [] }>();
const open = ref(false),
    loading = ref(false),
    saving = ref(false),
    acknowledged = ref(false),
    error = ref(''),
    data = ref<CdnflyRecord>({});
const candidate = computed(
        () => data.value.candidate as CdnflyRecord | undefined,
    ),
    local = computed(() => data.value.local as CdnflyRecord | undefined),
    order = computed(() => data.value.order as CdnflyRecord | undefined);
const packages = computed(() => extractCdnflyRows(data.value.remote_packages));
async function review() {
    open.value = true;
    loading.value = true;
    error.value = '';
    acknowledged.value = false;
    data.value = {};

    try {
        data.value = await apiRequest(
            `/api/admin/${props.kind === 'mapping' ? 'users' : 'services'}/${props.id}/${props.kind === 'mapping' ? 'mapping-preview' : 'recovery'}`,
        );
    } catch (e) {
        error.value = e instanceof Error ? e.message : '核对失败';
    } finally {
        loading.value = false;
    }
}
async function execute() {
    saving.value = true;
    error.value = '';

    try {
        if (props.kind === 'mapping') {
            await apiRequest(`/api/admin/users/${props.id}/mapping`, {
                method: 'PUT',
                body: JSON.stringify({
                    cdnfly_user_id: Number(candidate.value?.id),
                }),
            });
        } else {
            await apiRequest(
                `/api/orders/${encodeURIComponent(String(order.value?.order_no))}/provision`,
                { method: 'POST' },
            );
        }

        toast.success(
            props.kind === 'mapping'
                ? '映射已保存，请同步 API 密钥'
                : '开通请求已处理，请核对服务状态',
        );
        open.value = false;
        emit('updated');
    } catch (e) {
        error.value = e instanceof Error ? e.message : '操作失败';
    } finally {
        saving.value = false;
    }
}
</script>
<template>
    <Button size="sm" variant="outline" @click="review">{{
        kind === 'mapping' ? '核对映射' : '检查并重试'
    }}</Button
    ><Dialog v-model:open="open"
        ><DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl"
            ><DialogHeader
                ><DialogTitle>{{
                    kind === 'mapping' ? '账号映射核对' : '服务开通核对'
                }}</DialogTitle
                ><DialogDescription>{{
                    kind === 'mapping'
                        ? '按邮箱核对现有主控账号。'
                        : '核对订单与已售套餐后，再决定是否重试。'
                }}</DialogDescription></DialogHeader
            >
            <p
                data-typography="body"
                v-if="loading"
                class="py-8 text-center text-muted-foreground"
            >
                正在读取最新数据…
            </p>
            <p
                data-typography="body"
                v-if="error"
                role="alert"
                class="text-destructive"
            >
                {{ error }}
            </p>
            <template v-if="!loading && Object.keys(data).length"
                ><template v-if="kind === 'mapping'"
                    ><div class="rounded-lg border p-4 text-sm">
                        <p data-typography="body">
                            本地 #{{ local?.id }} · {{ local?.name }}
                        </p>
                        <p
                            data-typography="body"
                            class="mt-2 text-muted-foreground"
                        >
                            {{ local?.email }}
                        </p>
                        <p data-typography="body" class="mt-2">
                            当前主控 ID：{{ local?.cdnfly_user_id ?? '未绑定' }}
                        </p>
                    </div>
                    <div
                        v-if="candidate"
                        class="rounded-lg border bg-muted/30 p-4 text-sm"
                    >
                        <p data-typography="body">
                            匹配主控 #{{ candidate.id }} ·
                            {{ candidate.username }}
                        </p>
                        <p data-typography="body" class="mt-2">
                            {{ candidate.email }}
                        </p>
                        <p
                            data-typography="body"
                            v-if="candidate.linked_local_user_id"
                            class="mt-2 text-destructive"
                        >
                            已绑定本地用户 #{{ candidate.linked_local_user_id }}
                        </p>
                    </div>
                    <p
                        data-typography="body"
                        v-else
                        class="text-muted-foreground"
                    >
                        没有同邮箱账号。可关闭此窗口后使用“同步 API
                        密钥”创建账号。
                    </p></template
                ><template v-else
                    ><dl
                        class="grid grid-cols-2 gap-3 rounded-lg border p-4 text-sm"
                    >
                        <dt>订单</dt>
                        <dd>{{ order?.order_no ?? '—' }}</dd>
                        <dt>支付状态</dt>
                        <dd>{{ order?.status ?? '—' }}</dd>
                        <dt>已向主控入账</dt>
                        <dd>
                            {{
                                order?.balance_credited_at ?? '尚无成功入账记录'
                            }}
                        </dd>
                        <dt>主控用户 ID</dt>
                        <dd>{{ data.cdnfly_user_id ?? '未映射' }}</dd>
                        <dt>API 密钥</dt>
                        <dd>
                            {{ data.credentials_ready ? '已就绪' : '未就绪' }}
                        </dd>
                    </dl>
                    <p
                        data-typography="body"
                        class="break-words text-destructive"
                    >
                        {{ data.error }}
                    </p>
                    <h3 data-typography="section-title" class="font-semibold">
                        该用户已有主控套餐（{{ packages.length }}）
                    </h3>
                    <div class="max-h-52 overflow-auto rounded-lg border">
                        <table class="w-full text-sm">
                            <tbody>
                                <tr
                                    v-for="item in packages"
                                    :key="String(item.id)"
                                    class="border-b"
                                >
                                    <td class="p-3">#{{ item.id }}</td>
                                    <td class="p-3">
                                        {{ item.name ?? item.package_name }}
                                    </td>
                                    <td class="p-3">
                                        {{ item.create_at ?? item.created_at }}
                                    </td>
                                </tr>
                                <tr v-if="!packages.length">
                                    <td
                                        class="p-5 text-center text-muted-foreground"
                                    >
                                        暂无已售套餐
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <label v-if="data.can_retry" class="flex gap-3 text-sm"
                        ><CheckboxField
                            v-model="acknowledged"
                            class="mt-1"
                        />已核对主控套餐与充值记录，确认没有重复开通或未记账的入账。</label
                    >
                    <p
                        data-typography="body"
                        v-else
                        class="text-muted-foreground"
                    >
                        当前订单或服务状态不满足重试条件，请先处理映射或开通状态。
                    </p></template
                ></template
            ><DialogFooter
                ><Button
                    variant="outline"
                    :disabled="saving"
                    @click="open = false"
                    >关闭</Button
                ><Button
                    v-if="
                        kind === 'mapping' &&
                        candidate &&
                        !candidate.linked_local_user_id &&
                        !local?.cdnfly_user_id
                    "
                    :disabled="saving || loading"
                    @click="execute"
                    >使用此映射</Button
                ><Button
                    v-if="kind === 'service' && data.can_retry"
                    :disabled="saving || !acknowledged"
                    @click="execute"
                    >{{ saving ? '处理中…' : '重试开通' }}</Button
                ></DialogFooter
            ></DialogContent
        ></Dialog
    >
</template>
