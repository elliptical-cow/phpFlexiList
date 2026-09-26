import assert from 'node:assert/strict';

const requests = [];
globalThis.window = {
  FLEXI_CONFIG: { BACKEND_URL: 'https://lists.example.test' },
  location: {
    origin: 'https://lists.example.test',
    search: '?id=aaaaaaaaaaaaaaaaaaaaaaaa',
    hash: '#token=secret-token'
  }
};
globalThis.fetch = async (url, options = {}) => {
  requests.push({ url, options });
  return {
    ok: true,
    status: 200,
    async json() {
      return url.endsWith('/api/lists')
        ? { id: 'bbbbbbbbbbbbbbbbbbbbbbbb', token: 'new-token' }
        : { Checklist: [] };
    }
  };
};

await import('../assets/services/BackendService.js');
const service = new window.BackendService();
const backend = service.checkBackendAvailability();
assert.equal(backend.available, true);
assert.equal(backend.accessToken, 'secret-token');

await service.loadList(backend.listId);
assert.equal(requests[0].options.headers.Authorization, 'Bearer secret-token');
assert.equal(requests[0].url, 'https://lists.example.test/api/list/aaaaaaaaaaaaaaaaaaaaaaaa');

const created = await service.createList();
assert.equal(created.token, 'new-token');
assert.equal(requests[1].options.method, 'POST');

console.log('Frontend tests passed.');

