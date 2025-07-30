import { NextRequest, NextResponse } from 'next/server';
import { getMonitorScheduler } from '@/lib/cron/scheduler';

export async function GET(request: NextRequest) {
  try {
    const scheduler = getMonitorScheduler();
    const isRunning = scheduler.isRunning();
    
    return NextResponse.json({ 
      status: isRunning ? 'running' : 'stopped' 
    });
  } catch (error) {
    console.error('Error getting scheduler status:', error);
    return NextResponse.json(
      { error: 'Failed to get scheduler status' },
      { status: 500 }
    );
  }
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const { action } = body;
    
    if (!action || !['start', 'stop'].includes(action)) {
      return NextResponse.json(
        { error: 'Invalid action. Use "start" or "stop"' },
        { status: 400 }
      );
    }
    
    const scheduler = getMonitorScheduler();
    
    if (action === 'start') {
      if (scheduler.isRunning()) {
        return NextResponse.json({ 
          message: 'Scheduler is already running' 
        });
      }
      scheduler.start();
      return NextResponse.json({ 
        message: 'Scheduler started successfully' 
      });
    } else {
      if (!scheduler.isRunning()) {
        return NextResponse.json({ 
          message: 'Scheduler is already stopped' 
        });
      }
      scheduler.stop();
      return NextResponse.json({ 
        message: 'Scheduler stopped successfully' 
      });
    }
  } catch (error) {
    console.error('Error managing scheduler:', error);
    return NextResponse.json(
      { error: 'Failed to manage scheduler' },
      { status: 500 }
    );
  }
}