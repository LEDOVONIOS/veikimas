# Website Monitoring System

A production-ready website monitoring system built with Next.js that checks website availability every minute and sends email alerts when status changes occur.

![Website Monitor](https://img.shields.io/badge/Next.js-14-black)
![TypeScript](https://img.shields.io/badge/TypeScript-5-blue)
![Tailwind CSS](https://img.shields.io/badge/Tailwind-3-38B2AC)

## 🚀 Features

### Core Functionality
- **URL Monitoring**: Monitor any website URL (HTTP/HTTPS)
- **Status Detection**: 
  - ✅ UP (200-299 responses)
  - ❌ DOWN (500+, timeouts, DNS failures)
  - ⚠️ CLIENT ERROR (400-499 responses)
  - 🔒 SSL ERROR (invalid/expired certificates)
- **SSL Certificate Validation**: Automatic validation for HTTPS sites
- **Response Time Tracking**: Monitor website performance
- **Email Notifications**: Get notified only when status changes

### Technical Features
- **1-Minute Interval Checks**: Automatic monitoring using node-cron
- **Persistent Storage**: File-based storage with in-memory caching
- **RESTful API**: Well-structured API routes for all operations
- **Beautiful UI**: Modern, responsive interface with Tailwind CSS
- **Production Ready**: Modular, extensible architecture

## 📋 Prerequisites

- Node.js 18+ 
- npm or yarn
- Email account with SMTP access (Gmail, SendGrid, etc.)

## 🛠️ Installation

1. Clone the repository:
```bash
git clone <repository-url>
cd website-monitor
```

2. Install dependencies:
```bash
npm install
```

3. Set up environment variables:
```bash
cp .env.example .env
```

4. Configure your email settings in `.env`:
```env
EMAIL_SERVICE=gmail
EMAIL_USER=your-email@gmail.com
EMAIL_PASSWORD=your-app-password
EMAIL_FROM=Website Monitor <your-email@gmail.com>
```

### Email Configuration

#### Gmail Setup
1. Enable 2-factor authentication on your Google account
2. Generate an app-specific password: https://myaccount.google.com/apppasswords
3. Use the app password in `EMAIL_PASSWORD`

#### SendGrid Setup (Alternative)
```env
EMAIL_SERVICE=sendgrid
SENDGRID_API_KEY=your-sendgrid-api-key
```

## 🚀 Running the Application

### Development Mode
```bash
npm run dev
```
Visit http://localhost:3000

### Production Mode
```bash
npm run build
npm start
```

## 📖 Usage

### Adding a Monitor
1. Enter the website URL (must start with http:// or https://)
2. Enter your email address
3. Click "Add Monitor"
4. The website will be checked immediately and then every minute

### Managing Monitors
- **View Status**: See real-time status, response time, and SSL info
- **Remove Monitor**: Click "Remove" to stop monitoring a website
- **Manual Check**: Use "Trigger Manual Check" to check all sites immediately

### Scheduler Control
- **Start/Stop**: Control the automatic 1-minute checking
- **Status**: View if the scheduler is running

## 🔌 API Endpoints

### Monitors
- `GET /api/monitors` - Get all monitors
- `GET /api/monitors?email={email}` - Get monitors by email
- `POST /api/monitors` - Add new monitor
- `GET /api/monitors/{id}` - Get specific monitor
- `DELETE /api/monitors/{id}` - Remove monitor

### Scheduler
- `GET /api/scheduler` - Get scheduler status
- `POST /api/scheduler` - Start/stop scheduler

### Manual Check
- `POST /api/check` - Trigger manual check for all monitors

## 📁 Project Structure

```
website-monitor/
├── src/
│   ├── app/
│   │   ├── api/          # API routes
│   │   └── page.tsx      # Main UI page
│   ├── components/       # React components
│   └── lib/
│       ├── monitoring/   # Core monitoring logic
│       ├── email/        # Email service
│       ├── storage/      # Data persistence
│       ├── cron/         # Scheduler
│       └── types.ts      # TypeScript types
├── data/                 # Persistent storage (auto-created)
└── public/              # Static assets
```

## 🔧 Configuration

### Monitoring Settings
- **Timeout**: 30 seconds per check
- **Check Interval**: 1 minute
- **Max Redirects**: 5

### Storage
- **Type**: File-based JSON storage
- **Location**: `./data/monitors.json`
- **Auto-save**: Every 30 seconds

## 🚀 Deployment

### Vercel (Recommended)
1. Push to GitHub
2. Import project in Vercel
3. Add environment variables
4. Deploy

### Self-Hosted
1. Build the project: `npm run build`
2. Set environment variables
3. Run: `npm start`
4. Use PM2 or similar for process management

## 📊 Monitoring Logic

### Status Determination
- **UP**: HTTP 200-299 (and valid SSL if HTTPS)
- **DOWN**: HTTP 500+, timeouts, connection errors
- **CLIENT_ERROR**: HTTP 400-499
- **SSL_ERROR**: Invalid/expired SSL certificate

### Notification Rules
- Only send emails when status changes
- "Recovered" email when site comes back up
- Include detailed error information
- Beautiful HTML email templates

## 🔐 Security Considerations

- Store sensitive data in environment variables
- Use app-specific passwords for email
- Validate all user inputs
- Sanitize URLs before processing

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Open a Pull Request

## 📝 License

This project is open source and available under the MIT License.

## 🐛 Troubleshooting

### Email not sending
- Check email credentials in `.env`
- Ensure app password is correct (not regular password)
- Check spam folder

### SSL checks failing
- Ensure the site uses HTTPS
- Check if behind firewall/proxy
- Verify SSL certificate is valid

### Monitors not persisting
- Check write permissions for `./data` directory
- Ensure enough disk space
- Check console for error messages
