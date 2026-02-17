import { IsOptional, IsString, IsIn, MaxLength } from 'class-validator';

export class CreateCounterpartyDto {
  @IsString()
  @MaxLength(255)
  legalName!: string;

  @IsOptional()
  @IsString()
  @MaxLength(128)
  registrationNumber?: string;

  @IsOptional()
  @IsString()
  address?: string;

  @IsOptional()
  @IsString()
  @MaxLength(128)
  jurisdiction?: string;

  @IsOptional()
  @IsIn(['Active', 'Suspended', 'Blacklisted'])
  status?: 'Active' | 'Suspended' | 'Blacklisted';

  @IsOptional()
  @IsString()
  preferredLanguage?: string;
}
