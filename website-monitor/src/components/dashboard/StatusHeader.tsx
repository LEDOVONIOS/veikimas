import { WebsiteStatus } from '@/lib/types';

interface StatusHeaderProps {
  url: string;
  status: WebsiteStatus;
  lastChecked: Date;
  responseTime: number | null;
}

export default function StatusHeader({ url, status, lastChecked, responseTime }: StatusHeaderProps) {
  const getStatusColor = (status: WebsiteStatus) => {
    switch (status) {
      case 'up': return 'bg-green-500';
      case 'down': return 'bg-red-500';
      case 'client_error': return 'bg-yellow-500';
      case 'ssl_error': return 'bg-purple-500';
      default: return 'bg-gray-500';
    }
  };

  const getStatusText = (status: WebsiteStatus) => {
    switch (status) {
      case 'up': return 'Operational';
      case 'down': return 'Down';
      case 'client_error': return 'Client Error';
      case 'ssl_error': return 'SSL Error';
      default: return 'Unknown';
    }
  };

  const getStatusIcon = (status: WebsiteStatus) => {
    switch (status) {
      case 'up':
        return (
          <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        );
      case 'down':
        return (
          <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        );
      case 'client_error':
        return (
          <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        );
      case 'ssl_error':
        return (
          <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
        );
      default:
        return null;
    }
  };

  const formatLastChecked = (date: Date) => {
    const now = new Date();
    const diffMs = now.getTime() - new Date(date).getTime();
    const diffSecs = Math.floor(diffMs / 1000);
    
    if (diffSecs < 60) return `${diffSecs} seconds ago`;
    if (diffSecs < 3600) return `${Math.floor(diffSecs / 60)} minutes ago`;
    if (diffSecs < 86400) return `${Math.floor(diffSecs / 3600)} hours ago`;
    return new Date(date).toLocaleString();
  };

  return (
    <div className={`${getStatusColor(status)} text-white rounded-lg p-6 mb-8 shadow-lg`}>
      <div className="flex items-center justify-between">
        <div className="flex items-center space-x-4">
          {getStatusIcon(status)}
          <div>
            <h1 className="text-2xl font-bold">{url}</h1>
            <p className="text-xl opacity-90">{getStatusText(status)}</p>
          </div>
        </div>
        
        <div className="text-right">
          <div className="text-sm opacity-75">Last checked</div>
          <div className="font-medium">{formatLastChecked(lastChecked)}</div>
          {responseTime !== null && (
            <>
              <div className="text-sm opacity-75 mt-2">Response time</div>
              <div className="font-medium">{responseTime}ms</div>
            </>
          )}
        </div>
      </div>
    </div>
  );
}