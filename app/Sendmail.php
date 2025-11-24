<?php
/**
 * Outgoing emails.
 */

namespace App;

use Illuminate\Database\Eloquent\Model;

class Sendmail extends Model
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
    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Customer.
     */
    public function customer()
    {
        return $this->belongsTo('App\Customer');
    }

    /**
     * User.
     */
    public function user()
    {
        return $this->belongsTo('App\User');
    }
}
