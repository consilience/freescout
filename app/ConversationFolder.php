<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ConversationFolder extends Model
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
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'conversation_folder';

    protected $fillable = ['folder_id', 'conversation_id'];
}
