'use client';

import { useState, useEffect } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { MonitorDashboardData } from '@/lib/types';
import StatusHeader from '@/components/dashboard/StatusHeader';
import UptimeStats from '@/components/dashboard/UptimeStats';
import ResponseTimeChart from '@/components/dashboard/ResponseTimeChart';
import IncidentsList from '@/components/dashboard/IncidentsList';
import SSLInfo from '@/components/dashboard/SSLInfo';
import MonthlyMonitoringToggle from '@/components/dashboard/MonthlyMonitoringToggle';

export default function MonitorDashboard() {
  const params = useParams();
  const router = useRouter();
  const [data, setData] = useState<MonitorDashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchDashboardData = async () => {
    try {
      const response = await fetch(`/api/monitors/${params.id}/dashboard`);
      
      if (!response.ok) {
        if (response.status === 404) {
          router.push('/');
          return;
        }
        throw new Error('Failed to fetch dashboard data');
      }
      
      const dashboardData = await response.json();
      setData(dashboardData);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchDashboardData();
    
    // Refresh data every 30 seconds
    const interval = setInterval(fetchDashboardData, 30000);
    return () => clearInterval(interval);
  }, [params.id]);

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 p-8">
        <div className="max-w-7xl mx-auto">
          <div className="animate-pulse">
            <div className="h-32 bg-gray-200 rounded-lg mb-8"></div>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
              <div className="h-24 bg-gray-200 rounded-lg"></div>
              <div className="h-24 bg-gray-200 rounded-lg"></div>
              <div className="h-24 bg-gray-200 rounded-lg"></div>
            </div>
            <div className="h-96 bg-gray-200 rounded-lg"></div>
          </div>
        </div>
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="min-h-screen bg-gray-50 p-8">
        <div className="max-w-7xl mx-auto">
          <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
            {error || 'Failed to load dashboard data'}
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Navigation */}
      <nav className="bg-white shadow-sm border-b">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between items-center h-16">
            <button
              onClick={() => router.push('/')}
              className="flex items-center text-gray-600 hover:text-gray-900"
            >
              <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
              </svg>
              Back to Monitors
            </button>
            
            <MonthlyMonitoringToggle 
              monitorId={data.monitor.id}
              enabled={data.monitor.monthlyMonitoringEnabled || false}
              onToggle={fetchDashboardData}
            />
          </div>
        </div>
      </nav>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Status Header */}
        <StatusHeader 
          url={data.monitor.url}
          status={data.currentStatus}
          lastChecked={data.lastChecked}
          responseTime={data.monitor.lastResponseTime}
        />

        {/* Uptime Statistics */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
          <UptimeStats stats={data.uptimeStats.last7Days} />
          <UptimeStats stats={data.uptimeStats.last30Days} />
          <UptimeStats stats={data.uptimeStats.last365Days} />
        </div>

        {/* Response Time Chart */}
        <div className="bg-white rounded-lg shadow-md p-6 mb-8">
          <h2 className="text-xl font-semibold mb-4">Response Time</h2>
          <ResponseTimeChart data={data.responseTimeStats} />
        </div>

        {/* SSL Certificate Info */}
        {data.monitor.url.startsWith('https://') && (
          <div className="mb-8">
            <SSLInfo certificate={data.sslCertificate} />
          </div>
        )}

        {/* Recent Incidents */}
        <div className="bg-white rounded-lg shadow-md p-6">
          <h2 className="text-xl font-semibold mb-4">Recent Incidents</h2>
          <IncidentsList incidents={data.recentIncidents} />
        </div>
      </div>
    </div>
  );
}