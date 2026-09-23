import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import ts from 'typescript';

const source = ts.transpileModule(
    readFileSync('resources/js/lib/siteAnalysis.ts', 'utf8'),
    {
        compilerOptions: {
            module: ts.ModuleKind.ESNext,
            target: ts.ScriptTarget.ES2022,
        },
    },
).outputText;
const { topTabs, rankingLogUrl, formatRankingMetric } = await import(
    `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`
);
const query = (type, value, context = {}) =>
    new URL(
        rankingLogUrl('admin', type, value, context),
        'https://console.example',
    );

test('all eight dimensions share request, outbound, origin and action columns', () => {
    assert.equal(topTabs.length, 8);
    for (const tab of topTabs) {
        assert.equal(tab.cols.length, 5);
        assert.deepEqual(
            tab.cols.slice(1).map((c) => c.key),
            ['req', 'traffic', 'backend_traffic', '_action'],
        );
    }
    assert.deepEqual(
        topTabs.map((t) => t.cols[0].key),
        ['domain', 'url', 'fp', 'ip', 'country', 'province', 'isp', 'referer'],
    );
});
test('every dimension maps to the correct access-log filter', () => {
    for (const [type, field] of [
        ['top-tls-fp', 'tls_fp'],
        ['top-ip', 'addr'],
        ['top-country', 'country'],
        ['top-province', 'province'],
        ['top-isp', 'isp'],
        ['top-referer', 'referer'],
    ]) {
        const url = query(type, 'a+b & 中文?/');
        assert.equal(url.pathname, '/console/admin/analytics/logs');
        assert.equal(url.searchParams.get(field), 'a+b & 中文?/');
        assert.equal(url.searchParams.get('filter'), field);
    }
});
test('domain rankings separate listening port and carry the loaded time window', () => {
    const url = query('top-domain', 'example.com:8443', {
        domain: 'old.example',
        server_port: '80',
        start: '2026-09-23 21:00:00',
        end: '2026-09-23 22:00:00',
    });
    assert.equal(url.searchParams.get('domain'), 'example.com');
    assert.equal(url.searchParams.get('server_port'), '8443');
    assert.equal(url.searchParams.get('filter'), 'host');
    assert.equal(url.searchParams.get('start'), '2026-09-23 21:00:00');
    assert.equal(url.searchParams.get('end'), '2026-09-23 22:00:00');
});
test('URL links preserve the entire exact URI and encoded query string', () => {
    const url = query(
        'top-url',
        'https://example.com:8443/a/b/%2F?next=a%26b&tag=x+y',
    );
    assert.equal(url.searchParams.get('domain'), 'example.com');
    assert.equal(url.searchParams.get('server_port'), '8443');
    assert.equal(
        url.searchParams.get('req_uri'),
        '/a/b/%2F?next=a%26b&tag=x+y',
    );
    assert.equal(url.searchParams.get('uri_match_type'), 'exact');
    assert.equal(url.searchParams.get('filter'), 'req_uri');
});
test('implicit HTTP ports, empty paths, relative URIs and IPv6 hosts remain usable', () => {
    assert.equal(
        query('top-url', 'https://example.com').searchParams.get('req_uri'),
        '/',
    );
    assert.equal(
        query('top-url', 'https://example.com').searchParams.get('server_port'),
        '443',
    );
    assert.equal(
        query('top-url', 'http://example.com?q=1').searchParams.get('req_uri'),
        '/?q=1',
    );
    assert.equal(
        query('top-url', 'http://example.com').searchParams.get('server_port'),
        '80',
    );
    assert.equal(
        query('top-url', '/a/b?q=1', {
            domain: 'example.com',
        }).searchParams.get('domain'),
        'example.com',
    );
    assert.equal(
        query('top-domain', '[2001:db8::1]:443').searchParams.get('domain'),
        '[2001:db8::1]',
    );
});
test('scope and dimension filters never navigate to external row values', () => {
    assert.equal(
        query('top-referer', 'https://external.example/a').origin,
        'https://console.example',
    );
    assert.ok(
        rankingLogUrl('user', 'top-ip', '192.0.2.1').startsWith(
            '/console/analytics/logs?',
        ),
    );
});

test('ranking values match decimal units and retain real zero', () => {
    assert.equal(formatRankingMetric(0, 'bytes'), '0 Bytes');
    assert.equal(formatRankingMetric('1000', 'bytes'), '1.00 KB');
    assert.equal(formatRankingMetric(1000000, 'bytes'), '1.00 MB');
    assert.equal(formatRankingMetric(1000000000000, 'bytes'), '1.00 TB');
    assert.equal(formatRankingMetric(12345, 'count'), '12345');
    assert.equal(formatRankingMetric(0, 'count'), '0');
    for (const value of [null, undefined, '', false, 'bad']) {
        assert.equal(formatRankingMetric(value, 'bytes'), '-');
    }
});
