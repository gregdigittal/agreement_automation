import { Injectable, NotFoundException, BadRequestException } from '@nestjs/common';
import { SupabaseService } from '../supabase/supabase.service';
import { AuditService } from '../audit/audit.service';
import { CreateCounterpartyDto } from './dto/create-counterparty.dto';
import { UpdateCounterpartyDto } from './dto/update-counterparty.dto';
import { CounterpartyStatusDto } from './dto/counterparty-status.dto';

@Injectable()
export class CounterpartiesService {
  constructor(
    private readonly supabase: SupabaseService,
    private readonly audit: AuditService,
  ) {}

  /** Fuzzy duplicate check: same legal_name (case-insensitive) or same registration_number */
  async findPotentialDuplicates(legalName: string, registrationNumber?: string): Promise<{ id: string; legal_name: string; registration_number: string | null }[]> {
    const nameNorm = legalName.trim().toLowerCase();
    const { data: byName } = await this.supabase.getClient().from('counterparties').select('id, legal_name, registration_number').ilike('legal_name', legalName.trim());
    const results = (byName ?? []) as { id: string; legal_name: string; registration_number: string | null }[];
    if (registrationNumber?.trim()) {
      const { data: byReg } = await this.supabase.getClient().from('counterparties').select('id, legal_name, registration_number').eq('registration_number', registrationNumber.trim());
      for (const r of byReg ?? []) {
        if (!results.some((x) => x.id === r.id)) results.push(r);
      }
    }
    return results;
  }

  async create(dto: CreateCounterpartyDto, actor?: { id?: string; email?: string }) {
    const status = dto.status ?? 'Active';
    const { data, error } = await this.supabase.getClient().from('counterparties').insert({
      legal_name: dto.legalName,
      registration_number: dto.registrationNumber ?? null,
      address: dto.address ?? null,
      jurisdiction: dto.jurisdiction ?? null,
      status,
      preferred_language: dto.preferredLanguage ?? 'en',
      updated_at: new Date().toISOString(),
    }).select().single();
    if (error) throw new Error(error.message);
    await this.audit.log({
      action: 'counterparty.create',
      resourceType: 'counterparty',
      resourceId: data.id,
      details: { legalName: dto.legalName },
      actorId: actor?.id,
      actorEmail: actor?.email,
    });
    return data;
  }

  async findAll(status?: string) {
    let q = this.supabase.getClient().from('counterparties').select('*').order('legal_name');
    if (status) q = q.eq('status', status);
    const { data, error } = await q;
    if (error) throw new Error(error.message);
    return data ?? [];
  }

  async findOne(id: string) {
    const { data, error } = await this.supabase.getClient().from('counterparties').select('*, counterparty_contacts(*)').eq('id', id).single();
    if (error || !data) throw new NotFoundException('Counterparty not found');
    return data;
  }

  async update(id: string, dto: UpdateCounterpartyDto, actor?: { id?: string; email?: string }) {
    await this.findOne(id);
    const body: Record<string, unknown> = { updated_at: new Date().toISOString() };
    if (dto.legalName !== undefined) body.legal_name = dto.legalName;
    if (dto.registrationNumber !== undefined) body.registration_number = dto.registrationNumber;
    if (dto.address !== undefined) body.address = dto.address;
    if (dto.jurisdiction !== undefined) body.jurisdiction = dto.jurisdiction;
    if (dto.status !== undefined) body.status = dto.status;
    if (dto.preferredLanguage !== undefined) body.preferred_language = dto.preferredLanguage;
    const { data, error } = await this.supabase.getClient().from('counterparties').update(body).eq('id', id).select().single();
    if (error) throw new Error(error.message);
    await this.audit.log({
      action: 'counterparty.update',
      resourceType: 'counterparty',
      resourceId: id,
      details: dto as Record<string, unknown>,
      actorId: actor?.id,
      actorEmail: actor?.email,
    });
    return data;
  }

  async setStatus(id: string, dto: CounterpartyStatusDto, actor?: { id?: string; email?: string }) {
    const row = await this.findOne(id);
    const { data, error } = await this.supabase.getClient().from('counterparties').update({
      status: dto.status,
      status_reason: dto.reason,
      status_changed_at: new Date().toISOString(),
      status_changed_by: actor?.email ?? actor?.id,
      updated_at: new Date().toISOString(),
    }).eq('id', id).select().single();
    if (error) throw new Error(error.message);
    await this.audit.log({
      action: 'counterparty.setStatus',
      resourceType: 'counterparty',
      resourceId: id,
      details: { previousStatus: row.status, newStatus: dto.status, reason: dto.reason },
      actorId: actor?.id,
      actorEmail: actor?.email,
    });
    return data;
  }

  async remove(id: string, actor?: { id?: string; email?: string }) {
    await this.findOne(id);
    const { error } = await this.supabase.getClient().from('counterparties').delete().eq('id', id);
    if (error) throw new Error(error.message);
    await this.audit.log({
      action: 'counterparty.delete',
      resourceType: 'counterparty',
      resourceId: id,
      actorId: actor?.id,
      actorEmail: actor?.email,
    });
    return { deleted: true };
  }
}
