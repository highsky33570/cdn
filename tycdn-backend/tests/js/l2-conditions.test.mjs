import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import ts from 'typescript';

const source = ts.transpileModule(
    readFileSync('resources/js/lib/l2Conditions.ts', 'utf8'),
    {
        compilerOptions: {
            module: ts.ModuleKind.ESNext,
            target: ts.ScriptTarget.ES2022,
        },
    },
).outputText;
const { decodeL2Rules } = await import(
    `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`
);

test('reads the native JSON-encoded country condition without changing its meaning', () => {
    const rule = {
        item: 'node_country_code',
        value2: '',
        value: 'cn',
        op: '!=',
    };
    assert.deepEqual(decodeL2Rules(JSON.stringify([rule])), [rule]);
});

test('preserves empty, multiline, regex and secondary values through a read/write round trip', () => {
    const rules = [
        { item: 'host', op: '=', value: '', value2: '' },
        {
            item: 'req_uri',
            op: 'regex',
            value: '^/a\\d+\n^/b?x=1',
            value2: 'keep',
            extra: 'unchanged',
        },
    ];
    const decoded = decodeL2Rules(JSON.stringify(rules));
    assert.deepEqual(JSON.parse(JSON.stringify(decoded)), rules);
    assert.notEqual(decoded[0], rules[0]);
    assert.deepEqual(
        decodeL2Rules([{ item: 'host', op: '=', value: 'example.test' }]),
        [{ item: 'host', op: '=', value: 'example.test', value2: '' }],
    );
    assert.deepEqual(decodeL2Rules('[]'), []);
});

test('rejects malformed details so an edit cannot silently discard stored rules', () => {
    for (const invalid of [
        undefined,
        null,
        '',
        '{}',
        '[',
        '[null]',
        [{ item: 'host', op: '=' }],
        [{ item: 'host', op: '=', value: 'x', value2: 1 }],
    ]) {
        assert.throws(() => decodeL2Rules(invalid));
    }
});
