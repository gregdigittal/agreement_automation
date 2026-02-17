import { IsString, IsUUID, IsOptional, IsIn, MaxLength } from 'class-validator';

export class CreateContractDto {
  @IsUUID()
  regionId!: string;

  @IsUUID()
  entityId!: string;

  @IsUUID()
  projectId!: string;

  @IsUUID()
  counterpartyId!: string;

  @IsIn(['Commercial', 'Merchant'])
  contractType!: 'Commercial' | 'Merchant';

  @IsOptional()
  @IsString()
  @MaxLength(512)
  title?: string;
}
