import { thinkingProgress } from "./thinking-progress";
import { t, locale } from "./i18n";
import { showWritingWelcome } from "./writing-welcome";
import { api, notify, $, element, action } from "./api";
import { createEditor, fromText } from "./editor";
import { diffWordsWithSpace } from "diff";
import { money } from "./money";
import { revisionDiff, manuscript } from "./revision-diff";

export async function start() {
    for (const target of ["writing", "chat"]) {
        action(`#show-${target}`, () => {
            $("#workspace").classList.toggle("show-chat", target === "chat");
            $("#show-writing").setAttribute(
                "aria-pressed",
                String(target === "writing"),
            );
            $("#show-chat").setAttribute(
                "aria-pressed",
                String(target === "chat"),
            );
        });
    }
    const root = $("#workspace"),
        base = `/api/books/${root.dataset.book}`,
        draftKey = `writer-draft-${document.body.dataset.user}-${root.dataset.book}`;
    let state = await api(base),
        editor,
        dirty = false,
        changeVersion = 0,
        timer,
        writeChain = Promise.resolve(),
        conflicted = false,
        recoveryDocument = null,
        detailsDirty = false;
    let selectedEdit = null;
    let codexDirty = false,
        codexVersion = 0,
        codexTimer,
        codexSaving;
    const codexDraftKey = draftKey + "-codex";
    let models = [],
        model = root.dataset.model,
        favorites = JSON.parse(root.dataset.favorites || "[]"),
        mentions = [],
        activeProposal = null,
        busy = false;
    const welcome = showWritingWelcome(model);
    const enqueue = (fn) => {
        const operation = writeChain.then(fn);
        writeChain = operation.catch(() => {});
        return operation;
    };
    const status = (text) => ($("#save-status").textContent = text);
    function localDraft(doc) {
        try {
            localStorage.setItem(
                draftKey,
                JSON.stringify({
                    document: doc,
                    revision: state.book.revision,
                    savedAt: Date.now(),
                }),
            );
        } catch {
            notify(
                t(
                    "Browser draft storage is full. Keep this tab open until the server save completes.",
                ),
            );
        }
    }
    function changed(doc) {
        dirty = true;
        changeVersion++;
        localDraft(doc);
        status(t("Unsaved changes"));
        clearTimeout(timer);
        timer = setTimeout(() => flush().catch((e) => notify(e.message)), 900);
    }
    async function flush() {
        return enqueue(async () => {
            if (!dirty) return;
            if (conflicted)
                throw new Error(
                    t(
                        "Resolve the save conflict before continuing. Your draft is stored in this browser.",
                    ),
                );
            const version = changeVersion;
            status(t("Saving…"));
            try {
                const result = await api(base, "PATCH", {
                    document: editor.view.state.doc.toJSON(),
                    revision: state.book.revision,
                });
                state.book.revision = result.revision;
                if (version === changeVersion) {
                    dirty = false;
                    localStorage.removeItem(draftKey);
                    status(t("All changes saved"));
                } else {
                    localDraft(editor.view.state.doc.toJSON());
                    status(t("Saving new changes…"));
                }
            } catch (e) {
                if (e.status === 409) conflicted = true;
                status(t("Save failed · local draft kept"));
                throw e;
            }
        });
    }
    async function mutate(url, method, body) {
        await flush();
        return enqueue(async () => {
            const result = await api(url, method, {
                ...body,
                revision: state.book.revision,
            });
            if (result.revision) state.book.revision = result.revision;
            return result;
        });
    }
    async function refresh(loadDocument = false) {
        return enqueue(async () => {
            const fresh = await api(base);
            if (!loadDocument && fresh.book.revision !== state.book.revision) {
                if (dirty || codexDirty) {
                    conflicted = true;
                    fresh.book = { ...state.book };
                    status(t("Conflict · local draft kept"));
                    notify(
                        t(
                            "The book changed elsewhere while you were editing. Reload and review your recovered draft.",
                        ),
                    );
                } else editor.load(fresh.book.document);
            }
            state = fresh;
            if (loadDocument) {
                editor.load(state.book.document);
                dirty = false;
                localStorage.removeItem(draftKey);
            }
            render();
        });
    }
    const codexType = (value) =>
        [
            "People",
            "Places",
            "Items",
            "Organizations",
            "Events",
            "Lore",
        ].includes(value)
            ? t(value)
            : value;
    function options(select, values, empty) {
        const old = select.value;
        select.replaceChildren();
        if (empty !== undefined) select.add(new Option(empty, ""));
        values.forEach((v) =>
            select.add(new Option(v.name ?? codexType(v), v.code ?? v)),
        );
        if ([...select.options].some((o) => o.value === old))
            select.value = old;
    }
    function panel(name) {
        root.classList.add("panel-open");
        $("#side-panel").inert = false;
        $("#side-panel").setAttribute("aria-hidden", "false");
        $("#panel-title").textContent = {
            codex: t("Your codex"),
            names: t("Names & places"),
            details: t("Book details"),
            history: t("Revisions & chapters"),
        }[name];
        document
            .querySelectorAll("[data-content]")
            .forEach((el) => (el.hidden = el.dataset.content !== name));
    }
    function closePanel() {
        root.classList.remove("panel-open");
        $("#side-panel").inert = true;
        $("#side-panel").setAttribute("aria-hidden", "true");
    }
    function openEntry(entry) {
        panel("codex");
        if (codexDirty) {
            notify(
                t(
                    "Finish saving the current codex draft before opening another entry.",
                ),
            );
            return;
        }
        const form = $("#entry-form");
        form.hidden = false;
        $("#codex-browser").hidden = true;
        $("#panel-title").textContent = entry?.id
            ? t("Edit entry")
            : t("New entry");
        form.reset();
        form.elements.id.value = entry?.id || "";
        form.elements.name.value = entry?.name || "";
        form.elements.type.value = entry?.type || state.book.codex_types[0];
        form.elements.aliases.value = (entry?.aliases || []).join(", ");
        form.elements.content.value = entry?.content || "";
        $("#delete-entry").hidden = !Number.isSafeInteger(Number(entry?.id)) || Number(entry?.id) <= 0;
        form.scrollIntoView({ block: "nearest" });
    }
    editor = createEditor(
        state.book.document,
        changed,
        (ids) => {
            if (ids.length === 1)
                openEntry(state.entries.find((e) => e.id === ids[0]));
            else {
                panel("codex");
                showCodexList();
                renderEntries(ids);
                notify(
                    t(
                        "This alias matches several entries. Choose the intended entry below.",
                    ),
                );
            }
        },
        (scope) => {
            selectedEdit = scope;
            $("#selection-scope").hidden = !scope;
            $("#selection-preview").textContent = scope
                ? t("Editing selected text only: :v0:v1", {
                      v0: scope.text.slice(0, 120),
                      v1: scope.text.length > 120 ? "…" : "",
                  })
                : "";
        },
    );
    action("#clear-selection-scope", () => {
        selectedEdit = null;
        $("#selection-scope").hidden = true;
    });

    function showCodexList() {
        $("#entry-form").hidden = true;
        $("#codex-browser").hidden = false;
        $("#panel-title").textContent = t("Your codex");
    }
    const collapsedCodexTypes = new Set();
    function renderEntries(ids) {
        const list = $("#codex-list");
        const scrollTop = list.scrollTop;
        list.replaceChildren();
        const entries = state.entries.filter(
            (entry) => !ids || ids.includes(entry.id),
        );
        for (const type of [
            ...new Set([
                ...state.book.codex_types,
                ...entries.map((entry) => entry.type),
            ]),
        ]) {
            const group = entries.filter((entry) => entry.type === type);
            if (!group.length) continue;
            const section = element("details", undefined, "codex-group");
            section.open = !collapsedCodexTypes.has(type);
            section.append(
                element("summary", `${codexType(type)} · ${group.length}`),
            );
            section.addEventListener("toggle", () => {
                if (!section.isConnected) return;
                if (section.open) collapsedCodexTypes.delete(type);
                else collapsedCodexTypes.add(type);
            });
            for (const entry of group) {
                const card = element("button", entry.name, "entry-card");
                if (entry.aliases.length)
                    card.append(element("small", entry.aliases.join(", ")));
                card.onclick = () => openEntry(entry);
                section.append(card);
            }
            list.append(section);
        }
        if (!entries.length)
            list.append(
                element(
                    "p",
                    t("No entries yet. Give your world a little detail."),
                    "muted",
                ),
            );
        list.scrollTop = scrollTop;
    }
    function render() {
        $("#book-heading").textContent = state.book.title;
        document.title =
            state.book.title + " · " + document.body.dataset.appName;
        editor.updateEntries(state.entries);
        options($("#entry-form").elements.type, state.book.codex_types);
        renderEntries();
        const details = $("#details-form");
        if (!detailsDirty) {
            details.elements.title.value = state.book.title;
            for (const key of [
                "synopsis",
                "genre",
                "point_of_view",
                "tense",
                "style_notes",
            ])
                details.elements[key].value = state.book.metadata?.[key] || "";
        }
        $("#chat-messages").replaceChildren();
        if (!state.messages.length && !state.proposals.length) {
            const empty = element("div", undefined, "chat-empty");
            empty.append(
                element("span", "✧"),
                element("p", t("Every good story starts with a conversation.")),
                element(
                    "small",
                    t(
                        "Ask about a character, explore a scene, or bring your codex to life.",
                    ),
                ),
            );
            $("#chat-messages").append(empty);
        }
        const proposalButton = (proposal) => {
            const button = element(
                "button",
                t(":v0 :v1 changes · :v2 ↗", {
                    v0:
                        t(proposal.status) === "pending"
                            ? t("Review")
                            : t("View"),
                    v1: proposal.changes.length,
                    v2: t(proposal.status),
                }),
                "chat-diff-link",
            );
            button.onclick = () => review(proposal);
            return button;
        };
        for (const message of state.messages) {
            const item = element(
                "article",
                undefined,
                `message ${message.role}`,
            );
            item.append(
                element(
                    "header",
                    message.role === "user" ? t("YOU") : t("WRITING COMPANION"),
                ),
                element("div", message.content),
            );
            // AI suggestions are display-only; never turn model output into UI actions.
            if (message.role === "user" && message.status === "failed") {
                const edit = element("button", "✎", "edit-failed-message");
                edit.type = "button";
                edit.title = t("Edit and resend failed message");
                edit.setAttribute(
                    "aria-label",
                    t("Edit and resend failed message"),
                );
                edit.onclick = async () => {
                    if (busy) return;
                    const input = $("#chat-input");
                    if (
                        input.value.trim() &&
                        input.value !== message.content &&
                        !confirm(
                            t(
                                "Replace the current chat draft with this failed message?",
                            ),
                        )
                    )
                        return;
                    busy = true;
                    input.readOnly = true;
                    $("#send-chat").disabled = true;
                    edit.disabled = true;
                    try {
                        await api(`${base}/messages/${message.id}`, "DELETE");
                        input.value = message.content;
                        state.messages = state.messages.filter(
                            (row) => row.id !== message.id,
                        );
                        item.remove();
                        input.dispatchEvent(
                            new Event("input", { bubbles: true }),
                        );
                    } catch (error) {
                        notify(error.message);
                    } finally {
                        busy = false;
                        input.readOnly = false;
                        $("#send-chat").disabled = false;
                        edit.disabled = false;
                        input.focus();
                        input.setSelectionRange(
                            input.value.length,
                            input.value.length,
                        );
                    }
                };
                item.querySelector("header").append(edit);
            }
            const remove = element(
                "button",
                t("Delete"),
                "delete-chat-message",
            );
            remove.setAttribute(
                "aria-label",
                t("Delete :v0 message", { v0: t(message.role) }),
            );
            remove.onclick = async () => {
                remove.disabled = true;
                try {
                    await api(`${base}/messages/${message.id}`, "DELETE");
                    await refresh();
                } catch (e) {
                    notify(e.message);
                    remove.disabled = false;
                }
            };
            item.dataset.created = message.created_at;
            item.querySelector("header").append(remove);
            for (const proposal of state.proposals.filter(
                (proposal) => proposal.chat_message_id === message.id,
            ))
                item.append(proposalButton(proposal));
            $("#chat-messages").append(item);
        }
        $("#proposal-list").replaceChildren();
        for (const proposal of state.proposals.filter(
            (proposal) =>
                proposal.status !== "rejected" &&
                !state.messages.some(
                    (message) => message.id === proposal.chat_message_id,
                ),
        )) {
            const item = element("article", undefined, "message assistant");
            item.dataset.created = proposal.created_at;
            item.append(
                element("header", t("AI CHANGES · ORIGINAL REPLY UNAVAILABLE")),
                proposalButton(proposal),
            );
            const next = [...$("#chat-messages").children].find(
                (child) =>
                    new Date(child.dataset.created) >
                    new Date(proposal.created_at),
            );
            $("#chat-messages").insertBefore(item, next || null);
        }
        $("#chat-messages").scrollTop = $("#chat-messages").scrollHeight;
        $("#revision-list").replaceChildren();
        for (const revision of state.revisions) {
            const button = element(
                "button",
                `${t(revision.label)} · ${new Date(revision.created_at).toLocaleString(locale())}`,
            );
            button.onclick = () =>
                inspectRevision(revision).catch((e) => notify(e.message));
            $("#revision-list").append(button);
        }
        $("#usage").textContent = t(
            "Book :v0 · Account :v1 · Demo left :v2:v3",
            {
                v0: money(state.usage.book),
                v1: money(state.usage.account),
                v2: money(state.usage.demo_remaining),
                v3: Number(state.usage.pending)
                    ? t(" · Pending ") + money(state.usage.pending)
                    : "",
            },
        );
    }
    let inspectedRevision = null;
    async function inspectRevision(revision) {
        if (codexDirty) {
            await saveEntry();
            if (codexDirty)
                throw new Error(
                    t("Finish the codex entry before comparing revisions."),
                );
        }
        await flush();
        await refresh();
        const saved = await api(`${base}/revisions/${revision.id}`);
        const sections = revisionDiff(saved.snapshot, {
            ...state.book,
            document: editor.view.state.doc.toJSON(),
            entries: state.entries,
        });
        inspectedRevision = revision;
        const totalAdded = sections.reduce((sum, part) => sum + part.added, 0);
        const totalRemoved = sections.reduce(
            (sum, part) => sum + part.removed,
            0,
        );
        $("#revision-diff-title").textContent = t(revision.label);
        $("#revision-diff-date").textContent = t(":v0 → current version :v1", {
            v0: new Date(revision.created_at).toLocaleString(locale()),
            v1: state.book.revision,
        });
        $("#revision-diff-count").textContent = t(
            ":v0 total changed lines · +:v1 added / −:v2 removed",
            { v0: totalAdded + totalRemoved, v1: totalAdded, v2: totalRemoved },
        );
        const body = $("#revision-diff-content");
        body.replaceChildren();
        for (const section of sections) {
            const group = element("section", undefined, "revision-diff-file");
            group.append(
                element(
                    "h3",
                    t(":v0 · :v1 changed lines (+:v2 / −:v3)", {
                        v0: section.name,
                        v1: section.added + section.removed,
                        v2: section.added,
                        v3: section.removed,
                    }),
                ),
            );
            if (!section.hunks.length)
                group.append(element("p", t("No changes."), "muted"));
            for (const hunk of section.hunks) {
                group.append(
                    element(
                        "div",
                        `@@ −${hunk.oldStart},${hunk.oldLines} +${hunk.newStart},${hunk.newLines} @@`,
                        "diff-hunk",
                    ),
                );
                let oldLine = hunk.oldStart,
                    newLine = hunk.newStart;
                for (const line of hunk.lines) {
                    if (line.startsWith("\\")) continue;
                    const add = line.startsWith("+"),
                        remove = line.startsWith("-");
                    const row = element(
                        "div",
                        undefined,
                        `revision-diff-line ${add ? "added" : remove ? "removed" : "context"}`,
                    );
                    row.append(
                        element(
                            "span",
                            add ? "" : String(oldLine++),
                            "diff-number",
                        ),
                        element(
                            "span",
                            remove ? "" : String(newLine++),
                            "diff-number",
                        ),
                        element("span", line[0], "diff-sign"),
                        element("code", line.slice(1)),
                    );
                    group.append(row);
                }
            }
            body.append(group);
        }
        $("#revision-diff-dialog").showModal();
    }
    action("#restore-inspected-revision", async () => {
        if (
            !inspectedRevision ||
            !confirm(
                t(
                    "Restore this manuscript and codex snapshot? The current version will be saved first.",
                ),
            )
        )
            return;
        editor.view.setProps({ editable: () => false });
        try {
            await mutate(
                `${base}/revisions/${inspectedRevision.id}/restore`,
                "POST",
                {},
            );
            await refresh(true);
            $("#revision-diff-dialog").close();
            notify(t("Revision restored."));
        } finally {
            editor.view.setProps({ editable: () => true });
        }
    });
    render();
    status(t("All changes saved"));
    const recovered = JSON.parse(localStorage.getItem(draftKey) || "null");
    if (
        recovered &&
        JSON.stringify(recovered.document) !==
            JSON.stringify(state.book.document)
    )
        $("#recovery").hidden = false;
    action("#recover-draft", () => {
        recoveryDocument = recovered.document;
        $("#import-preview").value = schemaText(recovered.document);
        $("#import-dialog").showModal();
        $("#confirm-import").disabled = false;
        $("#recovery").hidden = true;
    });
    action("#discard-draft", () => {
        localStorage.removeItem(draftKey);
        $("#recovery").hidden = true;
    });
    document
        .querySelectorAll("[data-panel]")
        .forEach(
            (button) => (button.onclick = () => panel(button.dataset.panel)),
        );
    action("#close-panel", closePanel);
    document.addEventListener("click", (event) => {
        if (!$("#model-picker").contains(event.target))
            $("#model-picker").open = false;
    });
    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") $("#model-picker").open = false;
    });
    action("#new-entry", () => openEntry());
    action("#back-to-codex", async () => {
        if (codexDirty) await saveEntry(true);
        else showCodexList();
    });
    action("#open-names", () => {
        const full = $("#entry-form").elements.name.value.trim();
        const split = full.indexOf(" ");
        $("#selected-first-name").value =
            split < 0 ? full : full.slice(0, split);
        $("#selected-last-name").value = split < 0 ? "" : full.slice(split + 1);
        $("#name-results").replaceChildren();
        $("#names-title").textContent = full
            ? t("Names & places · :v0", { v0: full })
            : t("Names & places · New entry");
        $("#names-dialog").showModal();
    });
    action("#open-custom-type", () => {
        $("#new-type").value = "";
        $("#custom-type-dialog").showModal();
    });
    async function saveEntry(close = false) {
        clearTimeout(codexTimer);
        if (codexSaving) {
            await codexSaving;
            if (codexDirty) return saveEntry(close);
            if (close) showCodexList();
            return;
        }
        const form = $("#entry-form");
        if (!form.reportValidity()) return;
        const version = codexVersion,
            data = Object.fromEntries(new FormData(form));
        codexSaving = (async () => {
            status(t("Saving codex…"));
            const result = await mutate(
                `${base}/entries${data.id ? "/" + data.id : ""}`,
                "POST",
                data,
            );
            form.elements.id.value = result.entry.id;
            $("#delete-entry").hidden = false;
            if (version === codexVersion) {
                codexDirty = false;
                localStorage.removeItem(codexDraftKey);
                if (close) showCodexList();
            } else {
                localStorage.setItem(
                    codexDraftKey,
                    JSON.stringify(Object.fromEntries(new FormData(form))),
                );
                codexTimer = setTimeout(
                    () => saveEntry().catch((e) => notify(e.message)),
                    900,
                );
            }
            await refresh();
            status(
                dirty
                    ? t("Unsaved manuscript changes")
                    : t("All changes saved"),
            );
        })();
        try {
            await codexSaving;
        } finally {
            codexSaving = null;
        }
    }
    action(
        "#entry-form",
        async (e) => {
            e.preventDefault();
            await saveEntry(true);
        },
        "submit",
    );
    action(
        "#entry-form",
        () => {
            codexDirty = true;
            codexVersion++;
            status(t("Codex draft unsaved"));
            localStorage.setItem(
                codexDraftKey,
                JSON.stringify(
                    Object.fromEntries(new FormData($("#entry-form"))),
                ),
            );
            clearTimeout(codexTimer);
            if ($("#entry-form").elements.name.value.trim())
                codexTimer = setTimeout(
                    () =>
                        saveEntry().catch((e) => {
                            status(t("Codex save failed · local draft kept"));
                            notify(e.message);
                        }),
                    1000,
                );
        },
        "input",
    );
    $("#codex-recovery").hidden = !localStorage.getItem(codexDraftKey);
    action("#recover-codex", () => {
        const data = JSON.parse(localStorage.getItem(codexDraftKey));
        if (!data) return;
        openEntry({ ...data, aliases: (data.aliases || "").split(",") });
        codexDirty = true;
        codexVersion++;
        $("#codex-recovery").hidden = true;
        notify(
            t("Draft restored to the form. Review it and choose Save entry."),
        );
    });
    action("#discard-codex", () => {
        localStorage.removeItem(codexDraftKey);
        $("#codex-recovery").hidden = true;
    });
    action("#delete-entry", async () => {
        clearTimeout(codexTimer);
        if (codexSaving) await codexSaving;
        const id = Number($("#entry-form").elements.id.value);
        if (!Number.isSafeInteger(id) || id <= 0) {
            $("#delete-entry").hidden = true;
            return;
        }
        if (
            !confirm(
                t(
                    "Delete this entry? Its manuscript words will remain. You can restore it from revisions.",
                ),
            )
        )
            return;
        await mutate(
            `${base}/entries/${id}`,
            "DELETE",
            {},
        );
        codexDirty = false;
        codexVersion++;
        localStorage.removeItem(codexDraftKey);
        await refresh();
        showCodexList();
    });
    action(
        "#custom-type-form",
        async (event) => {
            event.preventDefault();
            const type = $("#new-type").value.trim();
            if (!type) return;
            await mutate(base, "PATCH", {
                codex_types: [...new Set([...state.book.codex_types, type])],
            });
            await refresh();
            $("#entry-form").elements.type.value = type;
            $("#entry-form").dispatchEvent(
                new Event("input", { bubbles: true }),
            );
            $("#custom-type-dialog").close();
        },
        "submit",
    );
    action(
        "#details-form",
        () => {
            detailsDirty = true;
        },
        "input",
    );
    action(
        "#details-form",
        async (e) => {
            e.preventDefault();
            const values = Object.fromEntries(new FormData(e.target));
            const { title, ...metadata } = values;
            await mutate(base, "PATCH", {
                title,
                metadata,
            });
            detailsDirty = false;
            await refresh();
            notify(t("Book details saved."));
        },
        "submit",
    );
    async function loadModels(force = false) {
        const catalog = await api(
            force ? "/api/models/refresh" : "/api/models",
            force ? "POST" : "GET",
            force ? {} : undefined,
        );
        models = catalog.data.filter((item) => {
            const outputs = item.architecture?.output_modalities || [];
            return (
                !/batch/i.test(`${item.name} ${item.id}`) &&
                outputs.includes("text") &&
                !outputs.includes("image")
            );
        });
        $("#catalog-status").textContent =
            catalog.error ||
            t("Updated :v0", {
                v0: catalog.refreshed_at
                    ? new Date(catalog.refreshed_at).toLocaleString(locale())
                    : t("not yet"),
            });
        renderModels();
    }
    function renderModels() {
        const selected = models.find((m) => m.id === model);
        welcome.setModel(
            selected?.name ||
                (model
                    ? t(":v0 (currently unavailable)", { v0: model })
                    : null),
        );
        $("#model-summary").textContent = selected
            ? t("Writing with :v0", { v0: selected.name })
            : model
              ? t("Saved model unavailable — choose another")
              : t("Choose an AI model");
        $("#model-list").replaceChildren();
        const q = $("#model-search").value.toLowerCase(),
            only = $("#favorites-only").checked;
        const min =
            $("#model-price-min").value === ""
                ? 0
                : Number($("#model-price-min").value);
        const max =
            $("#model-price-max").value === ""
                ? Infinity
                : Number($("#model-price-max").value);
        $("#model-search-filters").hidden = only;
        if (
            !only &&
            (!Number.isFinite(min) || min < 0 || Number.isNaN(max) || max < min)
        ) {
            $("#model-list").append(
                element(
                    "p",
                    t(
                        "Enter a nonnegative price range with Min no greater than Max.",
                    ),
                ),
            );
            return;
        }
        const matches = models.filter((m) =>
            only
                ? favorites.includes(m.id)
                : m.pricing?.completion != null &&
                  Number(m.pricing.completion) * 1e6 >= min &&
                  Number(m.pricing.completion) * 1e6 <= max &&
                  (m.name + " " + m.id).toLowerCase().includes(q),
        );
        if (!matches.length)
            $("#model-list").append(
                element(
                    "p",
                    only
                        ? t(
                              "No favorites yet. Uncheck Favorites only and star a model.",
                          )
                        : t(
                              "No models match this search and output price range.",
                          ),
                ),
            );
        for (const item of matches) {
            const row = element("div", undefined, "model-row"),
                pick = element("button", item.name),
                star = element(
                    "button",
                    favorites.includes(item.id) ? "★" : "☆",
                );
            const bounded = [
                item.pricing?.prompt,
                item.pricing?.completion,
            ].every(
                (price) =>
                    price !== undefined &&
                    Number.isFinite(Number(price)) &&
                    Number(price) >= 0,
            );
            pick.append(
                element(
                    "small",
                    bounded
                        ? t(":v0 in / :v1 out per 1M tokens", {
                              v0: money(Number(item.pricing.prompt) * 1e6),
                              v1: money(Number(item.pricing.completion) * 1e6),
                          })
                        : t(
                              "Variable pricing · cannot safely budget this model",
                          ),
                ),
            );
            pick.disabled =
                !bounded ||
                !item.architecture?.output_modalities?.includes("text");
            pick.onclick = async () => {
                try {
                    await api("/account", "PATCH", { selected_model: item.id });
                    model = item.id;
                    $("#model-picker").open = false;
                    renderModels();
                } catch (e) {
                    notify(e.message);
                }
            };
            star.setAttribute(
                "aria-label",
                t("Favorite :v0", { v0: item.name }),
            );
            star.onclick = async () => {
                try {
                    const next = favorites.includes(item.id)
                        ? favorites.filter((id) => id !== item.id)
                        : [...favorites, item.id];
                    await api("/account", "PATCH", { favorite_models: next });
                    favorites = next;
                    renderModels();
                } catch (e) {
                    notify(e.message);
                }
            };
            row.append(pick, star);
            $("#model-list").append(row);
        }
    }
    action("#model-search", renderModels, "input");
    action(
        "#favorites-only",
        async () => {
            renderModels();
            await api("/account", "PATCH", {
                favorites_only: $("#favorites-only").checked,
            });
        },
        "change",
    );
    action("#model-price-min", renderModels, "input");
    action("#model-price-max", renderModels, "input");
    action("#refresh-models", () => loadModels(true));
    loadModels().catch((e) => notify(e.message));
    async function send(message, extra = {}) {
        if (busy) return;
        if (!model) throw new Error(t("Choose an AI model first."));
        if (codexDirty) await saveEntry();
        await flush();
        const previousProposals = new Set(
            state.proposals.map((proposal) => proposal.id),
        );
        const selection =
            selectedEdit && !extra.action
                ? { ...selectedEdit, revision: state.book.revision }
                : null;
        const cursor = { ...editor.cursor(), revision: state.book.revision };
        busy = true;
        editor.setReadOnly(true);
        $("#chat-input").readOnly = true;
        $("#ai-thinking-status").hidden = false;
        $("#send-chat").disabled = true;
        $("#send-chat").textContent = t("Thinking…");
        $("#send-chat").classList.add("is-thinking");
        $("#ai-thinking-status").classList.add("is-thinking");
        $("#chat-form").setAttribute("aria-busy", "true");
        const requestId = crypto.randomUUID();
        try {
            const payload = {
                message,
                model,
                request_id: requestId,
                history: $("#chat-history").value,
                mentions,
                names: currentCodexNames(),
                selection,
                cursor,
                ...extra,
            };
            const call = async (data) => {
                const stop = thinkingProgress();
                try {
                    // Classification and execution each have a 120-second provider timeout.
                    return await api(`${base}/chat`, "POST", data, 270000);
                } finally {
                    stop();
                }
            };
            const result = await call(payload);
            if (result.large_prompt_warning) {
                const dialog = $("#large-prompt-dialog");
                $("#large-prompt-count").textContent =
                    result.word_count.toLocaleString(locale());
                $("#disable-large-prompt-warning").checked = false;
                dialog.returnValue = "cancel";
                const confirmed = await new Promise((resolve) => {
                    dialog.addEventListener(
                        "close",
                        () => resolve(dialog.returnValue === "send"),
                        { once: true },
                    );
                    dialog.showModal();
                });
                if (!confirmed) return;
                await call({
                    ...payload,
                    force_large_prompt: true,
                    disable_large_prompt_warning: $(
                        "#disable-large-prompt-warning",
                    ).checked,
                });
            }
            $("#chat-input").value = "";
            mentions = [];
            renderMentions();
        } finally {
            // Unlock immediately; a slow state refresh must not keep the inputs frozen.
            busy = false;
            editor.setReadOnly(false);
            $("#chat-input").readOnly = false;
            $("#ai-thinking-status").hidden = true;
            $("#send-chat").disabled = false;
            $("#send-chat").textContent = t("Send ↗");
            $("#send-chat").classList.remove("is-thinking");
            $("#ai-thinking-status").classList.remove("is-thinking");
            $("#chat-form").setAttribute("aria-busy", "false");
            await refresh().catch((error) => notify(error.message));
            const next = state.proposals.find(
                (proposal) =>
                    proposal.status === "pending" &&
                    !previousProposals.has(proposal.id),
            );
            if (next && !busy) review(next);
        }
    }
    action(
        "#chat-form",
        async (e) => {
            e.preventDefault();
            await send($("#chat-input").value);
        },
        "submit",
    );
    function renderMentions() {
        $("#selected-mentions").replaceChildren();
        for (const id of mentions) {
            const entry = state.entries.find((e) => e.id === id);
            if (!entry) continue;
            const chip = element("button", "@" + entry.name + " ×");
            chip.type = "button";
            chip.onclick = () => {
                mentions = mentions.filter((n) => n !== id);
                renderMentions();
            };
            $("#selected-mentions").append(chip);
        }
    }
    action(
        "#chat-input",
        () => {
            const field = $("#chat-input"),
                prefix = field.value.slice(0, field.selectionStart),
                match = prefix.match(/@([^@\n]*)$/);
            $("#mention-list").hidden = !match;
            if (!match) return;
            $("#mention-list").replaceChildren();
            for (const entry of state.entries
                .filter((e) =>
                    [e.name, ...e.aliases].some((n) =>
                        n.toLowerCase().includes(match[1].toLowerCase()),
                    ),
                )
                .slice(0, 15)) {
                const button = element(
                    "button",
                    `${entry.name} · ${entry.type}`,
                );
                button.type = "button";
                button.onclick = () => {
                    mentions = [...new Set([...mentions, entry.id])];
                    field.setRangeText(
                        "@" + entry.name + " ",
                        prefix.length - match[0].length,
                        field.selectionStart,
                        "end",
                    );
                    $("#mention-list").hidden = true;
                    renderMentions();
                    field.focus();
                };
                $("#mention-list").append(button);
            }
        },
        "input",
    );
    function diffText(before, after) {
        const block = element("div", undefined, "diff-columns"),
            left = element("div", undefined, "diff-text"),
            right = element("div", undefined, "diff-text");
        left.append(element("h4", t("Original")));
        right.append(element("h4", t("Proposed")));
        for (const part of diffWordsWithSpace(before, after)) {
            if (!part.added)
                left.append(element(part.removed ? "del" : "span", part.value));
            if (!part.removed)
                right.append(element(part.added ? "ins" : "span", part.value));
        }
        block.append(left, right);
        return block;
    }
    function review(proposal) {
        activeProposal = proposal;
        const historical = proposal.status !== "pending";
        $("#approve-changes").hidden = historical;
        $("#reject-changes").hidden = historical;
        $("#diff-content").replaceChildren();
        $("#review-description").textContent = historical
            ? t(
                  "Previously :v0. Checked changes were applied; unchecked changes were not applied.",
                  { v0: t(proposal.status) },
              )
            : proposal.base_revision === state.book.revision
              ? t(
                    "Review additions, removals and replacements. Only checked changes will be saved.",
                )
              : t(
                    "This proposal is based on an older revision. Reject it and request a new proposal to protect your latest work.",
                );
        $("#approve-changes").disabled =
            proposal.base_revision !== state.book.revision;
        proposal.changes.forEach((change, index) => {
            const section = element("section", undefined, "diff-block"),
                label = element("label", undefined, "check"),
                check = element("input");
            check.type = "checkbox";
            check.checked = historical
                ? (proposal.decisions || []).includes(index)
                : true;
            check.disabled = historical;
            check.value = index;
            label.append(
                check,
                document.createTextNode(
                    change.operation === "selection_replace"
                        ? t("Selected text only")
                        : change.operation === "manuscript_replace"
                          ? t("Manuscript · paragraphs :v0–:v1", {
                                v0: change.start_block + 1,
                                v1: change.end_block,
                            })
                          : `${change.name} · ${codexType(change.type)}`,
                ),
            );
            section.append(label);
            if (
                ["manuscript_replace", "selection_replace"].includes(
                    change.operation,
                )
            )
                section.append(diffText(
                    change.before_document ? manuscript(change.before_document) : change.before,
                    change.replacement_document ? manuscript(change.replacement_document) : change.content,
                ));
            else
                for (const field of ["name", "type", "content", "aliases"]) {
                    section.append(
                        element(
                            "small",
                            t(
                                {
                                    name: "Name",
                                    type: "Type",
                                    content: "Description",
                                    aliases: "Aliases",
                                }[field],
                            ),
                        ),
                        diffText(
                            Array.isArray(change.before?.[field])
                                ? change.before[field].join(", ")
                                : change.before?.[field] || "",
                            Array.isArray(change[field])
                                ? change[field].join(", ")
                                : change[field] || "",
                        ),
                    );
                }
            $("#diff-content").append(section);
        });
        $("#review-dialog").showModal();
        $("#diff-content").scrollTop = 0;
    }
    action("#close-review", () => $("#review-dialog").close());
    async function decide(accept) {
        await flush();
        editor.view.setProps({ editable: () => false });
        try {
            await mutate(`${base}/proposals/${activeProposal.id}`, "POST", {
                accept,
            });
            $("#review-dialog").close();
            await refresh(true);
            notify(
                accept.length
                    ? t(
                          "Approved changes saved. The previous version is in Revisions.",
                      )
                    : t("Proposal rejected."),
            );
        } finally {
            editor.view.setProps({ editable: () => true });
        }
    }
    action("#approve-changes", () =>
        decide(
            [...$("#diff-content").querySelectorAll("input:checked")].map(
                (input) => Number(input.value),
            ),
        ),
    );
    action("#reject-changes", () => decide([]));
    function currentCodexNames() {
        const form = $("#entry-form");
        const name = form.elements.name.value.trim();
        return !form.hidden && form.elements.type.value === "People" && name
            ? [name]
            : [];
    }
    action(
        "#apply-codex-name",
        (event) => {
            event.preventDefault();
            const name = [
                $("#selected-first-name").value.trim(),
                $("#selected-last-name").value.trim(),
            ]
                .filter(Boolean)
                .join(" ");
            if (!name) throw new Error(t("Select or enter a name first."));
            if (name.length > 200)
                throw new Error(
                    t("The full name must be 200 characters or fewer."),
                );
            $("#entry-form").elements.name.value = name;
            $("#entry-form").dispatchEvent(
                new Event("input", { bubbles: true }),
            );
            $("#names-dialog").close();
        },
        "submit",
    );
    api("/api/countries")
        .then((countries) => {
            options($("#first-country"), countries);
            options($("#last-country"), countries);
            options($("#place-country"), countries, t("Any country"));
            $("#first-country").value = "US";
            $("#last-country").value = "US";
        })
        .catch((error) => notify(error.message));
    async function names(random) {
        const params = new URLSearchParams({
            country: $("#first-country").value,
            last_country: $("#last-country").value,
            gender: $("#name-gender").value,
            q: $("#name-search").value,
            random: random ? "1" : "0",
        });
        const data = await api("/api/names?" + params);
        $("#name-results").replaceChildren();
        for (const [title, rows] of random
            ? [[t("Full names"), data.results]]
            : [
                  [t("First names"), data.first],
                  [t("Last names"), data.last],
              ]) {
            $("#name-results").append(element("h3", title));
            for (const row of rows) {
                const button = element("button", row.name);
                button.onclick = () => {
                    if (random) {
                        $("#selected-first-name").value = row.first_name;
                        $("#selected-last-name").value = row.last_name;
                    } else
                        $(
                            title === t("First names")
                                ? "#selected-first-name"
                                : "#selected-last-name",
                        ).value = row.name;
                };
                $("#name-results").append(button);
            }
        }
    }
    action("#search-names", () => names(false));
    action("#random-names", () => names(true));
    action("#suggest-places", async () => {
        const prompt = $("#place-prompt").value.trim();
        if (!prompt)
            throw new Error(t("Enter a guiding prompt for the place names."));
        closePanel();
        $("#chat-input").value = t("Suggest 10 place names: :v0", {
            v0: prompt,
        });
        $("#names-dialog").close();
        await send($("#chat-input").value, {
            action: "places",
            country: $("#place-country").selectedOptions[0].textContent,
        });
    });
    action("#scan-document", async () => {
        closePanel();
        $("#chat-input").value = t(
            "Scan this document to create or update codex entries for its people, places and things. Preserve existing names and flag uncertain details.",
        );
        await send($("#chat-input").value, { action: "scan" });
    });
    action("#import-button", () => {
        recoveryDocument = null;
        $("#import-dialog").showModal();
    });
    action("#cancel-import", () => $("#import-dialog").close());
    action(
        "#import-file",
        async () => {
            const file = $("#import-file").files[0];
            recoveryDocument = null;
            if (!file) return;
            if (file.size > 10 * 1024 * 1024)
                throw new Error(t("Please import a file smaller than 10 MB."));
            $("#confirm-import").disabled = true;
            let text;
            if (file.name.toLowerCase().endsWith(".docx")) {
                const mammoth = await import("mammoth/mammoth.browser");
                text = (
                    await mammoth.extractRawText({
                        arrayBuffer: await file.arrayBuffer(),
                    })
                ).value;
            } else if (file.name.toLowerCase().endsWith(".txt"))
                text = await file.text();
            else throw new Error(t("Choose a TXT or DOCX story."));
            $("#import-preview").value = text;
            $("#confirm-import").disabled = false;
        },
        "change",
    );
    action("#confirm-import", async () => {
        await flush();
        editor.view.setProps({ editable: () => false });
        try {
            const document =
                recoveryDocument &&
                $("#import-preview").value === schemaText(recoveryDocument)
                    ? recoveryDocument
                    : fromText($("#import-preview").value);
            await mutate(base, "PATCH", { document });
            conflicted = false;
            await refresh(true);
            $("#import-dialog").close();
            notify(
                t(
                    "Story imported. Open Codex to scan the document for entries.",
                ),
            );
        } finally {
            editor.view.setProps({ editable: () => true });
        }
    });
    action("#export-button", async () => {
        await flush();
        const link = element("a");
        link.href = `/books/${state.book.id}/export/${$("#export-format").value}`;
        link.download = "";
        document.body.append(link);
        link.click();
        link.remove();
    });
    window.addEventListener("beforeunload", (e) => {
        if (dirty || codexDirty) {
            e.preventDefault();
            e.returnValue = "";
        }
    });
    document.addEventListener("visibilitychange", () => {
        if (document.hidden && dirty) flush().catch(() => {});
    });
}

function schemaText(doc) {
    return (doc.content || [])
        .map((node) =>
            (node.content || []).map((child) => child.text || "").join(""),
        )
        .join("\n");
}
