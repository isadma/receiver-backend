<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
    ];

    public function receivedColumn(): string
    {
        return $this->slug.'_received_qty';
    }

    public function expectedColumn(): string
    {
        return $this->slug.'_expected_qty';
    }
}
