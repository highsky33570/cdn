import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { baseParse } from '@vue/compiler-dom';
import { parse } from '@vue/compiler-sfc';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const roots = [
    path.join(root, 'resources/js'),
    path.join(root, '../frontend/src'),
];
const failures = [];
let inspected = 0;

for (const directory of roots) {
    if (!fs.existsSync(directory)) {
        continue;
    }

    for (const relative of fs.readdirSync(directory, { recursive: true })) {
        if (!relative.endsWith('.vue')) {
            continue;
        }

        const normalized = relative.replaceAll('\\', '/');

        // Primitives own native text entry; hidden fields are form plumbing.
        if (normalized.startsWith('components/ui/')) {
            continue;
        }

        const filename = path.join(directory, relative);
        const { descriptor, errors } = parse(fs.readFileSync(filename, 'utf8'));

        if (errors.length) {
            failures.push(`${filename}: ${errors.join(', ')}`);
        }

        if (!descriptor.template) {
            continue;
        }

        inspected++;
        const ast = baseParse(descriptor.template.content);
        function walk(node) {
            if (node.type === 1) {
                const attribute = (name) =>
                    node.props.find(
                        (prop) => prop.type === 6 && prop.name === name,
                    )?.value?.content;
                const boundType = node.props.find(
                    (prop) =>
                        prop.type === 7 &&
                        prop.name === 'bind' &&
                        prop.arg?.content === 'type',
                )?.exp?.content;
                const raw =
                    ['select', 'option', 'textarea'].includes(node.tag) ||
                    (node.tag === 'input' && attribute('type') !== 'hidden') ||
                    (node.tag === 'button' &&
                        (directory === roots[0] ||
                            ['switch', 'radio', 'checkbox'].includes(
                                attribute('role'),
                            )));
                const nativeWidget =
                    node.tag === 'Input' &&
                    /date|time|checkbox|radio/.test(
                        attribute('type') ?? boundType ?? '',
                    );

                if (raw || nativeWidget) {
                    failures.push(
                        `${path.relative(root, filename)}:${descriptor.template.loc.start.line + node.loc.start.line - 1}: use a shared UI component for <${node.tag}>`,
                    );
                }
            }

            node.children?.forEach(walk);
        }
        walk(ast);
    }
}

if (failures.length) {
    console.error(failures.join('\n'));
    process.exitCode = 1;
} else {
    console.log(
        `Form control audit passed: ${inspected} Vue views/components; no page-level native form widgets.`,
    );
}
