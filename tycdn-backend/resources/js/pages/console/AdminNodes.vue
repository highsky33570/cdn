<script setup lang="ts">
import {
    AlertCircle,
    Check,
    ClipboardList,
    Copy,
    Pencil,
    Plus,
    Power,
    PowerOff,
    RefreshCw,
    Save,
    Search,
    Server,
    Share2,
    Terminal,
    Trash2,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import ConsolePageHeader from '@/components/console/ConsolePageHeader.vue';
import { toast } from 'vue-sonner';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import ConfirmDeleteDialog from '@/components/console/ConfirmDeleteDialog.vue';
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
import {
    assignAdminLines,
    createAdminNodeGroup,
    createAdminRegion,
    listAdminDnsLines,
    deleteAdminNode,
    deleteAdminNodeGroup,
    deleteAdminPendingNode,
    deleteAdminRegion,
    getAdminNodeInstallCommand,
    initializeAdminPendingNode,
    listAdminLines,
    listAdminNodeGroups,
    listAdminNodes,
    listAdminPendingNodes,
    listAdminRegions,
    setAdminNodeEnabled,
    unassignAdminLines,
    updateAdminNode,
    updateAdminNodeGroup,
    updateAdminRegion,
} from '@/lib/adminModulesApi';
import type {
    AdminDnsLine,
    AdminLineAssignment,
    AdminNodeGroupPayload,
    AdminNodeInstallCommand,
    AdminNodePayload,
    AdminPendingNodeInitPayload,
    AdminRegionPayload,
    CdnflyListData,
    CdnflyRecord,
} from '@/lib/adminModulesApi';

const NODE_STATUS_ALL = 'all';

type NodeOption = {
    id: string;
    label: string;
};

const loading = ref(false);
const installLoading = ref(false);
const pendingLoading = ref(false);
const saving = ref(false);
const initializing = ref(false);
const referencesLoading = ref(false);
const togglingNodeId = ref<number | null>(null);
const errorMessage = ref('');
const installError = ref('');
const pendingError = ref('');
const nodeFormError = ref('');
const initFormError = ref('');
const referenceError = ref('');
const deleteConfirmOpen = ref(false);
const deleteConfirmTitle = ref('');
const deleteConfirmDesc = ref('');
const deleteConfirmAction = ref<(() => Promise<void>) | null>(null);
const deleteConfirmError = ref('');
const deleteConfirmLoading = ref(false);
const commandCopied = ref(false);
const pendingListRequested = ref(false);
const nodeDialogOpen = ref(false);
const initDialogOpen = ref(false);
const editingNode = ref<CdnflyRecord | null>(null);
const selectedPendingNode = ref<CdnflyRecord | null>(null);
const page = ref(1);
const pendingPage = ref(1);
const total = ref<number | null>(null);
const pendingTotal = ref<number | null>(null);
const nodes = ref<CdnflyRecord[]>([]);
const pendingNodes = ref<CdnflyRecord[]>([]);
const nodeGroups = ref<CdnflyRecord[]>([]);
const regions = ref<CdnflyRecord[]>([]);
const lines = ref<CdnflyRecord[]>([]);
const installInfo = ref<AdminNodeInstallCommand | null>(null);

const filters = reactive({
    search: '',
    status: NODE_STATUS_ALL,
    per_page: '20',
});

const pendingFilters = reactive({
    per_page: '10',
});

const form = reactive({
    name: '',
    ip: '',
    node_group_id: '',
    region_id: '',
    line_id: '',
    status: '1',
    weight: '',
    bandwidth: '',
    des: '',
});

const initForm = reactive({
    name: '',
    des: '',
    region_id: '',
    type: 'L1' as 'L1' | 'L2',
});

const rows = computed(() => nodes.value);
const pendingRows = computed(() => pendingNodes.value);
const groupOptions = computed(() => toOptions(nodeGroups.value));
const regionOptions = computed(() => toOptions(regions.value));
const lineOptions = computed(() => toOptions(lines.value));
const installCommand = computed(() => installInfo.value?.command ?? '');
const installCommandAvailable = computed(() => installCommand.value !== '');
const hasPreviousPage = computed(() => page.value > 1);
const hasNextPage = computed(() => {
    const perPage = Number(filters.per_page);

    if (typeof total.value === 'number') {
        return page.value * perPage < total.value;
    }

    return rows.value.length >= perPage;
});
const hasPreviousPendingPage = computed(() => pendingPage.value > 1);
const hasNextPendingPage = computed(() => {
    const perPage = Number(pendingFilters.per_page);

    if (typeof pendingTotal.value === 'number') {
        return pendingPage.value * perPage < pendingTotal.value;
    }

    return pendingRows.value.length >= perPage;
});
const paginationText = computed(() => {
    if (total.value === null) {
        return `第 ${page.value} 页`;
    }

    if (total.value === 0) {
        return '暂无节点';
    }

    return `${total.value} 条节点`;
});
const pendingPaginationText = computed(() => {
    if (pendingTotal.value === null) {
        return `第 ${pendingPage.value} 页`;
    }

    if (pendingTotal.value === 0) {
        return '暂无待初始化节点';
    }

    return `${pendingTotal.value} 条待初始化节点`;
});

onMounted(() => {
    void Promise.all([
        loadNodes(),
        loadReferenceData(),
        loadInstallCommand(),
        loadRegions(),
        loadNodeGroups(),
        // the DNS line list, not assignments — those need a node group chosen first
        loadDnsLines(),
    ]);
});

async function loadNodes(targetPage = page.value): Promise<void> {
    loading.value = true;
    errorMessage.value = '';

    try {
        const params: Record<string, string | number> = {
            page: targetPage,
            limit: Number(filters.per_page),
        };

        const search = filters.search.trim();

        if (search !== '') {
            params.search = search;
        }

        if (filters.status !== NODE_STATUS_ALL) {
            params.enable = filters.status;
        }

        const result = await listAdminNodes(params);
        nodes.value = extractRows(result);
        total.value = extractTotal(result, nodes.value.length);
        page.value = targetPage;
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        loading.value = false;
    }
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

async function loadPendingNodes(targetPage = pendingPage.value): Promise<void> {
    pendingLoading.value = true;
    pendingError.value = '';
    pendingListRequested.value = true;

    try {
        const result = await listAdminPendingNodes({
            page: targetPage,
            limit: Number(pendingFilters.per_page),
        });

        pendingNodes.value = extractRows(result);
        pendingTotal.value = extractTotal(result, pendingNodes.value.length);
        pendingPage.value = targetPage;
    } catch (error) {
        pendingError.value = getErrorMessage(error);
    } finally {
        pendingLoading.value = false;
    }
}

async function loadReferenceData(): Promise<void> {
    referencesLoading.value = true;
    referenceError.value = '';

    try {
        const [groupsResult, regionsResult, linesResult] = await Promise.all([
            listAdminNodeGroups({ page: 1, limit: 200 }),
            listAdminRegions({ limit: 0 }),
            listAdminLines({ page: 1, limit: 200 }),
        ]);

        nodeGroups.value = extractRows(groupsResult);
        regions.value = extractRows(regionsResult);
        lines.value = extractRows(linesResult);
    } catch (error) {
        referenceError.value = getErrorMessage(error);
    } finally {
        referencesLoading.value = false;
    }
}

function submitSearch(): void {
    page.value = 1;
    void loadNodes(1);
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

function openInitDialog(node: CdnflyRecord): void {
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
    form.name = textValue(node.name);
    form.ip = textValue(node.ip);
    form.node_group_id = idField(node.node_group_id ?? node.group_id);
    form.region_id = idField(node.region_id);
    form.line_id = idField(node.line_id);
    form.status = idField(node.status) || '1';
    form.weight = idField(node.weight);
    form.bandwidth = idField(node.bandwidth);
    form.des = textValue(node.des ?? node.remark);
    nodeFormError.value = '';
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
        await Promise.all([loadPendingNodes(), loadNodes()]);
    } catch (error) {
        initFormError.value = getErrorMessage(error);
    } finally {
        initializing.value = false;
    }
}

async function submitNode(): Promise<void> {
    const payload = buildPayload();

    if (payload.name.trim() === '' || payload.ip.trim() === '') {
        nodeFormError.value = '节点名称和 IP 地址不能为空';

        return;
    }

    saving.value = true;
    nodeFormError.value = '';

    try {
        const id = asNumber(editingNode.value?.id);

        if (!id) {
            nodeFormError.value = '节点 ID 缺失';

            return;
        }

        await updateAdminNode(id, payload);
        toast.success('节点已提交更新');
        nodeDialogOpen.value = false;
        await loadNodes();
    } catch (error) {
        nodeFormError.value = getErrorMessage(error);
    } finally {
        saving.value = false;
    }
}

function openDeleteNode(node: CdnflyRecord) {
    const id = asNumber(node.id);
    if (!id) {
        errorMessage.value = '节点 ID 缺失';
        return;
    }
    deleteConfirmTitle.value = '确认删除';
    deleteConfirmDesc.value = `确认删除节点「${nodeName(node)}」？该操作会提交到 CDNfly，删除后不可恢复。`;
    deleteConfirmError.value = '';
    deleteConfirmAction.value = async () => {
        deleteConfirmLoading.value = true;
        try {
            await deleteAdminNode(id);
            deleteConfirmOpen.value = false;
            toast.success('节点删除请求已提交');
            await loadNodes();
        } catch (error) {
            deleteConfirmError.value = getErrorMessage(error);
        } finally {
            deleteConfirmLoading.value = false;
        }
    };
    deleteConfirmOpen.value = true;
}

async function setNodeEnabled(
    node: CdnflyRecord,
    enable: boolean,
): Promise<void> {
    const id = asNumber(node.id);

    if (!id) {
        errorMessage.value = '节点 ID 缺失';

        return;
    }

    const action = enable ? '启用' : '禁用';

    togglingNodeId.value = id;
    errorMessage.value = '';

    try {
        await setAdminNodeEnabled(id, enable);
        toast.success(`节点${action}请求已提交`);
        await loadNodes();
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    } finally {
        togglingNodeId.value = null;
    }
}

function openDeletePendingNode(node: CdnflyRecord) {
    const id = asNumber(node.id);
    if (!id) {
        pendingError.value = '待初始化节点 ID 缺失';
        return;
    }
    deleteConfirmTitle.value = '确认删除';
    deleteConfirmDesc.value = `确认删除待初始化节点 #${id}？`;
    deleteConfirmError.value = '';
    deleteConfirmAction.value = async () => {
        deleteConfirmLoading.value = true;
        try {
            await deleteAdminPendingNode(id);
            deleteConfirmOpen.value = false;
            toast.success('待初始化节点删除请求已提交');
            await loadPendingNodes();
        } catch (error) {
            deleteConfirmError.value = getErrorMessage(error);
        } finally {
            deleteConfirmLoading.value = false;
        }
    };
    deleteConfirmOpen.value = true;
}

function buildPayload(): AdminNodePayload {
    return {
        name: form.name.trim(),
        ip: form.ip.trim(),
        node_group_id: nullableNumber(form.node_group_id),
        region_id: nullableNumber(form.region_id),
        line_id: nullableNumber(form.line_id),
        status: nullableNumber(form.status),
        weight: nullableNumber(form.weight),
        bandwidth: nullableNumber(form.bandwidth),
        des: form.des.trim() === '' ? null : form.des.trim(),
    };
}

function extractRows(result: unknown): CdnflyRecord[] {
    if (Array.isArray(result)) {
        return result.filter(isRecord);
    }

    if (!isRecord(result)) {
        return [];
    }

    const directKeys = ['data', 'items', 'list', 'rows', 'records'];

    for (const key of directKeys) {
        const value = result[key];

        if (Array.isArray(value)) {
            return value.filter(isRecord);
        }

        if (isRecord(value)) {
            const nested = extractRows(value);

            if (nested.length > 0) {
                return nested;
            }
        }
    }

    return [];
}

function extractTotal(result: CdnflyListData, fallback: number): number | null {
    if (typeof result.total === 'number') {
        return result.total;
    }

    if (typeof result.count === 'number') {
        return result.count;
    }

    if (isRecord(result.meta) && typeof result.meta.total === 'number') {
        return result.meta.total;
    }

    if (isRecord(result.data)) {
        if (typeof result.data.total === 'number') {
            return result.data.total;
        }

        if (typeof result.data.count === 'number') {
            return result.data.count;
        }
    }

    return fallback;
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
                label: textValue(record.name ?? record.title) || `#${id}`,
            };
        })
        .filter((option): option is NodeOption => option !== null);
}

function isRecord(value: unknown): value is CdnflyRecord {
    return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
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

function nullableNumber(value: string): number | null {
    const trimmed = value.trim();

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

function nodeStatusLabel(node: CdnflyRecord): string {
    if (!nodeEnabled(node)) {
        const reason = textValue(node.disable_by);

        return reason === '' ? '禁用' : `禁用（${reason}）`;
    }

    const state = textValue(node.state);

    if (state === 'pending') {
        return '待同步';
    }

    if (state === 'process') {
        return '同步中';
    }

    if (state === 'failed') {
        return '同步失败';
    }

    return '正常';
}

function nodeStatusVariant(
    node: CdnflyRecord,
): 'secondary' | 'outline' | 'destructive' {
    if (!nodeEnabled(node)) {
        return 'outline';
    }

    return textValue(node.state) === 'failed' ? 'destructive' : 'secondary';
}

function nodeEnabled(node: CdnflyRecord): boolean {
    return isEnabledValue(node.enable ?? node.status);
}

function isEnabledValue(value: unknown): boolean {
    return value === 1 || value === '1' || value === true;
}

function formatDate(value: unknown): string {
    if (!value) {
        return '-';
    }

    return String(value).slice(0, 16);
}

function nextPage(): void {
    if (hasNextPage.value) {
        void loadNodes(page.value + 1);
    }
}

function prevPage(): void {
    if (hasPreviousPage.value) {
        void loadNodes(page.value - 1);
    }
}

function nextPendingPage(): void {
    if (hasNextPendingPage.value) {
        void loadPendingNodes(pendingPage.value + 1);
    }
}

function prevPendingPage(): void {
    if (hasPreviousPendingPage.value) {
        void loadPendingNodes(pendingPage.value - 1);
    }
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
        // Also refresh the reference data for dropdowns
        regions.value =
            regionRows.value.length > 0 ? regionRows.value : regions.value;
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
        sort: Number(regionForm.sort) || 100,
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
        await loadRegions();
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
/**
 * The page carried five stacked tables with no hierarchy — nodes, pending
 * nodes, node groups, regions, lines — so finding anything meant scrolling
 * past everything. They are now tabs, ordered by the dependency chain an
 * operator actually follows: 区域 -> 节点组 -> 线路 -> 节点.
 */
type NodeTab = 'nodes' | 'pending' | 'topology';

const activeTab = ref<NodeTab>('nodes');

const nodeTabs = computed(() => [
    { key: 'nodes' as const, label: '节点', icon: Server, count: total.value },
    {
        key: 'pending' as const,
        label: '待接入',
        icon: ClipboardList,
        // the count is the point of this tab — an operator needs to see at a
        // glance that a freshly installed node is waiting
        count: pendingRows.value.length,
    },
    {
        key: 'topology' as const,
        label: '区域·节点组·线路',
        icon: Share2,
        count: 0,
    },
]);

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
    const policyJson =
        switchType === 'interval'
            ? JSON.stringify({
                  ip_num: Number(ngForm.backup_policy_ip_num) || 2,
                  interval: Number(ngForm.backup_policy_interval) || 60,
                  switch_order: ngForm.backup_policy_switch_order || 'rand',
              })
            : '{}';

    const payload: AdminNodeGroupPayload = {
        region_id: regionId,
        name: ngForm.name.trim(),
        des: ngForm.des.trim(),
        backup_switch_type: switchType,
        backup_switch_policy: policyJson,
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

    if (typeof raw !== 'string' || raw.trim() === '') return fallback;

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

const lineGroupId = ref<string>('');
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
                'CDNfly 未返回任何 DNS 线路。请在主控面板「系统设置 → DNS 配置」中确认线路已配置。';

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

    if (!id) return;

    selectedIpIds.value = selectedIpIds.value.includes(id)
        ? selectedIpIds.value.filter((x) => x !== id)
        : [...selectedIpIds.value, id];
}

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

    if (!id) return;

    try {
        await unassignAdminLines([id]);
        toast.success('已移出线路');
        await loadLines();
    } catch (error) {
        lineError.value = getErrorMessage(error);
    }
}

function regionNameById(id: unknown): string {
    const numId = asNumber(id);
    if (!numId) return '-';
    const region = regions.value.find((r) => asNumber(r.id) === numId);
    return region ? textValue(region.name) : `#${numId}`;
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <ConsolePageHeader
            title="节点管理"
            :icon="Server"
            :show-api-badge="false"
        />

        <!-- top bar: tabs on the left, the once-per-node action on the right -->
        <div
            class="flex flex-col gap-3 border-b pb-3 md:flex-row md:items-center md:justify-between"
        >
            <div class="flex flex-wrap gap-1">
                <button
                    v-for="tab in nodeTabs"
                    :key="tab.key"
                    type="button"
                    class="flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors"
                    :class="
                        activeTab === tab.key
                            ? 'bg-primary/10 text-primary'
                            : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                    "
                    @click="activeTab = tab.key"
                >
                    <component :is="tab.icon" class="size-4" />
                    {{ tab.label }}
                    <Badge
                        v-if="tab.count"
                        variant="secondary"
                        class="ml-1 px-1.5 py-0 text-xs"
                    >
                        {{ tab.count }}
                    </Badge>
                </button>
            </div>

            <Button variant="outline" @click="installDialogOpen = true">
                <Terminal data-icon="inline-start" />
                执行命令
            </Button>
        </div>

        <template v-if="activeTab === 'nodes'">
            <Card class="gap-4">
                <CardContent class="pt-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative w-full sm:w-72">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                v-model="filters.search"
                                class="pl-9"
                                placeholder="搜索节点名称、IP、ID"
                                @keyup.enter="submitSearch"
                            />
                        </div>
                        <Select v-model="filters.status">
                            <SelectTrigger class="w-32">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem :value="NODE_STATUS_ALL">
                                        全部状态
                                    </SelectItem>
                                    <SelectItem value="1">启用</SelectItem>
                                    <SelectItem value="0">禁用</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <Select v-model="filters.per_page">
                            <SelectTrigger class="w-28">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="20">20 条</SelectItem>
                                    <SelectItem value="50">50 条</SelectItem>
                                    <SelectItem value="100">100 条</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <div class="flex flex-wrap gap-2">
                            <Button :disabled="loading" @click="submitSearch">
                                <Spinner
                                    v-if="loading"
                                    data-icon="inline-start"
                                />
                                <Search v-else data-icon="inline-start" />
                                搜索
                            </Button>
                            <Button
                                variant="outline"
                                :disabled="loading"
                                @click="loadNodes()"
                            >
                                <RefreshCw data-icon="inline-start" />
                                刷新
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Alert v-if="errorMessage" variant="destructive">
                <AlertCircle data-icon="alert" />
                <AlertTitle>节点管理请求失败</AlertTitle>
                <AlertDescription>{{ errorMessage }}</AlertDescription>
            </Alert>

            <Alert v-if="referenceError" variant="destructive">
                <AlertCircle data-icon="alert" />
                <AlertTitle>节点引用数据加载失败</AlertTitle>
                <AlertDescription>{{ referenceError }}</AlertDescription>
            </Alert>

            <Card>
                <CardHeader
                    class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
                >
                    <CardTitle class="text-base">节点列表</CardTitle>
                    <span class="text-sm text-muted-foreground">
                        {{ paginationText }}
                    </span>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto rounded-md border">
                        <table class="w-full min-w-[1040px] text-sm">
                            <thead
                                class="border-y bg-muted/50 text-muted-foreground"
                            >
                                <tr>
                                    <th class="px-6 py-3 text-left font-medium">
                                        节点
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium">
                                        状态
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium">
                                        节点组
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium">
                                        区域
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium">
                                        线路
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium">
                                        权重
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium">
                                        带宽
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium">
                                        创建时间
                                    </th>
                                    <th
                                        class="w-72 px-6 py-3 text-right font-medium"
                                    >
                                        操作
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="loading && rows.length === 0">
                                    <td
                                        class="px-6 py-16 text-center"
                                        colspan="9"
                                    >
                                        <Spinner />
                                    </td>
                                </tr>
                                <tr
                                    v-for="node in rows"
                                    :key="textValue(node.id) || nodeName(node)"
                                    class="border-b last:border-b-0"
                                >
                                    <td class="px-6 py-4">
                                        <div class="font-medium">
                                            {{ nodeName(node) }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            ID {{ textValue(node.id) || '-' }} /
                                            {{ textValue(node.ip) || '-' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <Badge
                                            :variant="nodeStatusVariant(node)"
                                        >
                                            {{ nodeStatusLabel(node) }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-4 text-muted-foreground">
                                        {{
                                            textValue(
                                                node.node_group_id ??
                                                    node.group_id,
                                            ) || '-'
                                        }}
                                    </td>
                                    <td class="px-4 py-4 text-muted-foreground">
                                        {{ textValue(node.region_id) || '-' }}
                                    </td>
                                    <td class="px-4 py-4 text-muted-foreground">
                                        {{ textValue(node.line_id) || '-' }}
                                    </td>
                                    <td class="px-4 py-4">
                                        {{ textValue(node.weight) || '-' }}
                                    </td>
                                    <td class="px-4 py-4">
                                        {{ textValue(node.bandwidth) || '-' }}
                                    </td>
                                    <td class="px-4 py-4 text-muted-foreground">
                                        {{
                                            formatDate(
                                                node.created_at ??
                                                    node.create_at,
                                            )
                                        }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex justify-end gap-2">
                                            <Button
                                                :variant="
                                                    nodeEnabled(node)
                                                        ? 'outline'
                                                        : 'default'
                                                "
                                                size="sm"
                                                :disabled="
                                                    togglingNodeId ===
                                                    asNumber(node.id)
                                                "
                                                @click="
                                                    setNodeEnabled(
                                                        node,
                                                        !nodeEnabled(node),
                                                    )
                                                "
                                            >
                                                <Spinner
                                                    v-if="
                                                        togglingNodeId ===
                                                        asNumber(node.id)
                                                    "
                                                    data-icon="inline-start"
                                                />
                                                <PowerOff
                                                    v-else-if="
                                                        nodeEnabled(node)
                                                    "
                                                    data-icon="inline-start"
                                                />
                                                <Power
                                                    v-else
                                                    data-icon="inline-start"
                                                />
                                                {{
                                                    nodeEnabled(node)
                                                        ? '禁用'
                                                        : '启用'
                                                }}
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="openEditDialog(node)"
                                            >
                                                <Pencil
                                                    data-icon="inline-start"
                                                />
                                                编辑
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                @click="openDeleteNode(node)"
                                            >
                                                <Trash2
                                                    data-icon="inline-start"
                                                />
                                                删除
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!loading && rows.length === 0">
                                    <td
                                        class="px-6 py-16 text-center text-muted-foreground"
                                        colspan="9"
                                    >
                                        暂无节点
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <div class="flex items-center justify-end gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!hasPreviousPage || loading"
                    @click="prevPage"
                >
                    上一页
                </Button>
                <span class="text-sm text-muted-foreground">
                    第 {{ page }} 页
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!hasNextPage || loading"
                    @click="nextPage"
                >
                    下一页
                </Button>
            </div>
        </template>

        <template v-if="activeTab === 'pending'">
            <Card>
                <CardHeader
                    class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-md border bg-card"
                        >
                            <ClipboardList class="size-5" />
                        </div>
                        <div>
                            <CardTitle class="text-base">节点接入</CardTitle>
                            <p class="mt-1 text-sm text-muted-foreground">
                                节点回传后在此填写名称、备注、区域和类型完成接入。
                            </p>
                        </div>
                    </div>
                    <Button
                        variant="outline"
                        :disabled="pendingLoading"
                        @click="loadPendingNodes()"
                    >
                        <RefreshCw data-icon="inline-start" />
                        刷新列表
                    </Button>
                </CardHeader>
                <CardContent class="grid gap-6">
                    <Alert v-if="pendingError" variant="destructive">
                        <AlertCircle data-icon="alert" />
                        <AlertTitle>待初始化节点加载失败</AlertTitle>
                        <AlertDescription>{{ pendingError }}</AlertDescription>
                    </Alert>

                    <div class="grid gap-3">
                        <div
                            class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
                        >
                            <div>
                                <div class="font-medium">待初始化节点</div>
                                <div class="text-sm text-muted-foreground">
                                    在节点机执行「执行命令」中的安装命令，节点回传后点击刷新。
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Select v-model="pendingFilters.per_page">
                                    <SelectTrigger class="w-28">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="10"
                                                >10 条</SelectItem
                                            >
                                            <SelectItem value="20"
                                                >20 条</SelectItem
                                            >
                                            <SelectItem value="50"
                                                >50 条</SelectItem
                                            >
                                            <SelectItem value="100"
                                                >100 条</SelectItem
                                            >
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <Button
                                    variant="default"
                                    :disabled="pendingLoading"
                                    @click="loadPendingNodes(1)"
                                >
                                    <RefreshCw data-icon="inline-start" />
                                    已执行安装命令，刷新待初始化列表
                                </Button>
                            </div>
                        </div>

                        <div class="overflow-x-auto rounded-md border">
                            <table class="w-full min-w-[720px] text-sm">
                                <thead
                                    class="border-b bg-muted/40 text-muted-foreground"
                                >
                                    <tr>
                                        <th
                                            class="w-28 px-4 py-3 text-left font-medium"
                                        >
                                            ID
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium"
                                        >
                                            IP
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left font-medium"
                                        >
                                            回传时间
                                        </th>
                                        <th
                                            class="w-52 px-4 py-3 text-right font-medium"
                                        >
                                            操作
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-if="
                                            pendingListRequested &&
                                            pendingLoading &&
                                            pendingRows.length === 0
                                        "
                                    >
                                        <td
                                            class="px-4 py-12 text-center"
                                            colspan="4"
                                        >
                                            <Spinner />
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="node in pendingRows"
                                        :key="textValue(node.id)"
                                        class="border-b last:border-b-0"
                                    >
                                        <td class="px-4 py-3">
                                            #{{ textValue(node.id) || '-' }}
                                        </td>
                                        <td class="px-4 py-3 font-mono">
                                            {{ pendingNodeIp(node) }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-muted-foreground"
                                        >
                                            {{
                                                formatDate(
                                                    node.create_at ??
                                                        node.created_at,
                                                )
                                            }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex justify-end gap-2">
                                                <Button
                                                    size="sm"
                                                    @click="
                                                        openInitDialog(node)
                                                    "
                                                >
                                                    <Plus
                                                        data-icon="inline-start"
                                                    />
                                                    初始化
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    @click="
                                                        openDeletePendingNode(
                                                            node,
                                                        )
                                                    "
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
                                            pendingListRequested &&
                                            !pendingLoading &&
                                            pendingRows.length === 0
                                        "
                                    >
                                        <td
                                            class="px-4 py-12 text-center text-muted-foreground"
                                            colspan="4"
                                        >
                                            暂无待初始化节点
                                        </td>
                                    </tr>
                                    <tr v-if="!pendingListRequested">
                                        <td
                                            class="px-4 py-12 text-center text-muted-foreground"
                                            colspan="4"
                                        >
                                            先在节点机执行安装命令，然后刷新待初始化列表。
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div
                            v-if="pendingListRequested"
                            class="flex items-center justify-end gap-2"
                        >
                            <span class="text-sm text-muted-foreground">
                                {{ pendingPaginationText }}
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="
                                    !hasPreviousPendingPage || pendingLoading
                                "
                                @click="prevPendingPage"
                            >
                                上一页
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="
                                    !hasNextPendingPage || pendingLoading
                                "
                                @click="nextPendingPage"
                            >
                                下一页
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </template>

        <template v-if="activeTab === 'topology'">
            <!-- ─── 节点组管理 (CRUD) ─── -->
            <Card>
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
            <Card>
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
                        <AlertTitle>无法读取 DNS 线路</AlertTitle>
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
                            <div class="overflow-x-auto rounded-md border">
                                <table class="w-full text-sm">
                                    <thead
                                        class="bg-muted/40 text-xs text-muted-foreground"
                                    >
                                        <tr>
                                            <th class="px-4 py-2 text-left">
                                                节点
                                            </th>
                                            <th class="px-4 py-2 text-left">
                                                IP
                                            </th>
                                            <th class="px-4 py-2 text-left">
                                                线路
                                            </th>
                                            <th class="px-4 py-2 text-right">
                                                操作
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="l in lineRows"
                                            :key="String(l.id)"
                                            class="border-t"
                                        >
                                            <td class="px-4 py-2">
                                                {{ textValue(l.node_name) }}
                                            </td>
                                            <td class="px-4 py-2">
                                                {{ textValue(l.ip) }}
                                            </td>
                                            <td class="px-4 py-2">
                                                {{ textValue(l.line_name) }}
                                            </td>
                                            <td class="px-4 py-2 text-right">
                                                <Button
                                                    variant="destructive"
                                                    size="sm"
                                                    @click="removeAssignment(l)"
                                                >
                                                    <Trash2
                                                        data-icon="inline-start"
                                                    />
                                                    移出
                                                </Button>
                                            </td>
                                        </tr>
                                        <tr v-if="lineRows.length === 0">
                                            <td
                                                colspan="4"
                                                class="px-4 py-8 text-center text-muted-foreground"
                                            >
                                                该线路暂无节点
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
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

                            <div class="overflow-x-auto rounded-md border">
                                <table class="w-full text-sm">
                                    <thead
                                        class="bg-muted/40 text-xs text-muted-foreground"
                                    >
                                        <tr>
                                            <th class="w-12 px-4 py-2"></th>
                                            <th class="px-4 py-2 text-left">
                                                节点
                                            </th>
                                            <th class="px-4 py-2 text-left">
                                                IP
                                            </th>
                                            <th class="px-4 py-2 text-left">
                                                类型
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="n in lineCandidates"
                                            :key="String(n.id)"
                                            class="cursor-pointer border-t hover:bg-accent/40"
                                            @click="toggleIpSelection(n)"
                                        >
                                            <td class="px-4 py-2">
                                                <Checkbox
                                                    :model-value="
                                                        isIpSelected(n)
                                                    "
                                                />
                                            </td>
                                            <td class="px-4 py-2">
                                                {{ textValue(n.name) }}
                                            </td>
                                            <td class="px-4 py-2">
                                                {{ textValue(n.ip) }}
                                            </td>
                                            <td
                                                class="px-4 py-2 text-muted-foreground"
                                            >
                                                {{
                                                    Number(n.pid) !== 0
                                                        ? '附加 IP'
                                                        : '主 IP'
                                                }}
                                            </td>
                                        </tr>
                                        <tr
                                            v-if="
                                                lineCandidates.length === 0 &&
                                                !lineCandidatesLoading
                                            "
                                        >
                                            <td
                                                colspan="4"
                                                class="px-4 py-8 text-center text-muted-foreground"
                                            >
                                                该区域暂无可用 L1 节点
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
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
                        登录新节点机执行。执行完成后节点会自动回传，到「待接入」完成初始化。
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
                        提交字段：pending_node_id、region_id、name、des、type。
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
                        <textarea
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

        <Dialog v-model:open="nodeDialogOpen">
            <DialogScrollContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>编辑节点</DialogTitle>
                    <DialogDescription>
                        修改节点配置，提交后立即生效。
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-5" @submit.prevent="submitNode">
                    <Alert v-if="nodeFormError" variant="destructive">
                        <AlertCircle data-icon="alert" />
                        <AlertTitle>提交失败</AlertTitle>
                        <AlertDescription>{{ nodeFormError }}</AlertDescription>
                    </Alert>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="node-name">节点名称</Label>
                            <Input
                                id="node-name"
                                v-model="form.name"
                                autocomplete="off"
                                required
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="node-ip">IP 地址</Label>
                            <Input
                                id="node-ip"
                                v-model="form.ip"
                                autocomplete="off"
                                required
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>状态</Label>
                            <Select v-model="form.status">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="1">正常</SelectItem>
                                        <SelectItem value="0">停用</SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="node-weight">权重</Label>
                            <Input
                                id="node-weight"
                                v-model="form.weight"
                                min="0"
                                type="number"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>节点组</Label>
                            <Select v-model="form.node_group_id">
                                <SelectTrigger :disabled="referencesLoading">
                                    <SelectValue placeholder="未选择" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem
                                            v-for="option in groupOptions"
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
                            <Label>区域</Label>
                            <Select v-model="form.region_id">
                                <SelectTrigger :disabled="referencesLoading">
                                    <SelectValue placeholder="未选择" />
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
                            <Label>线路</Label>
                            <Select v-model="form.line_id">
                                <SelectTrigger :disabled="referencesLoading">
                                    <SelectValue placeholder="未选择" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem
                                            v-for="option in lineOptions"
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
                            <Label for="node-bandwidth">带宽</Label>
                            <Input
                                id="node-bandwidth"
                                v-model="form.bandwidth"
                                min="0"
                                type="number"
                            />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="node-des">备注</Label>
                        <textarea
                            id="node-des"
                            v-model="form.des"
                            class="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            type="button"
                            @click="nodeDialogOpen = false"
                        >
                            取消
                        </Button>
                        <Button :disabled="saving" type="submit">
                            <Spinner v-if="saving" data-icon="inline-start" />
                            <Save v-else data-icon="inline-start" />
                            保存
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

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
                        <textarea
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
                        <textarea
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
