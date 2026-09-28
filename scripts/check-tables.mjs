import fs from "node:fs";
import path from "node:path";
import { createRequire } from "node:module";
import { fileURLToPath } from "node:url";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const app = ["tycdn-backend", "frontend"].find((name) =>
  fs.existsSync(
    path.join(root, name, "node_modules/@vue/compiler-sfc/package.json"),
  ),
);
const require = createRequire(path.join(root, app, "package.json"));
const { parse } = require("@vue/compiler-sfc");
const { baseParse } = require("@vue/compiler-dom");
const failures = [];
let tables = 0;
let cells = 0;

for (const folder of ["frontend/src", "tycdn-backend/resources/js"]) {
  const source = path.join(root, folder);
  if (!fs.existsSync(source)) continue;
  for (const name of fs.readdirSync(source, { recursive: true })) {
    if (!name.endsWith(".vue")) continue;
    const filename = path.join(folder, name);
    const { descriptor } = parse(
      fs.readFileSync(path.join(root, filename), "utf8"),
    );
    if (!descriptor.template) continue;
    function walk(node) {
      if (node.type === 1) {
        const as = node.props.find((p) => p.type === 6 && p.name === "as")
          ?.value?.content;
        const tag =
          as ||
          ({
            CalendarGrid: "table",
            CalendarCell: "td",
            CalendarHeadCell: "th",
          }[node.tag] ??
            node.tag);
        if (tag === "table") tables++;
        if (["td", "th"].includes(tag)) {
          cells++;
          const children = node.children.filter(
            (n) => n.type !== 3 && !(n.type === 2 && !n.content.trim()),
          );
          const child = children[0];
          if (
            children.length !== 1 ||
            child?.tag !== "div" ||
            !child.props.some(
              (p) =>
                p.type === 6 &&
                p.name === "data-slot" &&
                p.value?.content === "table-cell-content",
            )
          ) {
            failures.push(
              `${filename}:${node.loc.start.line}: <${tag}> needs the shared table-cell-content div.`,
            );
          }
          for (const prop of node.props) {
            const value =
              prop.type === 6 && prop.name === "class"
                ? prop.value?.content
                : prop.type === 7 && prop.arg?.content === "class"
                  ? prop.exp?.content
                  : "";
            if (
              value &&
              /(?:^|[\s'"`])(?:[\w-]+:)*(?:p[xytrblse]?|(?:min-|max-)?h)-/.test(
                value,
              )
            ) {
              failures.push(
                `${filename}:${node.loc.start.line}: cell sizing belongs in shared/table-layout.css.`,
              );
            }
          }
        }
      }
      node.children?.forEach(walk);
    }
    walk(baseParse(descriptor.template.content));
  }
}
for (const entry of [
  "frontend/src/styles/global.css",
  "tycdn-backend/resources/css/app.css",
]) {
  if (
    !/^@import ['"]\.\.\/\.\.\/\.\.\/shared\/table-layout\.css['"];/.test(
      fs.readFileSync(path.join(root, entry), "utf8"),
    )
  ) {
    failures.push(`${entry}: load the shared table contract first.`);
  }
}
if (failures.length) {
  console.error(failures.join("\n"));
  process.exitCode = 1;
} else {
  console.log(
    `Table audit passed: ${tables} table definitions and ${cells} header/data-cell templates share one layout.`,
  );
}
