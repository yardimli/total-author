export function money(value) {
    const units = Math.round(Number(value) * 100000000);
    return "$" + (Math.ceil(units / 1000000) / 100).toFixed(2);
}
