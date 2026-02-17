import { Controller, Get } from '@nestjs/common';
import { SupabaseService } from '../supabase/supabase.service';
import { Public } from '../auth/public.decorator';

@Controller('health')
export class HealthController {
  constructor(private readonly supabase: SupabaseService) {}

  @Public()
  @Get()
  async check(): Promise<{ status: string; db?: string }> {
    try {
      const { error } = await this.supabase.getClient().from('regions').select('id').limit(1).maybeSingle();
      return error ? { status: 'degraded', db: 'error' } : { status: 'ok', db: 'connected' };
    } catch {
      return { status: 'degraded', db: 'error' };
    }
  }
}
