<?php

namespace App\Listeners;

use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Support\Facades\Log;

class LogRequestSending
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  RequestSending  $event
     */
    public function handle(object $event): void
    {
        Log::debug('http client request log');
        Log::debug($event->request->body());
    }
}
