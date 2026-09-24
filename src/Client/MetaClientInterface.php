<?php

declare(strict_types=1);

namespace MetaMetrics\Client;

interface MetaClientInterface
{
    public function send(Request $request): Response;
}
