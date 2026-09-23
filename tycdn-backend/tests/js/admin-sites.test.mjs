import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import ts from 'typescript';
const compile = (file) => ts.transpileModule(readFileSync(file, 'utf8'), {compilerOptions:{module:ts.ModuleKind.ESNext,target:ts.ScriptTarget.ES2022}}).outputText;
const url = (text) => `data:text/javascript;base64,${Buffer.from(text).toString('base64')}`;
const source = compile('resources/js/lib/adminSiteWorkspace.ts').replace("'./apiRequest'", JSON.stringify(url('export function apiRequest(){throw new Error("Unexpected API request")}'))).replace("'./cdnflyResponse'", JSON.stringify(url(compile('resources/js/lib/cdnflyResponse.ts'))));
const {siteCname,siteOrigins,sitePorts,siteStatus,csvCell}=await import(url(source));

test('site and purchased-package CNAME modes use the matching native fields',()=>{
 const row={cname_mode:'site',cname_hostname:'site-a',cname_domain:'cdn.example',up_cname_hostname:'pkg-a',up_cname_domain:'package.example'};
 assert.equal(siteCname(row),'site-a.cdn.example');
 assert.equal(siteCname({...row,cname_mode:'user_package'}),'pkg-a.package.example');
 assert.equal(siteCname({cname:'direct.example'}),'direct.example');
 assert.equal(siteCname({}),'');
});
test('origins and listeners decode native JSON without inventing ports',()=>{
 assert.equal(siteOrigins({backend:'[{"addr":"192.0.2.1"},{"addr":"[2001:db8::1]"}]'}),'192.0.2.1, [2001:db8::1]');
 assert.deepEqual(sitePorts({http_listen:'{"port":"80 8080"}',https_listen:{port:'443 8443'}}),['80','8080','443s','8443s']);
 assert.deepEqual(sitePorts({http_listen:'bad json',https_listen:'{}'}),[]);
});
test('disabled, failed and abnormal sites are not labelled healthy',()=>{
 assert.equal(siteStatus({enable:0,sync_state:'done'}).tone,'muted');
 assert.equal(siteStatus({enable:1,sync_state:'failed'}).tone,'danger');
 assert.equal(siteStatus({enable:1,sync_state:'process'}).tone,'warning');
 assert.equal(siteStatus({enable:1,site_state:403}).tone,'danger');
 assert.equal(siteStatus({enable:1,site_state:'200'}).tone,'success');
});
test('CSV cells quote separators and neutralize formula-leading content',()=>{
 assert.equal(csvCell('a,"b"'), '"a,""b"""');
 assert.equal(csvCell('=HYPERLINK("https://example.com")'), '"\'=HYPERLINK(""https://example.com"")"');
});
