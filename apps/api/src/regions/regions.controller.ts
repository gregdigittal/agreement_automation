import { Controller, Get, Post, Body, Patch, Param, Delete, UseGuards } from '@nestjs/common';
import { RegionsService } from './regions.service';
import { CreateRegionDto } from './dto/create-region.dto';
import { UpdateRegionDto } from './dto/update-region.dto';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';
import { CurrentUser } from '../auth/current-user.decorator';

@Controller('regions')
export class RegionsController {
  constructor(private readonly regions: RegionsService) {}

  @Post()
  @UseGuards(JwtAuthGuard)
  create(@Body() dto: CreateRegionDto, @CurrentUser() user?: { id?: string; email?: string }) {
    return this.regions.create(dto, user);
  }

  @Get()
  @UseGuards(JwtAuthGuard)
  findAll() {
    return this.regions.findAll();
  }

  @Get(':id')
  @UseGuards(JwtAuthGuard)
  findOne(@Param('id') id: string) {
    return this.regions.findOne(id);
  }

  @Patch(':id')
  @UseGuards(JwtAuthGuard)
  update(
    @Param('id') id: string,
    @Body() dto: UpdateRegionDto,
    @CurrentUser() user?: { id?: string; email?: string },
  ) {
    return this.regions.update(id, dto, user);
  }

  @Delete(':id')
  @UseGuards(JwtAuthGuard)
  remove(@Param('id') id: string, @CurrentUser() user?: { id?: string; email?: string }) {
    return this.regions.remove(id, user);
  }
}
