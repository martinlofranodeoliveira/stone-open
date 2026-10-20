import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';

const root = process.cwd();
const view = fs.readFileSync(path.join(root, 'src/views/admin/users_edit.php'), 'utf8');
const scripts = [...view.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)]
  .map(match => match[1]).filter(script => script.includes("getElementById('formEnable2FA')"));
assert.equal(scripts.length, 1, 'The actual 2FA view must provide one initialization script');

for (const mode of ['success', 'throws', 'missing']) {
  test(`2FA keeps its manual secret/URI private when local QR is ${mode}`, async () => {
    const payload = {ok: true, secret: 'OFFLINEEXAMPLESECRET',
      otpauth_uri: 'otpauth://totp/Example:user@example.invalid?secret=OFFLINEEXAMPLESECRET&issuer=Example'};
    const requests = []; const externalImages = []; const qrValues = [];
    const errors = []; let shown = 0; let listener;
    const button = {disabled: false};
    const box = {children: [], set innerHTML(value) { assert.equal(value, ''); this.children = []; },
      querySelector(kind) { return this.children.find(child => child.kind === kind) || null; }};
    const nodes = {twofaSecret: {textContent: ''}, otpauthUri: {textContent: ''}, qrcodeBox: box,
      modal2FA: {id: 'modal2FA'}};
    const form = {action: '/admin/users/2fa/enable',
      addEventListener(event, callback) { assert.equal(event, 'submit'); listener = callback; },
      querySelector() { return button; }};
    nodes.formEnable2FA = form;
    const document = {
      getElementById(id) { return nodes[id] || null; },
      createElement(kind) {
        return {kind, set src(value) { externalImages.push(value); }};
      },
    };
    const sandbox = {document, URLSearchParams, FormData: class extends Array {
      constructor() { super(['csrf', 'offline-example']); }
    }, fetch: async (url, options) => {
      requests.push({url, options});
      assert.equal(url, form.action, 'Only the same-origin enable action may be requested');
      return {json: async () => payload};
    }, bootstrap: {Modal: class {
      constructor(element) { assert.equal(element, nodes.modal2FA); }
      show() { shown++; }
    }}, console: {error(error) { errors.push(String(error)); }}};
    sandbox.window = sandbox;
    if (mode !== 'missing') sandbox.QRCode = class {
      constructor(element, options) {
        qrValues.push(options.text);
        if (mode === 'throws') throw new Error('Synthetic local QR failure');
        element.children.push({kind: 'canvas'});
      }
    };
    vm.runInNewContext(scripts[0], sandbox, {timeout: 1000, filename: 'actual-twofa-view.js'});
    assert.equal(typeof listener, 'function');
    let prevented = false;
    listener({preventDefault() { prevented = true; }});
    await new Promise(resolve => setImmediate(resolve));
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(prevented, true);
    assert.equal(requests.length, 1);
    assert.equal(requests[0].options.credentials, 'same-origin');
    assert.deepEqual(externalImages, [], 'The TOTP URI must never be sent to an image service');
    assert.equal(shown, 1, 'The manual setup modal remains available without local QR');
    assert.equal(nodes.twofaSecret.textContent, payload.secret);
    assert.equal(nodes.otpauthUri.textContent, payload.otpauth_uri);
    assert.equal(button.disabled, false);
    assert.deepEqual(errors, []);
    assert.deepEqual(qrValues, mode === 'missing' ? [] : [payload.otpauth_uri]);
  });
}

test('The security policy no longer admits the external QR service', () => {
  const policy = fs.readFileSync(path.join(root,
    'src/App/Infrastructure/Http/Middleware/SecurityHeadersMiddleware.php'), 'utf8');
  assert.equal(policy.includes('api.qrserver.com'), false);
});
