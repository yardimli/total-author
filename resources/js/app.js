import { t, locale } from "./i18n";
import { setupTypography } from "./typography";
import { api, notify, $, element, action } from "./api";

document.querySelectorAll("[data-copy-payload]").forEach((button) => {
    button.addEventListener("click", async () => {
        const input = document.getElementById(button.dataset.copyPayload);
        try {
            await navigator.clipboard.writeText(input.value);
            button.textContent = t("Copied");
            setTimeout(() => (button.textContent = t("Copy JSON")), 1500);
        } catch {
            input.focus();
            input.select();
            notify(t("JSON selected. Press Ctrl+C or Cmd+C to copy."));
        }
    });
});

document.querySelectorAll("dialog").forEach((dialog) => {
    dialog.setAttribute("closedby", "closerequest");
    dialog.querySelectorAll("[data-close-dialog]").forEach((button) => {
        button.addEventListener("click", () => dialog.close());
    });
});

if (!document.body.dataset.user)
    document.documentElement.dataset.theme =
        localStorage.getItem("writer-theme") || "paper";
const themeButtons = [...document.querySelectorAll("[data-theme-choice]")];
function showTheme(value) {
    document.documentElement.dataset.theme = value;
    themeButtons.forEach((button) =>
        button.setAttribute(
            "aria-pressed",
            String(button.dataset.themeChoice === value),
        ),
    );
    const selected = themeButtons.find(
        (button) => button.dataset.themeChoice === value,
    );
    if (selected) {
        $("#theme-current").replaceChildren(
            selected.querySelector("svg").cloneNode(true),
        );
        $("#theme-current").setAttribute(
            "aria-label",
            t("Appearance: :v0", { v0: t(value) }),
        );
        $("#theme-current").title = t(":v0 mode · Change appearance", {
            v0: t(value[0].toUpperCase() + value.slice(1)),
        });
    }
}
showTheme(document.documentElement.dataset.theme);
let themeSave = Promise.resolve();
themeButtons.forEach((button) =>
    action(`#${button.id}`, async () => {
        const value = button.dataset.themeChoice;
        showTheme(value);
        $("#theme-picker").open = false;
        $("#theme-current").focus();
        localStorage.setItem("writer-theme", value);
        if (document.body.dataset.user) {
            themeSave = themeSave
                .catch(() => {})
                .then(() => api("/account", "PATCH", { theme: value }));
            await themeSave;
        }
    }),
);
document.addEventListener("click", (event) => {
    if (!$("#theme-picker").contains(event.target))
        $("#theme-picker").open = false;
});
document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && $("#theme-picker").open) {
        $("#theme-picker").open = false;
        $("#theme-current").focus();
    }
});
if ($("#workspace"))
    import("./workspace")
        .then((module) => module.start())
        .catch((e) => notify(e.message));
else if (document.body.dataset.user) api("/api/models").catch(() => {});
if ($("#library-import-dialog"))
    import("./library")
        .then((module) => module.start())
        .catch((e) => notify(e.message));

if (document.querySelector("#workspace")) setupTypography();
