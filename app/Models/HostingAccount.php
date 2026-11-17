<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class HostingAccount extends Model {
    protected $guarded = [];
    public function domains(): HasMany { return $this->hasMany(Domain::class); }
    public function databases(): HasMany { return $this->hasMany(HostingDatabase::class); }
}
