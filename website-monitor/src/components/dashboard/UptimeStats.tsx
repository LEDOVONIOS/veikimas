import { UptimeStats as UptimeStatsType } from '@/lib/types';

interface UptimeStatsProps {
  stats: UptimeStatsType;
}

export default function UptimeStats({ stats }: UptimeStatsProps) {
  const getPeriodLabel = (period: string) => {
    switch (period) {
      case '7d': return 'Last 7 Days';
      case '30d': return 'Last 30 Days';
      case '365d': return 'Last Year';
      default: return period;
    }
  };

  const formatDuration = (milliseconds: number) => {
    if (milliseconds === 0) return '0s';
    
    const seconds = Math.floor(milliseconds / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);
    
    if (days > 0) {
      const remainingHours = hours % 24;
      return `${days}d ${remainingHours}h`;
    }
    if (hours > 0) {
      const remainingMinutes = minutes % 60;
      return `${hours}h ${remainingMinutes}m`;
    }
    if (minutes > 0) {
      const remainingSeconds = seconds % 60;
      return `${minutes}m ${remainingSeconds}s`;
    }
    return `${seconds}s`;
  };

  const getUptimeColor = (percentage: number) => {
    if (percentage >= 99.9) return 'text-green-600';
    if (percentage >= 99) return 'text-yellow-600';
    if (percentage >= 95) return 'text-orange-600';
    return 'text-red-600';
  };

  return (
    <div className="bg-white rounded-lg shadow-md p-6">
      <h3 className="text-lg font-semibold text-gray-700 mb-4">{getPeriodLabel(stats.period)}</h3>
      
      <div className="space-y-3">
        <div>
          <div className="flex justify-between items-baseline">
            <span className="text-sm text-gray-600">Uptime</span>
            <span className={`text-2xl font-bold ${getUptimeColor(stats.uptimePercentage)}`}>
              {stats.uptimePercentage.toFixed(2)}%
            </span>
          </div>
          
          {/* Progress bar */}
          <div className="mt-2 h-2 bg-gray-200 rounded-full overflow-hidden">
            <div 
              className={`h-full transition-all duration-500 ${
                stats.uptimePercentage >= 99.9 ? 'bg-green-500' :
                stats.uptimePercentage >= 99 ? 'bg-yellow-500' :
                stats.uptimePercentage >= 95 ? 'bg-orange-500' :
                'bg-red-500'
              }`}
              style={{ width: `${stats.uptimePercentage}%` }}
            />
          </div>
        </div>
        
        <div className="grid grid-cols-2 gap-3 pt-3 border-t">
          <div>
            <div className="text-sm text-gray-600">Total Checks</div>
            <div className="font-semibold">{stats.totalChecks.toLocaleString()}</div>
          </div>
          
          <div>
            <div className="text-sm text-gray-600">Successful</div>
            <div className="font-semibold text-green-600">{stats.successfulChecks.toLocaleString()}</div>
          </div>
          
          <div>
            <div className="text-sm text-gray-600">Incidents</div>
            <div className="font-semibold text-red-600">{stats.totalIncidents}</div>
          </div>
          
          <div>
            <div className="text-sm text-gray-600">Downtime</div>
            <div className="font-semibold text-red-600">{formatDuration(stats.totalDowntime)}</div>
          </div>
        </div>
      </div>
    </div>
  );
}