<?php
// app/Models/CompanyGroup.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyGroup extends Model
{
    protected $fillable = ['name'];
    public function companies() { return $this->hasMany(Company::class); }

}
