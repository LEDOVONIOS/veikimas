import { useState, useEffect } from 'react';

export default function Home() {
  const [url, setUrl] = useState('');
  const [email, setEmail] = useState('');
  const [sites, setSites] = useState([]);
  const [loading, setLoading] = useState(false);

  const fetchSites = async () => {
    const res = await fetch('/api/monitor/list');
    const data = await res.json();
    setSites(data.sites);
  };

  useEffect(() => {
    fetchSites();
    const interval = setInterval(fetchSites, 60000);
    return () => clearInterval(interval);
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    await fetch('/api/monitor/register', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ url, email }),
    });
    setUrl('');
    setEmail('');
    await fetchSites();
    setLoading(false);
  };

  return (
    <main style={{ padding: '2rem', fontFamily: 'Arial, sans-serif' }}>
      <h1>Website Monitor</h1>
      <form onSubmit={handleSubmit} style={{ marginBottom: '2rem' }}>
        <input
          type="text"
          placeholder="https://example.com"
          value={url}
          onChange={(e) => setUrl(e.target.value)}
          required
          style={{ width: '300px', marginRight: '1rem' }}
        />
        <input
          type="email"
          placeholder="you@example.com"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          required
          style={{ width: '250px', marginRight: '1rem' }}
        />
        <button type="submit" disabled={loading}>Monitor</button>
      </form>

      <table border="1" cellPadding="8">
        <thead>
          <tr>
            <th>URL</th>
            <th>Email</th>
            <th>Status</th>
            <th>HTTP</th>
            <th>Last Checked</th>
            <th>Response Time (ms)</th>
          </tr>
        </thead>
        <tbody>
          {sites.map((s) => (
            <tr key={`${s.url}|${s.email}`}>
              <td>{s.url}</td>
              <td>{s.email}</td>
              <td>{s.lastStatus}</td>
              <td>{s.httpStatus || '-'}</td>
              <td>{s.lastChecked ? new Date(s.lastChecked).toLocaleString() : '-'}</td>
              <td>{s.responseTime || '-'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </main>
  );
}