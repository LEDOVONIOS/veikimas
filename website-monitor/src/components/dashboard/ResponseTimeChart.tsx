'use client';

import { useState } from 'react';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import { ResponseTimeStats } from '@/lib/types';

interface ResponseTimeChartProps {
  data: {
    last24Hours: ResponseTimeStats;
    last7Days: ResponseTimeStats;
    last30Days: ResponseTimeStats;
  };
}

export default function ResponseTimeChart({ data }: ResponseTimeChartProps) {
  const [selectedPeriod, setSelectedPeriod] = useState<'24h' | '7d' | '30d'>('24h');
  
  const getChartData = () => {
    const stats = selectedPeriod === '24h' ? data.last24Hours : 
                  selectedPeriod === '7d' ? data.last7Days : 
                  data.last30Days;
    
    return stats.dataPoints.map(point => ({
      time: new Date(point.timestamp).toLocaleString([], {
        month: 'short',
        day: 'numeric',
        hour: selectedPeriod === '24h' ? '2-digit' : undefined,
        minute: selectedPeriod === '24h' ? '2-digit' : undefined,
      }),
      value: point.value,
    }));
  };

  const getCurrentStats = () => {
    return selectedPeriod === '24h' ? data.last24Hours : 
           selectedPeriod === '7d' ? data.last7Days : 
           data.last30Days;
  };

  const stats = getCurrentStats();
  const chartData = getChartData();

  return (
    <div>
      {/* Period Selector */}
      <div className="flex space-x-2 mb-4">
        <button
          onClick={() => setSelectedPeriod('24h')}
          className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
            selectedPeriod === '24h'
              ? 'bg-blue-600 text-white'
              : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
          }`}
        >
          24 Hours
        </button>
        <button
          onClick={() => setSelectedPeriod('7d')}
          className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
            selectedPeriod === '7d'
              ? 'bg-blue-600 text-white'
              : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
          }`}
        >
          7 Days
        </button>
        <button
          onClick={() => setSelectedPeriod('30d')}
          className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
            selectedPeriod === '30d'
              ? 'bg-blue-600 text-white'
              : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
          }`}
        >
          30 Days
        </button>
      </div>

      {/* Stats Summary */}
      <div className="grid grid-cols-5 gap-4 mb-6 p-4 bg-gray-50 rounded-lg">
        <div>
          <div className="text-sm text-gray-600">Average</div>
          <div className="text-lg font-semibold">{stats.average}ms</div>
        </div>
        <div>
          <div className="text-sm text-gray-600">Min</div>
          <div className="text-lg font-semibold text-green-600">{stats.min}ms</div>
        </div>
        <div>
          <div className="text-sm text-gray-600">Max</div>
          <div className="text-lg font-semibold text-red-600">{stats.max}ms</div>
        </div>
        <div>
          <div className="text-sm text-gray-600">P95</div>
          <div className="text-lg font-semibold">{stats.p95}ms</div>
        </div>
        <div>
          <div className="text-sm text-gray-600">P99</div>
          <div className="text-lg font-semibold">{stats.p99}ms</div>
        </div>
      </div>

      {/* Chart */}
      {chartData.length > 0 ? (
        <div className="h-64">
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={chartData} margin={{ top: 5, right: 30, left: 20, bottom: 5 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
              <XAxis 
                dataKey="time" 
                stroke="#6b7280"
                tick={{ fontSize: 12 }}
                angle={selectedPeriod === '24h' ? -45 : 0}
                textAnchor={selectedPeriod === '24h' ? 'end' : 'middle'}
                height={selectedPeriod === '24h' ? 60 : 30}
              />
              <YAxis 
                stroke="#6b7280"
                tick={{ fontSize: 12 }}
                label={{ value: 'Response Time (ms)', angle: -90, position: 'insideLeft' }}
              />
              <Tooltip 
                contentStyle={{ 
                  backgroundColor: 'rgba(255, 255, 255, 0.95)',
                  border: '1px solid #e5e7eb',
                  borderRadius: '6px'
                }}
                formatter={(value: number) => [`${value}ms`, 'Response Time']}
              />
              <Line
                type="monotone"
                dataKey="value"
                stroke="#3b82f6"
                strokeWidth={2}
                dot={false}
                activeDot={{ r: 6 }}
              />
            </LineChart>
          </ResponsiveContainer>
        </div>
      ) : (
        <div className="h-64 flex items-center justify-center bg-gray-50 rounded-lg">
          <p className="text-gray-500">No data available for the selected period</p>
        </div>
      )}
    </div>
  );
}