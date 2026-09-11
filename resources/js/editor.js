import { t, locale } from "./i18n";
import { Schema } from "prosemirror-model";
import { schema as basic } from "prosemirror-schema-basic";
import {
    EditorState,
    Plugin,
    PluginKey,
    TextSelection,
} from "prosemirror-state";
import { EditorView, Decoration, DecorationSet } from "prosemirror-view";
import { history, undo, redo } from "prosemirror-history";
import { keymap } from "prosemirror-keymap";
import { baseKeymap, toggleMark, setBlockType } from "prosemirror-commands";
import { $, action, element } from "./api";

export const schema = new Schema({
    nodes: basic.spec.nodes
        .remove("image")
        .remove("blockquote")
        .remove("code_block"),
    marks: basic.spec.marks.remove("link"),
});
export const fromText = (text) => ({
    type: "doc",
    content: text.split(/\r?\n/).map((p) => ({
        type: "paragraph",
        ...(p ? { content: [{ type: "text", text: p }] } : {}),
    })),
});
const codexKey = new PluginKey("codex");
const pageMarkerKey = new PluginKey("page-markers");

export function createEditor(
    initialDocument,
    onChange,
    onReference,
    onSelection = () => {},
) {
    let entries = [];
    let readOnly = false;
    let markerFrame,
        markerGeneration = 0;
    function references(doc) {
        const aliases = new Map();
        for (const entry of entries)
            for (const name of [entry.name, ...entry.aliases]) {
                const key = name.trim().toLocaleLowerCase();
                if (!key) continue;
                aliases.set(key, [
                    ...new Set([...(aliases.get(key) || []), entry.id]),
                ]);
            }
        const names = [...aliases.keys()].sort((a, b) => b.length - a.length);
        if (!names.length) return DecorationSet.empty;
        const regex = new RegExp(
            `(?<![\\p{L}\\p{N}_])(?:${names.map((n) => n.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")).join("|")})(?![\\p{L}\\p{N}_])`,
            "giu",
        );
        const marks = [];
        doc.descendants((node, pos) => {
            if (!node.isTextblock) return;
            const text = node.textBetween(0, node.content.size, "", "\n");
            for (const match of text.matchAll(regex)) {
                const ids = aliases.get(match[0].toLocaleLowerCase());
                marks.push(
                    Decoration.inline(
                        pos + 1 + match.index,
                        pos + 1 + match.index + match[0].length,
                        {
                            class: `codex-reference ${ids.length > 1 ? "ambiguous" : ""}`,
                            "data-codex": ids.join(","),
                            title:
                                ids.length > 1
                                    ? t("Choose a matching codex entry")
                                    : t("Open codex entry"),
                        },
                    ),
                );
            }
            return false;
        });
        return DecorationSet.create(doc, marks);
    }
    const state = EditorState.create({
        schema,
        doc: schema.nodeFromJSON(initialDocument),
        plugins: [
            history(),
            keymap({
                "Mod-z": undo,
                "Mod-y": redo,
                "Shift-Mod-z": redo,
                "Mod-b": toggleMark(schema.marks.strong),
                "Mod-i": toggleMark(schema.marks.em),
            }),
            keymap(baseKeymap),
            new Plugin({
                key: pageMarkerKey,
                state: {
                    init: () => DecorationSet.empty,
                    apply: (tr, old) =>
                        tr.getMeta(pageMarkerKey) ??
                        old.map(tr.mapping, tr.doc),
                },
                props: {
                    decorations: (state) => pageMarkerKey.getState(state),
                },
            }),
            new Plugin({
                key: codexKey,
                state: {
                    init: (_, state) => references(state.doc),
                    apply: (tr, old) =>
                        tr.docChanged || tr.getMeta(codexKey)
                            ? references(tr.doc)
                            : old,
                },
                props: {
                    decorations: (state) => codexKey.getState(state),
                    handleClick: (view, pos, event) => {
                        const ref = event.target.closest("[data-codex]");
                        if (ref) {
                            onReference(
                                ref.dataset.codex.split(",").map(Number),
                            );
                            return true;
                        }
                        return false;
                    },
                },
            }),
        ],
    });
    const view = new EditorView($("#editor"), {
        state,
        attributes: {
            "aria-label": t("Book manuscript"),
            "data-placeholder": t("Begin here. The page is yours."),
            role: "textbox",
            "aria-multiline": "true",
        },
        dispatchTransaction(tr) {
            if (readOnly && tr.docChanged) return;
            view.updateState(view.state.apply(tr));
            if (tr.docChanged) {
                onChange(view.state.doc.toJSON());
                scheduleMarkers();
                outline();
            }
            updateCounts();
            if (tr.selectionSet || tr.docChanged) {
                const { $from, $to, empty } = view.state.selection;
                if (
                    empty ||
                    $from.depth !== 1 ||
                    $to.depth !== 1 ||
                    !$from.parent.isTextblock ||
                    !$to.parent.isTextblock
                )
                    onSelection(null);
                else {
                    const from = $from.index(0),
                        to = $to.index(0);
                    const parts = [];
                    for (let index = from; index <= to; index++) {
                        const node = view.state.doc.child(index);
                        parts.push(
                            node.textBetween(
                                index === from ? $from.parentOffset : 0,
                                index === to
                                    ? $to.parentOffset
                                    : node.content.size,
                                "",
                                "\n",
                            ),
                        );
                    }
                    onSelection({
                        from_block: from,
                        to_block: to,
                        from_offset: $from.parentOffset,
                        to_offset: $to.parentOffset,
                        text: parts.join("\n"),
                    });
                }
            }
        },
    });
    function scheduleMarkers() {
        cancelAnimationFrame(markerFrame);
        const generation = ++markerGeneration;
        markerFrame = requestAnimationFrame(() => {
            if (view.composing) {
                setTimeout(scheduleMarkers, 100);
                return;
            }
            const scroller = $("#editor-scroll");
            if (!scroller.clientHeight || !scroller.clientWidth) return;
            const scrollTop = scroller.scrollTop;
            view.dispatch(
                view.state.tr.setMeta(pageMarkerKey, DecorationSet.empty),
            );
            markerFrame = requestAnimationFrame(() => {
                if (generation !== markerGeneration) return;
                const top = view.dom.getBoundingClientRect().top;
                const padding = parseFloat(
                    getComputedStyle(view.dom).paddingTop,
                );
                const scrollStyle = getComputedStyle(scroller);
                // Use the original viewport-sized page boundaries, without page navigation.
                const pageHeight = Math.max(
                    240,
                    scroller.clientHeight -
                        parseFloat(scrollStyle.paddingTop) -
                        parseFloat(scrollStyle.paddingBottom),
                );
                const contentHeight = Math.max(
                    80,
                    pageHeight - 2 * padding - 30,
                );
                let page = 1,
                    shift = 0;
                const markers = [];
                view.state.doc.descendants((node, pos) => {
                    if (!node.isTextblock) return;
                    let offset = 0;
                    while (offset <= node.content.size) {
                        const box = view.coordsAtPos(pos + 1 + offset, 1);
                        if (
                            box.bottom - top + shift >
                            padding + contentHeight + (page - 1) * pageHeight
                        ) {
                            const gap = Math.max(
                                40,
                                padding +
                                    page * pageHeight -
                                    (box.top - top + shift),
                            );
                            const position = pos + 1 + offset;
                            markers.push(
                                Decoration.widget(
                                    position,
                                    () => {
                                        const marker = element(
                                            "span",
                                            undefined,
                                            "page-marker",
                                        );
                                        marker.style.height = `${gap}px`;
                                        marker.contentEditable = "false";
                                        marker.setAttribute(
                                            "aria-hidden",
                                            "true",
                                        );
                                        return marker;
                                    },
                                    { side: -1, key: `marker-${position}` },
                                ),
                            );
                            shift += gap;
                            page++;
                        }
                        let low = offset + 1,
                            high = node.content.size + 1;
                        while (low < high) {
                            const mid = Math.floor((low + high) / 2);
                            if (
                                view.coordsAtPos(pos + 1 + mid, 1).top >
                                box.top + 3
                            )
                                high = mid;
                            else low = mid + 1;
                        }
                        offset = low;
                    }
                    return false;
                });
                view.dispatch(
                    view.state.tr.setMeta(
                        pageMarkerKey,
                        DecorationSet.create(view.state.doc, markers),
                    ),
                );
                scroller.scrollTop = scrollTop;
            });
        });
    }
    function updateCounts() {
        const count = (text) => (text.trim().match(/\S+/gu) || []).length;
        const selection = view.state.selection;
        const selected = count(
            view.state.doc.textBetween(selection.from, selection.to, " "),
        );
        $("#word-count").textContent = t(":v0 words:v1", {
            v0: count(
                view.state.doc.textBetween(0, view.state.doc.content.size, " "),
            ).toLocaleString(locale()),
            v1: selected ? t(" · :v0 selected", { v0: selected }) : "",
        });
    }
    function outline() {
        $("#outline").replaceChildren();
        view.state.doc.descendants((node, pos) => {
            if (node.type.name === "heading") {
                const btn = element(
                    "button",
                    node.textContent || t("Untitled heading"),
                );
                btn.onclick = () => {
                    view.dispatch(
                        view.state.tr
                            .setSelection(
                                TextSelection.near(view.state.doc.resolve(pos)),
                            )
                            .scrollIntoView(),
                    );
                    view.focus();
                };
                $("#outline").append(btn);
            }
        });
    }
    const command = (fn) => {
        fn(view.state, view.dispatch, view);
        view.focus();
    };
    action("#undo", () => command(undo));
    action("#redo", () => command(redo));
    action("#bold", () => command(toggleMark(schema.marks.strong)));
    action("#italic", () => command(toggleMark(schema.marks.em)));
    action(
        "#block-style",
        () =>
            command(
                $("#block-style").value === "paragraph"
                    ? setBlockType(schema.nodes.paragraph)
                    : setBlockType(schema.nodes.heading, {
                          level: $("#block-style").value === "chapter" ? 1 : 2,
                      }),
            ),
        "change",
    );
    action("#scene-break", () => {
        view.dispatch(
            view.state.tr
                .replaceSelectionWith(schema.nodes.horizontal_rule.create())
                .scrollIntoView(),
        );
        view.focus();
    });
    new ResizeObserver(scheduleMarkers).observe($("#editor-scroll"));
    document.fonts.ready.then(scheduleMarkers);
    outline();
    updateCounts();
    return {
        view,
        setReadOnly(value) {
            readOnly = value;
            view.setProps({ editable: () => !readOnly });
            view.dom.setAttribute("aria-readonly", String(readOnly));
            document
                .querySelectorAll(
                    ".editor-toolbar button, .editor-toolbar select",
                )
                .forEach((control) => {
                    control.disabled = readOnly;
                });
        },
        cursor() {
            const { $head } = view.state.selection;
            const block = Math.min(
                $head.index(0),
                view.state.doc.childCount - 1,
            );
            return {
                block,
                offset: $head.depth === 1 ? $head.parentOffset : 0,
            };
        },
        updateEntries(next) {
            entries = next;
            view.dispatch(view.state.tr.setMeta(codexKey, true));
        },
        load(doc) {
            onSelection(null);
            view.updateState(
                EditorState.create({
                    schema,
                    doc: schema.nodeFromJSON(doc),
                    plugins: view.state.plugins,
                }),
            );
            scheduleMarkers();
            outline();
            updateCounts();
        },
        text() {
            return view.state.doc.textBetween(
                0,
                view.state.doc.content.size,
                "\n",
            );
        },
    };
}
