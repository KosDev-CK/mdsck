<?php

namespace Modules\Ejemplo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Ejemplo\Database\Factories\EjemploItemFactory;

class EjemploItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'title',
        'description',
        'created_by',
    ];

    // protected static function newFactory(): EjemploItemFactory
    // {
    //     // return EjemploItemFactory::new();
    // }
}
