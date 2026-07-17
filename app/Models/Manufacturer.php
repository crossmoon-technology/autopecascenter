<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'cnpj', 'logo', 'icon', 'external_link'])]
class Manufacturer extends Model
{
    use SoftDeletes;
}
