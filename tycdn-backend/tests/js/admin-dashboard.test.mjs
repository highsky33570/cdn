import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { test } from 'node:test';
import ts from 'typescript';

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
const dashboard = await import(
    moduleUrl(resolve('resources/js/lib/adminDashboard.ts'))
);

test('dashboard date ranges match master calendar boundaries', () => {
    const now = new Date(2026, 8, 23, 21, 0);

    for (const [period, start, end] of [
        ['today', '2026-09-23', '2026-09-24'],
        ['yesterday', '2026-09-22', '2026-09-23'],
        ['last7', '2026-09-16', '2026-09-24'],
        ['last30', '2026-08-24', '2026-09-24'],
        ['lastMonth', '2026-08-01', '2026-09-01'],
    ]) {
        assert.deepEqual(dashboard.dashboardRange(period, now), { start, end });
    }
});
test('last month remains correct across year and month ends', () => {
    assert.deepEqual(
        dashboard.dashboardRange('lastMonth', new Date(2026, 0, 31)),
        { start: '2025-12-01', end: '2026-01-01' },
    );
    assert.deepEqual(
        dashboard.dashboardRange('lastMonth', new Date(2026, 11, 31)),
        { start: '2026-11-01', end: '2026-12-01' },
    );
    assert.deepEqual(
        dashboard.dashboardRange('lastMonth', new Date(2024, 2, 31)),
        { start: '2024-02-01', end: '2024-03-01' },
    );
});
test('dashboard bandwidth uses byte rates and preserves real zero and unavailable data', () => {
    assert.equal(
        dashboard.dashboardMetric('25143', 'bandwidth'),
        '201.14 Kbps',
    );
    assert.equal(dashboard.dashboardMetric('20103063', 'traffic'), '20.10 MB');
    assert.equal(dashboard.dashboardMetric('6067', 'req'), '6067次');
    assert.equal(dashboard.dashboardMetric('0', 'blackip'), '0个');
    assert.equal(dashboard.dashboardMetric(null, 'bandwidth'), '—');
    assert.equal(dashboard.dashboardMetric('', 'req'), '—');
});
test('recharge amounts remain numeric and display USDT', () => {
    assert.equal(dashboard.dashboardMetric('50.00', 'recharge'), '50 USDT');
    assert.equal(dashboard.dashboardMetric(0, 'recharge'), '0 USDT');
    assert.deepEqual(
        dashboard.dashboardPoints(
            { data: [{ time: '2026-09-19', sum: '50.00' }] },
            'recharge',
        ),
        [{ label: '2026-09-19', value: 50 }],
    );
});
test('native usage samples are sorted and missing samples are not invented', () => {
    assert.deepEqual(
        dashboard.dashboardPoints(
            {
                data: {
                    data: [
                        { date: '2026-09-23 02:00:00', value: '0' },
                        { date: '2026-09-23 01:00:00', value: '25' },
                        { date: '2026-09-23 03:00:00', value: null },
                        { date: '2026-09-23 04:00:00', value: 'invalid' },
                    ],
                },
            },
            'bandwidth',
        ),
        [
            { label: '2026-09-23 01:00:00', value: 25 },
            { label: '2026-09-23 02:00:00', value: 0 },
        ],
    );
    assert.deepEqual(dashboard.dashboardPoints({ data: [] }, 'users'), []);
});
