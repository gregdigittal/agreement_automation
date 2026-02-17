import { Injectable, NotFoundException } from '@nestjs/common';
import { SupabaseService } from '../supabase/supabase.service';
import { AuditService } from '../audit/audit.service';
import { CreateEntityDto } from './dto/create-entity.dto';
import { UpdateEntityDto } from './dto/update-entity.dto';

@Injectable()
export class EntitiesService {
  constructor(
    private readonly supabase: SupabaseService,
    private readonly audit: AuditService,
  ) {}

  async create(dto: CreateEntityDto, actor?: { id?: string; email?: string }) {
    const { data, error } = await this.supabase.getClient().from('entities').insert({
      region_id: dto.regionId,
      name: dto.name,
      code: dto.code ?? null,
      updated_at: new Date().toISOString(),
    }).select().single();
    if (error) throw new Error(error.message);
    await this.audit.log({
      action: 'entity.create',
      resourceType: 'entity',
      resourceId: data.id,
      details: { name: dto.name, regionId: dto.regionId },
      actorId: actor?.id,
      actorEmail: actor?.email,
    });
    return data;
  }

  async findAll(regionId?: string) {
    let q = this.supabase.getClient().from('entities').select('*, regions(id,name,code)').order('name');
    if (regionId) q = q.eq('region_id', regionId);
    const { data, error } = await q;
    if (error) throw new Error(error.message);
    return data ?? [];
  }

  async findOne(id: string) {
    const { data, error } = await this.supabase.getClient().from('entities').select('*, regions(id,name,code)').eq('id', id).single();
    if (error || !data) throw new NotFoundException('Entity not found');
    return data;
  }

  async update(id: string, dto: UpdateEntityDto, actor?: { id?: string; email?: string }) {
    await this.findOne(id);
    const body: Record<string, unknown> = { updated_at: new Date().toISOString() };
    if (dto.regionId !== undefined) body.region_id = dto.regionId;
    if (dto.name !== undefined) body.name = dto.name;
    if (dto.code !== undefined) body.code = dto.code;
    const { data, error } = await this.supabase.getClient().from('entities').update(body).eq('id', id).select().single();
    if (error) throw new Error(error.message);
    await this.audit.log({
      action: 'entity.update',
      resourceType: 'entity',
      resourceId: id,
      details: dto as Record<string, unknown>,
      actorId: actor?.id,
      actorEmail: actor?.email,
    });
    return data;
  }

  async remove(id: string, actor?: { id?: string; email?: string }) {
    await this.findOne(id);
    const { error } = await this.supabase.getClient().from('entities').delete().eq('id', id);
    if (error) throw new Error(error.message);
    await this.audit.log({
      action: 'entity.delete',
      resourceType: 'entity',
      resourceId: id,
      actorId: actor?.id,
      actorEmail: actor?.email,
    });
    return { deleted: true };
  }
}
