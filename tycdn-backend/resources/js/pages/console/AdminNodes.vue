<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    Check,
    ChevronDown,
    ChevronRight,
    Copy,
    Pencil,
    Plus,
    RefreshCw,
    Save,
    Server,
    Trash2,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
import ConsolePageHeader from '@/components/console/ConsolePageHeader.vue';
import NodeEditDialog from '@/components/console/NodeEditDialog.vue';
import NodeManagementPanel from '@/components/console/NodeManagementPanel.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import {
    addAdminNodeSubIps,
    assignAdminLines,
    createAdminNodeGroup,
    createAdminRegion,
    listAdminDnsLines,
    listAdminNodeIps,
    deleteAdminNode,
    deleteAdminNodeGroup,
    deleteAdminRegion,
    getAdminNodeInstallCommand,
    initializeAdminPendingNode,
    listAdminLines,
    listAdminNodeGroups,
    listAdminNodes,
    listAdminRegions,
    unassignAdminLines,
    updateAdminNodeGroup,
    updateAdminRegion,
} from '@/lib/adminModulesApi';
import type {
    AdminDnsLine,
    AdminLineAssignment,
    AdminNodeGroupPayload,
    AdminNodeInstallCommand,
    AdminPendingNodeInitPayload,
    AdminRegionPayload,
    CdnflyRecord,
} from '@/lib/adminModulesApi';
import {
    extractCdnflyRows as extractRows,
    extractCdnflyTotal as extractTotal,
} from '@/lib/cdnflyResponse';

const managementPanel = ref<InstanceType<typeof NodeManagementPanel> | null>(
    null,
);

const linkedNodeId = new URLSearchParams(usePage().url.split('?')[1] ?? '').get(
    'node_id',
);
const nodeGroupQuery: Record<string, string | number> =
    linkedNodeId && /^\d+$/.test(linkedNodeId) ? { node_id: linkedNodeId } : {};

type NodeOption = {
    id: string;
    label: string;
};
const installLoading = ref(false);
const initializing = ref(false);
const referencesLoading = ref(false);
const installError = ref('');
const initFormError = ref('');
const referenceError = ref('');
const deleteConfirmOpen = ref(false);
const deleteConfirmTitle = ref('');
const deleteConfirmDesc = ref('');
const deleteConfirmAction = ref<(() => Promise<void>) | null>(null);
const deleteConfirmError = ref('');
const deleteConfirmLoading = ref(false);
const commandCopied = ref(false);
const nodeDialogOpen = ref(false);
const initDialogOpen = ref(false);
const editingNode = ref<CdnflyRecord | null>(null);
const selectedPendingNode = ref<CdnflyRecord | null>(null);
const nodeGroups = ref<CdnflyRecord[]>([]);
const regions = ref<CdnflyRecord[]>([]);
const installInfo = ref<AdminNodeInstallCommand | null>(null);

const initForm = reactive({
    name: '',
    des: '',
    region_id: '',
    type: 'L1' as 'L1' | 'L2',
});
const regionOptions = computed(() => toOptions(regions.value));

const installCommand = computed(() => installInfo.value?.command ?? '');
const installCommandAvailable = computed(() => installCommand.value !== '');

onMounted(() => {
    if (activeTab.value !== 'topology') {
        return;
    }

    void (async () => {
        await Promise.all([
            loadReferenceData(),
            loadRegions(),
            ...(props.resolutionOnly ? [] : [loadNodeGroups()]),
            loadDnsLines(),
        ]);

        if (lineGroupId.value === '' && nodeGroups.value.length > 0) {
            const firstId = asNumber(nodeGroups.value[0].id);

            if (firstId) {
                lineGroupId.value = String(firstId);
            }
        }

        await onLineGroupChange();
    })();
});

function openInstallDialog(): void {
    installDialogOpen.value = true;
    void loadInstallCommand();
}

async function loadNodes(): Promise<void> {
    await managementPanel.value?.refresh();
}

async function loadInstallCommand(): Promise<void> {
    installLoading.value = true;
    installError.value = '';
    commandCopied.value = false;

    try {
        installInfo.value = await getAdminNodeInstallCommand();
    } catch (error) {
        installError.value = getErrorMessage(error);
    } finally {
        installLoading.value = false;
    }
}

async function loadReferenceData(): Promise<void> {
    referencesLoading.value = true;
    referenceError.value = '';

    try {
        const [groupsResult, regionsResult] = await Promise.all([
            listAdminNodeGroups({ page: 1, limit: 200, ...nodeGroupQuery }),
            listAdminRegions({ limit: 0 }),
        ]);

        nodeGroups.value = extractRows(groupsResult);
        regions.value = extractRows(regionsResult);
    } catch (error) {
        referenceError.value = getErrorMessage(error);
    } finally {
        referencesLoading.value = false;
    }
}

function refreshInstallCommand(): void {
    void loadInstallCommand();
}

async function copyInstallCommand(): Promise<void> {
    if (!installCommandAvailable.value) {
        return;
    }

    await navigator.clipboard.writeText(installCommand.value);
    commandCopied.value = true;
    window.setTimeout(() => {
        commandCopied.value = false;
    }, 1800);
}

async function openInitDialog(node: CdnflyRecord): Promise<void> {
    await loadReferenceData();
    selectedPendingNode.value = node;
    initForm.name = '';
    initForm.des = '';
    initForm.region_id =
        regionOptions.value[regionOptions.value.length - 1]?.id ?? '';
    initForm.type = 'L1';
    initFormError.value = '';
    initDialogOpen.value = true;
}

function openEditDialog(node: CdnflyRecord): void {
    editingNode.value = node;
    nodeDialogOpen.value = true;
}

async function submitInitNode(): Promise<void> {
    const pendingNodeId = asNumber(selectedPendingNode.value?.id);
    const regionId = asNumber(initForm.region_id);
    const name = initForm.name.trim();

    if (!pendingNodeId) {
        initFormError.value = '待初始化节点 ID 缺失';

        return;
    }

    if (!regionId) {
        initFormError.value = '请选择区域';

        return;
    }

    if (name === '') {
        initFormError.value = '请填写节点名称';

        return;
    }

    const payload: AdminPendingNodeInitPayload = {
        pending_node_id: pendingNodeId,
        region_id: regionId,
        name,
        des: initForm.des.trim(),
        type: initForm.type,
    };

    initializing.value = true;
    initFormError.value = '';

    try {
        await initializeAdminPendingNode(payload);
        toast.success('节点初始化请求已提交');
        initDialogOpen.value = false;
        await loadNodes();
    } catch (error) {
        initFormError.value = getErrorMessage(error);
    } finally {
        initializing.value = false;
    }
}

const subIpOpen = ref(false);
const subIpNode = ref<CdnflyRecord | null>(null);
const subIpRows = ref<CdnflyRecord[]>([]);
const subIpLoading = ref(false);
const subIpError = ref('');
const subIpInput = ref('');
const subIpSaving = ref(false);
const subIpDeletingId = ref<number | null>(null);

function isMainIp(row: CdnflyRecord): boolean {
    return asNumber(row.id) === asNumber(subIpNode.value?.id);
}

async function openSubIpDialog(node: CdnflyRecord): Promise<void> {
    subIpNode.value = node;
    subIpInput.value = '';
    subIpError.value = '';
    subIpRows.value = [];
    subIpOpen.value = true;
    await loadSubIps();
}

async function loadSubIps(): Promise<void> {
    const id = asNumber(subIpNode.value?.id);

    if (!id) {
        subIpError.value = '节点 ID 缺失';

        return;
    }

    subIpLoading.value = true;
    subIpError.value = '';

    try {
        subIpRows.value = await listAdminNodeIps(id);
    } catch (error) {
        subIpError.value = getErrorMessage(error);
    } finally {
        subIpLoading.value = false;
    }
}

async function submitSubIps(): Promise<void> {
    const id = asNumber(subIpNode.value?.id);

    if (!id) {
        subIpError.value = '节点 ID 缺失';

        return;
    }

    const ips = subIpInput.value
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== '');

    if (ips.length === 0) {
        subIpError.value = '请输入至少一个 IP（每行一个）';

        return;
    }

    subIpSaving.value = true;
    subIpError.value = '';

    try {
        await addAdminNodeSubIps(id, ips);
        toast.success('子 IP 已添加');
        subIpInput.value = '';
        await loadSubIps();
        await loadNodes();
    } catch (error) {
        subIpError.value = getErrorMessage(error);
    } finally {
        subIpSaving.value = false;
    }
}

async function removeSubIp(row: CdnflyRecord): Promise<void> {
    const id = asNumber(row.id);

    if (!id) {
        return;
    }

    subIpDeletingId.value = id;
    subIpError.value = '';

    try {
        await deleteAdminNode(id);
        toast.success('子 IP 已移除');
        await loadSubIps();
        await loadNodes();
    } catch (error) {
        subIpError.value = getErrorMessage(error);
    } finally {
        subIpDeletingId.value = null;
    }
}

function toOptions(records: CdnflyRecord[]): NodeOption[] {
    return records
        .map((record): NodeOption | null => {
            const id = asNumber(record.id);

            if (!id) {
                return null;
            }

            return {
                id: String(id),
                // Lines label themselves with `line_name` (默认/电信/联通…),
                // while regions and node groups use `name`. Without the
                // line_name fallback the 线路 dropdown shows "#1".
                label:
                    textValue(
                        record.name ?? record.title ?? record.line_name,
                    ) || `#${id}`,
            };
        })
        .filter((option): option is NodeOption => option !== null);
}

function asNumber(value: unknown): number | null {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    if (typeof value === 'string' && value.trim() !== '') {
        const parsed = Number(value);

        return Number.isFinite(parsed) ? parsed : null;
    }

    return null;
}

function nullableNumber(value: string | number): number | null {
    const trimmed = String(value).trim();

    if (trimmed === '') {
        return null;
    }

    const parsed = Number(trimmed);

    return Number.isFinite(parsed) ? parsed : null;
}

function idField(value: unknown): string {
    const id = asNumber(value);

    return id === null ? '' : String(id);
}

function textValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value);
}

function getErrorMessage(error: unknown): string {
    return error instanceof Error ? error.message : '请求失败';
}

function nodeName(node: CdnflyRecord): string {
    return (
        textValue(node.name) || textValue(node.ip) || `#${textValue(node.id)}`
    );
}

function pendingNodeIp(node: CdnflyRecord): string {
    return textValue(node.ip) || textValue(node.addr) || '-';
}

// ─── Region CRUD ──────────────────────────────────────
const regionLoading = ref(false);
const regionError = ref('');
const regionDialogOpen = ref(false);
const regionSaving = ref(false);
const regionFormError = ref('');
const editingRegion = ref<CdnflyRecord | null>(null);
const regionPage = ref(1);
const regionTotal = ref<number | null>(null);
const regionRows = ref<CdnflyRecord[]>([]);

const regionForm = reactive({
    name: '',
    des: '',
    sort: '100',
    l2_check_port: '80',
});

const hasRegionPrevPage = computed(() => regionPage.value > 1);
const hasRegionNextPage = computed(() => {
    if (typeof regionTotal.value === 'number') {
        return regionPage.value * 20 < regionTotal.value;
    }

    return regionRows.value.length >= 20;
});

async function loadRegions(targetPage = regionPage.value): Promise<void> {
    regionLoading.value = true;
    regionError.value = '';

    try {
        const result = await listAdminRegions({ page: targetPage, limit: 20 });
        regionRows.value = extractRows(result);
        regionTotal.value = extractTotal(result, regionRows.value.length);
        regionPage.value = targetPage;
    } catch (error) {
        regionError.value = getErrorMessage(error);
    } finally {
        regionLoading.value = false;
    }
}

function openAddRegion(): void {
    editingRegion.value = null;
    regionForm.name = '';
    regionForm.des = '';
    regionForm.sort = '100';
    regionForm.l2_check_port = '80';
    regionFormError.value = '';
    regionDialogOpen.value = true;
}

function openEditRegion(record: CdnflyRecord): void {
    editingRegion.value = record;
    regionForm.name = textValue(record.name);
    regionForm.des = textValue(record.des);
    regionForm.sort = textValue(record.sort) || '100';
    regionForm.l2_check_port = textValue(record.l2_check_port) || '80';
    regionFormError.value = '';
    regionDialogOpen.value = true;
}

async function submitRegion(): Promise<void> {
    if (regionForm.name.trim() === '') {
        regionFormError.value = '区域名称不能为空';

        return;
    }

    const payload: AdminRegionPayload = {
        name: regionForm.name.trim(),
        des: regionForm.des.trim(),
        sort: nullableNumber(regionForm.sort) ?? 100,
        l2_check_port: Number(regionForm.l2_check_port) || 80,
    };

    regionSaving.value = true;
    regionFormError.value = '';

    try {
        if (editingRegion.value) {
            const id = asNumber(editingRegion.value.id);

            if (!id) {
                regionFormError.value = '区域 ID 缺失';

                return;
            }

            await updateAdminRegion(id, payload);
            toast.success('区域已更新');
        } else {
            await createAdminRegion(payload);
            toast.success('区域已创建');
        }

        regionDialogOpen.value = false;

        if (managementPanel.value) {
            await managementPanel.value.reload();
        } else {
            await loadRegions();
        }

        // Refresh reference data for node form dropdowns
        await loadReferenceData();
    } catch (error) {
        regionFormError.value = getErrorMessage(error);
    } finally {
        regionSaving.value = false;
    }
}

function openDeleteRegion(record: CdnflyRecord): void {
    const id = asNumber(record.id);

    if (!id) {
        regionError.value = '区域 ID 缺失';

        return;
    }

    deleteConfirmTitle.value = '确认删除区域';
    deleteConfirmDesc.value = `确认删除区域「${textValue(record.name) || '#' + id}」？删除后不可恢复。`;
    deleteConfirmError.value = '';
    deleteConfirmAction.value = async () => {
        deleteConfirmLoading.value = true;

        try {
            await deleteAdminRegion(id);
            deleteConfirmOpen.value = false;
            toast.success('区域已删除');
            await loadRegions();
            await loadReferenceData();
        } catch (error) {
            deleteConfirmError.value = getErrorMessage(error);
        } finally {
            deleteConfirmLoading.value = false;
        }
    };
    deleteConfirmOpen.value = true;
}

// ─── Node Group CRUD ──────────────────────────────────
const ngLoading = ref(false);
const ngError = ref('');
const ngDialogOpen = ref(false);
const ngSaving = ref(false);
const ngFormError = ref('');
type NodeTab = 'nodes' | 'topology';

const props = withDefaults(
    defineProps<{
        initialTab?: NodeTab;
        resolutionOnly?: boolean;
        initialGroupId?: string;
    }>(),
    {
        initialTab: 'nodes',
    },
);
const activeTab = ref<NodeTab>(props.initialTab);

// The install command is a once-per-node action, not something worth a
// permanent block at the top of every visit.
const installDialogOpen = ref(false);

const editingNodeGroup = ref<CdnflyRecord | null>(null);
const ngPage = ref(1);
const ngTotal = ref<number | null>(null);
const ngRows = ref<CdnflyRecord[]>([]);

const ngForm = reactive({
    region_id: '',
    name: '',
    des: '',
    backup_switch_type: 'master_down' as 'master_down' | 'interval',
    backup_policy_ip_num: '2',
    backup_policy_interval: '60',
    backup_policy_switch_order: 'rand' as 'rand' | 'seq',
});

const hasNgPrevPage = computed(() => ngPage.value > 1);
const hasNgNextPage = computed(() => {
    if (typeof ngTotal.value === 'number') {
        return ngPage.value * 20 < ngTotal.value;
    }

    return ngRows.value.length >= 20;
});

async function loadNodeGroups(targetPage = ngPage.value): Promise<void> {
    ngLoading.value = true;
    ngError.value = '';

    try {
        const result = await listAdminNodeGroups({
            ...nodeGroupQuery,
            page: targetPage,
            limit: 20,
        });
        ngRows.value = extractRows(result);
        ngTotal.value = extractTotal(result, ngRows.value.length);
        ngPage.value = targetPage;
        nodeGroups.value =
            ngRows.value.length > 0 ? ngRows.value : nodeGroups.value;
    } catch (error) {
        ngError.value = getErrorMessage(error);
    } finally {
        ngLoading.value = false;
    }
}

function openAddNodeGroup(): void {
    editingNodeGroup.value = null;
    ngForm.region_id = regionOptions.value[0]?.id ?? '';
    ngForm.name = '';
    ngForm.des = '';
    ngForm.backup_switch_type = 'master_down';
    ngForm.backup_policy_ip_num = '2';
    ngForm.backup_policy_interval = '60';
    ngForm.backup_policy_switch_order = 'rand';
    ngFormError.value = '';
    ngDialogOpen.value = true;
}

function openEditNodeGroup(record: CdnflyRecord): void {
    editingNodeGroup.value = record;
    ngForm.region_id = idField(record.region_id);
    ngForm.name = textValue(record.name);
    ngForm.des = textValue(record.des);
    const switchType = textValue(record.backup_switch_type);
    ngForm.backup_switch_type =
        switchType === 'interval' ? 'interval' : 'master_down';
    const policy = parseSwitchPolicy(record.backup_switch_policy);
    ngForm.backup_policy_ip_num = String(policy.ip_num ?? 2);
    ngForm.backup_policy_interval = String(policy.interval ?? 60);
    ngForm.backup_policy_switch_order =
        policy.switch_order === 'seq' ? 'seq' : 'rand';
    ngFormError.value = '';
    ngDialogOpen.value = true;
}

async function submitNodeGroup(): Promise<void> {
    if (ngForm.name.trim() === '') {
        ngFormError.value = '节点组名称不能为空';

        return;
    }

    const regionId = asNumber(ngForm.region_id);

    if (!regionId) {
        ngFormError.value = '请选择所属区域';

        return;
    }

    const switchType = ngForm.backup_switch_type || 'master_down';
    // CDNfly expects backup_switch_policy as an object, not a JSON string —
    // a string trips its "数据类型错误" type check. Mirror the master panel,
    // which always submits the {ip_num, interval, switch_order} object.
    const backupPolicy = {
        ip_num: Number(ngForm.backup_policy_ip_num) || 2,
        interval: Number(ngForm.backup_policy_interval) || 60,
        switch_order: ngForm.backup_policy_switch_order || 'rand',
    };

    const payload: AdminNodeGroupPayload = {
        region_id: regionId,
        name: ngForm.name.trim(),
        des: ngForm.des.trim(),
        backup_switch_type: switchType,
        backup_switch_policy: backupPolicy,
    };

    ngSaving.value = true;
    ngFormError.value = '';

    try {
        if (editingNodeGroup.value) {
            const id = asNumber(editingNodeGroup.value.id);

            if (!id) {
                ngFormError.value = '节点组 ID 缺失';

                return;
            }

            await updateAdminNodeGroup(id, payload);
            toast.success('节点组已更新');
        } else {
            await createAdminNodeGroup(payload);
            toast.success('节点组已创建');
        }

        ngDialogOpen.value = false;
        await loadNodeGroups();
        await loadReferenceData();
    } catch (error) {
        ngFormError.value = getErrorMessage(error);
    } finally {
        ngSaving.value = false;
    }
}

function openDeleteNodeGroup(record: CdnflyRecord): void {
    const id = asNumber(record.id);

    if (!id) {
        ngError.value = '节点组 ID 缺失';

        return;
    }

    deleteConfirmTitle.value = '确认删除节点组';
    deleteConfirmDesc.value = `确认删除节点组「${textValue(record.name) || '#' + id}」？删除后不可恢复。`;
    deleteConfirmError.value = '';
    deleteConfirmAction.value = async () => {
        deleteConfirmLoading.value = true;

        try {
            await deleteAdminNodeGroup(id);
            deleteConfirmOpen.value = false;
            toast.success('节点组已删除');
            await loadNodeGroups();
            await loadReferenceData();
        } catch (error) {
            deleteConfirmError.value = getErrorMessage(error);
        } finally {
            deleteConfirmLoading.value = false;
        }
    };
    deleteConfirmOpen.value = true;
}

// ─── 线路分配 ─────────────────────────────────────────
/**
 * backup_switch_policy arrives as a JSON string; the node-group form edits its
 * parts as separate fields. Shared by the group editor.
 */
function parseSwitchPolicy(raw: unknown): {
    ip_num: string;
    interval: string;
    switch_order: string;
} {
    const fallback = { ip_num: '2', interval: '60', switch_order: 'rand' };

    if (typeof raw !== 'string' || raw.trim() === '') {
        return fallback;
    }

    try {
        const parsed = JSON.parse(raw) as Record<string, unknown>;

        return {
            ip_num: String(parsed.ip_num ?? fallback.ip_num),
            interval: String(parsed.interval ?? fallback.interval),
            switch_order: String(parsed.switch_order ?? fallback.switch_order),
        };
    } catch {
        return fallback;
    }
}

/**
 * Assigning nodes to a DNS line.
 *
 * The old 新增线路 form asked for a name, region, CNAME hostname, sort order and
 * failover policy — none of which CDNfly's /v1/lines accepts. A line is not an
 * object you create: the DNS lines live in a system config, and this endpoint
 * binds a node's IPs to one of them inside a node group.
 *
 * Shape verified against the master's own panel source
 * (cdnfly-go/panel/dashboard/js/chunk-49f49657).
 */
const lineRows = ref<CdnflyRecord[]>([]);
const lineLoading = ref(false);
const lineError = ref('');
const dnsLines = ref<AdminDnsLine[]>([]);

const lineGroupId = ref<string>(props.initialGroupId ?? '');
const lineDnsId = ref<string>('');

/** Candidate nodes, one row per IP (sub-ip=1). */
const lineCandidates = ref<CdnflyRecord[]>([]);
const lineCandidatesLoading = ref(false);
const selectedIpIds = ref<number[]>([]);
const assigning = ref(false);

const currentDnsLine = computed(() =>
    dnsLines.value.find((l) => String(l.id) === lineDnsId.value),
);

const currentLineGroup = computed(() =>
    nodeGroups.value.find((g) => String(g.id) === lineGroupId.value),
);

const dnsLinesError = ref('');

async function loadDnsLines(): Promise<void> {
    dnsLinesError.value = '';

    try {
        dnsLines.value = await listAdminDnsLines();

        if (dnsLines.value.length === 0) {
            dnsLinesError.value =
                '主控尚未配置 DNS 解析线路。请先在 CDNfly 主控面板「节点管理 → DNS 设置」中' +
                '填写 DNS 服务商（阿里云 / DNSPod / Cloudflare 等）的 API 凭据，' +
                '并在「CNAME 域名」标签页添加一个 CNAME 域名。' +
                '主控会在配置完成后自动写入线路列表，届时刷新本页即可。';

            return;
        }

        if (lineDnsId.value === '') {
            lineDnsId.value = String(dnsLines.value[0].id);
        }
    } catch (error) {
        dnsLinesError.value = getErrorMessage(error);
    }
}

async function loadLines(): Promise<void> {
    if (lineGroupId.value === '') {
        lineRows.value = [];

        return;
    }

    lineLoading.value = true;
    lineError.value = '';

    try {
        const result = await listAdminLines({
            limit: 0,
            node_group_id: Number(lineGroupId.value),
            line_id: lineDnsId.value,
        });

        lineRows.value = extractRows(result);
    } catch (error) {
        lineError.value = getErrorMessage(error);
    } finally {
        lineLoading.value = false;
    }
}

/**
 * sub-ip=1 returns one row per IP instead of per node. A row with pid !== 0 is
 * an extra IP belonging to node pid; otherwise the row is the node itself.
 * That is exactly the node_id / node_ip_id distinction the API wants.
 */
async function loadLineCandidates(): Promise<void> {
    const group = currentLineGroup.value;

    if (!group) {
        lineCandidates.value = [];

        return;
    }

    lineCandidatesLoading.value = true;

    try {
        const result = await listAdminNodes({
            limit: 0,
            'sub-ip': 1,
            type: 'L1',
            region_id: asNumber(group.region_id) ?? 0,
        });

        lineCandidates.value = extractRows(result);
    } catch (error) {
        lineError.value = getErrorMessage(error);
    } finally {
        lineCandidatesLoading.value = false;
    }
}

async function onLineGroupChange(): Promise<void> {
    selectedIpIds.value = [];
    await Promise.all([loadLines(), loadLineCandidates()]);
}

function isIpSelected(row: CdnflyRecord): boolean {
    return selectedIpIds.value.includes(Number(row.id));
}

function toggleIpSelection(row: CdnflyRecord): void {
    const id = Number(row.id);

    if (!id) {
        return;
    }

    selectedIpIds.value = selectedIpIds.value.includes(id)
        ? selectedIpIds.value.filter((x) => x !== id)
        : [...selectedIpIds.value, id];
}

type CandidateGroup = {
    nodeId: number;
    name: string;
    main: CdnflyRecord | null;
    subs: CdnflyRecord[];
    count: number;
};

// One CDNfly row per IP is noisy — the node name repeats on every line. Group
// the candidate IPs under their node so each machine shows once: its main IP
// sits in the node header (selectable), with the 附加 IP listed beneath it.
// IPs already bound to the current group + line, keyed by IP address — used to
// hide them from the candidate list (a fully-assigned node then drops out).
const assignedIps = computed<Set<string>>(() => {
    const set = new Set<string>();

    for (const row of lineRows.value) {
        const ip = textValue(row.ip);

        if (ip !== '') {
            set.add(ip);
        }
    }

    return set;
});

const groupedCandidates = computed<CandidateGroup[]>(() => {
    const groups = new Map<
        number,
        { nodeId: number; name: string; ips: CdnflyRecord[] }
    >();

    for (const row of lineCandidates.value) {
        // Skip IPs already on this line — no point offering them again.
        if (assignedIps.value.has(textValue(row.ip))) {
            continue;
        }

        const pid = Number(row.pid) || 0;
        const nodeId = pid !== 0 ? pid : Number(row.id);

        if (!Number.isFinite(nodeId) || nodeId === 0) {
            continue;
        }

        let group = groups.get(nodeId);

        if (!group) {
            group = { nodeId, name: '', ips: [] };
            groups.set(nodeId, group);
        }

        group.ips.push(row);

        // The main-IP row (pid 0) carries the node name; prefer it.
        if (pid === 0) {
            group.name = textValue(row.name);
        }
    }

    return [...groups.values()].map((group) => {
        const name =
            group.name || (group.ips[0] ? textValue(group.ips[0].name) : '');
        const main =
            group.ips.find((ip) => (Number(ip.pid) || 0) === 0) ?? null;
        const subs = group.ips.filter((ip) => (Number(ip.pid) || 0) !== 0);

        return {
            nodeId: group.nodeId,
            name,
            main,
            subs,
            count: group.ips.length,
        };
    });
});

async function submitLineAssignment(): Promise<void> {
    const group = currentLineGroup.value;
    const line = currentDnsLine.value;

    if (!group || !line) {
        lineError.value = '请先选择节点组和线路';

        return;
    }

    if (selectedIpIds.value.length === 0) {
        lineError.value = '请选择节点';

        return;
    }

    const assignments: AdminLineAssignment[] = selectedIpIds.value.map(
        (ipId) => {
            const row = lineCandidates.value.find((r) => Number(r.id) === ipId);
            const pid = Number(row?.pid) || 0;

            return {
                node_group_id: Number(group.id),
                node_id: pid !== 0 ? pid : ipId,
                node_ip_id: ipId,
                line_id: Number(line.id),
                line_name: String(line.display_name || line.name),
            };
        },
    );

    assigning.value = true;
    lineError.value = '';

    try {
        await assignAdminLines(assignments);
        toast.success('节点已加入线路');
        selectedIpIds.value = [];
        await loadLines();
    } catch (error) {
        lineError.value = getErrorMessage(error);
    } finally {
        assigning.value = false;
    }
}

async function removeAssignment(row: CdnflyRecord): Promise<void> {
    const id = Number(row.id);

    if (!id) {
        return;
    }

    try {
        await unassignAdminLines([id]);
        toast.success('已移出线路');
        await loadLines();
    } catch (error) {
        lineError.value = getErrorMessage(error);
    }
}

type LineGroup = {
    key: string;
    name: string;
    main: CdnflyRecord | null;
    subs: CdnflyRecord[];
    count: number;
};

// A binding is the node's MAIN IP when its ip-record id equals the node id —
// the panel builds node_id = pid for a sub and node_id = its own id for the
// main, so main ⟺ node_id === node_ip_id.
function isMainLineRow(row: CdnflyRecord): boolean {
    const nodeId = asNumber(row.node_id);
    const ipId = asNumber(row.node_ip_id);

    return nodeId !== null && ipId !== null && nodeId === ipId;
}

// Bindings come back one row per IP, repeating the node name. Group them under
// their node so a machine with a /29 shows once: main IP in the header, 附加 IP
// beneath it.
const groupedLineRows = computed<LineGroup[]>(() => {
    const groups = new Map<
        string,
        { key: string; name: string; rows: CdnflyRecord[] }
    >();

    for (const row of lineRows.value) {
        const nodeId = asNumber(row.node_id);
        const name = textValue(row.node_name);
        const key = nodeId ? String(nodeId) : name || String(row.id);

        let group = groups.get(key);

        if (!group) {
            group = { key, name, rows: [] };
            groups.set(key, group);
        }

        group.rows.push(row);

        if (group.name === '' && name !== '') {
            group.name = name;
        }
    }

    return [...groups.values()].map((group) => {
        const main = group.rows.find(isMainLineRow) ?? null;
        const subs = group.rows.filter((row) => row !== main);

        return {
            key: group.key,
            name: group.name,
            main,
            subs,
            count: group.rows.length,
        };
    });
});

async function removeGroupAssignment(group: LineGroup): Promise<void> {
    const ids = [group.main, ...group.subs]
        .filter((row): row is CdnflyRecord => row !== null)
        .map((row) => Number(row.id))
        .filter((id) => Number.isFinite(id) && id !== 0);

    if (ids.length === 0) {
        return;
    }

    try {
        await unassignAdminLines(ids);
        toast.success('已移出线路');
        await loadLines();
    } catch (error) {
        lineError.value = getErrorMessage(error);
    }
}

// Collapsed group ids per section (default expanded = absent from the list).
const collapsedAssigned = ref<string[]>([]);
const collapsedCandidates = ref<number[]>([]);

function isAssignedCollapsed(key: string): boolean {
    return collapsedAssigned.value.includes(key);
}

function toggleAssignedCollapse(key: string): void {
    collapsedAssigned.value = collapsedAssigned.value.includes(key)
        ? collapsedAssigned.value.filter((x) => x !== key)
        : [...collapsedAssigned.value, key];
}

function isCandidateCollapsed(id: number): boolean {
    return collapsedCandidates.value.includes(id);
}

function toggleCandidateCollapse(id: number): void {
    collapsedCandidates.value = collapsedCandidates.value.includes(id)
        ? collapsedCandidates.value.filter((x) => x !== id)
        : [...collapsedCandidates.value, id];
}

function regionNameById(id: unknown): string {
    const numId = asNumber(id);

    if (!numId) {
        return '-';
    }

    const region = regions.value.find((r) => asNumber(r.id) === numId);

    return region ? textValue(region.name) : `#${numId}`;
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <ConsolePageHeader
            v-if="activeTab === 'topology' && !resolutionOnly"
            title="线路分组"
            :icon="Server"
            :show-api-badge="false"
        />

        <NodeManagementPanel
            v-if="activeTab !== 'topology'"
            ref="managementPanel"
            @install="openInstallDialog"
            @edit="openEditDialog"
            @initialize="openInitDialog"
            @sub-ips="openSubIpDialog"
            @add-region="openAddRegion"
            @edit-region="openEditRegion"
            @regions-changed="loadReferenceData"
        />

        <template v-if="activeTab === 'topology'">
            <!-- ─── 节点组管理 (CRUD) ─── -->
            <Card v-if="!resolutionOnly">
                <CardHeader
                    class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
                >
                    <CardTitle class="text-base">节点组管理</CardTitle>
                    <div class="flex items-center gap-2">
                        <Button size="sm" @click="openAddNodeGroup">
                            <Plus data-icon="inline-start" />
                            新增节点组
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="ngLoading"
                            @click="loadNodeGroups(1)"
                        >
                            <RefreshCw data-icon="inline-start" />
                            刷新
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <Alert v-if="ngError" variant="destructive" class="mb-4">
                        <AlertCircle data-icon="alert" />
                        <AlertTitle>节点组请求失败</AlertTitle>
                        <AlertDescription>{{ ngError }}</AlertDescription>
                    </Alert>
                    <div class="overflow-x-auto rounded-md border">
                        <table class="w-full min-w-[720px] text-sm">
                            <thead
                                class="border-b bg-muted/40 text-muted-foreground"
                            >
                                <tr>
                                    <th
                                        class="w-16 px-4 py-3 text-left font-medium"
                                    >
                                        ID
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium">
                                        名称
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium">
                                        所属区域
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium">
                                        CNAME
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium">
                                        备注
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium">
                                        切换策略
                                    </th>
                                    <th
                                        class="w-20 px-4 py-3 text-left font-medium"
                                    >
                                        节点数
                                    </th>
                                    <th
                                        class="w-44 px-4 py-3 text-right font-medium"
                                    >
                                        操作
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="ngLoading && ngRows.length === 0">
                                    <td
                                        class="px-4 py-12 text-center"
                                        colspan="8"
                                    >
                                        <Spinner />
                                    </td>
                                </tr>
                                <tr
                                    v-for="g in ngRows"
                                    :key="textValue(g.id)"
                                    class="border-b last:border-b-0"
                                >
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ textValue(g.id) }}
                                    </td>
                                    <td class="px-4 py-3 font-medium">
                                        {{ textValue(g.name) || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{
                                            textValue(g.region_name) ||
                                            regionNameById(g.region_id)
                                        }}
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs">
                                        {{ textValue(g.cname_hostname) || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ textValue(g.des) || '-' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{
                                            textValue(g.backup_switch_type) ||
                                            'master_down'
                                        }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ textValue(g.node_count) || '0' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="openEditNodeGroup(g)"
                                            >
                                                <Pencil
                                                    data-icon="inline-start"
                                                />
                                                编辑
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                @click="openDeleteNodeGroup(g)"
                                            >
                                                <Trash2
                                                    data-icon="inline-start"
                                                />
                                                删除
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!ngLoading && ngRows.length === 0">
                                    <td
                                        class="px-4 py-12 text-center text-muted-foreground"
                                        colspan="8"
                                    >
                                        暂无节点组
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 flex items-center justify-end gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="!hasNgPrevPage || ngLoading"
                            @click="loadNodeGroups(ngPage - 1)"
                            >上一页</Button
                        >
                        <span class="text-sm text-muted-foreground"
                            >第 {{ ngPage }} 页</span
                        >
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="!hasNgNextPage || ngLoading"
                            @click="loadNodeGroups(ngPage + 1)"
                            >下一页</Button
                        >
                    </div>
                </CardContent>
            </Card>

            <!-- ─── 区域管理 (CRUD) ─── -->
            <Card v-if="!resolutionOnly">
                <CardHeader
                    class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
                >
                    <CardTitle class="text-base">区域管理</CardTitle>
                    <div class="flex items-center gap-2">
                        <Button size="sm" @click="openAddRegion">
                            <Plus data-icon="inline-start" />
                            新增区域
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="regionLoading"
                            @click="loadRegions(1)"
                        >
                            <RefreshCw data-icon="inline-start" />
                            刷新
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <Alert
                        v-if="regionError"
                        variant="destructive"
                        class="mb-4"
                    >
                        <AlertCircle data-icon="alert" />
                        <AlertTitle>区域请求失败</AlertTitle>
                        <AlertDescription>{{ regionError }}</AlertDescription>
                    </Alert>
                    <div class="overflow-x-auto rounded-md border">
                        <table class="w-full min-w-[640px] text-sm">
                            <thead
                                class="border-b bg-muted/40 text-muted-foreground"
                            >
                                <tr>
                                    <th
                                        class="w-20 px-4 py-3 text-left font-medium"
                                    >
                                        ID
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium">
                                        名称
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium">
                                        备注
                                    </th>
                                    <th
                                        class="w-24 px-4 py-3 text-left font-medium"
                                    >
                                        排序
                                    </th>
                                    <th
                                        class="w-32 px-4 py-3 text-left font-medium"
                                    >
                                        L2检测端口
                                    </th>
                                    <th
                                        class="w-44 px-4 py-3 text-right font-medium"
                                    >
                                        操作
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-if="
                                        regionLoading && regionRows.length === 0
                                    "
                                >
                                    <td
                                        class="px-4 py-12 text-center"
                                        colspan="6"
                                    >
                                        <Spinner />
                                    </td>
                                </tr>
                                <tr
                                    v-for="r in regionRows"
                                    :key="textValue(r.id)"
                                    class="border-b last:border-b-0"
                                >
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ textValue(r.id) }}
                                    </td>
                                    <td class="px-4 py-3 font-medium">
                                        {{ textValue(r.name) || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ textValue(r.des) || '-' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ textValue(r.sort) || '-' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ textValue(r.l2_check_port) || '-' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="openEditRegion(r)"
                                            >
                                                <Pencil
                                                    data-icon="inline-start"
                                                />
                                                编辑
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                @click="openDeleteRegion(r)"
                                            >
                                                <Trash2
                                                    data-icon="inline-start"
                                                />
                                                删除
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        !regionLoading &&
                                        regionRows.length === 0
                                    "
                                >
                                    <td
                                        class="px-4 py-12 text-center text-muted-foreground"
                                        colspan="6"
                                    >
                                        暂无区域
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 flex items-center justify-end gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="!hasRegionPrevPage || regionLoading"
                            @click="loadRegions(regionPage - 1)"
                            >上一页</Button
                        >
                        <span class="text-sm text-muted-foreground"
                            >第 {{ regionPage }} 页</span
                        >
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="!hasRegionNextPage || regionLoading"
                            @click="loadRegions(regionPage + 1)"
                            >下一页</Button
                        >
                    </div>
                </CardContent>
            </Card>

            <!-- ─── 线路管理 (CRUD) ─── -->
            <!--
                Assigning nodes to a line, not creating one. A CDNfly line is a
                DNS line defined in system config; this binds a node's IPs to it
                inside a node group. The previous 新增线路 form invented fields
                the endpoint never accepted.
            -->
            <Card>
                <CardHeader
                    class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
                >
                    <CardTitle class="text-base">线路分配</CardTitle>
                    <div class="flex flex-wrap items-center gap-2">
                        <Select
                            v-if="!resolutionOnly"
                            v-model="lineGroupId"
                            @update:model-value="onLineGroupChange"
                        >
                            <SelectTrigger class="w-48">
                                <SelectValue placeholder="选择节点组" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem
                                        v-for="g in nodeGroups"
                                        :key="String(g.id)"
                                        :value="String(g.id)"
                                    >
                                        {{ textValue(g.name) }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <Select
                            v-model="lineDnsId"
                            @update:model-value="loadLines"
                        >
                            <SelectTrigger class="w-36">
                                <SelectValue placeholder="选择线路" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem
                                        v-for="l in dnsLines"
                                        :key="String(l.id)"
                                        :value="String(l.id)"
                                    >
                                        {{ l.display_name || l.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="lineLoading"
                            @click="onLineGroupChange"
                        >
                            <RefreshCw data-icon="inline-start" />
                            刷新
                        </Button>
                    </div>
                </CardHeader>

                <CardContent class="grid gap-6">
                    <Alert v-if="dnsLinesError" variant="destructive">
                        <AlertCircle data-icon="alert" />
                        <AlertTitle>DNS 线路不可用</AlertTitle>
                        <AlertDescription>
                            {{ dnsLinesError }}
                        </AlertDescription>
                    </Alert>

                    <Alert v-if="lineError" variant="destructive">
                        <AlertCircle data-icon="alert" />
                        <AlertTitle>线路请求失败</AlertTitle>
                        <AlertDescription>{{ lineError }}</AlertDescription>
                    </Alert>

                    <p
                        v-if="lineGroupId === ''"
                        class="text-sm text-muted-foreground"
                    >
                        先选择一个节点组。线路是把节点 IP 绑定到某条 DNS
                        线路上，不是单独创建的对象。
                    </p>

                    <template v-else>
                        <!-- already on this line -->
                        <div class="grid gap-2">
                            <div class="text-sm font-medium">
                                已在该线路上的节点
                            </div>

                            <div
                                v-if="groupedLineRows.length === 0"
                                class="rounded-md border px-4 py-10 text-center text-sm text-muted-foreground"
                            >
                                该线路暂无节点
                            </div>

                            <div v-else class="space-y-3">
                                <div
                                    v-for="group in groupedLineRows"
                                    :key="group.key"
                                    class="overflow-hidden rounded-lg border"
                                >
                                    <div
                                        class="flex items-center gap-2 border-b bg-muted/40 px-3 py-2.5"
                                    >
                                        <button
                                            type="button"
                                            class="flex size-6 items-center justify-center rounded text-muted-foreground hover:text-foreground"
                                            @click="
                                                toggleAssignedCollapse(
                                                    group.key,
                                                )
                                            "
                                        >
                                            <ChevronDown
                                                v-if="
                                                    !isAssignedCollapsed(
                                                        group.key,
                                                    )
                                                "
                                                class="size-4"
                                            />
                                            <ChevronRight
                                                v-else
                                                class="size-4"
                                            />
                                        </button>
                                        <Server
                                            class="size-4 text-muted-foreground"
                                        />
                                        <span class="font-medium">{{
                                            group.name
                                        }}</span>
                                        <span
                                            v-if="group.main"
                                            class="font-mono text-sm"
                                            >{{
                                                textValue(group.main.ip)
                                            }}</span
                                        >
                                        <Badge
                                            v-if="group.main"
                                            variant="secondary"
                                            class="font-normal"
                                        >
                                            主 IP
                                        </Badge>
                                        <Badge
                                            variant="outline"
                                            class="ml-auto font-normal"
                                        >
                                            {{ group.count }} 个 IP
                                        </Badge>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            @click="
                                                removeGroupAssignment(group)
                                            "
                                        >
                                            <Trash2 data-icon="inline-start" />
                                            移出全部
                                        </Button>
                                    </div>
                                    <ul
                                        v-show="!isAssignedCollapsed(group.key)"
                                    >
                                        <li
                                            v-for="l in group.subs"
                                            :key="String(l.id)"
                                            class="flex items-center gap-3 border-t px-4 py-2 pl-11 first:border-t-0"
                                        >
                                            <span class="font-mono text-sm">{{
                                                textValue(l.ip)
                                            }}</span>
                                            <Badge
                                                v-if="Number(l.is_backup) === 1"
                                                variant="outline"
                                                class="font-normal"
                                            >
                                                备用
                                            </Badge>
                                            <Badge
                                                v-else
                                                variant="outline"
                                                class="font-normal"
                                            >
                                                附加 IP
                                            </Badge>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="ml-auto"
                                                @click="removeAssignment(l)"
                                            >
                                                <Trash2
                                                    data-icon="inline-start"
                                                />
                                                移出
                                            </Button>
                                        </li>
                                        <li
                                            v-if="group.subs.length === 0"
                                            class="border-t px-4 py-2 pl-11 text-xs text-muted-foreground"
                                        >
                                            无附加 IP
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- candidates -->
                        <div class="grid gap-2">
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <div class="text-sm font-medium">
                                    可加入的节点
                                    <span
                                        class="ml-1 text-xs text-muted-foreground"
                                    >
                                        已选 {{ selectedIpIds.length }}
                                    </span>
                                </div>
                                <Button
                                    size="sm"
                                    :disabled="
                                        assigning ||
                                        selectedIpIds.length === 0 ||
                                        lineDnsId === ''
                                    "
                                    @click="submitLineAssignment"
                                >
                                    <Spinner
                                        v-if="assigning"
                                        data-icon="inline-start"
                                    />
                                    <Plus v-else data-icon="inline-start" />
                                    加入线路
                                </Button>
                            </div>

                            <div
                                v-if="lineCandidatesLoading"
                                class="flex items-center justify-center gap-2 rounded-md border px-4 py-10 text-sm text-muted-foreground"
                            >
                                <Spinner /> 正在读取
                            </div>

                            <div
                                v-else-if="groupedCandidates.length === 0"
                                class="rounded-md border px-4 py-10 text-center text-sm text-muted-foreground"
                            >
                                该区域暂无可用 L1 节点
                            </div>

                            <div v-else class="space-y-3">
                                <div
                                    v-for="group in groupedCandidates"
                                    :key="group.nodeId"
                                    class="overflow-hidden rounded-lg border"
                                >
                                    <div
                                        class="flex items-center gap-2 border-b bg-muted/40 px-3 py-2.5"
                                    >
                                        <button
                                            type="button"
                                            class="flex size-6 items-center justify-center rounded text-muted-foreground hover:text-foreground"
                                            @click.stop="
                                                toggleCandidateCollapse(
                                                    group.nodeId,
                                                )
                                            "
                                        >
                                            <ChevronDown
                                                v-if="
                                                    !isCandidateCollapsed(
                                                        group.nodeId,
                                                    )
                                                "
                                                class="size-4"
                                            />
                                            <ChevronRight
                                                v-else
                                                class="size-4"
                                            />
                                        </button>
                                        <div
                                            class="flex flex-1 cursor-pointer items-center gap-3"
                                            @click="
                                                group.main &&
                                                toggleIpSelection(group.main)
                                            "
                                        >
                                            <Checkbox
                                                v-if="group.main"
                                                :model-value="
                                                    isIpSelected(group.main)
                                                "
                                            />
                                            <Server
                                                class="size-4 text-muted-foreground"
                                            />
                                            <span class="font-medium">{{
                                                group.name
                                            }}</span>
                                            <span
                                                v-if="group.main"
                                                class="font-mono text-sm"
                                                >{{
                                                    textValue(group.main.ip)
                                                }}</span
                                            >
                                            <Badge
                                                v-if="group.main"
                                                variant="secondary"
                                                class="font-normal"
                                            >
                                                主 IP
                                            </Badge>
                                            <Badge
                                                variant="outline"
                                                class="ml-auto font-normal"
                                            >
                                                {{ group.count }} 个 IP
                                            </Badge>
                                        </div>
                                    </div>
                                    <ul
                                        v-show="
                                            !isCandidateCollapsed(group.nodeId)
                                        "
                                    >
                                        <li
                                            v-for="ip in group.subs"
                                            :key="String(ip.id)"
                                            class="flex cursor-pointer items-center gap-3 border-t px-4 py-2 pl-11 first:border-t-0 hover:bg-accent/40"
                                            @click="toggleIpSelection(ip)"
                                        >
                                            <Checkbox
                                                :model-value="isIpSelected(ip)"
                                            />
                                            <span class="font-mono text-sm">{{
                                                textValue(ip.ip)
                                            }}</span>
                                            <Badge
                                                variant="outline"
                                                class="ml-auto font-normal"
                                            >
                                                附加 IP
                                            </Badge>
                                        </li>
                                        <li
                                            v-if="group.subs.length === 0"
                                            class="border-t px-4 py-2 pl-11 text-xs text-muted-foreground"
                                        >
                                            无附加 IP
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </template>
                </CardContent>
            </Card>
        </template>

        <!--
            Installing a node happens once per machine, so the command does not
            earn permanent space at the top of a page visited daily for other
            reasons. It lives behind 执行命令 in the top bar instead.
        -->
        <Dialog v-model:open="installDialogOpen">
            <DialogScrollContent class="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>节点安装命令</DialogTitle>
                    <DialogDescription>
                        以 root
                        登录新节点机执行。执行完成后节点会自动回传，到「待初始化」完成初始化。
                    </DialogDescription>
                </DialogHeader>

                <Alert v-if="installError" variant="destructive">
                    <AlertCircle data-icon="alert" />
                    <AlertTitle>安装命令加载失败</AlertTitle>
                    <AlertDescription>{{ installError }}</AlertDescription>
                </Alert>

                <div
                    class="max-h-72 min-h-32 overflow-auto rounded-md border bg-muted/30 p-4 font-mono text-sm leading-7"
                >
                    <Spinner v-if="installLoading" />
                    <pre
                        v-else-if="installCommandAvailable"
                        class="break-all whitespace-pre-wrap"
                        >{{ installCommand }}</pre
                    >
                    <div v-else class="text-muted-foreground">
                        暂无可用安装命令，请稍后重试。
                    </div>
                </div>

                <p class="text-xs text-muted-foreground">
                    命令中包含 Elasticsearch 密码，请勿转发或截图外发。
                </p>

                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="installLoading"
                        @click="refreshInstallCommand"
                    >
                        <RefreshCw data-icon="inline-start" />
                        刷新
                    </Button>
                    <Button
                        :disabled="installLoading || !installCommandAvailable"
                        @click="copyInstallCommand"
                    >
                        <Check v-if="commandCopied" data-icon="inline-start" />
                        <Copy v-else data-icon="inline-start" />
                        {{ commandCopied ? '已复制' : '复制命令' }}
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <Dialog v-model:open="initDialogOpen">
            <DialogScrollContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>初始化节点</DialogTitle>
                    <DialogDescription>
                        设置节点名称、区域和类型，完成初始化后即可分配线路。
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-5" @submit.prevent="submitInitNode">
                    <Alert v-if="initFormError" variant="destructive">
                        <AlertCircle data-icon="alert" />
                        <AlertTitle>初始化失败</AlertTitle>
                        <AlertDescription>{{ initFormError }}</AlertDescription>
                    </Alert>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label>待初始化 ID</Label>
                            <Input
                                :model-value="
                                    textValue(selectedPendingNode?.id) || '-'
                                "
                                disabled
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>IP</Label>
                            <Input
                                :model-value="
                                    selectedPendingNode
                                        ? pendingNodeIp(selectedPendingNode)
                                        : '-'
                                "
                                disabled
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="init-node-name">名称</Label>
                            <Input
                                id="init-node-name"
                                v-model="initForm.name"
                                autocomplete="off"
                                required
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>区域</Label>
                            <Select v-model="initForm.region_id">
                                <SelectTrigger :disabled="referencesLoading">
                                    <SelectValue placeholder="请选择区域" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem
                                            v-for="option in regionOptions"
                                            :key="option.id"
                                            :value="option.id"
                                        >
                                            {{ option.label }}
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label>类型</Label>
                            <Select v-model="initForm.type">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="L1">
                                            L1 边缘节点
                                        </SelectItem>
                                        <SelectItem value="L2">
                                            L2 中间节点
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="init-node-des">备注</Label>
                        <Textarea
                            id="init-node-des"
                            v-model="initForm.des"
                            class="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            type="button"
                            @click="initDialogOpen = false"
                        >
                            取消
                        </Button>
                        <Button :disabled="initializing" type="submit">
                            <Spinner
                                v-if="initializing"
                                data-icon="inline-start"
                            />
                            <Save v-else data-icon="inline-start" />
                            确定初始化
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <NodeEditDialog
            v-model:open="nodeDialogOpen"
            :node="editingNode"
            @saved="loadNodes"
        />

        <!-- ─── Node Group Dialog ─── -->
        <Dialog v-model:open="ngDialogOpen">
            <DialogScrollContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{
                        editingNodeGroup ? '编辑节点组' : '新增节点组'
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            editingNodeGroup
                                ? '修改节点组配置，提交后立即生效。'
                                : '创建新节点组。'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-5" @submit.prevent="submitNodeGroup">
                    <Alert v-if="ngFormError" variant="destructive">
                        <AlertCircle data-icon="alert" />
                        <AlertTitle>提交失败</AlertTitle>
                        <AlertDescription>{{ ngFormError }}</AlertDescription>
                    </Alert>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="ng-name">节点组名称</Label>
                            <Input
                                id="ng-name"
                                v-model="ngForm.name"
                                autocomplete="off"
                                required
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>所属区域</Label>
                            <Select v-model="ngForm.region_id">
                                <SelectTrigger :disabled="referencesLoading">
                                    <SelectValue placeholder="请选择区域" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem
                                            v-for="option in regionOptions"
                                            :key="option.id"
                                            :value="option.id"
                                        >
                                            {{ option.label }}
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2 md:col-span-2">
                            <Label>备用IP切换策略</Label>
                            <Select v-model="ngForm.backup_switch_type">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="master_down"
                                            >master_down（主IP不可用时切换）</SelectItem
                                        >
                                        <SelectItem value="interval"
                                            >interval（间隔时间切换）</SelectItem
                                        >
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div
                        v-if="ngForm.backup_switch_type === 'interval'"
                        class="grid gap-4 md:grid-cols-3"
                    >
                        <div class="grid gap-2">
                            <Label for="ng-policy-ip-num"
                                >同时启用备用IP数</Label
                            >
                            <Input
                                id="ng-policy-ip-num"
                                v-model="ngForm.backup_policy_ip_num"
                                type="number"
                                min="1"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="ng-policy-interval"
                                >切换间隔（秒）</Label
                            >
                            <Input
                                id="ng-policy-interval"
                                v-model="ngForm.backup_policy_interval"
                                type="number"
                                min="1"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>切换顺序</Label>
                            <Select v-model="ngForm.backup_policy_switch_order">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="rand"
                                            >rand（随机）</SelectItem
                                        >
                                        <SelectItem value="seq"
                                            >seq（顺序）</SelectItem
                                        >
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="ng-des">备注</Label>
                        <Textarea
                            id="ng-des"
                            v-model="ngForm.des"
                            class="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            type="button"
                            @click="ngDialogOpen = false"
                            >取消</Button
                        >
                        <Button :disabled="ngSaving" type="submit">
                            <Spinner v-if="ngSaving" data-icon="inline-start" />
                            <Save v-else data-icon="inline-start" />
                            {{ editingNodeGroup ? '保存' : '创建' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <!-- ─── Region Dialog ─── -->
        <Dialog v-model:open="regionDialogOpen">
            <DialogScrollContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{
                        editingRegion ? '编辑区域' : '新增区域'
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            editingRegion
                                ? '修改区域配置，提交后立即生效。'
                                : '创建新区域。'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-5" @submit.prevent="submitRegion">
                    <Alert v-if="regionFormError" variant="destructive">
                        <AlertCircle data-icon="alert" />
                        <AlertTitle>提交失败</AlertTitle>
                        <AlertDescription>{{
                            regionFormError
                        }}</AlertDescription>
                    </Alert>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="region-name">区域名称</Label>
                            <Input
                                id="region-name"
                                v-model="regionForm.name"
                                autocomplete="off"
                                required
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="region-sort">排序</Label>
                            <Input
                                id="region-sort"
                                v-model="regionForm.sort"
                                type="number"
                                min="0"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="region-l2-port">L2 检测端口</Label>
                            <Input
                                id="region-l2-port"
                                v-model="regionForm.l2_check_port"
                                type="number"
                                min="1"
                                max="65535"
                            />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="region-des">备注</Label>
                        <Textarea
                            id="region-des"
                            v-model="regionForm.des"
                            class="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            type="button"
                            @click="regionDialogOpen = false"
                            >取消</Button
                        >
                        <Button :disabled="regionSaving" type="submit">
                            <Spinner
                                v-if="regionSaving"
                                data-icon="inline-start"
                            />
                            <Save v-else data-icon="inline-start" />
                            {{ editingRegion ? '保存' : '创建' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <!-- ─── 子IP Dialog ─── -->
        <Dialog v-model:open="subIpOpen">
            <DialogScrollContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>子 IP 管理</DialogTitle>
                    <DialogDescription>
                        为节点
                        <span class="font-medium">{{
                            subIpNode ? nodeName(subIpNode) : ''
                        }}</span>
                        登记 /29 里的备用
                        IP。登记后会出现在「线路分配」的可加入节点中，可用于
                        DDoS 攻击时自动切换。CDNfly 不会自动识别系统里的副
                        IP，需要在此手动登记。
                    </DialogDescription>
                </DialogHeader>

                <Alert v-if="subIpError" variant="destructive" class="mt-2">
                    <AlertCircle />
                    <AlertTitle>操作失败</AlertTitle>
                    <AlertDescription>{{ subIpError }}</AlertDescription>
                </Alert>

                <div class="mt-4 space-y-4">
                    <div>
                        <Label class="mb-2 block">当前 IP</Label>
                        <div
                            v-if="subIpLoading"
                            class="flex items-center gap-2 py-4 text-sm text-muted-foreground"
                        >
                            <Spinner /> 正在读取
                        </div>
                        <div
                            v-else-if="subIpRows.length === 0"
                            class="py-4 text-sm text-muted-foreground"
                        >
                            暂无 IP 记录
                        </div>
                        <ul v-else class="space-y-1">
                            <li
                                v-for="row in subIpRows"
                                :key="textValue(row.id) || textValue(row.ip)"
                                class="flex items-center justify-between rounded-md border px-3 py-2 text-sm"
                            >
                                <span class="flex items-center gap-2">
                                    <span class="font-mono">{{
                                        textValue(row.ip)
                                    }}</span>
                                    <Badge
                                        :variant="
                                            isMainIp(row)
                                                ? 'secondary'
                                                : 'outline'
                                        "
                                    >
                                        {{ isMainIp(row) ? '主IP' : '副IP' }}
                                    </Badge>
                                </span>
                                <Button
                                    v-if="!isMainIp(row)"
                                    variant="ghost"
                                    size="sm"
                                    :disabled="
                                        subIpDeletingId === asNumber(row.id)
                                    "
                                    @click="removeSubIp(row)"
                                >
                                    <Spinner
                                        v-if="
                                            subIpDeletingId === asNumber(row.id)
                                        "
                                        data-icon="inline-start"
                                    />
                                    <Trash2 v-else data-icon="inline-start" />
                                    移除
                                </Button>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <Label for="sub-ip-input" class="mb-2 block"
                            >添加子 IP（每行一个）</Label
                        >
                        <Textarea
                            id="sub-ip-input"
                            v-model="subIpInput"
                            rows="5"
                            placeholder="156.234.124.163&#10;156.234.124.164&#10;156.234.124.165&#10;156.234.124.166"
                            class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        ></Textarea>
                        <p class="mt-1 text-xs text-muted-foreground">
                            在节点上执行
                            <code>ip addr show</code> 可查看该机器的副 IP（标记
                            secondary 的地址）。
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="subIpOpen = false"
                        >关闭</Button
                    >
                    <Button :disabled="subIpSaving" @click="submitSubIps">
                        <Spinner v-if="subIpSaving" data-icon="inline-start" />
                        <Plus v-else data-icon="inline-start" />
                        添加
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <!-- ─── Line Dialog ─── -->

        <ConfirmDeleteDialog
            :open="deleteConfirmOpen"
            :title="deleteConfirmTitle"
            :description="deleteConfirmDesc"
            :loading="deleteConfirmLoading"
            :error="deleteConfirmError"
            @confirm="deleteConfirmAction?.()"
            @cancel="deleteConfirmOpen = false"
        />
    </div>
</template>
