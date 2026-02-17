import { Module } from '@nestjs/common';
import { ConfigModule } from '@nestjs/config';
import { APP_GUARD } from '@nestjs/core';
import { AppController } from './app.controller';
import { AppService } from './app.service';
import { AuthModule } from './auth/auth.module';
import { JwtAuthGuard } from './auth/jwt-auth.guard';
import { SupabaseModule } from './supabase/supabase.module';
import { HealthModule } from './health/health.module';
import { RegionsModule } from './regions/regions.module';
import { EntitiesModule } from './entities/entities.module';
import { ProjectsModule } from './projects/projects.module';
import { CounterpartiesModule } from './counterparties/counterparties.module';
import { ContractsModule } from './contracts/contracts.module';
import { AuditModule } from './audit/audit.module';

@Module({
  imports: [
    ConfigModule.forRoot({ isGlobal: true }),
    AuthModule,
    SupabaseModule,
    HealthModule,
    RegionsModule,
    EntitiesModule,
    ProjectsModule,
    CounterpartiesModule,
    ContractsModule,
    AuditModule,
  ],
  controllers: [AppController],
  providers: [AppService, { provide: APP_GUARD, useClass: JwtAuthGuard }],
})
export class AppModule {}
