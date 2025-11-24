<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Follower extends Model
{

    /**
     * Get the queueable relationships for this model (Laravel 11 requirement).
     *
     * @return array
     */
    public function getQueueableRelations()
    {
        return [];
    }
    public $timestamps = false;
}
