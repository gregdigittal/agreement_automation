import { IsOptional, IsString, IsUUID, MaxLength } from 'class-validator';

export class CreateEntityDto {
  @IsUUID()
  regionId!: string;

  @IsString()
  @MaxLength(255)
  name!: string;

  @IsOptional()
  @IsString()
  @MaxLength(64)
  code?: string;
}
