import { NextRequest, NextResponse } from 'next/server';
import { auth } from '@/auth';

const API_BASE = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:4000';

function getBearerToken(request: NextRequest, session: { accessToken?: string } | null): string | null {
  if (session?.accessToken) return session.accessToken;
  const cookie = request.cookies.get('authjs.session-token') ?? request.cookies.get('next-auth.session-token');
  return cookie?.value ?? null;
}

export async function GET(
  request: NextRequest,
  { params }: { params: Promise<{ path: string[] }> },
) {
  const session = await auth();
  if (!session?.user) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
  const token = getBearerToken(request, session as { accessToken?: string });
  if (!token) return NextResponse.json({ error: 'No token' }, { status: 401 });
  const path = (await params).path.join('/');
  const url = new URL(request.url);
  const qs = url.searchParams.toString();
  const res = await fetch(`${API_BASE}/${path}${qs ? `?${qs}` : ''}`, {
    headers: { Authorization: `Bearer ${token}` },
  });
  const data = await res.text();
  return new NextResponse(res.ok ? data : JSON.stringify({ error: data }), {
    status: res.status,
    headers: { 'Content-Type': res.headers.get('Content-Type') ?? 'application/json' },
  });
}

export async function POST(
  request: NextRequest,
  { params }: { params: Promise<{ path: string[] }> },
) {
  const session = await auth();
  if (!session?.user) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
  const token = getBearerToken(request, session as { accessToken?: string });
  if (!token) return NextResponse.json({ error: 'No token' }, { status: 401 });
  const path = (await params).path.join('/');
  const body = request.headers.get('content-type')?.includes('multipart') ? await request.formData() : await request.text();
  const res = await fetch(`${API_BASE}/${path}`, {
    method: 'POST',
    body: body as BodyInit,
    headers: {
      ...(request.headers.get('content-type') ? { 'Content-Type': request.headers.get('Content-Type')! } : {}),
      Authorization: `Bearer ${token}`,
    },
  });
  const data = await res.text();
  return new NextResponse(res.ok ? data : JSON.stringify({ error: data }), {
    status: res.status,
    headers: { 'Content-Type': res.headers.get('Content-Type') ?? 'application/json' },
  });
}

export async function PATCH(
  request: NextRequest,
  { params }: { params: Promise<{ path: string[] }> },
) {
  const session = await auth();
  if (!session?.user) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
  const token = getBearerToken(request, session as { accessToken?: string });
  if (!token) return NextResponse.json({ error: 'No token' }, { status: 401 });
  const path = (await params).path.join('/');
  const body = await request.text();
  const res = await fetch(`${API_BASE}/${path}`, {
    method: 'PATCH',
    body: body || undefined,
    headers: {
      'Content-Type': request.headers.get('Content-Type') ?? 'application/json',
      Authorization: `Bearer ${token}`,
    },
  });
  const data = await res.text();
  return new NextResponse(res.ok ? data : JSON.stringify({ error: data }), {
    status: res.status,
    headers: { 'Content-Type': res.headers.get('Content-Type') ?? 'application/json' },
  });
}

export async function DELETE(
  request: NextRequest,
  { params }: { params: Promise<{ path: string[] }> },
) {
  const session = await auth();
  if (!session?.user) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
  const token = getBearerToken(request, session as { accessToken?: string });
  if (!token) return NextResponse.json({ error: 'No token' }, { status: 401 });
  const path = (await params).path.join('/');
  const res = await fetch(`${API_BASE}/${path}`, {
    method: 'DELETE',
    headers: { Authorization: `Bearer ${token}` },
  });
  const data = await res.text();
  return new NextResponse(res.ok ? data ?? '{}' : JSON.stringify({ error: data }), {
    status: res.status,
    headers: { 'Content-Type': 'application/json' },
  });
}
