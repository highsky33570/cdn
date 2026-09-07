# CC 规则结构化表单方案

> 将 CC 三种资源（匹配器、过滤器、规则组）的 JSON textarea 改为结构化表单，
> 并区分系统规则和用户规则。

---

## 一、CDNfly API 字段确认

### 1.1 匹配器 (cc-matchs)

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `name` | string | 是 | 匹配器名称 |
| `data` | object | 是 | 匹配条件，`{}` 表示匹配所有 |
| `des` | string | 否 | 备注 |
| `enable` | number | 否 | 1=启用 / 0=禁用 |
| `uid` | number | 否 | 用户 ID（管理端：有=用户规则，无=系统规则） |

**`data` 结构（对象，键名为匹配字段）：**

```json
{
  "uri": {
    "operator": "contain",
    "value": "/api"
  }
}
```

`data` 为 `{}` 时表示匹配所有请求。

**可用匹配键：**

| 键名 | 说明 |
|------|------|
| `ip` | IP 地址 |
| `host` | 请求域名 |
| `req_uri` | 完整请求 URI（含查询参数） |
| `req_method` | 请求方法 |
| `uri` | URI 路径 |
| `user_agent` | User-Agent |
| `referer` | Referer |
| `country_iso_code` | 国家 ISO 代码 |
| `asnumber` | AS 号码 |
| `province` | 省份 |
| `city` | 城市 |
| `isp` | 运营商 |

**可用操作符：**

| operator | 说明 | value 格式 |
|----------|------|-----------|
| `=` | 等于 | string |
| `!=` | 不等于 | string |
| `contain` | 包含 | string |
| `!contain` | 不包含 | string |
| `AC` | 在列表中 | **array** |
| `!AC` | 不在列表中 | **array** |

> 与 ACL 完全一致，多了 `asnumber`、`province`、`city`、`isp` 四个匹配键。

### 1.2 过滤器 (cc-filters)

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `name` | string | 是 | 过滤器名称 |
| `type` | string | 是 | 过滤器类型（见下表） |
| `within_second` | number | 是 | 统计时间窗口（秒） |
| `max_req` | number | 是 | 最大请求数 |
| `max_req_per_uri` | number | 否 | 单 URI 最大请求数 |
| `extra` | object | 否 | 扩展字段，仅 `url_auth` 类型需要 |
| `des` | string | 否 | 备注 |
| `enable` | number | 否 | 1=启用 / 0=禁用 |
| `uid` | number | 否 | 用户 ID |

**`type` 可选值：**

| type | 说明 |
|------|------|
| `req_rate` | 请求频率限制 |
| `302_challenge` | 302 跳转验证 |
| `browser_verify_auto` | 自动浏览器验证 |
| `slide_filter` | 滑块验证 |
| `captcha_filter` | 验证码 |
| `click_filter` | 点击验证 |
| `url_auth` | URL 鉴权 |
| `delay_jump_filter` | 延迟跳转 |

**`extra` 字段（仅 type=url_auth 时填写）：**

TypeA 模式：

```json
{
  "mode": "TypeA",
  "key": "密钥",
  "sign_name": "sign的参数名",
  "time_name": "time的参数名",
  "time_diff": 300,
  "sign_use_times": 1
}
```

TypeB 模式：

```json
{
  "mode": "TypeB",
  "key": "密钥",
  "sign_name": "sign的参数名",
  "time_diff": 300,
  "sign_use_times": 1
}
```

| 字段 | TypeA | TypeB | 说明 |
|------|-------|-------|------|
| `mode` | 是 | 是 | 鉴权模式 |
| `key` | 是 | 是 | 密钥 |
| `sign_name` | 是 | 是 | sign 的参数名 |
| `time_name` | 是 | — | time 的参数名 |
| `time_diff` | 是 | 是 | 允许的时间差（秒） |
| `sign_use_times` | 是 | 是 | 签名可用次数 |

### 1.3 规则组 (cc-rules)

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `name` | string | 是 | 规则组名称 |
| `sort` | number | 否 | 排序（默认 100） |
| `data` | array | 是 | 规则条目数组 |
| `des` | string | 否 | 备注 |
| `enable` | number | 否 | 1=启用 / 0=禁用 |
| `is_show` | number | 否 | 1=显示 / 0=隐藏 |
| `uid` | number | 否 | 用户 ID |

**`data` 条目结构：**

```json
{
  "action": "ipset",
  "matcher": "1",
  "filter1": "1",
  "filter2": "",
  "state": true
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| `action` | string | 动作：`ipset`（加黑名单）/ `exit`（终止）/ `log`（记录） |
| `matcher` | string | 匹配器 **ID** |
| `filter1` | string | 过滤器 1 **ID** |
| `filter2` | string | 过滤器 2 **ID**（可为空） |
| `state` | boolean | 是否启用此条目 |

---

## 二、系统规则 vs 用户规则

同一个端点，通过 `uid` 字段区分：

| 场景 | `uid` 字段 | 说明 |
|------|-----------|------|
| 系统规则 | 不传 / 空 | 全局生效 |
| 用户规则 | 传用户 ID | 仅对该用户生效 |

**权限控制：**

| 页面 | 可创建系统规则 | 可创建用户规则 | uid 表单项 |
|------|--------------|--------------|-----------|
| AdminSecurity | 是 | 是 | 下拉选择用户（不选=系统规则） |
| UserSecurity | 否 | 否（只能创建自己的） | 不显示 uid 字段，后端自动绑定 |

> 用户端不需要改 uid 逻辑，CDNfly 会根据用户 token 自动绑定。
> 管理端新增时可选 uid，编辑时 uid 只读。

---

## 三、结构化表单设计

### 3.1 匹配器表单

**替换：** JSON textarea → 动态条件行

```
┌─────────────────────────────────────────────────┐
│ 名称:  [________________]                        │
│                                                   │
│ 匹配条件:                        [+ 添加条件]    │
│ ┌──────────────┬──────────┬──────────────┬──┐    │
│ │ 匹配字段 ▼   │ 操作符 ▼  │ 值            │ ✕ │   │
│ │ uri          │ contain  │ /api          │   │   │
│ ├──────────────┼──────────┼──────────────┼──┤    │
│ │ ip           │ =        │ 1.2.3.4       │   │   │
│ └──────────────┴──────────┴──────────────┴──┘    │
│                                                   │
│ 不添加任何条件 = 匹配所有请求（data 为 {}）       │
│                                                   │
│ 启用: [Switch]    备注: [________________]        │
│ 用户 (管理端): [Select 用户 / 不选=系统规则]      │
└─────────────────────────────────────────────────┘
```

**实现方式：**

```ts
type MatcherCondition = {
    key: string;       // ip, host, uri, ...
    operator: string;  // =, !=, contain, ...
    value: string;     // 单值或逗号分隔（AC/!AC 时）
};

const conditions = ref<MatcherCondition[]>([]);

// 表单 → data 对象
function buildMatcherData(): Record<string, unknown> {
    const data: Record<string, unknown> = {};
    for (const c of conditions.value) {
        const isArrayOp = c.operator === 'AC' || c.operator === '!AC';
        data[c.key] = {
            operator: c.operator,
            value: isArrayOp
                ? c.value.split(',').map(s => s.trim()).filter(Boolean)
                : c.value,
        };
    }
    return data;
}

// data 对象 → 表单（编辑时回填）
function parseMatcherData(data: Record<string, unknown>): MatcherCondition[] {
    return Object.entries(data).map(([key, rule]) => ({
        key,
        operator: (rule as any).operator ?? '=',
        value: Array.isArray((rule as any).value)
            ? (rule as any).value.join(', ')
            : String((rule as any).value ?? ''),
    }));
}
```

**匹配字段下拉选项：**

```ts
const MATCHER_KEYS = [
    { value: 'ip', label: 'IP 地址' },
    { value: 'host', label: '域名 (Host)' },
    { value: 'uri', label: 'URI 路径' },
    { value: 'req_uri', label: '完整 URI' },
    { value: 'req_method', label: '请求方法' },
    { value: 'user_agent', label: 'User-Agent' },
    { value: 'referer', label: 'Referer' },
    { value: 'country_iso_code', label: '国家代码' },
    { value: 'asnumber', label: 'AS 号码' },
    { value: 'province', label: '省份' },
    { value: 'city', label: '城市' },
    { value: 'isp', label: '运营商' },
] as const;

const OPERATORS = [
    { value: '=', label: '等于' },
    { value: '!=', label: '不等于' },
    { value: 'contain', label: '包含' },
    { value: '!contain', label: '不包含' },
    { value: 'AC', label: '在列表中' },
    { value: '!AC', label: '不在列表中' },
] as const;
```

### 3.2 过滤器表单

**当前已是结构化**（name, type, within_second, max_req 等），只需改造 `extra` 字段。

**extra 改为：** 仅当 `type === 'url_auth'` 时显示结构化子表单：

```
┌───────────────────────────────────────────────┐
│ ... 现有字段（name, type, within_second 等）  │
│                                                │
│ ▼ URL 鉴权配置（仅 type=url_auth 时显示）     │
│                                                │
│ 模式:           [TypeA ▼]                      │
│ 密钥 (key):     [________________]             │
│ sign 参数名:    [sign__________]               │
│ time 参数名:    [time__________]  ← TypeA only │
│ 时间差 (秒):    [300_____________]             │
│ 签名可用次数:   [1_______________]             │
└───────────────────────────────────────────────┘
```

```ts
const extraForm = reactive({
    mode: 'TypeA',
    key: '',
    sign_name: 'sign',
    time_name: 'time',
    time_diff: '300',
    sign_use_times: '1',
});

function buildExtra(): Record<string, unknown> {
    if (ccForm.type !== 'url_auth') return {};
    const obj: Record<string, unknown> = {
        mode: extraForm.mode,
        key: extraForm.key,
        sign_name: extraForm.sign_name,
        time_diff: Number(extraForm.time_diff),
        sign_use_times: Number(extraForm.sign_use_times),
    };
    if (extraForm.mode === 'TypeA') {
        obj.time_name = extraForm.time_name;
    }
    return obj;
}
```

### 3.3 规则组表单

**替换：** JSON textarea → 动态规则行，matcher/filter 从下拉框选择

**前置：** 打开规则组新增/编辑弹窗时，先 fetch 匹配器列表和过滤器列表。

```
┌───────────────────────────────────────────────────────────┐
│ 名称:  [________________]    排序: [100]                   │
│                                                            │
│ 规则条目:                                   [+ 添加条目]  │
│ ┌────────┬──────────────┬──────────────┬──────────────┬─────┬──┐
│ │ 动作 ▼  │ 匹配器 ▼      │ 过滤器1 ▼     │ 过滤器2 ▼     │ 启用 │ ✕ │
│ │ ipset  │ #1 全站匹配   │ #3 频率限制   │ (无)         │  ✓  │   │
│ │ log    │ #2 API路径    │ #5 验证码     │ #6 滑块      │  ✓  │   │
│ └────────┴──────────────┴──────────────┴──────────────┴─────┴──┘
│                                                            │
│ 启用: [Switch]    显示: [Switch]                           │
│ 备注: [________________]                                   │
│ 用户 (管理端): [Select 用户 / 不选=系统规则]               │
└───────────────────────────────────────────────────────────┘
```

```ts
type RuleEntry = {
    action: string;   // ipset / exit / log
    matcher: string;  // 匹配器 ID
    filter1: string;  // 过滤器 ID
    filter2: string;  // 过滤器 ID（可空）
    state: boolean;
};

const ruleEntries = ref<RuleEntry[]>([]);
const matcherOptions = ref<{ id: string; name: string }[]>([]);
const filterOptions = ref<{ id: string; name: string }[]>([]);

// 打开规则组弹窗时 fetch
async function loadRuleFormOptions(): Promise<void> {
    const [matchers, filters] = await Promise.all([
        ccList('matcher', { limit: 200 }),
        ccList('filter', { limit: 200 }),
    ]);
    matcherOptions.value = extractCdnflyRows(matchers).map(r => ({
        id: String(r.id), name: textValue(r.name),
    }));
    filterOptions.value = extractCdnflyRows(filters).map(r => ({
        id: String(r.id), name: textValue(r.name),
    }));
}

const RULE_ACTIONS = [
    { value: 'ipset', label: '加黑名单 (ipset)' },
    { value: 'exit', label: '终止 (exit)' },
    { value: 'log', label: '仅记录 (log)' },
] as const;
```

---

## 四、管理端 uid 字段

### 4.1 AdminSecurity — CC 新增/编辑弹窗

三种 CC 资源（匹配器、过滤器、规则组）的弹窗都加 `uid` 字段：

```
用户:  [Select ▼]
       ├─ (不选 — 系统规则)
       ├─ #1 admin
       ├─ #2 user01
       └─ ...
```

- 新增时：不选 = 系统规则，选了 = 用户规则
- 编辑时：uid 只读（不允许改归属）
- 列表中增加「类型」列显示「系统」或「用户 #xx」

**用户列表数据源：** 复用已有的 `listAdminUsers` API，弹窗打开时 fetch。

### 4.2 UserSecurity — 不变

用户端不显示 uid 字段，CDNfly 根据 token 自动绑定当前用户。表单逻辑不变，只改 JSON → 结构化。

---

## 五、涉及文件

| 文件 | 改动 |
|------|------|
| `UserSecurity.vue` | 匹配器 data → 动态条件行；过滤器 extra → 结构化子表单；规则组 data → 动态规则行 + fetch 下拉选项 |
| `AdminSecurity.vue` | 同上 + 三种 CC 弹窗加 uid 用户选择（fetch 用户列表） |
| `cdnUserApi.ts` | 无需改（API 函数已有） |
| `adminModulesApi.ts` | CC 相关 admin API（如果还没有的话需要新增） |

---

## 六、执行顺序

| 步骤 | 内容 | 说明 |
|------|------|------|
| 1 | UserSecurity — 匹配器表单结构化 | 最核心的改动，做完验证模式 |
| 2 | UserSecurity — 过滤器 extra 结构化 | 只影响 url_auth 类型 |
| 3 | UserSecurity — 规则组表单结构化 | 需要 fetch 匹配器/过滤器下拉 |
| 4 | AdminSecurity — 复制用户端结构化表单 | CC 部分与用户端相同 |
| 5 | AdminSecurity — 加 uid 用户选择 | 三种 CC 弹窗都加 |

步骤 1-3 在用户端做完验证后，步骤 4 直接复用。
