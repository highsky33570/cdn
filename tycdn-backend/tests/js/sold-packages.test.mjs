import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import ts from 'typescript';

const source = ts.transpileModule(
    readFileSync('resources/js/lib/soldPackages.ts', 'utf8'),
    {
        compilerOptions: {
            module: ts.ModuleKind.ESNext,
            target: ts.ScriptTarget.ES2022,
        },
    },
).outputText;
const { resources, recordData } = await import(
    `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`
);

test('usage includes purchased upgrade quantities and isolates resource types', () => {
    const rows = resources(
        { traffic: 300, domain: 60 },
        { traffic_usage: 35, domain_usage: 2 },
        [
            { type: 'traffic', package_up_amount: 10, amount: 2 },
            { type: 'traffic', package_up_amount: 5, amount: 1 },
            { type: 'domain', package_up_amount: 20, amount: 3 },
        ],
    );
    assert.equal(rows[0].total, 325);
    assert.equal(rows[0].remaining, 290);
    assert.equal(rows[1].total, 120);
    assert.equal(rows[1].used, 2);
});

test('unlimited resources stay unlimited and exceeded resources clamp progress', () => {
    const rows = resources(
        { traffic: -1, domain: 2, main_domain: 0 },
        { traffic_usage: 900, domain_usage: 5, main_domain_usage: 1 },
        [{ type: 'traffic', package_up_amount: 10, amount: 2 }],
    );
    assert.equal(rows[0].total, -1);
    assert.equal(rows[0].unlimited, true);
    assert.equal(rows[0].remaining, null);
    assert.equal(rows[1].remaining, 0);
    assert.equal(rows[1].percent, 100);
    assert.equal(rows[2].percent, 100);
});

test('missing or malformed usage is unknown rather than zero or exceeded', () => {
    const rows = resources(
        { traffic: 300, domain: 60, main_domain: 'invalid' },
        { domain_usage: 'invalid' },
        [],
    );
    assert.equal(rows[0].used, null);
    assert.equal(rows[0].remaining, null);
    assert.equal(rows[1].used, null);
    assert.equal(rows[1].remaining, null);
    assert.equal(rows[1].status, rows[0].status);
    assert.equal(rows[2].total, null);
});

test('native detail envelopes unwrap and invalid bodies are rejected', () => {
    assert.deepEqual(recordData({ data: { data: { id: 5 } } }), { id: 5 });
    assert.throws(() => recordData({ data: null }));
    assert.throws(() => recordData({ data: [] }));
});
