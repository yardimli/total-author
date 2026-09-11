import { t, locale } from "./i18n";
const dialog = document.querySelector("#screenshot-preview");
dialog.setAttribute("closedby", "closerequest");
for (const button of document.querySelectorAll("[data-preview]")) {
    button.addEventListener("click", () => {
        const image = document.querySelector("#preview-image");
        image.src = button.dataset.preview;
        image.alt = button.querySelector("img")?.alt || button.dataset.alt;
        dialog.showModal();
    });
}
document
    .querySelector("#close-screenshot")
    .addEventListener("click", () => dialog.close());
