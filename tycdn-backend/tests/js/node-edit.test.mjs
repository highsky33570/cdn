import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import ts from 'typescript';
const source = ts.transpileModule(
    readFileSync('resources/js/lib/nodeEdit.ts', 'utf8'),
    {
        compilerOptions: {
            module: ts.ModuleKind.ESNext,
            target: ts.ScriptTarget.ES2022,
        },
    },
).outputText;
const {
    nodeDetail,
    nodeObject,
    nginxNodeConfig,
    cacheGigabytes,
    trafficSettings,
    trafficPayload,
} = await import(
    `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`
);
test('detail and native JSON settings decode without replacing malformed configuration', () => {
    assert.equal(nodeDetail({ data: { id: 6 } }).id, 6);
    assert.equal(nodeObject('{"country":"CN"}').country, 'CN');
    assert.throws(() => nodeObject('{bad'));
    const quota = trafficSettings(
        '{"enable":false,"from_day":1,"from_hour":"12:00:00","traffic_total":500,"type":["inbound","outbound"],"excl_nic":""}',
    );
    assert.deepEqual(trafficPayload(quota), {
        enable: false,
        from_day: 1,
        from_hour: '12:00:00',
        traffic_total: 500,
        type: ['inbound', 'outbound'],
        excl_nic: '',
    });
    assert.throws(() => trafficPayload({ ...quota, from_day: '32' }));
    assert.throws(() => trafficPayload({ ...quota, enable: true, type: [] }));
});
test('node nginx edits preserve unrelated settings and clear only these overrides', () => {
    const original = {
        worker_processes: 2,
        logs_dir: '/logs',
        http: {
            proxy_cache_dir: '/cache',
            proxy_cache_max_size: '100g',
            gzip: 'on',
        },
        stream: { proxy_timeout: '1m' },
    };
    const updated = nginxNodeConfig(original, {
        cache: '',
        size: '',
        logs: '',
    });
    assert.deepEqual(updated, {
        worker_processes: 2,
        http: { gzip: 'on' },
        stream: { proxy_timeout: '1m' },
    });
    assert.equal(original.http.proxy_cache_dir, '/cache');
    assert.equal(
        nginxNodeConfig(original, { cache: '', size: 250, logs: '' }).http
            .proxy_cache_max_size,
        '250g',
    );
    assert.equal(
        nginxNodeConfig(original, {
            cache: '/new',
            size: '1.5',
            logs: '/newlogs',
        }).http.proxy_cache_max_size,
        '1.5g',
    );
    assert.equal(cacheGigabytes('1024m'), '1');
    assert.throws(() =>
        nginxNodeConfig(original, { cache: '', size: '-1', logs: '' }),
    );
});
