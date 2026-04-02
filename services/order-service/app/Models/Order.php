<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = ['uuid', 'customer_id', 'status', 'subtotal', 'tax', 'total', 'shipping_address', 'tracking_number', 'carrier'];
    protected $casts = ['subtotal' => 'float', 'tax' => 'float', 'total' => 'float'];
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
}
