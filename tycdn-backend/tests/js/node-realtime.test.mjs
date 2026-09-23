import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import ts from 'typescript';
const source = ts.transpileModule(
    readFileSync('resources/js/lib/nodeRealtime.ts', 'utf8'),
    {
        compilerOptions: {
            module: ts.ModuleKind.ESNext,
            target: ts.ScriptTarget.ES2022,
        },
    },
).outputText;
const { nodeCharts, nodeTraffic, nodeValue } = await import(
    `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`
);
const t = 1790181420000;
test('bandwidth preserves interfaces, named directions and zero samples through console envelopes', () => {
    const charts = nodeCharts(
        {
            ok: true,
            data: {
                code: 0,
                data: {
                    net0: [
                        {
                            name: '入站带宽',
                            data: [
                                [t, 0],
                                [t + 60000, 12000],
                            ],
                        },
                        {
                            name: '出站带宽',
                            data: [
                                [t, 0],
                                [t + 60000, 17000],
                            ],
                        },
                    ],
                    net1: [{ name: '入站带宽', data: [[t, 0]] }],
                },
            },
        },
        'bandwidth',
    );
    assert.equal(charts.length, 2);
    assert.equal(charts[0].series.length, 2);
    assert.deepEqual(charts[0].series[0].points, [
        [t, 0],
        [t + 60000, 12000],
    ]);
    assert.equal(charts[1].title, 'net1');
    assert.equal(nodeValue(12000, 'bps', 1), '12.0 Kbps');
});
test('system load supports nested native values and rejects absent or invalid values', () => {
    const charts = nodeCharts(
        {
            data: {
                cpu: [
                    [t, { value: 0.13 }],
                    [t + 1, { value: null }],
                    [t + 2, 'bad'],
                ],
                mem: [[t, { value: 9.33 }]],
                load: [[t, { value: 0.14 }]],
            },
        },
        'sys_load',
    );
    assert.deepEqual(
        charts.map((c) => c.series[0].points),
        [[[t, 0.13]], [[t, 9.33]], [[t, 0.14]]],
    );
    assert.deepEqual(
        charts.map((c) => c.unit),
        ['%', '%', 'load'],
    );
});
test('TCP and disk retain point counts and partition series', () => {
    assert.deepEqual(
        nodeCharts({ data: [[t, 93]] }, 'tcp_conn')[0].series[0].points,
        [[t, 93]],
    );
    const disk = nodeCharts(
        {
            data: {
                '/': [
                    { name: '空间使用率', data: [[t, 5.52]] },
                    { name: 'inode使用率', data: [[t, 1.1]] },
                ],
            },
        },
        'disk_usage',
    );
    assert.equal(disk[0].series.length, 2);
    assert.equal(disk[0].unit, '%');
});
test('traffic uses native MB, sums shared timestamps and selected directions', () => {
    const payload = {
        data: [
            {
                create_at: '2026-09-23 01:41:41',
                bytes_sent: 2,
                bytes_received: 3,
            },
            {
                create_at: '2026-09-23 01:41:41',
                bytes_sent: '5',
                bytes_received: 7,
            },
            { create_at: 'invalid', bytes_sent: 999 },
        ],
    };
    assert.equal(nodeTraffic(payload, true, false).total, 7);
    assert.equal(nodeTraffic(payload, false, true).total, 10);
    const result = nodeTraffic(payload, true, true);
    assert.equal(result.total, 17);
    assert.equal(result.chart.series[0].points.length, 1);
    assert.equal(nodeValue(result.total, 'MB'), '17.00 MB');
    assert.equal(nodeTraffic(payload, false, false).total, 0);
    assert.equal(nodeValue(1200, 'MB'), '1.20 GB');
});
test('empty monitoring responses do not fabricate samples', () => {
    assert.deepEqual(nodeCharts({ data: {} }, 'bandwidth'), []);
    assert.deepEqual(
        nodeCharts({ data: [] }, 'tcp_conn')[0].series[0].points,
        [],
    );
    assert.equal(nodeTraffic({ data: [] }, true, false).total, 0);
});
