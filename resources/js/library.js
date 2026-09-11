import { api, $, action, notify } from "./api";

export function start() {
    document.querySelectorAll("[data-archive-book]").forEach((button) => {
        button.onclick = async () => {
            button.disabled = true;
            try {
                await api(`/api/books/${button.dataset.archiveBook}`, "PATCH", {
                    revision: Number(button.dataset.revision),
                    archived: button.dataset.archived !== "1",
                });
                window.location.reload();
            } catch (e) {
                notify(e.message);
                button.disabled = false;
            }
        };
    });
    document.querySelectorAll("[data-delete-book]").forEach((form) => {
        form.addEventListener("submit", (event) => {
            if (
                !confirm(
                    "Move this book to recently deleted? You can recover it from your library.",
                )
            )
                event.preventDefault();
        });
    });
    let bookId, revision;
    let readVersion = 0;
    const dialog = $("#library-import-dialog");
    document.querySelectorAll("[data-book-files]").forEach((card) => {
        card.querySelector("[data-import-book]").onclick = async () => {
            try {
                const state = await api(`/api/books/${card.dataset.bookFiles}`);
                bookId = card.dataset.bookFiles;
                revision = state.book.revision;
                readVersion++;
                $("#library-import-file").value = "";
                $("#library-import-preview").value = "";
                $("#library-confirm-import").disabled = true;
                dialog.showModal();
            } catch (e) {
                notify(e.message);
            }
        };
        card.querySelector("[data-export-book]").onclick = () => {
            const link = document.createElement("a");
            link.href = `/books/${card.dataset.bookFiles}/export/${card.querySelector("select").value}`;
            link.download = "";
            link.click();
        };
    });
    action("#library-cancel-import", () => dialog.close());
    action(
        "#library-import-file",
        async () => {
            const version = ++readVersion;
            const file = $("#library-import-file").files[0];
            $("#library-confirm-import").disabled = true;
            if (!file) return;
            if (file.size > 10 * 1024 * 1024)
                throw new Error("Please import a file smaller than 10 MB.");
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
            else throw new Error("Choose a TXT or DOCX story.");
            if (version !== readVersion) return;
            $("#library-import-preview").value = text;
            $("#library-confirm-import").disabled = false;
        },
        "change",
    );
    action("#library-confirm-import", async () => {
        $("#library-confirm-import").disabled = true;
        try {
            const document = {
                type: "doc",
                content: $("#library-import-preview")
                    .value.split(/\r?\n/)
                    .map((text) => ({
                        type: "paragraph",
                        ...(text ? { content: [{ type: "text", text }] } : {}),
                    })),
            };
            await api(`/api/books/${bookId}`, "PATCH", { revision, document });
            dialog.close();
            notify(
                "Story imported. Open the manuscript to continue writing or scan its codex.",
            );
        } finally {
            $("#library-confirm-import").disabled = false;
        }
    });
}
