<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Domain extends Model {
    protected $guarded = [];
    protected function casts(): array { return ['ssl' => 'boolean']; }
    public function account(): BelongsTo { return $this->belongsTo(HostingAccount::class, 'hosting_account_id'); }
}
