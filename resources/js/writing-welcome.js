import { t, locale } from "./i18n";
export function showWritingWelcome(initialModel) {
    const dialog = document.querySelector("#writing-welcome");
    const chat = document.querySelector("#chat-input");
    const picker = document.querySelector("#model-picker");
    const setModel = (name) => {
        document.querySelector("#welcome-model").textContent = name
            ? t("You’ll be writing with :v0.", { v0: name })
            : t(
                  "Choose an AI model from the model picker before sending your first message.",
              );
    };
    setModel(initialModel);
    let highlightTimer;
    const open = () => {
        clearTimeout(highlightTimer);
        chat.classList.remove("welcome-highlight");
        picker.classList.remove("welcome-highlight");
        if (!dialog.open) dialog.showModal();
    };
    dialog.addEventListener("close", () => {
        if (window.matchMedia("(max-width: 760px)").matches) {
            document.querySelector("#show-chat").click();
        }
        requestAnimationFrame(() => {
            chat.classList.add("welcome-highlight");
            picker.classList.add("welcome-highlight");
            clearTimeout(highlightTimer);
            highlightTimer = setTimeout(() => {
                chat.classList.remove("welcome-highlight");
                picker.classList.remove("welcome-highlight");
            }, 2400);
            chat.focus({ preventScroll: true });
            chat.setSelectionRange(chat.value.length, chat.value.length);
            chat.scrollIntoView({ block: "nearest" });
        });
    });
    dialog.querySelectorAll("[data-welcome-close]").forEach((button) => {
        button.addEventListener("click", () => dialog.close());
    });
    dialog.setAttribute("closedby", "closerequest");
    window.addEventListener("pageshow", (event) => {
        if (event.persisted) open();
    });
    open();
    return { setModel };
}
