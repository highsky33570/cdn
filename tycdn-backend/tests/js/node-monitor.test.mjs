import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import ts from 'typescript';
const source = ts.transpileModule(
    readFileSync('resources/js/lib/nodeMonitor.ts', 'utf8'),
    {
        compilerOptions: {
            module: ts.ModuleKind.ESNext,
            target: ts.ScriptTarget.ES2022,
        },
    },
).outputText;
const { decodeMonitorConfig, encodeMonitorConfig, smsTemplate } = await import(
    `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`
);
const native = () => ({
    global_check_on: 1,
    check_protocol: 'tcp',
    check_port: 80,
    check_timeout: 5,
    check_node_group: '1,3',
    notify_method: 'email sms',
    notify_msg_type: '节点IP解析 带宽监控',
    interval: 30,
    failed_times: 3,
    failed_rate: 90,
    bw_exceed_times: 2,
    notification_period: '8-22',
    monitor_api: 'preserved',
    ip_enable_templ: '【cdn】节点 {{node_id}} IP {{ip}}',
    ip_enable_templ_id: '123',
    ip_enable_templ_content: 'custom',
    future: { enabled: true },
});
test('monitor config round trips native delimiters, numeric types and hidden fields', () => {
    const original = native(),
        decoded = decodeMonitorConfig(JSON.stringify(original));
    assert.deepEqual(decoded.check_node_group, ['1', '3']);
    assert.deepEqual(decoded.notify_method, ['email', 'sms']);
    assert.deepEqual(JSON.parse(encodeMonitorConfig(decoded)), original);
    decoded.check_node_group = [];
    decoded.notify_method = [];
    decoded.notify_msg_type = [];
    assert.equal(JSON.parse(encodeMonitorConfig(decoded)).notify_method, '');
    decoded.check_timeout = '0.5';
    assert.equal(JSON.parse(encodeMonitorConfig(decoded)).check_timeout, 0.5);
});
test('invalid thresholds and periods block writes', () => {
    for (const [key, value] of [
        ['failed_times', ''],
        ['check_port', 0],
        ['check_port', 65537],
        ['check_timeout', 0],
        ['check_timeout', 6],
        ['interval', 29],
        ['failed_rate', 101],
        ['bw_exceed_times', 1.5],
        ['notification_period', '22-8'],
        ['notification_period', '0-25'],
    ])
        assert.throws(() =>
            encodeMonitorConfig({
                ...decodeMonitorConfig(native()),
                [key]: value,
            }),
        );
    assert.throws(() => decodeMonitorConfig('{}'));
    assert.throws(() => decodeMonitorConfig('bad'));
});
test('SMS conversion preserves native placeholder syntax for each provider', () => {
    const input = '【cdn】节点 {{node_id}} IP {{ip}}';
    assert.equal(
        smsTemplate(input, 'aliyun'),
        '【cdn】节点 ${node_id} IP ${ip}',
    );
    assert.equal(smsTemplate(input, 'qcloud'), '节点 {1} IP {2}');
    assert.equal(smsTemplate(input, 'smsbao'), '【cdn】节点 {node_id} IP {ip}');
    assert.equal(
        smsTemplate(input, 'submail'),
        '【cdn】节点 {node_id} IP {ip}',
    );
    assert.throws(() => smsTemplate(input, 'unknown'));
});
