import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { test } from 'node:test';
import ts from 'typescript';

// Compile the actual pure adapters with the project's TypeScript dependency.
// No browser, network, generated source files, or additional test runtime needed.
function moduleUrl(file) {
    let js = ts.transpileModule(readFileSync(file, 'utf8'), {
        compilerOptions: {
            module: ts.ModuleKind.ESNext,
            target: ts.ScriptTarget.ES2022,
        },
    }).outputText;
    js = js.replace(
        /from ['"](\.\/[^'"]+)['"]/g,
        (_, path) =>
            `from '${moduleUrl(resolve(dirname(file), `${path}.ts`))}'`,
    );

    return `data:text/javascript;base64,${Buffer.from(js).toString('base64')}`;
}
const response = await import(
    moduleUrl(resolve('resources/js/lib/cdnflyResponse.ts'))
);
const security = await import(
    moduleUrl(resolve('resources/js/lib/cdnflySecurity.ts'))
);
const formatters = await import(
    moduleUrl(resolve('resources/js/lib/formatters.ts'))
);
const blockLogs = await import(
    moduleUrl(resolve('resources/js/lib/blockLogs.ts'))
);
const accessLogs = await import(
    moduleUrl(resolve('resources/js/lib/accessLogs.ts'))
);
const wafLogs = await import(moduleUrl(resolve('resources/js/lib/wafLogs.ts')));
const stream = await import(
    moduleUrl(resolve('resources/js/lib/streamAnalytics.ts'))
);

test('stream queries use master periods, second-precision custom ranges and TCP/UDP ports', () => {
    const now = new Date(2026, 8, 23, 15, 45, 12);

    for (const period of ['1', '6', '12']) {
        const range = stream.streamRange(period, { start: '', end: '' }, now);
        assert.equal(
            Date.parse(range.end.replace(' ', 'T')) -
                Date.parse(range.start.replace(' ', 'T')),
            Number(period) * 3600000,
        );
        assert.equal(range.end, '2026-09-23 15:45:12');
    }

    const range = stream.streamRange('custom', {
        start: '2026-09-22T01:02:03',
        end: '2026-09-23T04:05',
    });
    assert.deepEqual(
        stream.streamRealtimeParams(
            'stream-bandwidth',
            range,
            ' 88/TCP 99/UDP ',
        ),
        {
            type: 'stream-bandwidth',
            start: '2026-09-22 01:02:03',
            end: '2026-09-23 04:05:00',
            port: '88/TCP 99/UDP',
        },
    );

    for (const custom of [
        { start: '', end: '' },
        { start: '2026-09-23T04:05', end: '2026-09-23T04:05' },
        { start: '2026-09-23T04:05', end: '2026-09-22T04:05' },
        { start: 'invalid', end: '2026-09-23T04:05' },
    ]) {
        assert.throws(() => stream.streamRange('custom', custom));
    }
});

test('stream bandwidth converts byte rates to network bits while traffic remains decimal bytes', () => {
    assert.equal(stream.streamMetric(125000, 'stream-bandwidth'), '1.00 Mbps');
    assert.equal(
        stream.streamMetric(125000000, 'stream-bandwidth'),
        '1.00 Gbps',
    );
    assert.equal(stream.streamMetric(0, 'stream-bandwidth'), '0.00 Kbps');
    assert.equal(stream.streamMetric('1000', 'stream-traffic'), '1.00 KB');
    assert.equal(stream.streamMetric(1000000, 'stream-traffic'), '1.00 MB');
    assert.equal(stream.streamMetric(1000000000, 'stream-traffic'), '1.00 GB');
    assert.equal(stream.streamMetric(null, 'stream-traffic'), '—');
    assert.equal(stream.streamBytes(0), '0 Bytes');
    assert.equal(stream.streamBytes('1000'), '1.00 KB');
    assert.equal(stream.streamBytes(undefined), '—');
});

test('stream samples retain real zeros and directional gaps without inventing empty points', () => {
    const time = Date.parse('2026-09-23T00:00:00Z');
    assert.deepEqual(stream.streamSeries({ code: 0, data: [] }), {
        outbound: [],
        inbound: [],
    });
    assert.deepEqual(
        stream.streamSeries({
            code: 0,
            data: [
                [time + 2000, 30],
                [time, 0],
                [0, 0],
                [time + 1000, -1],
            ],
        }),
        {
            outbound: [
                [time, 0],
                [time + 2000, 30],
            ],
            inbound: [],
        },
    );
    assert.deepEqual(
        stream.streamSeries({
            code: 0,
            data: {
                outbound: [
                    [time, 0],
                    [time + 2000, 30],
                ],
                inbound: [[time + 1000, 10]],
            },
        }),
        {
            outbound: [
                [time, 0],
                [time + 2000, 30],
            ],
            inbound: [[time + 1000, 10]],
        },
    );
});

test('port rankings read native values, preserve zero and sort numerically with missing values last', () => {
    const ranks = stream.streamRanks({
        code: 0,
        data: [
            { res: '88/TCP', count: '2', traffic: '1000' },
            { res: '99/UDP', count: '10', traffic: 0, outbound_traffic: 100 },
            { res: '80/TCP', count: 0, new_connections: 123, traffic: '20' },
            { res: '443/TCP' },
        ],
    });
    assert.equal(ranks[1].traffic, 0);
    assert.equal(ranks[2].count, 0);
    assert.equal(ranks[3].count, null);
    assert.deepEqual(
        stream.sortStreamRanks(ranks, 'count', 'desc').map((row) => row.port),
        ['99/UDP', '88/TCP', '80/TCP', '443/TCP'],
    );
    assert.deepEqual(
        stream.sortStreamRanks(ranks, 'traffic', 'asc').map((row) => row.port),
        ['99/UDP', '80/TCP', '88/TCP', '443/TCP'],
    );
    assert.equal(ranks[0].port, '88/TCP');
});

test('WAF queries preserve every native filter, multi-domain input and false/zero values', () => {
    const filters = wafLogs.defaultWafFilters(new Date(2026, 11, 31, 12));
    assert.match(filters.end, /2027-01-01T00:00:00/);

    for (const field of wafLogs.wafFields) {
        filters[field.key] = `test-${field.key}`;
    }

    Object.assign(filters, {
        site_id: '0',
        auto_blocked: 'false',
        host: 'a.test b.test',
        uri_match_type: 'prefix',
    });
    const params = wafLogs.wafParams(filters);

    for (const field of wafLogs.wafFields) {
        assert.equal(params[field.key], filters[field.key]);
    }

    assert.equal(params.uri_match_type, 'prefix');
    assert.equal(params.start, '2026-12-31 00:00:00');
    assert.throws(() => wafLogs.wafParams({ ...filters, end: filters.start }));
});

test('WAF overview decodes nested stats, empty top arrays and all native ranking dimensions', () => {
    const empty = wafLogs.normalizeWafStats({
        ok: true,
        data: {
            code: 0,
            data: {
                total: 0,
                top: [],
                trend: [
                    {
                        time: '2026-09-22 00:00:00',
                        total: 0,
                        protect: 0,
                        observe: 0,
                    },
                ],
            },
        },
    });
    assert.equal(empty.total, 0);
    assert.deepEqual(empty.top, {});
    assert.equal(empty.trend.length, 1);
    const top = {
        domain: [{ key: 'a.test', count: 4 }],
        client_ip: [
            {
                ip: '192.0.2.1',
                country: '中国',
                province: '广东省',
                city: '-',
                count: 4,
            },
        ],
        country: [{ key: '中国', count: 4 }],
        province: [{ key: '广东省', count: 4 }],
        isp: [{ key: '中国移动', count: 4 }],
        uri: [{ key: '/api/', count: 4 }],
        attack_type: [
            { category: 'sqli', count: 3 },
            { category: 'xss', count: 1 },
        ],
    };
    assert.deepEqual(wafLogs.wafRankRows(top, 'uri')[0].filter, {
        request_uri: '/api/',
        uri_match_type: 'exact',
    });
    assert.equal(
        wafLogs.wafRankRows(top, 'client_ip')[0].display,
        '192.0.2.1 (中国-广东省)',
    );
    const types = wafLogs.wafRankRows(top, 'attack_type');
    assert.equal(types[0].percent, 75);
    assert.equal(types[0].display, 'SQL注入');
    assert.deepEqual(types[0].filter, { attack_category: 'sqli' });

    for (const rank of wafLogs.wafRankings) {
        assert.equal(wafLogs.wafRankRows(top, rank.key)[0].count, 4);
    }
});

test('WAF native detail fields translate without losing unknown types, zero IDs or literal evidence', () => {
    const row = {
        site_id: 0,
        node_id: 3,
        host: 'a.test',
        host2: 'b.test',
        action: 'protect',
        module: 'sqli',
        attack_category: 'sqli',
        attack_subtype: 'union',
        client_ip: '192.0.2.1',
        waf_matched_part: 'arg',
        waf_matched_key: 'q',
        waf_payload_sample: '<script>alert(1)</script>',
        req_header: '{"X-Test":"literal"}',
        auto_blocked: 'false',
    };
    assert.equal(wafLogs.wafCell(row, 'host'), 'a.test (b.test)');
    assert.equal(wafLogs.wafCell(row, 'attack_type'), 'UNION查询');
    assert.equal(wafLogs.wafCell(row, 'action'), '拦截');
    assert.equal(wafLogs.wafCell(row, 'waf_matched_part'), '请求参数:q');
    assert.equal(
        wafLogs.wafCell({ ...row, module: 'new-module' }, 'module'),
        'new-module',
    );
    assert.equal(wafLogs.wafTruthy('false'), false);
    const sections = wafLogs.wafDetailSections(row);
    assert.equal(
        sections[3].items.find((i) => i.label === '站点ID').value,
        '0',
    );
    assert.equal(
        sections[2].items.find((i) => i.label === '命中内容').value,
        row.waf_payload_sample,
    );
});

test('WAF false-positive rules merge exact host and URI targets while preserving existing conditions', () => {
    const target = wafLogs.wafAllowTarget({
        site_id: 0,
        host: 'Example.TEST',
        request_uri: 'https://example.test/api?a=1#part',
    });
    assert.deepEqual(target, {
        siteId: '0',
        host: 'example.test',
        uri: '/api',
    });
    const result = wafLogs.mergeWafAllowRule('', target);
    assert.equal(result.rules[0].matcher_groups[0].matcher[1].value[0], '/api');
    assert.equal(
        wafLogs.mergeWafAllowRule(JSON.stringify(result.rules), target).exists,
        true,
    );
    const merged = wafLogs.mergeWafAllowRule(result.rules, {
        ...target,
        uri: '/next',
    });
    assert.deepEqual(merged.rules[0].matcher_groups[0].matcher[1].value, [
        '/api',
        '/next',
    ]);
    assert.equal(result.rules[0].matcher_groups[0].matcher[1].value.length, 1);
    const complex = structuredClone(result.rules);
    complex[0].matcher_groups[0].matcher.push({
        field: 'ip',
        op: '=',
        value: '192.0.2.1',
    });
    const kept = wafLogs.mergeWafAllowRule(complex, target);
    assert.deepEqual(kept.rules[0], complex[0]);
    assert.equal(kept.rules.length, 2);
    assert.throws(() => wafLogs.mergeWafAllowRule('{broken', target));
    assert.throws(() => wafLogs.mergeWafAllowRule('{}', target));
    assert.equal(wafLogs.wafAllowTarget({ host: 'a.test' }), null);
});

test('access logs query the complete current day and retain every download filter', () => {
    const filters = accessLogs.defaultAccessFilters(
        new Date(2026, 11, 31, 16, 20, 30),
    );
    assert.equal(filters.start, '2026-12-31T00:00:00');
    assert.equal(filters.end, '2027-01-01T00:00:00');
    const query = accessLogs.accessLogParams({
        ...filters,
        start: '2026-12-31T00:00',
        host: ' example.test ',
        addr: '2001:db8::1',
        req_uri: '/api/',
        uri_match_type: 'prefix',
        method: 'GET',
        status: '530',
        cache_status: 'MISS',
        server_port: '443',
        node_id: '0',
        tls_fp: 'fp',
        country: '中国',
        province: '广东省',
        isp: '中国移动',
        referer: 'https://example.test/',
    });
    assert.deepEqual(query, {
        start: '2026-12-31 00:00:00',
        end: '2027-01-01 00:00:00',
        host: 'example.test',
        addr: '2001:db8::1',
        req_uri: '/api/',
        uri_match_type: 'prefix',
        method: 'GET',
        status: '530',
        cache_status: 'MISS',
        server_port: 443,
        node_id: 0,
        tls_fp: 'fp',
        country: '中国',
        province: '广东省',
        isp: '中国移动',
        referer: 'https://example.test/',
    });
    assert.throws(() => accessLogs.accessLogParams({ ...filters, end: '' }));
    assert.throws(() =>
        accessLogs.accessLogParams({ ...filters, end: filters.start }),
    );
    assert.throws(() =>
        accessLogs.accessLogParams({ ...filters, server_port: '65536' }),
    );
});

test('access-log cells use millisecond timestamps and preserve zero and the native master columns', () => {
    const timestamp = new Date(2026, 8, 22, 16, 9, 38).getTime();
    assert.equal(
        accessLogs.accessLogCell({ timestamp }, 'timestamp'),
        '09-22 16:09:38',
    );
    assert.equal(
        accessLogs.accessLogCell({ timestamp: null }, 'timestamp'),
        '-',
    );
    assert.equal(
        accessLogs.accessLogCell(
            { host: 'example.test', host2: 'alias.test' },
            'host',
        ),
        'example.test (alias.test)',
    );
    assert.equal(
        accessLogs.accessLogCell(
            { host: 'example.test-no-config', host2: 'example.test' },
            'host',
        ),
        'example.test-no-config',
    );
    assert.equal(
        accessLogs.accessLogCell(
            { country: '中国', province: '广东省', city: '深圳市' },
            'country',
        ),
        '中国-广东省-深圳市',
    );
    assert.equal(
        accessLogs.accessLogCell({ bytes_sent: 0 }, 'bytes_sent'),
        '0',
    );

    for (const key of [
        'sip',
        'content_type',
        'referer',
        'user_agent',
        'up_resp_time',
        'bytes_sent',
        'cache_status',
        'l1_cache_status',
        'l2_cache_status',
        'l2_ip',
        'nid',
    ]) {
        assert.ok(
            accessLogs.accessLogColumns.some((column) => column.key === key),
        );
    }
});

test('access-log job metadata, states and UTF-8 request details are decoded without inventing progress', () => {
    assert.deepEqual(
        accessLogs.accessJobData({
            data: '{"host":"example.test","start":"2026-09-22 00:00:00"}',
        }),
        { host: 'example.test', start: '2026-09-22 00:00:00' },
    );
    assert.deepEqual(
        accessLogs.accessJobData({ data: { host: 'example.test' } }),
        { host: 'example.test' },
    );
    assert.deepEqual(accessLogs.accessJobData({ data: 'broken' }), {});
    assert.equal(accessLogs.accessJobState('process'), '处理中');
    assert.equal(accessLogs.accessJobState('unknown'), 'unknown');
    assert.equal(
        accessLogs.decodeAccessBody(
            Buffer.from('你好 <script>alert(1)</script>').toString('base64'),
        ),
        '你好 <script>alert(1)</script>',
    );
    assert.equal(accessLogs.decodeAccessBody('!invalid!'), '!invalid!');
    assert.equal(
        accessLogs.accessHeaderText(
            '{"Host":"example.test","X-Value":["one","two"]}',
        ),
        'Host: example.test\nX-Value: one, two',
    );
    assert.equal(accessLogs.accessHeaderText('-'), '未开启记录');
});

test('block-log tabs use their actual rule fields and auto-unlock meaning', () => {
    assert.equal(
        blockLogs.blockFilterLabel({ fname: '9', name: '请求速率5-300-50' }),
        '请求速率5-300-50 (ID: 9)',
    );
    assert.equal(
        blockLogs.blockFilterLabel(
            { filter: '9', fname: '请求速率5-300-50' },
            true,
        ),
        '请求速率5-300-50',
    );
    assert.equal(
        blockLogs.blockFilterLabel({ fname: 'extra_f_3' }),
        '自定义规则第3条 (ID: extra_f_3)',
    );
    assert.equal(
        blockLogs.blockFilterLabel({ filter: 'waf_auto_block' }, true),
        'WAF 攻击自动封禁',
    );
    assert.equal(blockLogs.blockFilterLabel({}, true), '-');

    for (const value of [0, '0', false]) {
        assert.equal(blockLogs.manualUnlockLabel(value), '是');
    }

    for (const value of [1, '1', true]) {
        assert.equal(blockLogs.manualUnlockLabel(value), '否');
    }

    for (const value of [null, undefined, '', 2]) {
        assert.equal(blockLogs.manualUnlockLabel(value), '-');
    }
});

test('block-log queries preserve site zero and have no implicit history time cutoff', () => {
    const filters = {
        ip: '',
        site_id: '',
        filter_name: '',
        start: '',
        end: '',
    };
    assert.deepEqual(blockLogs.blockLogQuery('history', filters), {});
    assert.deepEqual(
        blockLogs.blockLogQuery('current', {
            ...filters,
            site_id: '0',
            ip: ' 192.0.2.1 ',
            filter_name: '9',
        }),
        { site_id: '0', ip: '192.0.2.1', filter_name: '9' },
    );
    const range = {
        ...filters,
        start: '2026-09-22T00:00:00',
        end: '2026-09-22T13:30:47',
        filter_name: '9',
    };
    assert.deepEqual(blockLogs.blockLogQuery('history', range), {
        start: new Date(2026, 8, 22, 0, 0, 0).getTime() / 1000,
        end: new Date(2026, 8, 22, 13, 30, 47).getTime() / 1000,
    });
    assert.deepEqual(blockLogs.blockLogQuery('stats', range), {});
    assert.throws(() =>
        blockLogs.blockLogQuery('history', { ...range, end: '' }),
    );
    assert.throws(() =>
        blockLogs.blockLogQuery('history', { ...range, start: 'invalid' }),
    );
    assert.throws(() =>
        blockLogs.blockLogQuery('history', {
            ...range,
            end: '2026-09-21T00:00:00',
        }),
    );
});

test('block timestamps decode Unix seconds and row keys distinguish site and filter', () => {
    const date = new Date(2026, 8, 22, 13, 30, 47);
    assert.equal(
        blockLogs.blockTimestamp(date.getTime() / 1000),
        '2026-09-22 13:30:47',
    );
    assert.equal(
        blockLogs.blockTimestamp(String(date.getTime() / 1000)),
        '2026-09-22 13:30:47',
    );

    for (const value of [null, undefined, '', 'invalid']) {
        assert.equal(blockLogs.blockTimestamp(value), '-');
    }

    const row = {
        site_id: 0,
        ip: '192.0.2.1',
        fname: '9',
        create_at: 1790055047,
    };
    assert.notEqual(
        blockLogs.blockRowKey(row),
        blockLogs.blockRowKey({ ...row, site_id: 1 }),
    );
    assert.notEqual(
        blockLogs.blockRowKey(row),
        blockLogs.blockRowKey({ ...row, fname: '10' }),
    );
});

test('USDT display preserves numeric units, zero and six-decimal gateway amounts', () => {
    assert.equal(formatters.formatMoney('50.00'), '50.00 USDT');
    assert.equal(formatters.formatMoney(0), '0.00 USDT');
    assert.equal(formatters.formatMoney('50.000123'), '50.000123 USDT');
    assert.equal(formatters.formatMoney('0.000001'), '0.000001 USDT');
    assert.equal(formatters.formatMoney(-2.5), '-2.50 USDT');

    for (const missing of [null, undefined, '', ' ', 'invalid', true]) {
        assert.equal(formatters.formatMoney(missing), '-');
    }
});

test('CDNfly count controls totals instead of the number of rows on this page', () => {
    assert.equal(
        response.extractCdnflyTotal(
            { code: 0, count: 81, data: [{ id: 1 }] },
            1,
        ),
        81,
    );
    assert.equal(
        response.extractCdnflyTotal(
            { ok: true, data: { count: '81', data: [] } },
            0,
        ),
        81,
    );
    assert.equal(response.extractCdnflyTotal({ total: 0 }, 9), 0);
    assert.equal(response.extractCdnflyTotal({ meta: { total: '20' } }, 0), 20);
    assert.equal(response.extractCdnflyTotal({ count: null }, 9), 9);
});

test('null API-key data is disabled, never mistaken for an enabled envelope', () => {
    assert.equal(response.extractCdnflyRecord({ code: 0, data: null }), null);
    assert.deepEqual(
        response.extractCdnflyRecord({
            ok: true,
            data: { code: 0, data: { api_key: 'key' } },
        }),
        { api_key: 'key' },
    );
    assert.deepEqual(response.extractCdnflyRecord({ id: 9, data: '[]' }), {
        id: 9,
        data: '[]',
    });
});

test('rows unwrap local and CDNfly pagination without treating metadata as records', () => {
    assert.deepEqual(
        response.extractCdnflyRows({
            ok: true,
            data: { count: 2, data: [{ id: 1 }, { id: 2 }] },
        }),
        [{ id: 1 }, { id: 2 }],
    );
    assert.deepEqual(response.extractCdnflyRows({ code: 0, data: null }), []);
});

test('all site ranking dimensions use res and preserve zero metrics', () => {
    for (const [type, key] of Object.entries({
        'top-domain': 'domain',
        'top-url': 'url',
        'top-tls-fp': 'fp',
        'top-ip': 'ip',
        'top-country': 'country',
        'top-province': 'province',
        'top-isp': 'isp',
        'top-referer': 'referer',
    })) {
        const [row] = response.siteRankingRows(
            { data: [{ res: 'sample', count: 0, traffic: 0, up_recv: 0 }] },
            type,
        );
        assert.equal(row[key], 'sample');
        assert.equal(row.req, 0);
        assert.equal(row.backend_traffic, 0);
    }
});

test('native JSON strings and already-decoded forwarding settings both render', () => {
    const listens = [
        { protocol: 'tcp', port: 80 },
        { protocol: 'udp', port: 53 },
    ];
    assert.equal(
        response.streamListenText(JSON.stringify(listens)),
        '80/tcp, 53/udp',
    );
    assert.equal(response.streamListenText(listens), '80/tcp, 53/udp');
    assert.equal(
        response.streamBackendText({
            backend: '[{"addr":"2001:db8::1"},{"addr":"origin.test"}]',
            backend_port: 443,
        }),
        '[2001:db8::1]:443, origin.test:443',
    );
    assert.deepEqual(response.cdnflyJsonObject('{"enable":true}'), {
        enable: true,
    });
    assert.deepEqual(response.cdnflyJsonRows('invalid'), []);
});

test('stream charts support legacy point arrays and v6 directional series', () => {
    assert.deepEqual(
        response.cdnflyStreamSeries({
            data: [
                [1000, 0],
                [2000, 10],
            ],
        }),
        {
            outbound: [
                [1000, 0],
                [2000, 10],
            ],
            inbound: [],
        },
    );
    assert.deepEqual(
        response.cdnflyStreamSeries({
            data: { outbound: [[1000, 3]], inbound: [[1000, 5]] },
        }),
        { outbound: [[1000, 3]], inbound: [[1000, 5]] },
    );
    assert.deepEqual(
        response.cdnflyStreamSeries({
            data: [
                ['invalid', 3],
                [1000, NaN],
            ],
        }).outbound,
        [],
    );
});

test('inclusive date selection becomes the exclusive CDNfly end date across calendar boundaries', () => {
    assert.equal(
        response.inclusiveUsageEnd('2026-09-18 23:59:59'),
        '2026-09-19',
    );
    assert.equal(response.inclusiveUsageEnd('2026-12-31'), '2027-01-01');
    assert.equal(response.inclusiveUsageEnd('2028-02-28'), '2028-02-29');
});

test('overview totals send date-only ranges including today without adding an eighth day', () => {
    const now = new Date(2026, 8, 21, 16, 8, 2);
    assert.deepEqual(response.usageCountRange('today', now), {
        start: '2026-09-21',
        end: '2026-09-22',
    });
    assert.deepEqual(response.usageCountRange('7d', now), {
        start: '2026-09-15',
        end: '2026-09-22',
    });
    assert.deepEqual(
        response.usageCountRange('7d', new Date(2027, 0, 2, 0, 5)),
        { start: '2026-12-27', end: '2027-01-03' },
    );
    assert.deepEqual(
        response.usageCountRange('today', new Date(2028, 1, 29, 23, 59)),
        { start: '2028-02-29', end: '2028-03-01' },
    );
});

test('CC matchers use arrays and retain header values and unknown fields while editing', () => {
    const native = [
        {
            item: 'header',
            op: '=',
            value: 'User-Agent',
            value2: 'curl',
            case_sensitive: false,
        },
    ];
    const form = security.parseCcMatcher(JSON.stringify(native));
    assert.deepEqual(security.buildCcMatcher(form), native);
    form[0].operator = 'contain';
    assert.deepEqual(security.buildCcMatcher(form), [
        { ...native[0], op: 'contain' },
    ]);
});

const configEditor = await import(
    moduleUrl(resolve('resources/js/lib/configEditor.ts'))
);
const streamBatch = await import(
    moduleUrl(resolve('resources/js/lib/streamBatch.ts'))
);
const monitoring = await import(
    moduleUrl(resolve('resources/js/lib/monitorSeries.ts'))
);

test('editing a JSON-text origin preserves other origins, weights and unknown fields', () => {
    const original = configEditor.configRecord({
        id: 9,
        backend:
            '[{"addr":"192.0.2.1","weight":4,"state":"up","custom":true},{"addr":"192.0.2.2","weight":2,"state":"down"}]',
        https_listen: '{"cert":4,"http3":1,"future_flag":"keep"}',
    });
    const edited = configEditor.setField(original, 'https_listen.http3', 0);
    assert.deepEqual(
        configEditor.configPatch(original, edited, ['backend', 'https_listen']),
        { https_listen: { cert: 4, http3: 0, future_flag: 'keep' } },
    );
    assert.equal(edited.backend[0].weight, 4);
    assert.equal(edited.backend[1].addr, '192.0.2.2');
    assert.equal(original.https_listen.http3, 1);
});
test('malformed stored configuration is retained and cannot be silently replaced through a nested edit', () => {
    const original = configEditor.configRecord({ https_listen: '{broken' });
    assert.equal(original.https_listen, '{broken');
    assert.throws(() =>
        configEditor.setField(original, 'https_listen.cert', 9),
    );
    assert.deepEqual(
        configEditor.configPatch(original, original, ['https_listen']),
        {},
    );
    assert.deepEqual(
        configEditor.setField({ https_listen: '' }, 'https_listen.cert', 9),
        { https_listen: { cert: 9 } },
    );
});
test('batch forwarding validates every line before any creation and handles IPv6 origins', () => {
    assert.deepEqual(
        streamBatch.parseStreamBatch('tcp | 8443 | 2001:db8::1 | 443')[0],
        {
            protocol: 'tcp',
            port: 8443,
            origin: '2001:db8::1',
            originPort: 443,
            status: 'pending',
        },
    );
    assert.throws(() =>
        streamBatch.parseStreamBatch(
            'tcp|8443|192.0.2.1|443\nudp|70000|192.0.2.2|443',
        ),
    );
    assert.throws(() =>
        streamBatch.parseStreamBatch(
            'tcp|8443|192.0.2.1|443\ntcp|8443|192.0.2.2|443',
        ),
    );
});
test('node monitoring decodes nested interface series and preserves real empty datasets', () => {
    assert.deepEqual(
        monitoring.monitorSeries({
            code: 0,
            data: {
                eth0: [
                    {
                        name: 'outbound',
                        data: [
                            [1700000000000, 1200],
                            [1700000060000, 1800],
                        ],
                    },
                ],
            },
        }),
        [
            {
                name: 'outbound',
                points: [
                    [1700000000000, 1200],
                    [1700000060000, 1800],
                ],
            },
        ],
    );
    assert.deepEqual(monitoring.monitorSeries({ code: 0, data: [] }), []);
    assert.deepEqual(
        monitoring.monitorSeries({
            code: 0,
            data: [
                {
                    create_at: 1700000000000,
                    bytes_sent: '1024',
                    bytes_received: 0,
                },
                {
                    create_at: 1700000060000,
                    bytes_sent: 2048,
                    bytes_received: null,
                },
            ],
        }),
        [
            {
                name: '出站流量（B）',
                points: [
                    [1700000000000, 1024],
                    [1700000060000, 2048],
                ],
            },
            { name: '入站流量（B）', points: [[1700000000000, 0]] },
        ],
    );
});

const nodeManagement = await import(
    moduleUrl(resolve('resources/js/lib/nodeManagement.ts'))
);

test('node list sends master filters and explicitly requests secondary IPs', () => {
    assert.deepEqual(
        nodeManagement.nodeListQuery(
            { search: '  156.234  ', region: '2', status: '0', type: 'L2' },
            3,
            30,
        ),
        {
            'sub-ip': 1,
            page: 3,
            limit: 30,
            search: '156.234',
            region_id: '2',
            enable: '0',
            type: 'L2',
        },
    );
    assert.deepEqual(
        nodeManagement.nodeListQuery(
            { search: ' ', region: 'all', status: 'all', type: 'all' },
            1,
            10,
        ),
        { 'sub-ip': 1, page: 1, limit: 10 },
    );
});
test('secondary IPs stay under their node without increasing the node total', () => {
    const rows = [
        { id: 6, pid: 0 },
        { id: 7, pid: 6 },
        { id: 8, pid: 6 },
        { id: 1, pid: 0 },
        { id: 99, pid: 42 },
    ];
    const tree = nodeManagement.nodeTree(rows);
    assert.equal(tree.length, 2);
    assert.deepEqual(
        tree[0].children.map((r) => r.id),
        [7, 8],
    );
    assert.deepEqual(tree[1].children, []);
    assert.equal(rows.length, 5);
});
test('node bandwidth is already bits per second and unknown measurements remain unknown', () => {
    assert.equal(nodeManagement.nodeBandwidth(3384), '3.38 Kbps');
    assert.equal(nodeManagement.nodeBandwidth(900000), '0.90 Mbps');
    assert.equal(nodeManagement.nodeBandwidth(900000000), '0.90 Gbps');

    for (const value of [0, null, undefined, '', 'invalid']) {
        assert.equal(nodeManagement.nodeBandwidth(value), '未知');
    }
});
test('node status distinguishes sync failure, disabled reasons, and secondary IPs', () => {
    assert.equal(
        nodeManagement.nodeStatus({ enable: 1, pid: 0, state: 'failed' }).label,
        '同步失败',
    );
    assert.equal(
        nodeManagement.nodeStatus({ enable: 0, disable_by: 'sync_error' })
            .label,
        '禁用（同步错误）',
    );
    assert.equal(
        nodeManagement.nodeStatus({ enable: 1, pid: 6 }).label,
        '正常',
    );
    assert.equal(
        nodeManagement.nodeStatus({ enable: 1, pid: 0 }).label,
        '状态未知',
    );
    assert.equal(
        nodeManagement.nodeStatus({ enable: 1, pid: 0, state: 'process' })
            .label,
        '同步中',
    );
});
