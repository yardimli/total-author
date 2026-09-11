import { t, locale } from "./i18n";
export async function api(url, method = "GET", body, timeout = 120000) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeout);
    try {
        const response = await fetch(url, {
            method,
            credentials: "same-origin",
            signal: controller.signal,
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document.querySelector(
                    'meta[name="csrf-token"]',
                ).content,
            },
            ...(body === undefined ? {} : { body: JSON.stringify(body) }),
        });
        const data = await response.json().catch((error) => {
            if (controller.signal.aborted) throw error;
            return {
                message: t("The server returned an unexpected response."),
            };
        });
        if (!response.ok) {
            const error = new Error(
                Object.values(data.errors || {})
                    .flat()
                    .join(" ") ||
                    data.message ||
                    t("Request failed"),
            );
            error.status = response.status;
            throw error;
        }
        return data;
    } catch (error) {
        if (controller.signal.aborted) {
            const timeoutError = new Error(
                t(
                    "The request timed out. You can try sending again without refreshing.",
                ),
            );
            timeoutError.status = 408;
            throw timeoutError;
        }
        throw error;
    } finally {
        clearTimeout(timer);
    }
}
export function notify(message) {
    const box = document.querySelector("#toast");
    box.textContent = message;
    box.hidden = false;
    clearTimeout(notify.timer);
    notify.timer = setTimeout(() => (box.hidden = true), 9000);
}
export const $ = (selector) => document.querySelector(selector);
export function element(tag, text, className) {
    const node = document.createElement(tag);
    if (text !== undefined) node.textContent = text;
    if (className) node.className = className;
    return node;
}
export function action(selector, fn, event = "click") {
    $(selector)?.addEventListener(event, async (e) => {
        try {
            await fn(e);
        } catch (error) {
            notify(error.message);
        }
    });
}
