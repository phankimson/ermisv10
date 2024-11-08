<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;
use App\Http\Traits\ScopesTraits;
use App\Http\Traits\BootedTraits;
use DB;

class AccNumberVoucher extends Model
{
    use ScopesTraits,BootedTraits;
      protected $connection = 'mysql2';
      protected $table = 'number_voucher';
      public $incrementing = false; // and it doesn't even have to be auto-incrementing!
      protected $guarded = []; //Thiếu dòng create bị lỗi Add [code] to fillable property to allow mass assignment on

          protected static function booted()
      {
          static::BootedBaseTrait();
      }

      static public function get_raw() {
        $result = AccNumberVoucher::WithRowNumberDb('mysql2')->orderBy('row_number','desc')->get();       
        return $result;
      }

      static public function get_raw_export($select) {
        $env = env("DB_DATABASE");
        $result = AccNumberVoucher::WithRowNumberDb('mysql2')->orderBy('row_number','asc')
        ->leftJoin($env.'.menu as m', 't.menu_id', '=', 'm.id')
        ->leftJoin($env.'.menu as n', 't.menu_general_id', '=', 'm.id')
        ->get(['row_number',DB::raw($select)]);
        return $result;
      }

      static public function get_menu($menu) {
        $result = AccNumberVoucher::where('menu_id',$menu)->orWhere('menu_general_id',$menu)->first();
        return $result;
      }
}
