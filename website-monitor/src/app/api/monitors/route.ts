import { NextRequest, NextResponse } from 'next/server';
import { getMonitorStore } from '@/lib/storage/monitorStore';
import { getMonitorOrchestrator } from '@/lib/monitoring/monitorOrchestrator';

export async function GET(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url);
    const email = searchParams.get('email');
    
    const store = getMonitorStore();
    
    if (email) {
      // Get monitors for specific email
      const monitors = store.getMonitorsByEmail(email);
      return NextResponse.json({ monitors });
    } else {
      // Get all monitors
      const monitors = store.getAllMonitors();
      return NextResponse.json({ monitors });
    }
  } catch (error) {
    console.error('Error fetching monitors:', error);
    return NextResponse.json(
      { error: 'Failed to fetch monitors' },
      { status: 500 }
    );
  }
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const { url, email } = body;
    
    // Validate input
    if (!url || !email) {
      return NextResponse.json(
        { error: 'URL and email are required' },
        { status: 400 }
      );
    }
    
    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      return NextResponse.json(
        { error: 'Invalid email format' },
        { status: 400 }
      );
    }
    
    // Add monitor and perform initial check
    const orchestrator = getMonitorOrchestrator();
    await orchestrator.addAndCheckMonitor(url, email);
    
    // Get the created monitor
    const store = getMonitorStore();
    const monitor = store.getMonitorByUrlAndEmail(url, email);
    
    return NextResponse.json({ 
      message: 'Monitor added successfully',
      monitor 
    });
  } catch (error) {
    console.error('Error adding monitor:', error);
    
    if (error instanceof Error && error.message.includes('URL')) {
      return NextResponse.json(
        { error: error.message },
        { status: 400 }
      );
    }
    
    return NextResponse.json(
      { error: 'Failed to add monitor' },
      { status: 500 }
    );
  }
}