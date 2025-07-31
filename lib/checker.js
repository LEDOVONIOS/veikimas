import axios from 'axios';
import sslChecker from 'ssl-checker';

export async function checkSite(url) {
  const start = Date.now();
  try {
    const response = await axios.get(url, {
      timeout: 10000,
      validateStatus: () => true, // allow all status codes
    });
    const responseTime = Date.now() - start;
    let statusCategory;
    if (response.status >= 200 && response.status < 300) {
      statusCategory = 'up';
    } else if (response.status >= 400 && response.status < 500) {
      statusCategory = 'client_error';
    } else if (response.status >= 500 && response.status < 600) {
      statusCategory = 'down';
    } else {
      statusCategory = 'down';
    }

    let sslInfo = null;
    if (url.startsWith('https://')) {
      try {
        const domain = url.replace(/^https?:\/\//, '').split('/')[0];
        sslInfo = await sslChecker(domain, { method: 'GET', port: 443 });
        if (!sslInfo.valid || sslInfo.daysRemaining <= 0) {
          statusCategory = 'ssl_error';
        }
      } catch (err) {
        statusCategory = 'ssl_error';
      }
    }

    return {
      status: statusCategory,
      httpStatus: response.status,
      responseTime,
      sslInfo,
    };
  } catch (error) {
    const responseTime = Date.now() - start;
    return {
      status: 'down',
      error: error.code || error.message,
      responseTime,
    };
  }
}