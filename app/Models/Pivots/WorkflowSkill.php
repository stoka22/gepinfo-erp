<?php

namespace App\Models\Pivots;

use Illuminate\Database\Eloquent\Model;
use App\Models\Workflow;
use App\Models\Skill;

class WorkflowSkill extends Model
{
    protected $table = 'workflow_skill';

    protected $fillable = [
        'workflow_id',
        'skill_id',
        'required_level',
    ];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function skill()
    {
        return $this->belongsTo(Skill::class);
    }
}
