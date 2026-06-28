// Defense in depth against the documented jspreadsheet formula XSS surface
// (jspreadsheet-ce is EOL — no upstream patches). Allow only the safe subset
// that real-world formulas need: letters, digits, math operators, parens,
// commas, colons, whitespace, dots, underscores, and quoted string literals.
// Then explicitly reject anything that looks like an HTML tag — comparison
// operators like `>` and `<` are fine on their own, but `<script` is not.
const FORMULA_ALLOWED = /^=[A-Z0-9_(),:.+\-*\/ \s"<>=!]+$/i;
const HTML_TAG = /<\/?[a-zA-Z]/;

export function sanitizeFormula(input: unknown): string {
    if (input == null) return '';
    const s = String(input);
    if (!s.startsWith('=')) return s;
    if (!FORMULA_ALLOWED.test(s) || HTML_TAG.test(s)) return '=';
    return s;
}
