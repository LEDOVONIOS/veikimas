'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { MonitorEntry, WebsiteStatus } from '@/lib/types';

interface MonitorListProps {
  refreshTrigger?: number;
}

export default function MonitorList({ refreshTrigger }: MonitorListProps) {
  const router = useRouter();
  const [monitors, setMonitors] = useState<MonitorEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchMonitors = async () => {
    try {
      const response = await fetch('/api/monitors');
      const data = await response.json();
      
      if (!response.ok) {
        throw new Error(data.error || 'Failed to fetch monitors');
      }
      
      setMonitors(data.monitors);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchMonitors();
  }, [refreshTrigger]);

  // Auto-refresh every 30 seconds
  useEffect(() => {
    const interval = setInterval(fetchMonitors, 30000);
    return () => clearInterval(interval);
  }, []);

  const handleDelete = async (id: string) => {
    if (!confirm('Are you sure you want to remove this monitor?')) {
      return;
    }

    try {
      const response = await fetch(`/api/monitors/${id}`, {
        method: 'DELETE',
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.error || 'Failed to delete monitor');
      }

      // Remove from local state
      setMonitors(monitors.filter(m => m.id !== id));
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Failed to delete monitor');
    }
  };

  const getStatusBadge = (status: WebsiteStatus | null) => {
    const baseClasses = "px-2 py-1 text-xs font-medium rounded-full";
    
    switch (status) {
      case 'up':
        return <span className={`${baseClasses} bg-green-100 text-green-800`}>✅ UP</span>;
      case 'down':
        return <span className={`${baseClasses} bg-red-100 text-red-800`}>❌ DOWN</span>;
      case 'client_error':
        return <span className={`${baseClasses} bg-yellow-100 text-yellow-800`}>⚠️ CLIENT ERROR</span>;
      case 'ssl_error':
        return <span className={`${baseClasses} bg-purple-100 text-purple-800`}>🔒 SSL ERROR</span>;
      default:
        return <span className={`${baseClasses} bg-gray-100 text-gray-800`}>⏳ PENDING</span>;
    }
  };

  const formatDate = (date: Date | null) => {
    if (!date) return 'Never';
    return new Date(date).toLocaleString();
  };

  const getSSLStatus = (monitor: MonitorEntry) => {
    if (!monitor.sslInfo) return null;
    
    if (!monitor.sslInfo.valid) {
      return <span className="text-red-600">❌ Invalid</span>;
    }
    
    if (monitor.sslInfo.daysRemaining !== undefined) {
      const days = monitor.sslInfo.daysRemaining;
      const color = days > 30 ? 'text-green-600' : days > 7 ? 'text-yellow-600' : 'text-red-600';
      return <span className={color}>✅ Valid ({days} days)</span>;
    }
    
    return <span className="text-green-600">✅ Valid</span>;
  };

  if (loading) {
    return (
      <div className="bg-white p-6 rounded-lg shadow-md">
        <div className="animate-pulse">
          <div className="h-4 bg-gray-200 rounded w-1/4 mb-4"></div>
          <div className="space-y-3">
            <div className="h-12 bg-gray-200 rounded"></div>
            <div className="h-12 bg-gray-200 rounded"></div>
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-white p-6 rounded-lg shadow-md">
        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md">
          {error}
        </div>
      </div>
    );
  }

  return (
    <div className="bg-white p-6 rounded-lg shadow-md">
      <h2 className="text-2xl font-bold mb-4">Monitored Websites</h2>
      
      {monitors.length === 0 ? (
        <p className="text-gray-500">No monitors added yet. Add a website to start monitoring!</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Website
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Response Time
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  SSL Status
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Last Checked
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Monthly Monitoring
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {monitors.map((monitor) => (
                <tr key={monitor.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap">
                    <a 
                      href={monitor.url} 
                      target="_blank" 
                      rel="noopener noreferrer"
                      className="text-blue-600 hover:text-blue-800 hover:underline"
                    >
                      {monitor.url}
                    </a>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    {getStatusBadge(monitor.lastStatus)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {monitor.lastResponseTime ? `${monitor.lastResponseTime}ms` : '-'}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm">
                    {monitor.url.startsWith('https://') ? getSSLStatus(monitor) : 
                     <span className="text-gray-400">N/A</span>}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {formatDate(monitor.lastChecked)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm">
                    {monitor.monthlyMonitoringEnabled ? (
                      <span className="text-green-600">✓ Enabled</span>
                    ) : (
                      <span className="text-gray-400">Disabled</span>
                    )}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm">
                    <div className="flex items-center space-x-3">
                      <button
                        onClick={() => router.push(`/monitor/${monitor.id}`)}
                        className="text-blue-600 hover:text-blue-900"
                      >
                        Dashboard
                      </button>
                      <button
                        onClick={() => handleDelete(monitor.id)}
                        className="text-red-600 hover:text-red-900"
                      >
                        Remove
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}