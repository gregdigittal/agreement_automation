const API_BASE = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:4000';

export async function apiFetch<T>(
  path: string,
  options: RequestInit & { token?: string } = {},
): Promise<T> {
  const { token, ...init } = options;
  const headers = new Headers(init.headers);
  if (token) headers.set('Authorization', `Bearer ${token}`);
  const res = await fetch(`${API_BASE}${path}`, { ...init, headers });
  if (!res.ok) {
    const err = await res.text();
    throw new Error(err || res.statusText);
  }
  if (res.headers.get('content-type')?.includes('application/json')) return res.json() as Promise<T>;
  return undefined as T;
}
