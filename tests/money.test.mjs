import assert from "node:assert/strict";
import { test } from "node:test";
import { money } from "../resources/js/money.js";

test("money rounds fractional cents upward and preserves exact cents", () => {
    for (const [value, expected] of [
        [0, "$0.00"],
        [0.00000001, "$0.01"],
        [1.001, "$1.01"],
        [1.01, "$1.01"],
        [1.1, "$1.10"],
        [1.2301, "$1.24"],
    ]) {
        assert.equal(money(value), expected);
    }
});
