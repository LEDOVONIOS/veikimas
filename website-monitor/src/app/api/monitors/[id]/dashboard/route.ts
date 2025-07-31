import { NextRequest, NextResponse } from 'next/server';
import { getMonitorStore } from '@/lib/storage/monitorStore';
import { getHistoricalStore } from '@/lib/storage/historicalStore';
import { MonitorDashboardData } from '@/lib/types';

export async function GET(
  request: NextRequest,
  { params }: { params: { id: string } }
) {
  try {
    const store = getMonitorStore();
    const historicalStore = getHistoricalStore();
    
    const monitor = store.getMonitor(params.id);
    
    if (!monitor) {
      return NextResponse.json(
        { error: 'Monitor not found' },
        { status: 404 }
      );
    }
    
    // Calculate uptime statistics
    const last7Days = historicalStore.calculateUptimeStats(params.id, 7);
    const last30Days = historicalStore.calculateUptimeStats(params.id, 30);
    const last365Days = historicalStore.calculateUptimeStats(params.id, 365);
    
    // Calculate response time statistics
    const last24Hours = historicalStore.calculateResponseTimeStats(params.id, 24);
    const last7DaysRT = historicalStore.calculateResponseTimeStats(params.id, 168);
    const last30DaysRT = historicalStore.calculateResponseTimeStats(params.id, 720);
    
    // Get recent incidents
    const oneMonthAgo = new Date();
    oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1);
    const recentIncidents = historicalStore.getIncidents(params.id, oneMonthAgo)
      .sort((a, b) => new Date(b.startTime).getTime() - new Date(a.startTime).getTime())
      .slice(0, 20); // Last 20 incidents
    
    // Prepare SSL certificate data
    const sslCertificate = monitor.sslInfo ? {
      expiryDate: monitor.sslInfo.validTo || null,
      daysUntilExpiry: monitor.sslInfo.daysRemaining || null,
      issuer: monitor.sslInfo.issuer || null,
    } : {
      expiryDate: null,
      daysUntilExpiry: null,
      issuer: null,
    };
    
    const dashboardData: MonitorDashboardData = {
      monitor,
      currentStatus: monitor.lastStatus || 'up',
      lastChecked: monitor.lastChecked || new Date(),
      uptimeStats: {
        last7Days,
        last30Days,
        last365Days,
      },
      responseTimeStats: {
        last24Hours,
        last7Days: last7DaysRT,
        last30Days: last30DaysRT,
      },
      recentIncidents,
      sslCertificate,
    };
    
    return NextResponse.json(dashboardData);
  } catch (error) {
    console.error('Error fetching dashboard data:', error);
    return NextResponse.json(
      { error: 'Failed to fetch dashboard data' },
      { status: 500 }
    );
  }
}