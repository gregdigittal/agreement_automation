import { IsOptional, IsString, MaxLength } from 'class-validator';

export class CreateRegionDto {
  @IsString()
  @MaxLength(255)
  name!: string;

  @IsOptional()
  @IsString()
  @MaxLength(64)
  code?: string;
}
