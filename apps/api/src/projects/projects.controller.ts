import { Controller, Get, Post, Body, Patch, Param, Delete, Query, UseGuards } from '@nestjs/common';
import { ProjectsService } from './projects.service';
import { CreateProjectDto } from './dto/create-project.dto';
import { UpdateProjectDto } from './dto/update-project.dto';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';
import { CurrentUser } from '../auth/current-user.decorator';

@Controller('projects')
@UseGuards(JwtAuthGuard)
export class ProjectsController {
  constructor(private readonly projects: ProjectsService) {}

  @Post()
  create(@Body() dto: CreateProjectDto, @CurrentUser() user?: { id?: string; email?: string }) {
    return this.projects.create(dto, user);
  }

  @Get()
  findAll(@Query('entityId') entityId?: string) {
    return this.projects.findAll(entityId);
  }

  @Get(':id')
  findOne(@Param('id') id: string) {
    return this.projects.findOne(id);
  }

  @Patch(':id')
  update(
    @Param('id') id: string,
    @Body() dto: UpdateProjectDto,
    @CurrentUser() user?: { id?: string; email?: string },
  ) {
    return this.projects.update(id, dto, user);
  }

  @Delete(':id')
  remove(@Param('id') id: string, @CurrentUser() user?: { id?: string; email?: string }) {
    return this.projects.remove(id, user);
  }
}
