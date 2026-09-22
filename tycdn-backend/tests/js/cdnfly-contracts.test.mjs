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
