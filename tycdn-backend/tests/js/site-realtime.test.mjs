import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import ts from 'typescript';

const source = ts.transpileModule(
    readFileSync('resources/js/lib/siteRealtime.ts', 'utf8'),
    {
        compilerOptions: {
            module: ts.ModuleKind.ESNext,
            target: ts.ScriptTarget.ES2022,
        },
    },
).outputText;
const { extractMetricSeries } = await import(
    `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`
);
const metric = { label: 'Status codes', color: '#2d8cf0' };
const t = 1790168160000;

test('native 4xx and 5xx series retain their code and actual counts', () => {
    for (const [code, counts] of [
        ['400', [7, 17]],
        ['530', [31, 417]],
    ]) {
        const points = counts.map((value, index) => [t + index * 60000, value]);
        assert.deepEqual(
            extractMetricSeries(
                { data: [{ name: code, value: points }] },
                metric,
            ),
            [{ label: code, color: metric.color, points }],
        );
    }
});

test('multiple codes stay separate, sorted, and do not fabricate missing samples', () => {
    const series = extractMetricSeries(
        {
            data: [
                {
                    name: '400',
                    value: [
                        [t + 60000, 3],
                        [t, 0],
                    ],
                },
                { name: '404', value: [[t + 60000, 9]] },
            ],
        },
        metric,
    );
    assert.deepEqual(
        series.map(({ label, points }) => ({ label, points })),
        [
            {
                label: '400',
                points: [
                    [t, 0],
                    [t + 60000, 3],
                ],
            },
            { label: '404', points: [[t + 60000, 9]] },
        ],
    );
    assert.notEqual(series[0].color, series[1].color);
});

test('native cache percentages below one are not multiplied by 100', () => {
    const points = [
        [t, 0],
        [t + 60000, 0.5],
        [t + 120000, 0.99],
    ];
    assert.deepEqual(
        extractMetricSeries({ data: points }, { ...metric, unit: 'percent' })[0]
            .points,
        points,
    );
});

test('plain metrics retain zero and convert second timestamps and numeric strings', () => {
    assert.deepEqual(
        extractMetricSeries(
            {
                data: [
                    [t / 1000, '0'],
                    [t / 1000 + 60, '2.86'],
                ],
            },
            metric,
        )[0].points,
        [
            [t, 0],
            [t + 60000, 2.86],
        ],
    );
});

test('invalid and unavailable samples do not become a false zero or epoch sample', () => {
    const bad = [
        [null, 3],
        ['', 4],
        [false, 5],
        ['invalid', 1],
        [t, null],
        [t, ''],
        [t, false],
        [t, {}],
        [t, Infinity],
        [t, 'NaN'],
    ];
    for (const data of [bad, [{ name: '500', value: bad }]]) {
        assert.deepEqual(extractMetricSeries({ data }, metric), []);
    }
    for (const result of [
        null,
        {},
        { data: [] },
        { data: [{ name: '500', value: [] }] },
    ]) {
        assert.deepEqual(extractMetricSeries(result, metric), []);
    }
});

test('alternative named datasets and keyed datasets remain supported', () => {
    const points = [[t, 4]];
    for (const data of [
        [{ name: '404', data: points }],
        { 404: points },
        { 404: { data: points } },
        { 404: { value: points } },
    ]) {
        assert.deepEqual(extractMetricSeries({ data }, metric), [
            { label: '404', color: metric.color, points },
        ]);
    }
});

test('timestamped status rows and wide status rows remain supported', () => {
    for (const data of [
        [{ time: t, status: 404, count: 4 }],
        [{ timestamp: t, 404: 4 }],
    ]) {
        assert.deepEqual(extractMetricSeries({ data }, metric), [
            { label: '404', color: metric.color, points: [[t, 4]] },
        ]);
    }
    assert.deepEqual(
        extractMetricSeries(
            { data: [{ time: t, status: 404, count: null }] },
            metric,
        ),
        [],
    );
});
