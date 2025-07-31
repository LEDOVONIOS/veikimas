const store = global.__monitorStore || new Map();
// Persist store across serverless function cold starts (only for dev/prototype)
global.__monitorStore = store;

export function addEntry(url, email) {
  const key = `${url}|${email}`;
  if (!store.has(key)) {
    store.set(key, {
      url,
      email,
      lastStatus: 'unknown',
      lastChecked: null,
      responseTime: null,
      sslInfo: null,
    });
  }
  return key;
}

export function getEntries() {
  return Array.from(store.values());
}

export function getEntry(key) {
  return store.get(key);
}

export function updateEntry(key, updates) {
  const existing = store.get(key) || {};
  store.set(key, { ...existing, ...updates });
}