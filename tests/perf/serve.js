// Production-like front for the PHP test server: gzip/brotli for text, long cache for theme assets.
// (PHP's built-in server sends everything uncompressed, which Lighthouse would blame on the theme.)
//   node perf/serve.js <listenPort> <phpPort>
const http = require('http');
const zlib = require('zlib');
const [listen, php] = [Number(process.argv[2] || 8686), Number(process.argv[3] || 8282)];
const cache = new Map(); // compressed bodies (like a CDN / precompressed assets)
const TEXT = /^(text\/|application\/(javascript|json|xml)|image\/svg)/;
http.createServer((req, res) => {
  const up = http.request({ host: '127.0.0.1', port: php, path: req.url, method: req.method, headers: { ...req.headers, host: 'localhost:' + listen, 'accept-encoding': 'identity', 'x-public-port': String(listen) } }, (u) => {
    const type = u.headers['content-type'] || '';
    const headers = { ...u.headers };
    delete headers['content-length'];
    if (/\/wp-content\/themes\/.*\.(css|js|woff2?|svg|png|jpe?g|webp)(\?|$)/.test(req.url)) { headers['cache-control'] = 'public, max-age=31536000, immutable'; }
    const chunks = [];
    u.on('data', (c) => chunks.push(c));
    u.on('end', () => {
      let body = Buffer.concat(chunks);
      const ae = String(req.headers['accept-encoding'] || '');
      if (TEXT.test(type) && body.length > 1024) {
        const key = req.url + '|' + body.length;
        if (/\bbr\b/.test(ae)) { body = cache.get('br' + key) || (cache.set('br' + key, zlib.brotliCompressSync(body, { params: { [zlib.constants.BROTLI_PARAM_QUALITY]: 5 } })), cache.get('br' + key)); headers['content-encoding'] = 'br'; }
        else if (/\bgzip\b/.test(ae)) { body = cache.get('gz' + key) || (cache.set('gz' + key, zlib.gzipSync(body, { level: 6 })), cache.get('gz' + key)); headers['content-encoding'] = 'gzip'; }
        headers.vary = 'Accept-Encoding';
      }
      headers['content-length'] = body.length;
      res.writeHead(u.statusCode, headers);
      res.end(body);
    });
  });
  up.on('error', () => { res.writeHead(502); res.end('upstream down'); });
  req.pipe(up);
}).listen(listen, () => console.log('perf front on', listen, '->', php));
