import { Injectable, NotFoundException } from '@nestjs/common';
import { SupabaseService } from '../supabase/supabase.service';
import { AuditService } from '../audit/audit.service';
import { CreateProjectDto } from './dto/create-project.dto';
import { UpdateProjectDto } from './dto/update-project.dto';

@Injectable()
export class ProjectsService {
  constructor(
    private readonly supabase: SupabaseService,
    private readonly audit: AuditService,
  ) {}

  async create(dto: CreateProjectDto, actor?: { id?: string; email?: string }) {
    const { data, error } = await this.supabase.getClient().from('projects').insert({
      entity_id: dto.entityId,
      name: dto.name,
      code: dto.code ?? null,
      updated_at: new Date().toISOString(),
    }).select().single();
    if (error) throw new Error(error.message);
    await this.audit.log({
      action: 'project.create',
      resourceType: 'project',
      resourceId: data.id,
      details: { name: dto.name, entityId: dto.entityId },
      actorId: actor?.id,
      actorEmail: actor?.email,
    });
    return data;
  }

  async findAll(entityId?: string) {
    let q = this.supabase.getClient().from('projects').select('*, entities(id,name,code,region_id)').order('name');
    if (entityId) q = q.eq('entity_id', entityId);
    const { data, error } = await q;
    if (error) throw new Error(error.message);
    return data ?? [];
  }

  async findOne(id: string) {
    const { data, error } = await this.supabase.getClient().from('projects').select('*, entities(id,name,code,region_id)').eq('id', id).single();
    if (error || !data) throw new NotFoundException('Project not found');
    return data;
  }

  async update(id: string, dto: UpdateProjectDto, actor?: { id?: string; email?: string }) {
    await this.findOne(id);
    const body: Record<string, unknown> = { updated_at: new Date().toISOString() };
    if (dto.entityId !== undefined) body.entity_id = dto.entityId;
    if (dto.name !== undefined) body.name = dto.name;
    if (dto.code !== undefined) body.code = dto.code;
    const { data, error } = await this.supabase.getClient().from('projects').update(body).eq('id', id).select().single();
    if (error) throw new Error(error.message);
    await this.audit.log({
      action: 'project.update',
      resourceType: 'project',
      resourceId: id,
      details: dto as Record<string, unknown>,
      actorId: actor?.id,
      actorEmail: actor?.email,
    });
    return data;
  }

  async remove(id: string, actor?: { id?: string; email?: string }) {
    await this.findOne(id);
    const { error } = await this.supabase.getClient().from('projects').delete().eq('id', id);
    if (error) throw new Error(error.message);
    await this.audit.log({
      action: 'project.delete',
      resourceType: 'project',
      resourceId: id,
      actorId: actor?.id,
      actorEmail: actor?.email,
    });
    return { deleted: true };
  }
}
