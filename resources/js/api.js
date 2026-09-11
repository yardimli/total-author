export async function api(url, method = "GET", body) {
    const response = await fetch(url, {
        method,
        credentials: "same-origin",
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')
                .content,
        },
        ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    });
    const data = await response.json().catch(() => ({
        message: "The server returned an unexpected response.",
    }));
    if (!response.ok) {
        const error = new Error(
            Object.values(data.errors || {})
                .flat()
                .join(" ") ||
                data.message ||
                "Request failed",
        );
        error.status = response.status;
        throw error;
    }
    return data;
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
