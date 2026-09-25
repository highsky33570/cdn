import fs from "node:fs";
import path from "node:path";
import { createRequire } from "node:module";
import { fileURLToPath } from "node:url";

const project = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "..",
);
const applications = ["frontend", "tycdn-backend"];
const dependencyRoot = applications.find((app) =>
  fs.existsSync(
    path.join(project, app, "node_modules/@vue/compiler-sfc/package.json"),
  ),
);
if (!dependencyRoot) {
  throw new Error(
    "Install dependencies in frontend or tycdn-backend before checking styles.",
  );
}
const require = createRequire(
  path.join(project, dependencyRoot, "package.json"),
);
const { parse } = require("@vue/compiler-sfc");
const postcss = require("postcss");
const failures = [];
const counts = {};

for (const [app, source, styles] of [
  ["frontend", "src", "src"],
  ["tycdn-backend", "resources/js", "resources/css"],
]) {
  const sourceRoot = path.join(project, app, source);
  if (!fs.existsSync(sourceRoot)) continue;
  counts[app] = 0;
  for (const name of fs.readdirSync(sourceRoot, { recursive: true })) {
    if (!name.endsWith(".vue")) continue;
    const filename = path.join(sourceRoot, name);
    const relative = path.relative(project, filename);
    const { descriptor, errors } = parse(fs.readFileSync(filename, "utf8"));
    failures.push(...errors.map((error) => `${relative}: ${error}`));
    counts[app]++;
    if (descriptor.styles.length) {
      failures.push(
        `${relative}: move embedded styles into the application's global CSS files.`,
      );
    }
  }
  const styleRoot = path.join(project, app, styles);
  for (const name of fs.readdirSync(styleRoot, { recursive: true })) {
    if (!name.endsWith(".css")) continue;
    const filename = path.join(styleRoot, name);
    postcss.parse(fs.readFileSync(filename, "utf8")).walkRules((rule) => {
      if (/::?(deep|global|slotted)\(|data-v-/.test(rule.selector)) {
        failures.push(
          `${path.relative(project, filename)}: Vue-only selector ${rule.selector}`,
        );
      }
    });
  }
}

if (failures.length) {
  console.error(failures.join("\n"));
  process.exitCode = 1;
} else {
  console.log(
    `Global CSS audit passed: ${Object.entries(counts)
      .map(([app, count]) => `${app}: ${count} Vue components`)
      .join("; ")}. No embedded styles or Vue-only CSS selectors.`,
  );
}
