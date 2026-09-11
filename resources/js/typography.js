export function setupTypography() {
    const root = document.documentElement;
    const workspace = document.querySelector("#workspace");
    const key = `total-author-typography-${document.body.dataset.user}`;
    const defaults = {
        font: "Georgia",
        size: "18",
        line: "1.8",
        indent: "0",
        spacing: "1",
        width: "760",
        align: "left",
        ui: "100",
        panel: 300,
        chat: 360,
    };
    let saved = {};
    try {
        saved = JSON.parse(localStorage.getItem(key)) || {};
    } catch {}
    let settings = { ...defaults };
    for (const name of Object.keys(defaults)) {
        const control = document.querySelector(`#type-${name}`);
        if (
            control?.tagName === "SELECT" &&
            [...control.options].some((o) => o.value === String(saved[name]))
        )
            settings[name] = String(saved[name]);
    }
    settings.ui = String(Math.min(130, Math.max(80, Number(saved.ui) || 100)));
    settings.panel = Math.min(560, Math.max(180, Number(saved.panel) || 300));
    settings.chat = Math.min(640, Math.max(260, Number(saved.chat) || 360));
    function persist() {
        try {
            localStorage.setItem(key, JSON.stringify(settings));
        } catch {}
    }
    function panelWidth(value) {
        const max = Math.max(
            180,
            Math.min(
                560,
                workspace.clientWidth * (innerWidth <= 760 ? 0.55 : 0.45),
            ),
        );
        const width = Math.min(max, Math.max(180, value));
        workspace.style.setProperty("--sidebar-size", `${width}px`);
        const handle = document.querySelector("#panel-resizer");
        handle.setAttribute("aria-valuenow", String(Math.round(width)));
        handle.setAttribute("aria-valuemax", String(Math.round(max)));
        return width;
    }
    function chatWidth(value) {
        const max = Math.max(260, Math.min(640, workspace.clientWidth * 0.45));
        const width = Math.min(max, Math.max(260, value));
        workspace.style.setProperty("--chat-width", `${width}px`);
        const handle = document.querySelector("#chat-resizer");
        handle.setAttribute("aria-valuenow", String(Math.round(width)));
        handle.setAttribute("aria-valuemax", String(Math.round(max)));
        return width;
    }
    function apply() {
        for (const name of [
            "font",
            "size",
            "line",
            "indent",
            "spacing",
            "width",
            "align",
        ]) {
            const unit = ["size", "width"].includes(name)
                ? "px"
                : ["indent", "spacing"].includes(name)
                  ? "em"
                  : "";
            root.style.setProperty(
                `--type-${name}`,
                name === "font" ? `"${settings[name]}"` : settings[name] + unit,
            );
            document.querySelector(`#type-${name}`).value = settings[name];
        }
        root.style.setProperty("--ui-scale", Number(settings.ui) / 100);
        document.querySelector("#type-ui").value = settings.ui;
        document.querySelector("#type-ui-output").textContent =
            settings.ui + "%";
        document.querySelector("#ui-scale-label").textContent =
            settings.ui + "%";
        panelWidth(settings.panel);
        window.dispatchEvent(new Event("resize"));
    }
    const dialog = document.querySelector("#typography-dialog");
    document
        .querySelector("#open-typography")
        .addEventListener("click", () => dialog.showModal());
    dialog.querySelectorAll("select,input").forEach((control) =>
        control.addEventListener("input", () => {
            settings[control.id.replace("type-", "")] = control.value;
            apply();
            persist();
        }),
    );
    document
        .querySelector("#reset-typography")
        .addEventListener("click", () => {
            settings = { ...defaults };
            apply();
            persist();
        });
    const handle = document.querySelector("#panel-resizer");
    handle.addEventListener("pointerdown", (event) => {
        if (event.button !== 0) return;
        event.preventDefault();
        handle.setPointerCapture(event.pointerId);
        workspace.classList.add("resizing-panel");
    });
    handle.addEventListener("pointermove", (event) => {
        if (!handle.hasPointerCapture(event.pointerId)) return;
        settings.panel = panelWidth(
            event.clientX - workspace.getBoundingClientRect().left,
        );
    });
    const finish = () => {
        workspace.classList.remove("resizing-panel");
        persist();
    };
    handle.addEventListener("lostpointercapture", finish);
    handle.addEventListener("pointerup", (event) => {
        if (handle.hasPointerCapture(event.pointerId))
            handle.releasePointerCapture(event.pointerId);
    });
    handle.addEventListener("keydown", (event) => {
        if (!["ArrowLeft", "ArrowRight", "Home", "End"].includes(event.key))
            return;
        event.preventDefault();
        settings.panel = panelWidth(
            event.key === "Home"
                ? 180
                : event.key === "End"
                  ? 560
                  : settings.panel + (event.key === "ArrowRight" ? 20 : -20),
        );
        persist();
    });
    const chatHandle = document.querySelector("#chat-resizer");
    chatHandle.addEventListener("pointerdown", (event) => {
        if (event.button !== 0) return;
        event.preventDefault();
        chatHandle.setPointerCapture(event.pointerId);
        workspace.classList.add("resizing-panel");
    });
    chatHandle.addEventListener("pointermove", (event) => {
        if (!chatHandle.hasPointerCapture(event.pointerId)) return;
        settings.chat = chatWidth(
            workspace.getBoundingClientRect().right - event.clientX,
        );
    });
    chatHandle.addEventListener("lostpointercapture", finish);
    chatHandle.addEventListener("pointerup", (event) => {
        if (chatHandle.hasPointerCapture(event.pointerId))
            chatHandle.releasePointerCapture(event.pointerId);
    });
    chatHandle.addEventListener("keydown", (event) => {
        if (!["ArrowLeft", "ArrowRight", "Home", "End"].includes(event.key))
            return;
        event.preventDefault();
        settings.chat = chatWidth(
            event.key === "Home"
                ? 260
                : event.key === "End"
                  ? 640
                  : settings.chat + (event.key === "ArrowLeft" ? 20 : -20),
        );
        persist();
    });
    window.addEventListener("resize", () => {
        panelWidth(settings.panel);
        chatWidth(settings.chat);
    });
    apply();
}
