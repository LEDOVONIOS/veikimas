import { NextRequest, NextResponse } from 'next/server';
import { getMonitorOrchestrator } from '@/lib/monitoring/monitorOrchestrator';

export async function POST(request: NextRequest) {
  try {
    const orchestrator = getMonitorOrchestrator();
    
    // Trigger check for all monitors
    await orchestrator.checkAllMonitors();
    
    return NextResponse.json({ 
      message: 'Monitor check initiated for all websites' 
    });
  } catch (error) {
    console.error('Error checking monitors:', error);
    return NextResponse.json(
      { error: 'Failed to check monitors' },
      { status: 500 }
    );
  }
}