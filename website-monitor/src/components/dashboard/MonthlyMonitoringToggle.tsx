'use client';

import { useState } from 'react';

interface MonthlyMonitoringToggleProps {
  monitorId: string;
  enabled: boolean;
  onToggle?: () => void;
}

export default function MonthlyMonitoringToggle({ monitorId, enabled, onToggle }: MonthlyMonitoringToggleProps) {
  const [loading, setLoading] = useState(false);
  const [isEnabled, setIsEnabled] = useState(enabled);

  const handleToggle = async () => {
    setLoading(true);
    try {
      const response = await fetch(`/api/monitors/${monitorId}/monthly`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ enabled: !isEnabled }),
      });

      if (response.ok) {
        setIsEnabled(!isEnabled);
        if (onToggle) {
          onToggle();
        }
      } else {
        throw new Error('Failed to toggle monthly monitoring');
      }
    } catch (error) {
      console.error('Error toggling monthly monitoring:', error);
      alert('Failed to toggle monthly monitoring');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex items-center space-x-3">
      <span className="text-sm font-medium text-gray-700">Monthly Monitoring</span>
      <button
        onClick={handleToggle}
        disabled={loading}
        className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 ${
          isEnabled ? 'bg-blue-600' : 'bg-gray-200'
        } ${loading ? 'opacity-50 cursor-not-allowed' : ''}`}
      >
        <span
          className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
            isEnabled ? 'translate-x-6' : 'translate-x-1'
          }`}
        />
      </button>
      <span className="text-sm text-gray-500">
        {isEnabled ? 'Enabled' : 'Disabled'}
      </span>
    </div>
  );
}