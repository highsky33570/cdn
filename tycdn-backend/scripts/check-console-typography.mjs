import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { baseParse } from '@vue/compiler-dom';
import { parse } from '@vue/compiler-sfc';
import postcss from 'postcss';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const roles = new Set([
    'body',
    'page-title',
    'section-title',
    'dialog-title',
    'description',
    'label',
    'helper',
    'metric',
    'code',
]);
const failures = [];
let inspected = 0;

for (const folder of [
    'resources/js/pages/console',
    'resources/js/components/console',
]) {
    for (const name of fs.readdirSync(path.join(root, folder))) {
        if (!name.endsWith('.vue')) {
            continue;
        }

        const filename = `${folder}/${name}`;
        const { descriptor, errors } = parse(
            fs.readFileSync(path.join(root, filename), 'utf8'),
        );
        failures.push(...errors.map((error) => `${filename}: ${error}`));
        inspected++;

        for (const style of descriptor.styles) {
            postcss
                .parse(style.content)
                .walkDecls('font-size', (declaration) => {
                    if (
                        !/^var\(--console-text-[\w-]+\)$/.test(
                            declaration.value,
                        )
                    ) {
                        failures.push(
                            `${filename}: ${declaration.parent.selector} must use a shared typography token`,
                        );
                    }
                });
        }

        if (!descriptor.template) {
            continue;
        }

        function walk(node) {
            if (node.type === 1) {
                const role = node.props.find(
                    (prop) =>
                        prop.type === 6 && prop.name === 'data-typography',
                )?.value?.content;

                if (role && !roles.has(role)) {
                    failures.push(
                        `${filename}: unknown typography role ${role}`,
                    );
                }

                if (/^h[1-6]$/.test(node.tag) && !role) {
                    failures.push(
                        `${filename}: <${node.tag}> must declare its typography role`,
                    );
                }
            }

            node.children?.forEach(walk);
        }
        walk(baseParse(descriptor.template.content));
    }
}

if (failures.length) {
    console.error(failures.join('\n'));
    process.exitCode = 1;
} else {
    console.log(
        `Typography audit passed: ${inspected} console files use shared heading roles and font-size tokens.`,
    );
}
