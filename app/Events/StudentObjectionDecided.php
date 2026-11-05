<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\StudentObjection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StudentObjectionDecided
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly StudentObjection $objection) {}
}
