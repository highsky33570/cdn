import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import ts from 'typescript';
const source = ts.transpileModule(readFileSync('resources/js/lib/adminCertificates.ts', 'utf8'), {compilerOptions:{module:ts.ModuleKind.ESNext}}).outputText;
const {certificateExpiry, certificateDays, certificateStatus, certificateSummary} = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
const now = Date.parse('2026-09-24T00:00:00Z');

test('certificate expiry accepts native timestamps, date strings and missing values', () => {
    assert.equal(certificateExpiry({expire_time: now / 1000}), now);
    assert.equal(certificateExpiry({expire_time: now}), now);
    assert.equal(certificateDays({expire_time: '2026-10-01T00:00:00Z'}, now), 7);
    assert.equal(certificateDays({expire_time: 'invalid'}, now), null);
    assert.equal(certificateDays({}, now), null);
});
test('disabled, cancelled, pending and failed certificates are not shown as normal', () => {
    assert.equal(certificateStatus({enable: 0}).tone, 'danger');
    assert.equal(certificateStatus({type: 'lets', task_enable: 0}).tone, 'danger');
    assert.equal(certificateStatus({type: 'lets', issue_state: 'pending'}).tone, 'warning');
    assert.equal(certificateStatus({type: 'lets', issue_state: 'failed', task_ret: 'DNS failure'}).tip, 'DNS failure');
    assert.equal(certificateStatus({type: 'custom', issue_state: 'pending', sync_state: 'done'}).tone, 'success');
    assert.equal(certificateStatus({type: 'custom', sync_state: 'failed'}).tone, 'danger');
});
test('current-page counts distinguish expiry, issuance status and renewal', () => {
    const rows = [
        {type:'custom', expire_time: now + 86*86400000, auto_renew:1},
        {type:'lets', expire_time: now + 5*86400000, auto_renew:1, issue_state:'failed'},
        {type:'custom', expire_time: now - 2*86400000, auto_renew:0},
        {type:'lets', issue_state:'pending', auto_renew:0},
    ];
    assert.deepEqual(certificateSummary(rows,now), {all:4,normal:2,expiring:1,expired:1,renew:2,noRenew:2});
});
