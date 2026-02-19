import { toast } from 'sonner';

export async function handleApiError(res: Response): Promise<boolean> {
  if (res.ok) return false;
  const text = await res.text();
  let message = 'An error occurred';
  try {
    const parsed = JSON.parse(text);
    message = parsed.detail || parsed.message || text;
  } catch {
    message = text;
  }
  toast.error(message);
  return true;
}
