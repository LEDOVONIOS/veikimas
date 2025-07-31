import { addEntry } from '../../../lib/storage.js';
import '../../../lib/cron.js'; // Ensure scheduler is initialized

export default function handler(req, res) {
  if (req.method !== 'POST') {
    res.setHeader('Allow', ['POST']);
    return res.status(405).json({ error: 'Method not allowed' });
  }

  const { url, email } = req.body || {};
  if (!url || !email) {
    return res.status(400).json({ error: 'Both url and email are required.' });
  }

  const urlPattern = /^https?:\/\/.+/i;
  if (!urlPattern.test(url)) {
    return res.status(400).json({ error: 'URL must start with http:// or https://' });
  }

  addEntry(url, email);
  return res.status(200).json({ message: 'Monitoring registered successfully.' });
}