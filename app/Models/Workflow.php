<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Skill;
use App\Models\Pivots\WorkflowSkill;

class Workflow extends Model
{
    protected $fillable = ['name','description','company_id'];

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'workflow_skill')
            ->withPivot(['required_level'])
            ->withTimestamps();
    }

    public function workflowSkills() // hasMany a pivot modellre
    {
        return $this->hasMany(WorkflowSkill::class);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class)
           
            ->withTimestamps();
    }

    public function company() { return $this->belongsTo(\App\Models\Company::class); }
}
