import { Injectable } from '@nestjs/common';
import { SupabaseService } from '../supabase/supabase.service';

export interface AuditEntry {
  action: string;
  resourceType: string;
  resourceId?: string;
  details?: Record<string, unknown>;
  actorId?: string;
  actorEmail?: string;
  ipAddress?: string;
}

@Injectable()
export class AuditService {
  constructor(private readonly supabase: SupabaseService) {}

  async log(entry: AuditEntry): Promise<void> {
    await this.supabase.getClient().from('audit_log').insert({
      action: entry.action,
      resource_type: entry.resourceType,
      resource_id: entry.resourceId ?? null,
      details: entry.details ?? null,
      actor_id: entry.actorId ?? null,
      actor_email: entry.actorEmail ?? null,
      ip_address: entry.ipAddress ?? null,
    });
  }

  async findForResource(resourceType: string, resourceId: string, limit = 100) {
    const { data, error } = await this.supabase.getClient().from('audit_log').select('*').eq('resource_type', resourceType).eq('resource_id', resourceId).order('at', { ascending: false }).limit(limit);
    if (error) throw new Error(error.message);
    return data ?? [];
  }

  async export(filters: { from?: string; to?: string; resourceType?: string; actorId?: string }, limit = 10_000) {
    let q = this.supabase.getClient().from('audit_log').select('*').order('at', { ascending: false }).limit(limit);
    if (filters.from) q = q.gte('at', filters.from);
    if (filters.to) q = q.lte('at', filters.to);
    if (filters.resourceType) q = q.eq('resource_type', filters.resourceType);
    if (filters.actorId) q = q.eq('actor_id', filters.actorId);
    const { data, error } = await q;
    if (error) throw new Error(error.message);
    return data ?? [];
  }
}
