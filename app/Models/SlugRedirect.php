<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlugRedirect extends Model
{
    use HasFactory;

    protected $fillable = ['from', 'to', 'status_code'];
}
