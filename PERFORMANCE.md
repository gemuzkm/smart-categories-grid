# Layout and performance verification

This document describes the stylesheet shipped in 2.4.1. It does not claim a PageSpeed score or a field Core Web Vitals result.

## Stylesheet

- Box sizing is scoped to the grid, so padding and borders do not add unexpected width or height to 100%-sized cards.
- Grid tracks can shrink and long titles wrap without horizontal overflow.
- Every image container has a known size before the image arrives. Normal images use a 120:96 aspect ratio, Modern mobile images use the same ratio at a smaller width, and Card images keep fixed 140px/100px heights.
- Images stay inside their reserved containers even when a theme sets `img { height: auto; max-width: 100%; }`.
- The five styles follow the 2.3.2 look again: shadows, borders, the Modern accent bar and underline, the Card overlay, underline and arrow are restored. Hover transitions animate `transform` and `opacity` and also `box-shadow`, `border-color`, `background` and `color`; the transform/opacity-only restriction of the earlier 2.4.0 follow-up no longer applies.
- Hover effects are limited to fine-pointer devices. Reduced-motion preferences disable transitions and animations for descendants and pseudo-elements.
- Title and View All links are at least 44px high.
- Source CSS is 11,932 bytes. The committed minified copy is 9,094 bytes; the Build assets and Release workflows regenerate it with clean-css, and their output size may differ.

Card wrappers and PHP rendering are unchanged. This does not implement DOM flattening or a new PHP image-loading policy.

## Executed checks

The Playwright/Chromium synthetic fixture checks all five styles at viewport widths 320, 375, 768 and 1280px, configured columns 2 and 6, using both source and minified CSS: 80 cases.

The fixture deliberately delays image responses, then serves images with large intrinsic dimensions. It checks card/image/content geometry before and after arrival, layout-shift entries, horizontal overflow, title/button heights, responsive columns, touch hover and reduced-motion behavior. It also uses long unbroken titles and a theme-like image rule.

A local 80-case run against the 2.4.1 stylesheet passed every threshold: image-related geometry change below 0.5px, layout-shift sum below 0.001, no horizontal overflow and link heights of at least 44px. That run used a simple local minifier instead of clean-css. Hover effects were also checked once per style at 1280px with a mouse pointer. This is an isolated static-markup fixture matching the renderer's card structure, not a WordPress/theme integration test and not a measurement of a live site's CLS.

## Automated checks

The Layout and Lighthouse checks workflow rebuilds production CSS and runs the fixture in Chromium on relevant pushes and pull requests. Its report is uploaded as an artifact. The layout job runs on a pinned ubuntu-24.04 image with Python 3.12; its first successful run on GitHub was on the 2.4.1 pull request. The existing Build assets workflow continues committing clean-css output to main; Release rebuilds it again before packaging.

To run the same fixture locally, install Playwright 1.51.0 and its Chromium browser, then run `python .github/tests/layout.py`.

## Live-site LCP, INP and PageSpeed

Use Actions → Layout and Lighthouse checks → Run workflow and provide a public HTTPS page containing the grid. The optional mobile Lighthouse job uploads HTML and JSON reports for performance, accessibility, best practices and SEO. It audits the supplied deployed URL, not the unpublished Git commit. Do not put credentials or private tokens in the URL.

Lighthouse reports laboratory LCP, CLS and TBT. TBT is diagnostic, not a replacement for field INP. Compare repeated runs under the same conditions, and use Search Console/CrUX or RUM for real-user INP and other field results. Theme scripts, fonts, page caches, images outside the grid, consent widgets, ads and the hosting environment can all affect the score.

Before publishing a release, review the five styles on a WordPress staging site, test keyboard navigation and measure pages with and without the grid. From 2.4.1 asset URLs include the file modification time, so a replaced stylesheet gets a new URL. Full-page and CDN caches that still serve older HTML need a purge.
