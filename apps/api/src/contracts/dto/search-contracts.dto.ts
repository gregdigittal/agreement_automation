import { IsOptional, IsString, IsUUID, IsIn, MaxLength } from 'class-validator';

export class SearchContractsDto {
  @IsOptional()
  @IsString()
  @MaxLength(200)
  q?: string;

  @IsOptional()
  @IsUUID()
  regionId?: string;

  @IsOptional()
  @IsUUID()
  entityId?: string;

  @IsOptional()
  @IsUUID()
  projectId?: string;

  @IsOptional()
  @IsIn(['Commercial', 'Merchant'])
  contractType?: string;

  @IsOptional()
  @IsString()
  workflowState?: string;
}
