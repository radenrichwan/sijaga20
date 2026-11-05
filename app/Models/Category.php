<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends ViolationCategory
{
    public function pointLedgers(): HasMany
    {
        return $this->hasMany(PointLedger::class);
    }
}
