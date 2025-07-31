import { NextRequest, NextResponse } from 'next/server';
import { getMonitorOrchestrator } from '@/lib/monitoring/monitorOrchestrator';
import { getMonitorStore } from '@/lib/storage/monitorStore';

export async function POST(
  request: NextRequest,
  { params }: { params: { id: string } }
) {
  try {
    const body = await request.json();
    const { enabled } = body;
    
    if (typeof enabled !== 'boolean') {
      return NextResponse.json(
        { error: 'enabled must be a boolean value' },
        { status: 400 }
      );
    }
    
    const store = getMonitorStore();
    const monitor = store.getMonitor(params.id);
    
    if (!monitor) {
      return NextResponse.json(
        { error: 'Monitor not found' },
        { status: 404 }
      );
    }
    
    const orchestrator = getMonitorOrchestrator();
    await orchestrator.toggleMonthlyMonitoring(params.id, enabled);
    
    return NextResponse.json({ 
      message: `Monthly monitoring ${enabled ? 'enabled' : 'disabled'} for ${monitor.url}`,
      monthlyMonitoringEnabled: enabled
    });
  } catch (error) {
    console.error('Error toggling monthly monitoring:', error);
    return NextResponse.json(
      { error: 'Failed to toggle monthly monitoring' },
      { status: 500 }
    );
  }
}