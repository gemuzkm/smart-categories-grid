#!/usr/bin/env node
/*
 * Splits assets/front.css into one stylesheet per grid style.
 *
 * assets/front.css stays the single source. For every style this writes
 * <outdir>/front-<style>.css with the shared rules plus the rules of that style only;
 * selectors that name another style (.scg-style-*) are dropped. The workflows minify
 * the results into assets/front-<style>.min.css.
 *
 * Usage: node .github/scripts/build-css.js <outdir>
 */
'use strict';
const fs = require('fs');
const path = require('path');

const STYLES = ['classic', 'modern', 'minimal', 'card', 'text'];
const root = path.resolve(__dirname, '..', '..');
const outdir = process.argv[2];
if (!outdir) {
    console.error('Usage: node .github/scripts/build-css.js <outdir>');
    process.exit(2);
}

function parse(css) {
    const rules = [];
    let i = 0;
    while (i < css.length) {
        const open = css.indexOf('{', i);
        if (open === -1) {
            break;
        }
        const prelude = css.slice(i, open).trim();
        let depth = 1;
        let j = open + 1;
        while (j < css.length && depth) {
            if (css[j] === '{') {
                depth++;
            } else if (css[j] === '}') {
                depth--;
            }
            j++;
        }
        if (depth) {
            throw new Error('Unbalanced braces after: ' + prelude);
        }
        const body = css.slice(open + 1, j - 1);
        rules.push(prelude.startsWith('@') ? { prelude, children: parse(body) } : { prelude, body: body.trim() });
        i = j;
    }
    return rules;
}

function keeps(selector, style) {
    const named = selector.match(/\.scg-style-([a-z]+)/);
    if (named && !STYLES.includes(named[1])) {
        throw new Error('Unknown style in selector: ' + selector);
    }
    return !named || named[1] === style;
}

function emit(rules, style) {
    return rules.map((rule) => {
        if (rule.children) {
            const inner = emit(rule.children, style);
            return inner ? rule.prelude + ' {\n' + inner + '\n}' : '';
        }
        const selectors = rule.prelude.split(',').map((s) => s.trim()).filter((s) => keeps(s, style));
        return selectors.length ? selectors.join(',\n') + ' {\n' + rule.body + '\n}' : '';
    }).filter(Boolean).join('\n');
}

const source = fs.readFileSync(path.join(root, 'assets', 'front.css'), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');
const rules = parse(source);
fs.mkdirSync(outdir, { recursive: true });
for (const style of STYLES) {
    const css = emit(rules, style) + '\n';
    if (style !== 'classic' && !css.includes('.scg-style-' + style)) {
        throw new Error('No rules found for style: ' + style);
    }
    for (const other of STYLES) {
        if (other !== style && css.includes('.scg-style-' + other)) {
            throw new Error('Rules of ' + other + ' leaked into ' + style);
        }
    }
    fs.writeFileSync(path.join(outdir, 'front-' + style + '.css'), css);
    console.log('front-' + style + '.css', Buffer.byteLength(css), 'bytes');
}
