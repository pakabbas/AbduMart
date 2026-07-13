<?php

declare(strict_types=1);

use App\StoreHoursService;

function store_status(): array
{
    return StoreHoursService::status();
}

function store_is_open(): bool
{
    return StoreHoursService::isOpen();
}
