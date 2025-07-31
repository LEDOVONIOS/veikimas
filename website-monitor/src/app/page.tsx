'use client';

import { useState } from 'react';
import MonitorForm from '@/components/MonitorForm';
import MonitorList from '@/components/MonitorList';
import SchedulerControl from '@/components/SchedulerControl';

export default function Home() {
  const [refreshTrigger, setRefreshTrigger] = useState(0);

  const handleMonitorAdded = () => {
    // Trigger refresh of monitor list
    setRefreshTrigger(prev => prev + 1);
  };

  return (
    <main className="min-h-screen bg-gray-50">
      {/* Header */}
      <header className="bg-white shadow-sm border-b">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex items-center space-x-3">
            <div className="bg-blue-600 p-2 rounded-lg">
              <svg className="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
            </div>
            <div>
              <h1 className="text-3xl font-bold text-gray-900">Website Monitor</h1>
              <p className="text-sm text-gray-600">Monitor your websites and get notified when they go down</p>
            </div>
          </div>
        </div>
      </header>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Left Column - Forms */}
          <div className="lg:col-span-1 space-y-8">
            <MonitorForm onSuccess={handleMonitorAdded} />
            <SchedulerControl />
            
            {/* Info Card */}
            <div className="bg-blue-50 border border-blue-200 rounded-lg p-6">
              <h3 className="text-lg font-semibold text-blue-900 mb-2">How it works</h3>
              <ul className="space-y-2 text-sm text-blue-800">
                <li className="flex items-start">
                  <span className="mr-2">•</span>
                  <span>Add websites with your email to start monitoring</span>
                </li>
                <li className="flex items-start">
                  <span className="mr-2">•</span>
                  <span>Websites are checked every minute automatically</span>
                </li>
                <li className="flex items-start">
                  <span className="mr-2">•</span>
                  <span>Get email alerts when status changes (up → down, etc.)</span>
                </li>
                <li className="flex items-start">
                  <span className="mr-2">•</span>
                  <span>SSL certificates are validated for HTTPS sites</span>
                </li>
              </ul>
            </div>
          </div>

          {/* Right Column - Monitor List */}
          <div className="lg:col-span-2">
            <MonitorList refreshTrigger={refreshTrigger} />
          </div>
        </div>

        {/* Status Legend */}
        <div className="mt-8 bg-white p-6 rounded-lg shadow-md">
          <h3 className="text-lg font-semibold mb-4">Status Definitions</h3>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div className="flex items-center space-x-2">
              <span className="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">✅ UP</span>
              <span className="text-sm text-gray-600">HTTP 200-299 response</span>
            </div>
            <div className="flex items-center space-x-2">
              <span className="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">❌ DOWN</span>
              <span className="text-sm text-gray-600">HTTP 500+, timeout, DNS fail</span>
            </div>
            <div className="flex items-center space-x-2">
              <span className="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">⚠️ CLIENT ERROR</span>
              <span className="text-sm text-gray-600">HTTP 400-499 response</span>
            </div>
            <div className="flex items-center space-x-2">
              <span className="px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800">🔒 SSL ERROR</span>
              <span className="text-sm text-gray-600">Invalid/expired certificate</span>
            </div>
          </div>
        </div>
      </div>
    </main>
  );
}
