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
