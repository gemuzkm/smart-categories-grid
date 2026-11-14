# Synthetic layout checks, not a live WordPress or PageSpeed audit.
import asyncio
import functools
import json
import os
import threading
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, urlparse
from playwright.async_api import async_playwright

ROOT = Path(__file__).resolve().parents[2]
STYLES = ('classic', 'modern', 'minimal', 'card', 'text')

class Handler(SimpleHTTPRequestHandler):
    def log_message(self, *args):
        pass

    def do_GET(self):
        url = urlparse(self.path)
        if url.path != '/fixture':
            return super().do_GET()
        q = parse_qs(url.query)
        style = q.get('style', ['classic'])[0]
        columns = int(q.get('columns', ['6'])[0])
        assert style in STYLES and columns in (2, 6)
        # Minified cases load the per-style stylesheet visitors receive; source cases load the full source.
        asset = 'front-' + style + '.min.css' if q.get('min', ['0'])[0] == '1' else 'front.css'
        cards = []
        for i in range(10):
            image = '' if style == 'text' else '<a class="scg-image" href="#target" tabindex="-1" aria-hidden="true"><img src="/slow.svg" width="120" height="96" decoding="async" alt=""></a>'
            title = 'Category ' + str(i + 1) + ('_averylongunbrokentitle' * 4 if i == 2 else '')
            cards.append('<div class="scg-col"><div class="scg-card has-hover">' + image + '<div class="scg-title"><a href="#target">' + title + '</a></div></div></div>')
        html = '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:16px;font-family:Arial,sans-serif}img{max-width:100%;height:auto}#sentinel{margin-top:20px}</style><link rel="stylesheet" href="/assets/' + asset + '"></head><body><div class="scg-grid scg-style-' + style + '" style="--scg-columns:' + str(columns) + '">' + ''.join(cards) + '<div class="scg-view-all"><a class="scg-view-all-link" href="#target">View All</a></div></div><p id="sentinel">Content after the grid</p></body></html>'
        body = html.encode()
        self.send_response(200)
        self.send_header('Content-Type', 'text/html; charset=utf-8')
        self.send_header('Content-Length', str(len(body)))
        self.end_headers()
        self.wfile.write(body)

async def geometry(page):
    return await page.evaluate('''() => [...document.querySelectorAll('.scg-card,.scg-image,#sentinel')].map(e => {const r=e.getBoundingClientRect();return [r.x,r.y,r.width,r.height];})''')

async def main():
    server = ThreadingHTTPServer(('127.0.0.1', 0), functools.partial(Handler, directory=str(ROOT)))
    threading.Thread(target=server.serve_forever, daemon=True).start()
    records = []
    try:
        async with async_playwright() as pw:
            browser = await pw.chromium.launch()
            for mini in (0, 1):
                for width in (320, 375, 768, 1280):
                    for columns in (2, 6):
                        for style in STYLES:
                            context = await browser.new_context(viewport={'width': width, 'height': 900}, has_touch=width < 768, is_mobile=width < 768)
                            page = await context.new_page()
                            await page.add_init_script("window.layoutShiftSum=0;new PerformanceObserver(l=>{for(const e of l.getEntries())if(!e.hadRecentInput)window.layoutShiftSum+=e.value}).observe({type:'layout-shift',buffered:true});")
                            ready = asyncio.Event()
                            async def image(route):
                                await ready.wait()
                                await route.fulfill(content_type='image/svg+xml', body='<svg xmlns="http://www.w3.org/2000/svg" width="2400" height="1920"><rect width="2400" height="1920" fill="#ddd"/></svg>')
                            await page.route('**/slow.svg', image)
                            await page.goto(f'http://127.0.0.1:{server.server_port}/fixture?style={style}&columns={columns}&min={mini}', wait_until='domcontentloaded')
                            await page.wait_for_timeout(50)
                            before = await geometry(page)
                            ready.set()
                            await page.wait_for_load_state('load')
                            await page.wait_for_timeout(50)
                            after = await geometry(page)
                            delta = max((abs(a-b) for old,new in zip(before,after) for a,b in zip(old,new)), default=0)
                            metrics = await page.evaluate('''() => ({overflow:document.documentElement.scrollWidth-innerWidth,shift:window.layoutShiftSum,minTarget:Math.min(...[...document.querySelectorAll('.scg-title a,.scg-view-all-link')].map(e=>e.getBoundingClientRect().height)),columns:getComputedStyle(document.querySelector('.scg-grid')).gridTemplateColumns.split(' ').length})''')
                            assert delta < .5, (style, width, 'image geometry', delta)
                            assert metrics['overflow'] <= 1, (style, width, 'horizontal overflow', metrics)
                            assert metrics['shift'] < .001, (style, width, 'image-loading shifts', metrics)
                            # 44px tap targets are required on touch contexts only; pointer devices keep text-height links.
                            if width < 768:
                                assert metrics['minTarget'] >= 43.9, (style, width, 'tap target', metrics)
                            assert metrics['columns'] == min(columns, 2 if width < 768 else 3 if width <= 1024 else 6), metrics
                            if width < 768:
                                await page.locator('.scg-card').first.hover()
                                assert await page.locator('.scg-card').first.evaluate("e=>getComputedStyle(e).transform") == 'none'
                            await page.emulate_media(reduced_motion='reduce')
                            await page.locator('.scg-card').first.hover()
                            await page.wait_for_timeout(30)
                            assert await page.locator('.scg-card').first.evaluate("e=>getComputedStyle(e).transitionDuration") == '0s'
                            records.append({'style':style,'configuredColumns':columns,'width':width,'minified':bool(mini),'maxGeometryChange':delta,**metrics})
                            await context.close()
            await browser.close()
        output = Path(os.getenv('SCG_LAYOUT_REPORT', str(ROOT / '.github' / 'layout-results.json')))
        output.parent.mkdir(parents=True, exist_ok=True)
        output.write_text(json.dumps({'scope':'Synthetic markup matching the plugin card structure; not a live-site audit','cases':records}, indent=2))
        print(f'{len(records)} synthetic layout cases passed')
    finally:
        server.shutdown()

if __name__ == '__main__':
    asyncio.run(main())
