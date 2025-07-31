import { getEntries } from '../../../lib/storage.js';
import '../../../lib/cron.js';

export default function handler(req, res) {
  if (req.method !== 'GET') {
    res.setHeader('Allow', ['GET']);
    return res.status(405).json({ error: 'Method not allowed' });
  }
  return res.status(200).json({ sites: getEntries() });
}