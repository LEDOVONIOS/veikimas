import { NextRequest, NextResponse } from 'next/server';
import { getMonitorStore } from '@/lib/storage/monitorStore';

export async function GET(
  request: NextRequest,
  { params }: { params: { id: string } }
) {
  try {
    const store = getMonitorStore();
    const monitor = store.getMonitor(params.id);
    
    if (!monitor) {
      return NextResponse.json(
        { error: 'Monitor not found' },
        { status: 404 }
      );
    }
    
    return NextResponse.json({ monitor });
  } catch (error) {
    console.error('Error fetching monitor:', error);
    return NextResponse.json(
      { error: 'Failed to fetch monitor' },
      { status: 500 }
    );
  }
}

export async function DELETE(
  request: NextRequest,
  { params }: { params: { id: string } }
) {
  try {
    const store = getMonitorStore();
    const removed = await store.removeMonitor(params.id);
    
    if (!removed) {
      return NextResponse.json(
        { error: 'Monitor not found' },
        { status: 404 }
      );
    }
    
    return NextResponse.json({ 
      message: 'Monitor removed successfully' 
    });
  } catch (error) {
    console.error('Error removing monitor:', error);
    return NextResponse.json(
      { error: 'Failed to remove monitor' },
      { status: 500 }
    );
  }
}