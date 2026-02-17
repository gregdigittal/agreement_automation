import { Controller, Get, Post, Body, Patch, Param, Delete, Query, UseGuards } from '@nestjs/common';
import { EntitiesService } from './entities.service';
import { CreateEntityDto } from './dto/create-entity.dto';
import { UpdateEntityDto } from './dto/update-entity.dto';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';
import { CurrentUser } from '../auth/current-user.decorator';

@Controller('entities')
@UseGuards(JwtAuthGuard)
export class EntitiesController {
  constructor(private readonly entities: EntitiesService) {}

  @Post()
  create(@Body() dto: CreateEntityDto, @CurrentUser() user?: { id?: string; email?: string }) {
    return this.entities.create(dto, user);
  }

  @Get()
  findAll(@Query('regionId') regionId?: string) {
    return this.entities.findAll(regionId);
  }

  @Get(':id')
  findOne(@Param('id') id: string) {
    return this.entities.findOne(id);
  }

  @Patch(':id')
  update(
    @Param('id') id: string,
    @Body() dto: UpdateEntityDto,
    @CurrentUser() user?: { id?: string; email?: string },
  ) {
    return this.entities.update(id, dto, user);
  }

  @Delete(':id')
  remove(@Param('id') id: string, @CurrentUser() user?: { id?: string; email?: string }) {
    return this.entities.remove(id, user);
  }
}
