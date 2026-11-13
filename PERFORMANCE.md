# Layout and performance verification

This follow-up completes the CSS work deferred from the 2.4.0 changes. It does not claim a PageSpeed score or field Core Web Vitals result.

## Changes

- Scope box sizing to the grid. Padding and borders no longer add unexpected width or height to 100%-sized cards.
- Allow grid tracks and long titles to shrink and wrap without horizontal overflow.
- Give every image container a known size before the image arrives. Normal images use a 120:96 aspect ratio, Modern mobile images use the same ratio at a smaller width, and Card images retain fixed 140px/100px heights.
- Keep images inside their reserved containers even when a theme sets `img { height: auto; max-width: 100%; }`.
- Use static shadows. Only `transform` and `opacity` are eligible for transitions; no animated shadow, background, color or width.
- Restrict hover motion to fine-pointer devices and honor reduced-motion preferences for descendants and pseudo-elements.
- Give title and View All links at least 44px height. Fix the Text style selector so it targets the grid itself.
- Reduce source CSS from 12,501 bytes to 5,868 bytes. The conservative minified copy is 4,610 bytes versus 9,054 bytes previously. The bot and release workflow regenerate it using clean-css; their exact output size may differ.

The five styles remain available, but spacing and hover presentation have intentionally been simplified. Card wrappers and PHP rendering are unchanged. This does not implement DOM flattening or a new PHP image-loading policy.

## Executed checks

The Playwright/Chromium synthetic fixture checks all five styles at viewport widths 320, 375, 768 and 1280px, configured columns 2 and 6, using both source and minified CSS: 80 cases.

The fixture deliberately delays image responses, then serves images with large intrinsic dimensions. It checks card/image/content geometry before and after arrival, layout-shift entries, horizontal overflow, title/button heights, responsive columns, touch hover and reduced-motion behavior. It also uses long unbroken titles and a theme-like image rule.

In the local 80-case run, maximum image-related geometry change, observed layout-shift sum and horizontal overflow were zero; the smallest tested link height was 44px. This is an isolated static-markup fixture matching the renderer's card structure, not a WordPress/theme integration test and not a measurement of the live site's CLS.

## Automated checks

The Layout and Lighthouse checks workflow rebuilds production CSS and runs the fixture in Chromium on relevant pushes and pull requests. Its report is uploaded as an artifact. The existing Build assets workflow continues committing clean-css output to main; Release rebuilds it again before packaging.

To run the same fixture locally, install Playwright 1.51.0 and its Chromium browser, then run `python .github/tests/layout.py`.

## Live-site LCP, INP and PageSpeed

Use Actions → Layout and Lighthouse checks → Run workflow and provide a public HTTPS page containing the grid. The optional mobile Lighthouse job uploads HTML and JSON reports for performance, accessibility, best practices and SEO. It audits the supplied deployed URL, not the unpublished Git commit. Do not put credentials or private tokens in the URL.

Lighthouse reports laboratory LCP, CLS and TBT. TBT is diagnostic, not a replacement for field INP. Compare repeated runs under the same conditions, and use Search Console/CrUX or RUM for real-user INP and other field results. Theme scripts, fonts, page caches, images outside the grid, consent widgets, ads and the hosting environment can all affect the score.

Before publishing a release, review the five styles on a WordPress staging site, test keyboard navigation and measure pages with and without the grid. If updating an existing 2.4.0 installation directly from main, purge its page/CDN/browser asset caches: these CSS changes do not alter the PHP asset-version constant. No new release tag is created by this follow-up.
