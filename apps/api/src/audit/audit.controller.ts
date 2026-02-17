import { Controller, Get, Param, Query, UseGuards } from '@nestjs/common';
import { AuditService } from './audit.service';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';
import { RolesGuard } from '../auth/roles.guard';
import { Roles } from '../auth/roles.decorator';

@Controller('audit')
@UseGuards(JwtAuthGuard, RolesGuard)
@Roles('System Admin', 'Legal', 'Audit')
export class AuditController {
  constructor(private readonly audit: AuditService) {}

  @Get('resource/:resourceType/:resourceId')
  findForResource(
    @Param('resourceType') resourceType: string,
    @Param('resourceId') resourceId: string,
    @Query('limit') limit?: string,
  ) {
    return this.audit.findForResource(resourceType, resourceId, limit ? parseInt(limit, 10) : 100);
  }

  @Get('export')
  export(
    @Query('from') from?: string,
    @Query('to') to?: string,
    @Query('resourceType') resourceType?: string,
    @Query('actorId') actorId?: string,
    @Query('limit') limit?: string,
  ) {
    return this.audit.export(
      { from, to, resourceType, actorId },
      limit ? parseInt(limit, 10) : 10_000,
    );
  }
}
