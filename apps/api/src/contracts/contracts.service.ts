import { Injectable, NotFoundException, BadRequestException } from '@nestjs/common';
import { SupabaseService } from '../supabase/supabase.service';
import { AuditService } from '../audit/audit.service';
import { CreateContractDto } from './dto/create-contract.dto';

const STORAGE_BUCKET = 'contracts';

@Injectable()
export class ContractsService {
  constructor(
    private readonly supabase: SupabaseService,
    private readonly audit: AuditService,
  ) {}

  async createWithFile(
    dto: CreateContractDto,
    file: { buffer: Buffer; originalname: string; mimetype: string },
    actor?: { id?: string; email?: string },
  ) {
    const allowed = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    if (!allowed.includes(file.mimetype)) throw new BadRequestException('Only PDF and DOCX are allowed');
    const ext = file.originalname.split('.').pop() ?? 'bin';
    const path = `${dto.regionId}/${dto.entityId}/${dto.projectId}/${Date.now()}-${file.originalname}`;
    const client = this.supabase.getClient();
    const { error: uploadError } = await client.storage.from(STORAGE_BUCKET).upload(path, file.buffer, {
      contentType: file.mimetype,
      upsert: false,
    });
    if (uploadError) throw new Error(uploadError.message);
    const { data, error } = await client.from('contracts').insert({
      region_id: dto.regionId,
      entity_id: dto.entityId,
      project_id: dto.projectId,
      counterparty_id: dto.counterpartyId,
      contract_type: dto.contractType,
      title: dto.title ?? file.originalname,
      workflow_state: 'draft',
      storage_path: path,
      file_name: file.originalname,
      file_version: 1,
      created_by: actor?.email ?? actor?.id,
      updated_by: actor?.email ?? actor?.id,
      updated_at: new Date().toISOString(),
    }).select().single();
    if (error) throw new Error(error.message);
    await this.audit.log({
      action: 'contract.upload',
      resourceType: 'contract',
      resourceId: data.id,
      details: { title: data.title, storagePath: path },
      actorId: actor?.id,
      actorEmail: actor?.email,
    });
    return data;
  }

  async search(filters: {
    q?: string;
    regionId?: string;
    entityId?: string;
    projectId?: string;
    contractType?: string;
    workflowState?: string;
  }, limit = 50) {
    let q = this.supabase.getClient().from('contracts').select('id, title, contract_type, workflow_state, signing_status, created_at, region_id, entity_id, project_id, counterparty_id').order('created_at', { ascending: false }).limit(limit);
    if (filters.regionId) q = q.eq('region_id', filters.regionId);
    if (filters.entityId) q = q.eq('entity_id', filters.entityId);
    if (filters.projectId) q = q.eq('project_id', filters.projectId);
    if (filters.contractType) q = q.eq('contract_type', filters.contractType);
    if (filters.workflowState) q = q.eq('workflow_state', filters.workflowState);
    if (filters.q?.trim()) {
      q = q.textSearch('search_vector', filters.q.trim(), { type: 'websearch', config: 'english' });
    }
    const { data, error } = await q;
    if (error) throw new Error(error.message);
    return data ?? [];
  }

  async findOne(id: string) {
    const { data, error } = await this.supabase.getClient().from('contracts').select('*, regions(id,name), entities(id,name), projects(id,name), counterparties(id,legal_name,status)').eq('id', id).single();
    if (error || !data) throw new NotFoundException('Contract not found');
    return data;
  }

  async getDownloadUrl(id: string): Promise<{ url: string }> {
    const row = await this.findOne(id);
    if (!row.storage_path) throw new BadRequestException('No file stored');
    const { data, error } = await this.supabase.getClient().storage.from(STORAGE_BUCKET).createSignedUrl(row.storage_path, 3600);
    if (error) throw new Error(error.message);
    return { url: data.signedUrl };
  }
}
