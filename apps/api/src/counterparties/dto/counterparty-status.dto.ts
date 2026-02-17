import { IsIn, IsOptional, IsString } from 'class-validator';

export class CounterpartyStatusDto {
  @IsIn(['Active', 'Suspended', 'Blacklisted'])
  status!: 'Active' | 'Suspended' | 'Blacklisted';

  @IsString()
  reason!: string;

  @IsOptional()
  @IsString()
  supportingDocumentRef?: string;
}
