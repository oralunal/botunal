<?php

arch('kick controllers extend the base controller')
    ->expect('App\Http\Controllers\Kick')
    ->toExtend('App\Http\Controllers\Controller');

arch('kick jobs are queueable')
    ->expect('App\Jobs\Kick')
    ->toImplement('Illuminate\Contracts\Queue\ShouldQueue');

arch('kick services are free of debug statements')
    ->expect('App\Services\Kick')
    ->not->toUse(['dd', 'dump', 'ray', 'var_dump']);
