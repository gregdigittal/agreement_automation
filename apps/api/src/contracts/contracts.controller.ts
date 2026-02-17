import { Controller, Get, Post, Body, Param, Query, UseGuards, UseInterceptors, UploadedFile, BadRequestException } from '@nestjs/common';
import { FileInterceptor } from '@nestjs/platform-express';
import { ContractsService } from './contracts.service';
import { CreateContractDto } from './dto/create-contract.dto';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';
import { CurrentUser } from '../auth/current-user.decorator';

@Controller('contracts')
@UseGuards(JwtAuthGuard)
export class ContractsController {
  constructor(private readonly contracts: ContractsService) {}

  @Post('upload')
  @UseInterceptors(FileInterceptor('file'))
  upload(
    @Body() dto: CreateContractDto,
    @UploadedFile() file: Express.Multer.File | undefined,
    @CurrentUser() user?: { id?: string; email?: string },
  ) {
    if (!file?.buffer) throw new BadRequestException('File is required');
    return this.contracts.createWithFile(
      dto,
      { buffer: file.buffer, originalname: file.originalname, mimetype: file.mimetype },
      user,
    );
  }

  @Get()
  search(
    @Query('q') q?: string,
    @Query('regionId') regionId?: string,
    @Query('entityId') entityId?: string,
    @Query('projectId') projectId?: string,
    @Query('contractType') contractType?: string,
    @Query('workflowState') workflowState?: string,
    @Query('limit') limit?: string,
  ) {
    return this.contracts.search(
      { q, regionId, entityId, projectId, contractType, workflowState },
      limit ? parseInt(limit, 10) : 50,
    );
  }

  @Get(':id')
  findOne(@Param('id') id: string) {
    return this.contracts.findOne(id);
  }

  @Get(':id/download-url')
  getDownloadUrl(@Param('id') id: string) {
    return this.contracts.getDownloadUrl(id);
  }
}
