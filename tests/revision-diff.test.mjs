import { test } from "node:test";
import assert from "node:assert/strict";
import { revisionDiff } from "../resources/js/revision-diff.js";

const snapshot = (text) => ({
    title: "Book",
    document: {
        type: "doc",
        content: [{ type: "paragraph", content: [{ type: "text", text }] }],
    },
    entries: [],
    metadata: {},
    codex_types: ["People"],
});
test("revision counts reflect additions and removals, including whitespace and formatting", () => {
    const before = snapshot("Old line"),
        after = snapshot("New line");
    const result = revisionDiff(before, after);
    assert.equal(result[0].added, 1);
    assert.equal(result[0].removed, 1);
    assert.equal(
        revisionDiff(before, before).reduce(
            (n, s) => n + s.added + s.removed,
            0,
        ),
        0,
    );
    assert.equal(revisionDiff(before, snapshot("Old line "))[0].added, 1);
    const formatted = snapshot("Old line");
    formatted.document.content[0].content[0].marks = [{ type: "em" }];
    assert.equal(revisionDiff(before, formatted)[0].added, 1);
});
test("codex comparison ignores bookkeeping timestamps but includes alias changes", () => {
    const before = snapshot("Text"),
        after = snapshot("Text");
    before.entries = [
        {
            id: 1,
            name: "Mara",
            type: "People",
            content: "Writer",
            aliases: [],
            updated_at: "yesterday",
        },
    ];
    after.entries = [{ ...before.entries[0], updated_at: "today" }];
    assert.equal(revisionDiff(before, after)[2].added, 0);
    after.entries[0].aliases = ["Captain"];
    assert.ok(revisionDiff(before, after)[2].added > 0);
});
