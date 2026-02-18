import { NextRequest, NextResponse } from 'next/server';
import { auth } from '@/auth';

const API_BASE = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:4000';

function getBearerToken(request: NextRequest, session: { accessToken?: string } | null): string | null {
  if (session?.accessToken) return session.accessToken;
  const cookie = request.cookies.get('authjs.session-token') ?? request.cookies.get('next-auth.session-token');
  return cookie?.value ?? null;
}

async function forwardResponse(res: Response): Promise<NextResponse> {
  const contentType = res.headers.get('Content-Type') ?? 'application/json';
  const body = await res.arrayBuffer();
  return new NextResponse(body, {
    status: res.status,
    headers: { 'Content-Type': contentType },
  });
}

async function authenticate(request: NextRequest): Promise<{ token: string } | NextResponse> {
  const session = await auth();
  if (!session?.user) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
  const token = getBearerToken(request, session as { accessToken?: string });
  if (!token) return NextResponse.json({ error: 'No token' }, { status: 401 });
  return { token };
}

export async function GET(request: NextRequest, { params }: { params: Promise<{ path: string[] }> }) {
  const authResult = await authenticate(request);
  if (authResult instanceof NextResponse) return authResult;
  const path = (await params).path.join('/');
  const qs = new URL(request.url).searchParams.toString();
  const res = await fetch(`${API_BASE}/${path}${qs ? `?${qs}` : ''}`, {
    headers: { Authorization: `Bearer ${authResult.token}` },
  });
  return forwardResponse(res);
}

export async function POST(request: NextRequest, { params }: { params: Promise<{ path: string[] }> }) {
  const authResult = await authenticate(request);
  if (authResult instanceof NextResponse) return authResult;
  const path = (await params).path.join('/');
  const isMultipart = request.headers.get('content-type')?.includes('multipart');
  const body = isMultipart ? await request.formData() : await request.text();
  const headers: Record<string, string> = { Authorization: `Bearer ${authResult.token}` };
  if (!isMultipart) {
    const ct = request.headers.get('content-type');
    if (ct) headers['Content-Type'] = ct;
  }
  const res = await fetch(`${API_BASE}/${path}`, { method: 'POST', body: body as BodyInit, headers });
  return forwardResponse(res);
}

export async function PATCH(request: NextRequest, { params }: { params: Promise<{ path: string[] }> }) {
  const authResult = await authenticate(request);
  if (authResult instanceof NextResponse) return authResult;
  const path = (await params).path.join('/');
  const body = await request.text();
  const res = await fetch(`${API_BASE}/${path}`, {
    method: 'PATCH',
    body: body || undefined,
    headers: {
      'Content-Type': request.headers.get('Content-Type') ?? 'application/json',
      Authorization: `Bearer ${authResult.token}`,
    },
  });
  return forwardResponse(res);
}

export async function DELETE(request: NextRequest, { params }: { params: Promise<{ path: string[] }> }) {
  const authResult = await authenticate(request);
  if (authResult instanceof NextResponse) return authResult;
  const path = (await params).path.join('/');
  const res = await fetch(`${API_BASE}/${path}`, {
    method: 'DELETE',
    headers: { Authorization: `Bearer ${authResult.token}` },
  });
  return forwardResponse(res);
}
