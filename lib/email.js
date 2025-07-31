import nodemailer from 'nodemailer';

const transporter = nodemailer.createTransport({
  host: process.env.SMTP_HOST,
  port: Number(process.env.SMTP_PORT) || 587,
  secure: false,
  auth: {
    user: process.env.SMTP_USER,
    pass: process.env.SMTP_PASS,
  },
});

export async function sendStatusChangeEmail({ to, url, newStatus, oldStatus, details }) {
  const subjectPrefix = newStatus === 'up' ? '[Recovered]' : '[Alert]';
  const subject = `${subjectPrefix} ${url} status ${newStatus.toUpperCase()}`;
  const time = new Date().toISOString();

  const html = `<p>Time: ${time}</p>
    <p>URL: ${url}</p>
    <p>Previous status: ${oldStatus}</p>
    <p>Current status: <strong>${newStatus.toUpperCase()}</strong></p>
    <pre>${JSON.stringify(details, null, 2)}</pre>`;

  await transporter.sendMail({
    from: process.env.FROM_EMAIL || 'monitor@localhost',
    to,
    subject,
    html,
  });
}