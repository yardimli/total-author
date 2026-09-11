export function t(key, replacements = {}) {
    let text = window.appTranslations?.[key] ?? key;
    return text.replace(/:([a-zA-Z_][a-zA-Z_0-9]*)/g, (match, name) =>
        Object.hasOwn(replacements, name) ? String(replacements[name]) : match,
    );
}
export const locale = () => document.documentElement.lang || "en";
document
    .querySelectorAll(".language-switch select")
    .forEach((select) =>
        select.addEventListener("change", () => select.form.requestSubmit()),
    );
