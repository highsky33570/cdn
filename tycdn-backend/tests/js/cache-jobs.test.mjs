import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import ts from 'typescript';
const source=ts.transpileModule(readFileSync('resources/js/lib/cacheJobs.ts','utf8'),{compilerOptions:{module:ts.ModuleKind.ESNext}}).outputText;
const {cacheUrls, validCacheUrl, cacheJobPayload, cacheJobQuery, cacheJobStatus, cacheJobSummary}=await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
test('bulk input ignores blank lines and requires complete HTTP URLs',()=>{
    assert.deepEqual(cacheUrls(' https://example.test/a\r\n\r\n https://example.test/b \n'),['https://example.test/a','https://example.test/b']);
    for(const url of ['https://example.test/a?q=a%20b','http://example.test/目录/','https://example.test/static/'])assert.equal(validCacheUrl(url),true,url);
    for(const url of ['example.test/a','javascript:alert(1)','https://','https://example.test/a b'])assert.equal(validCacheUrl(url),false,url);
});
test('resubmission reconstructs only supported jobs from native JSON and rejects bad records',()=>{
    assert.deepEqual(cacheJobPayload({id:1,type:'clean_dir',data:'{"url":"https://example.test/static/","extra":"ignored"}'}),{type:'clean_dir',data:{url:'https://example.test/static/'}});
    assert.deepEqual(cacheJobPayload({id:2,type:'clean_url',data:'broken',key2:'https://example.test/a'}),{type:'clean_url',data:{url:'https://example.test/a'}});
    assert.throws(()=>cacheJobPayload({id:3,type:'shell',key2:'https://example.test/a'}));
    assert.throws(()=>cacheJobPayload({id:4,type:'clean_url',data:'{}'}));
});
test('domain and URL search use separate native keys without double encoding',()=>{
    assert.deepEqual(cacheJobQuery('',' example.test ',1,10),{page:1,limit:10,type:'clean_url,clean_dir,pre_cache_url',key1:'example.test',key2:''});
    assert.deepEqual(cacheJobQuery('clean_url','https://example.test/a?q=a%20b&x=1',2,30),{page:2,limit:30,type:'clean_url',key1:'',key2:'https://example.test/a?q=a%20b&x=1'});
});
test('history summaries include cancelled jobs as failures and do not label unknown states complete',()=>{
    const rows=[{state:'done'},{state:'failed'},{state:'done',enable:0},{state:'pending'},{state:'process',progress:'3 / 5'},{state:'unknown'}];
    assert.deepEqual(cacheJobSummary(rows),{all:6,done:1,failed:2,process:3});
    assert.equal(cacheJobStatus(rows[4]).label,'3 / 5');
});
