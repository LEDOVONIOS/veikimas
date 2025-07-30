'use client';

import { useState, useEffect } from 'react';

export default function SchedulerControl() {
  const [status, setStatus] = useState<'running' | 'stopped' | null>(null);
  const [loading, setLoading] = useState(false);

  const fetchStatus = async () => {
    try {
      const response = await fetch('/api/scheduler');
      const data = await response.json();
      setStatus(data.status);
    } catch (error) {
      console.error('Failed to fetch scheduler status:', error);
    }
  };

  useEffect(() => {
    fetchStatus();
  }, []);

  const handleAction = async (action: 'start' | 'stop') => {
    setLoading(true);
    try {
      const response = await fetch('/api/scheduler', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ action }),
      });

      if (response.ok) {
        setStatus(action === 'start' ? 'running' : 'stopped');
      }
    } catch (error) {
      console.error('Failed to control scheduler:', error);
    } finally {
      setLoading(false);
    }
  };

  const manualCheck = async () => {
    setLoading(true);
    try {
      await fetch('/api/check', {
        method: 'POST',
      });
      alert('Manual check initiated for all monitors');
    } catch (error) {
      console.error('Failed to trigger manual check:', error);
      alert('Failed to trigger manual check');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="bg-white p-6 rounded-lg shadow-md">
      <h2 className="text-2xl font-bold mb-4">Monitoring Scheduler</h2>
      
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <span className="text-gray-700">
            Status: {' '}
            {status === 'running' ? (
              <span className="text-green-600 font-semibold">🟢 Running</span>
            ) : status === 'stopped' ? (
              <span className="text-red-600 font-semibold">🔴 Stopped</span>
            ) : (
              <span className="text-gray-500">Loading...</span>
            )}
          </span>
          
          <div className="space-x-2">
            {status === 'stopped' && (
              <button
                onClick={() => handleAction('start')}
                disabled={loading}
                className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:bg-gray-400"
              >
                Start Scheduler
              </button>
            )}
            
            {status === 'running' && (
              <button
                onClick={() => handleAction('stop')}
                disabled={loading}
                className="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:bg-gray-400"
              >
                Stop Scheduler
              </button>
            )}
          </div>
        </div>
        
        <div className="pt-4 border-t">
          <p className="text-sm text-gray-600 mb-2">
            The scheduler automatically checks all monitored websites every minute when running.
          </p>
          <button
            onClick={manualCheck}
            disabled={loading}
            className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:bg-gray-400"
          >
            Trigger Manual Check
          </button>
        </div>
      </div>
    </div>
  );
}