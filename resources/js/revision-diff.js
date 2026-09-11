import { t, locale } from "./i18n";
import { structuredPatch } from "diff";

// Markdown makes prose, headings, and inline formatting readable in a line diff.
export function manuscript(doc) {
    function inline(node) {
        if (node.type === "hard_break") return "\n";
        let text =
            node.text?.replace(/([\\*_`])/g, "\\$1") ??
            (node.content || []).map(inline).join("");
        for (const mark of [...(node.marks || [])].sort((a, b) =>
            a.type.localeCompare(b.type),
        )) {
            const wrap = { strong: "**", em: "_", code: "`" }[mark.type];
            if (wrap) text = wrap + text + wrap;
        }
        return text;
    }
    return (doc?.content || [])
        .map((node) =>
            node.type === "horizontal_rule"
                ? "---"
                : (node.type === "heading"
                      ? "#".repeat(node.attrs?.level || 1) + " "
                      : "") + inline(node),
        )
        .join("\n");
}

function canonical(value) {
    if (Array.isArray(value)) return value.map(canonical);
    if (value && typeof value === "object")
        return Object.fromEntries(
            Object.keys(value)
                .sort()
                .map((key) => [key, canonical(value[key])]),
        );
    return value;
}

export function revisionDiff(before, after) {
    const sections = [];
    const compare = (name, oldText, newText) => {
        const patch = structuredPatch(
            name,
            name,
            oldText,
            newText,
            t("Revision"),
            t("Current"),
            { context: 3, timeout: 1500 },
        );
        if (!patch)
            throw new Error(
                t(
                    "This revision is too large to compare interactively. No changes were made.",
                ),
            );
        const lines = patch.hunks.flatMap((hunk) => hunk.lines);
        const added = lines.filter((line) => line.startsWith("+")).length;
        const removed = lines.filter((line) => line.startsWith("-")).length;
        sections.push({ name, hunks: patch.hunks, added, removed });
    };
    compare(
        t("Manuscript"),
        manuscript(before.document),
        manuscript(after.document),
    );
    const settings = (value) =>
        JSON.stringify(
            canonical({
                title: value.title,
                metadata: value.metadata || {},
                codex_types: value.codex_types || [],
            }),
            null,
            2,
        );
    compare(t("Book details & codex types"), settings(before), settings(after));
    const entries = (value) =>
        JSON.stringify(
            (value.entries || [])
                .map(({ id, name, type, aliases, content }) => ({
                    id,
                    name,
                    type,
                    aliases,
                    content,
                }))
                .sort((a, b) => a.id - b.id),
            null,
            2,
        );
    compare(t("Codex entries"), entries(before), entries(after));
    return sections;
}
