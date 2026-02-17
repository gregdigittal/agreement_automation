import { Controller, Get, Post, Body, Patch, Param, Delete, Query, UseGuards } from '@nestjs/common';
import { CounterpartiesService } from './counterparties.service';
import { CreateCounterpartyDto } from './dto/create-counterparty.dto';
import { UpdateCounterpartyDto } from './dto/update-counterparty.dto';
import { CounterpartyStatusDto } from './dto/counterparty-status.dto';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';
import { CurrentUser } from '../auth/current-user.decorator';

@Controller('counterparties')
@UseGuards(JwtAuthGuard)
export class CounterpartiesController {
  constructor(private readonly counterparties: CounterpartiesService) {}

  @Get('duplicates')
  findPotentialDuplicates(
    @Query('legalName') legalName: string,
    @Query('registrationNumber') registrationNumber?: string,
  ) {
    if (!legalName?.trim()) return [];
    return this.counterparties.findPotentialDuplicates(legalName.trim(), registrationNumber?.trim());
  }

  @Post()
  create(@Body() dto: CreateCounterpartyDto, @CurrentUser() user?: { id?: string; email?: string }) {
    return this.counterparties.create(dto, user);
  }

  @Get()
  findAll(@Query('status') status?: string) {
    return this.counterparties.findAll(status);
  }

  @Get(':id')
  findOne(@Param('id') id: string) {
    return this.counterparties.findOne(id);
  }

  @Patch(':id')
  update(
    @Param('id') id: string,
    @Body() dto: UpdateCounterpartyDto,
    @CurrentUser() user?: { id?: string; email?: string },
  ) {
    return this.counterparties.update(id, dto, user);
  }

  @Patch(':id/status')
  setStatus(
    @Param('id') id: string,
    @Body() dto: CounterpartyStatusDto,
    @CurrentUser() user?: { id?: string; email?: string },
  ) {
    return this.counterparties.setStatus(id, dto, user);
  }

  @Delete(':id')
  remove(@Param('id') id: string, @CurrentUser() user?: { id?: string; email?: string }) {
    return this.counterparties.remove(id, user);
  }
}
