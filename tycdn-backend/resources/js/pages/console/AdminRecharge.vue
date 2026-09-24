<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import CertificateUserPicker from '@/components/console/CertificateUserPicker.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { apiRequest } from '@/lib/apiRequest';
import { getErrorMessage } from '@/lib/formatters';

const type = ref('add'),
    uid = ref(''),
    amount = ref(''),
    remark = ref('');
const saving = ref(false),
    error = ref(''),
    success = ref('');
watch([type, uid, amount, remark], () => {
    error.value = '';
    success.value = '';
});
async function submit() {
    if (saving.value) {
        return;
    }

    error.value = '';
    success.value = '';

    if (!uid.value) {
        error.value = '请选择用户';

        return;
    }

    const value = String(amount.value).trim();

    if (
        !/^\d+(\.\d{1,2})?$/.test(value) ||
        !Number.isFinite(Number(value)) ||
        Number(value) <= 0
    ) {
        error.value = '请输入大于 0 的金额，最多两位小数';

        return;
    }

    saving.value = true;

    try {
        await apiRequest('/api/admin/finance/recharge', {
            method: 'POST',
            body: JSON.stringify({
                uid: uid.value,
                type: type.value,
                amount: value,
                des: remark.value.trim(),
            }),
        });
        amount.value = '';
        remark.value = '';
        // Set the result after the reset watchers have cleared prior feedback.
        const message = type.value === 'add' ? '充值成功' : '扣款成功';
        await nextTick();
        success.value = message;
    } catch (e) {
        error.value = getErrorMessage(e);
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="recharge-workspace min-w-0 p-4 md:p-6">
        <section
            class="console-panel rounded-xl border bg-card p-4 text-card-foreground md:p-5"
            aria-labelledby="recharge-title"
        >
            <h2 id="recharge-title" class="text-lg font-semibold">余额调整</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                充值或扣款后会写入用户余额记录。
            </p>
            <form
                class="mt-6 max-w-[600px]"
                :aria-busy="saving"
                @submit.prevent="submit"
            >
                <fieldset :disabled="saving" class="space-y-7">
                    <div
                        class="grid grid-cols-[64px_minmax(0,1fr)] items-center gap-4"
                    >
                        <span
                            id="recharge-type-label"
                            class="text-right text-sm"
                            >类型：</span
                        >
                        <div
                            class="flex gap-3"
                            role="group"
                            aria-labelledby="recharge-type-label"
                        >
                            <Button
                                type="button"
                                variant="outline"
                                :aria-pressed="type === 'add'"
                                :class="
                                    type === 'add'
                                        ? 'border-primary bg-accent text-primary'
                                        : ''
                                "
                                @click="type = 'add'"
                                >充值</Button
                            >
                            <Button
                                type="button"
                                variant="outline"
                                :aria-pressed="type === 'reduce'"
                                :class="
                                    type === 'reduce'
                                        ? 'border-primary bg-accent text-primary'
                                        : ''
                                "
                                @click="type = 'reduce'"
                                >扣款</Button
                            >
                        </div>
                    </div>
                    <div
                        class="grid grid-cols-[64px_minmax(0,1fr)] items-center gap-4"
                    >
                        <Label for="recharge-user" class="justify-end"
                            >用户：</Label
                        >
                        <CertificateUserPicker
                            v-model="uid"
                            input-id="recharge-user"
                            :disabled="saving"
                            style="width: 100%"
                        />
                    </div>
                    <div
                        class="grid grid-cols-[64px_minmax(0,1fr)] items-center gap-4"
                    >
                        <Label for="recharge-amount" class="justify-end"
                            >金额：</Label
                        >
                        <div class="flex min-w-0">
                            <Input
                                id="recharge-amount"
                                v-model="amount"
                                inputmode="decimal"
                                autocomplete="off"
                                class="min-w-0 rounded-r-none"
                                aria-describedby="recharge-currency"
                            />
                            <span
                                id="recharge-currency"
                                class="flex items-center rounded-r-md border border-l-0 bg-muted/40 px-3 text-sm text-muted-foreground"
                                >元</span
                            >
                        </div>
                    </div>
                    <div
                        class="grid grid-cols-[64px_minmax(0,1fr)] items-start gap-4"
                    >
                        <Label for="recharge-remark" class="justify-end pt-2"
                            >备注：</Label
                        >
                        <textarea
                            id="recharge-remark"
                            v-model="remark"
                            rows="3"
                            placeholder="请输入备注"
                            class="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50 disabled:opacity-50"
                        />
                    </div>
                    <div class="grid grid-cols-[64px_minmax(0,1fr)] gap-4">
                        <div class="col-start-2 space-y-3">
                            <Alert v-if="error" variant="destructive"
                                ><AlertDescription>{{
                                    error
                                }}</AlertDescription></Alert
                            >
                            <p
                                v-if="success"
                                role="status"
                                class="text-sm text-primary"
                            >
                                {{ success }}
                            </p>
                            <Button type="submit" :disabled="saving"
                                ><Spinner v-if="saving" class="mr-2" />{{
                                    saving ? '提交中…' : '确定'
                                }}</Button
                            >
                        </div>
                    </div>
                </fieldset>
            </form>
        </section>
    </div>
</template>
