import { Injectable, NotFoundException } from '@nestjs/common';
import { SupabaseService } from '../supabase/supabase.service';
import { AuditService } from '../audit/audit.service';
import { CreateRegionDto } from './dto/create-region.dto';
import { UpdateRegionDto } from './dto/update-region.dto';

@Injectable()
export class RegionsService {
  constructor(
    private readonly supabase: SupabaseService,
    private readonly audit: AuditService,
  ) {}

  async create(dto: CreateRegionDto, actor?: { id?: string; email?: string }) {
    const { data, error } = await this.supabase.getClient().from('regions').insert({
      name: dto.name,
      code: dto.code ?? null,
      updated_at: new Date().toISOString(),
    }).select().single();
    if (error) throw new Error(error.message);
    await this.audit.log({
      action: 'region.create',
      resourceType: 'region',
      resourceId: data.id,
      details: { name: dto.name },
      actorId: actor?.id,
      actorEmail: actor?.email,
    });
    return data;
  }

  async findAll() {
    const { data, error } = await this.supabase.getClient().from('regions').select('*').order('name');
    if (error) throw new Error(error.message);
    return data ?? [];
  }

  async findOne(id: string) {
    const { data, error } = await this.supabase.getClient().from('regions').select('*').eq('id', id).single();
    if (error || !data) throw new NotFoundException('Region not found');
    return data;
  }

  async update(id: string, dto: UpdateRegionDto, actor?: { id?: string; email?: string }) {
    await this.findOne(id);
    const { data, error } = await this.supabase.getClient().from('regions').update({
      ...dto,
      updated_at: new Date().toISOString(),
    }).eq('id', id).select().single();
    if (error) throw new Error(error.message);
    await this.audit.log({
      action: 'region.update',
      resourceType: 'region',
      resourceId: id,
      details: dto as Record<string, unknown>,
      actorId: actor?.id,
      actorEmail: actor?.email,
    });
    return data;
  }

  async remove(id: string, actor?: { id?: string; email?: string }) {
    await this.findOne(id);
    const { error } = await this.supabase.getClient().from('regions').delete().eq('id', id);
    if (error) throw new Error(error.message);
    await this.audit.log({
      action: 'region.delete',
      resourceType: 'region',
      resourceId: id,
      actorId: actor?.id,
      actorEmail: actor?.email,
    });
    return { deleted: true };
  }
}
