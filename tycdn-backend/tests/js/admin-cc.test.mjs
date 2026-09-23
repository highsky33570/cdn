import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import ts from 'typescript';
const source=ts.transpileModule(readFileSync('resources/js/lib/adminCc.ts','utf8'),{compilerOptions:{module:ts.ModuleKind.ESNext}}).outputText;
const {ccQuery,emptyCcFilters,ccStatus,ccSummary,ccFilterLabels}=await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
test('CC filters preserve zero flags and isolate rule-only display filtering',()=>{
    const filters={...emptyCcFilters(),internal:'0',enable:'0',is_show:'0',uid:'6',name:' Custom ',id:'42'};
    assert.deepEqual(ccQuery('rule',filters,2,10),{page:2,limit:10,internal:'0',enable:'0',is_show:'0',uid:'6',name:'Custom',id:'42'});
    const query=ccQuery('matcher',filters,1,30);assert.ok(!('is_show' in query));assert.equal(query.internal,'0');
    assert.deepEqual(ccQuery('filter',emptyCcFilters(),1,10),{page:1,limit:10});
});
test('disabled and unsynchronized resources never receive a normal badge',()=>{
    assert.equal(ccStatus({enable:'0',state:'done'}).label,'禁用');
    assert.equal(ccStatus({enable:1,state:'failed'}).tone,'danger');
    assert.equal(ccStatus({enable:1,state:'pending'}).label,'待同步');
    assert.equal(ccStatus({enable:1,state:'process'}).label,'同步中');
    assert.equal(ccStatus({enable:1,state:'unknown'}).label,'unknown');
    assert.equal(ccStatus({enable:1,state:'done'}).label,'正常');
});
test('rule summary distinguishes result total from current-page counts and native flags',()=>{
    assert.deepEqual(ccSummary([{internal:1,is_show:'1',enable:1},{internal:true,is_show:0,enable:0},{internal:0,is_show:true,enable:'1'}],11),{all:11,system:2,custom:1,shown:2,disabled:1});
    for(const type of ['easy_click_filter','easy_slide_filter','rotate_filter'])assert.ok(ccFilterLabels[type]);
});
