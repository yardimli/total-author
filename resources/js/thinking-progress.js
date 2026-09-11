export function thinkingProgress() {
    const dialog = document.querySelector("#thinking-dialog");
    const timer = setTimeout(() => {
        if (!dialog.open) dialog.showModal();
    }, 7000);
    return () => {
        clearTimeout(timer);
        if (dialog.open) dialog.close();
    };
}
