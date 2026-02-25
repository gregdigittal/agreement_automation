<?php

use App\Jobs\CheckSlaBreaches;
use App\Services\EscalationService;

it('delegates to EscalationService::checkSlaBreaches', function () {
    $mock = Mockery::mock(EscalationService::class);
    $mock->shouldReceive('checkSlaBreaches')->once()->andReturn(3);
    app()->instance(EscalationService::class, $mock);

    $job = new CheckSlaBreaches();
    $job->handle($mock);
});

it('handles zero breaches gracefully', function () {
    $mock = Mockery::mock(EscalationService::class);
    $mock->shouldReceive('checkSlaBreaches')->once()->andReturn(0);
    app()->instance(EscalationService::class, $mock);

    $job = new CheckSlaBreaches();
    $job->handle($mock);
});

it('can be dispatched to the queue', function () {
    Queue::fake();
    CheckSlaBreaches::dispatch();
    Queue::assertPushed(CheckSlaBreaches::class);
});
