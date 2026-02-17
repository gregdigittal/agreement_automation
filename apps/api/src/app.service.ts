import { Injectable } from '@nestjs/common';

@Injectable()
export class AppService {
  getInfo(): { name: string; version: string } {
    return { name: 'CCRS API', version: '0.1.0' };
  }
}
