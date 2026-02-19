<?php

namespace Ai\AiAgent\Models;

use Illuminate\Database\Eloquent\Model;

class AiAgentVector extends Model
{
    const MODEL_TYPE = 'aiAgentVector';

    protected $guarded = ['id'];

    public function chunks()
    {
        return $this->hasMany(AiAgentChunk::class, 'vector_id');
    }

    public static function jsonEncodeVector(array $vector): string
    {
        // prevent json_encode from outputting high precision floats if this setting is set too high,
        // otherwise it will cause json_encoded vector not to fit into blob column in database
        @ini_set('serialize_precision', -1);

        return json_encode($vector);
    }
}
